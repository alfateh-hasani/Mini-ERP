<?php
/** Browser-like authentication checks against an isolated PHP server and database. */
require __DIR__ . '/bootstrap.php';

function authAssert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function authClient()
{
    $client = curl_init();
    curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 10]);
    return $client;
}

function authRequest($client, string $base, string $path, ?array $post = null, int $expectedStatus = 200): array
{
    $headers = [];
    curl_setopt($client, CURLOPT_HEADERFUNCTION, static function ($client, $header) use (&$headers) { $headers[] = $header; return strlen($header); });
    curl_setopt($client, CURLOPT_URL, $base . $path);
    curl_setopt($client, CURLOPT_HTTPGET, true);
    if ($post !== null) { curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = curl_exec($client);
    if ($body === false) { throw new RuntimeException('HTTP request failed: ' . curl_error($client)); }
    authAssert(curl_getinfo($client, CURLINFO_HTTP_CODE) === $expectedStatus, 'Unexpected HTTP status for ' . $path);
    return ['body' => $body, 'headers' => implode('', $headers)];
}

function authCookie($client, string $name): ?array
{
    foreach (curl_getinfo($client, CURLINFO_COOKIELIST) as $line) {
        $parts = explode("\t", $line);
        if ($parts[5] === $name && ((int) $parts[4] === 0 || (int) $parts[4] > time())) { return ['line' => $line, 'value' => $parts[6], 'expires' => (int) $parts[4]]; }
    }
    return null;
}

function authLogin($client, string $base, bool $remember): array
{
    $page = authRequest($client, $base, '/login');
    authAssert((bool) preg_match('/name="(csrf_[^"]+)" value="([^"]+)"/', $page['body'], $csrf), 'Missing login CSRF field.');
    return authRequest($client, $base, '/login', [$csrf[1] => $csrf[2], 'email' => 'auth@example.test', 'password' => 'test-password-123', 'remember' => $remember ? '1' : '0']);
}

function authSetIdle($db, $client, int $seconds): void
{
    $cookie = authCookie($client, 'ci_session');
    $row = $db->where('id', $cookie['value'])->get('ci_sessions')->row_array();
    $data = preg_replace('/auth_last_activity\|i:\d+;/', 'auth_last_activity|i:' . (time() - $seconds) . ';', $row['data'], -1, $count);
    authAssert($count === 1, 'Cannot adjust isolated session timestamp.');
    $db->where('id', $cookie['value'])->update('ci_sessions', ['data' => $data]);
}

$admin = integration_database('');
$name = 'mini_erp_auth_test_' . bin2hex(random_bytes(6));
$created = false;
$server = null;
$log = tempnam(sys_get_temp_dir(), 'erp-auth-');
try {
    authAssert((bool) $admin->query('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'Cannot create test database.');
    $created = true;
    $db = integration_database($name);
    $schema = preg_replace('/^(?:CREATE DATABASE|USE ).*;\r?\n/m', '', file_get_contents($projectRoot . 'database/schema.sql'));
    foreach (explode(';', $schema) as $statement) { if (trim($statement) !== '') { authAssert((bool) $db->query($statement), 'Cannot install schema.'); } }
    $db->insert('warehouses', ['id' => 1, 'name' => 'One', 'name_ar' => 'One']);
    $db->insert('categories', ['id' => 1, 'name' => 'First', 'name_ar' => 'First']);
    $db->insert('categories', ['id' => 2, 'name' => 'Second', 'name_ar' => 'Second']);
    $db->insert('products', ['category_id' => 1, 'code' => 'TAKEN', 'name' => 'Existing', 'name_ar' => 'Existing', 'unit' => 'piece', 'price' => '10.00']);
    $db->insert('users', ['id' => 1, 'name' => 'Auth test', 'email' => 'auth@example.test', 'password_hash' => password_hash('test-password-123', PASSWORD_BCRYPT), 'role' => 'admin', 'preferred_lang' => 'en']);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $error);
    authAssert(is_resource($socket), 'Cannot allocate local port.');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $base = 'http://' . $address;
    $environment = getenv();
    $environment['DB_DATABASE'] = $name;
    $environment['APP_BASE_URL'] = $base . '/';
    $environment['CI_ENV'] = 'testing';
    $pipes = [];
    $server = proc_open([PHP_BINARY, '-S', $address, $projectRoot . 'index.php'], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $projectRoot, $environment);
    authAssert(is_resource($server), 'Cannot start isolated HTTP server.');
    fclose($pipes[0]);
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $connection = @stream_socket_client('tcp://' . $address, $errorCode, $error, 0.1);
        if ($connection) { fclose($connection); break; }
        usleep(100000);
    }

    $client = authClient();
    authLogin($client, $base, false);
    $session = authCookie($client, 'ci_session');
    authAssert($session !== null && abs($session['expires'] - time() - 7200) < 10, 'Session cookie is not 120 minutes.');
    authAssert(authCookie($client, 'erp_remember') === null, 'Unchecked login created a persistent cookie.');
    foreach ([['code' => 'NEW', 'price' => 'not-money'], ['code' => 'TAKEN', 'price' => '25']] as $invalidProduct) {
        $form = authRequest($client, $base, '/products/create');
        authAssert((bool) preg_match('/name="(csrf_[^"]+)" value="([^"]+)"/', $form['body'], $csrf), 'Missing product CSRF field.');
        $input = array_replace(['name' => 'Keep "me" & values', 'name_ar' => 'Test', 'category_id' => '2', 'unit' => 'box', 'alert_quantity' => '3', 'warehouse_id' => '1', 'initial_quantity' => '17', $csrf[1] => $csrf[2]], $invalidProduct);
        $response = authRequest($client, $base, '/products/store', $input);
        $document = new DOMDocument();
        $oldErrors = libxml_use_internal_errors(true);
        $document->loadHTML($response['body'], LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($oldErrors);
        $xpath = new DOMXPath($document);
        authAssert($xpath->evaluate('string(//select[@name="warehouse_id"]/option[@selected]/@value)') === '1', 'Product error lost warehouse selection.');
        authAssert($xpath->evaluate('string(//input[@name="initial_quantity"]/@value)') === '17', 'Product error lost initial stock.');
        authAssert($xpath->query('//div[@data-initial-stock and not(@hidden)]//input[@name="initial_quantity" and not(@disabled)]')->length === 1, 'Returned initial stock is hidden or disabled.');
        authAssert($xpath->evaluate('string(//select[@name="category_id"]/option[@selected]/@value)') === '2', 'Product error lost category.');
        authAssert($xpath->evaluate('string(//select[@name="unit"]/option[@selected]/@value)') === 'box', 'Product error lost unit.');
        authAssert($xpath->evaluate('string(//select[@name="unit"]/option[@selected])') === 'Box', 'Product form did not use the configured English unit label.');
        authAssert($xpath->evaluate('string(//input[@name="name"]/@value)') === $input['name'], 'Product name was lost or double-escaped.');
        authAssert($db->count_all('products') === 1 && $db->count_all('inventory') === 0, 'Invalid product submission wrote data.');
    }
    echo "PASS: validation and duplicate-code errors preserve warehouse, initial stock, category, unit and escaped product values.\n";
    authRequest($client, $base, '/language/ar');
    $arabicForm = authRequest($client, $base, '/products/create');
    $config = [];
    require $projectRoot . 'application/config/units.php';
    authAssert(strpos($arabicForm['body'], $config['units']['piece']['ar']) !== false, 'Product form did not use the configured Arabic unit label.');
    authRequest($client, $base, '/language/en');
    $db->insert('users', ['id' => 2, 'name' => 'Original name', 'email' => 'second@example.test', 'password_hash' => 'not-used-for-login', 'role' => 'user_warehouse', 'warehouse_id' => 1]);
    foreach (['/users/store', '/users/update/2'] as $action) {
        $form = authRequest($client, $base, $action === '/users/store' ? '/users/create' : '/users/edit/2');
        authAssert((bool) preg_match('/name="(csrf_[^"]+)" value="([^"]+)"/', $form['body'], $csrf), 'Missing user CSRF field.');
        $input = ['name' => 'Keep "this" & name', 'email' => 'auth@example.test', 'password' => 'new-user-password', 'role' => 'user_warehouse', 'warehouse_id' => '1', 'preferred_lang' => 'en', 'status' => 'inactive', $csrf[1] => $csrf[2]];
        $response = authRequest($client, $base, $action, $input);
        $document = new DOMDocument();
        $oldErrors = libxml_use_internal_errors(true);
        $document->loadHTML($response['body'], LIBXML_NONET);
        libxml_clear_errors(); libxml_use_internal_errors($oldErrors);
        $xpath = new DOMXPath($document);
        authAssert($xpath->evaluate('string(//input[@name="name"]/@value)') === $input['name'], 'Duplicate user email lost the name.');
        authAssert($xpath->evaluate('string(//input[@name="email"]/@value)') === $input['email'], 'Duplicate user email lost the submitted email.');
        authAssert($xpath->query('//input[@name="email" and contains(@class,"is-invalid") and @aria-invalid="true"]')->length === 1, 'Duplicate email is not marked invalid.');
        authAssert(trim($xpath->evaluate('string(//*[@id="user-email-error" and contains(@class,"invalid-feedback") and @role="alert"])')) !== '', 'Duplicate email has no visible field feedback.');
        foreach (['role', 'warehouse_id', 'preferred_lang', 'status'] as $field) {
            authAssert($xpath->evaluate('string(//select[@name="' . $field . '"]/option[@selected]/@value)') === $input[$field], 'User error lost selection: ' . $field);
        }
        authAssert($xpath->query('//input[@name="password" and @value]')->length === 0, 'User form echoed a submitted password.');
        authAssert($db->count_all('users') === 2, 'Duplicate email inserted a user.');
        authAssert($db->where('id', 2)->get('users')->row_array()['name'] === 'Original name', 'Rejected edit changed the user.');
    }
    $form = authRequest($client, $base, '/users/create');
    preg_match('/name="(csrf_[^"]+)" value="([^"]+)"/', $form['body'], $csrf);
    $response = authRequest($client, $base, '/users/store', ['name' => 'Keep validation name', 'email' => 'invalid-email', 'password' => 'short', 'role' => 'admin', $csrf[1] => $csrf[2]]);
    authAssert(strpos($response['body'], 'value="Keep validation name"') !== false, 'Validation lost the user name.');
    authAssert(strpos($response['body'], 'id="user-email-error"') !== false && strpos($response['body'], 'id="user-password-error"') !== false, 'Validation lacks field feedback.');
    authAssert(strpos($response['body'], '&lt;p&gt;') === false, 'Validation feedback displays escaped HTML wrappers.');
    $db->insert('customers', ['name' => 'Existing', 'email' => 'duplicate@example.test']);
    $form = authRequest($client, $base, '/customers/create');
    authAssert((bool) preg_match('/name="(csrf_[^"]+)" value="([^"]+)"/', $form['body'], $csrf), 'Missing customer CSRF field.');
    $response = authRequest($client, $base, '/customers/store', ['name' => 'Duplicate', 'email' => 'duplicate@example.test', $csrf[1] => $csrf[2]]);
    authAssert((bool) preg_match('/class="alert alert-danger" role="alert"/', $response['body']), 'Error flash is not rendered as a danger alert.');
    authAssert(strpos($response['body'], 'alert-error') === false, 'Unsupported error alert class remains.');
    echo "PASS: user create/edit errors preserve non-password fields, duplicate email has inline invalid styling, and error flashes use danger styling.\n";
    foreach ([['not' => 'a string'], json_encode(['warehouse_id' => [], 'customer_id' => 1, 'items' => []]), json_encode(['warehouse_id' => 1, 'customer_id' => 1, 'items' => [['product_id' => 1, 'quantity' => '1.9']]])] as $payload) {
        $csrf = json_decode(authRequest($client, $base, '/auth/csrf')['body'], true);
        $response = json_decode(authRequest($client, $base, '/invoices/store', ['csrf_mini_erp' => $csrf['csrf_hash'], 'payload' => $payload])['body'], true);
        authAssert(is_array($response) && !$response['success'] && $response['error']['code'] === 'VALIDATION_FAILED', 'Malformed invoice input did not produce localized JSON validation.');
    }
    authSetIdle($db, $client, 7190);
    authAssert(json_decode(authRequest($client, $base, '/auth/csrf')['body'], true)['success'] === true, 'Session expired before 120 minutes.');
    authSetIdle($db, $client, 7201);
    authAssert(strpos(authRequest($client, $base, '/auth/csrf')['body'], 'id="login-email"') !== false, 'Idle session did not expire.');

    $login = authLogin($client, $base, true);
    $cookie = authCookie($client, 'erp_remember');
    authAssert($cookie !== null && abs($cookie['expires'] - time() - 30 * 86400) < 10, 'Remember lifetime is not 30 days.');
    authAssert((bool) preg_match('/Set-Cookie: erp_remember=[^\r\n]+HttpOnly; SameSite=Lax/i', $login['headers']), 'Remember cookie lacks security attributes.');
    $stored = $db->get('remember_tokens')->row_array();
    $value = rawurldecode($cookie['value']);
    authAssert((bool) preg_match('/^[a-f0-9]{24}:[a-f0-9]{64}$/D', $value), 'Invalid remember cookie format.');
    $validator = explode(':', $value)[1];
    authAssert($stored['token_hash'] !== $validator && hash_equals($stored['token_hash'], hash('sha256', $validator)), 'Remember hash storage is incorrect.');

    // A new browser session keeps only the persistent cookie.
    $restored = authClient();
    curl_setopt($restored, CURLOPT_COOKIELIST, $cookie['line']);
    authAssert(json_decode(authRequest($restored, $base, '/auth/csrf')['body'], true)['success'] === true, 'Remembered login did not restore.');
    $rotated = authCookie($restored, 'erp_remember');
    authAssert($rotated['value'] !== $cookie['value'] && $rotated['expires'] === $cookie['expires'], 'Token was not rotated with fixed expiry.');
    $replay = authClient();
    curl_setopt($replay, CURLOPT_COOKIELIST, $cookie['line']);
    authAssert(strpos(authRequest($replay, $base, '/auth/csrf')['body'], 'id="login-email"') !== false, 'Old remember token replay succeeded.');

    // Log out with a freshly issued remembered login.
    $logoutClient = authClient();
    authLogin($logoutClient, $base, true);
    authAssert(strpos(authRequest($logoutClient, $base, '/logout')['body'], 'data-logout-form') !== false, 'Direct logout URL lacks confirmation.');
    authAssert($db->count_all('remember_tokens') === 1, 'GET logout revoked authentication.');
    authRequest($logoutClient, $base, '/logout', [], 403);
    authAssert($db->count_all('remember_tokens') === 1, 'Logout without CSRF changed authentication.');
    $csrf = json_decode(authRequest($logoutClient, $base, '/auth/csrf')['body'], true);
    // A different submission rotates the token from the originally rendered form.
    authRequest($logoutClient, $base, '/invoices/store', ['csrf_mini_erp' => $csrf['csrf_hash']]);
    $fresh = json_decode(authRequest($logoutClient, $base, '/auth/logout_csrf')['body'], true);
    authAssert($fresh['csrf_hash'] !== $csrf['csrf_hash'], 'Test did not rotate the stale token.');
    curl_setopt($logoutClient, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $logout = json_decode(authRequest($logoutClient, $base, '/logout', ['csrf_mini_erp' => $fresh['csrf_hash']])['body'], true);
    authAssert($logout['success'] === true && $logout['data']['redirect'] === $base . '/login', 'JSON logout did not return login destination.');
    curl_setopt($logoutClient, CURLOPT_HTTPHEADER, []);
    authAssert(authCookie($logoutClient, 'erp_remember') === null, 'Logout retained remember cookie.');
    authAssert($db->count_all('remember_tokens') === 0, 'Logout retained remember token.');
    $expiredLogout = authClient();
    authLogin($expiredLogout, $base, false);
    authSetIdle($db, $expiredLogout, 7201);
    $csrf = json_decode(authRequest($expiredLogout, $base, '/auth/logout_csrf')['body'], true);
    authAssert($csrf['success'] === true, 'Expired session cannot refresh logout CSRF.');
    authRequest($expiredLogout, $base, '/logout', ['csrf_mini_erp' => $csrf['csrf_hash']]);
    authAssert(strpos(authRequest($expiredLogout, $base, '/logout')['body'], 'id="login-email"') !== false, 'Anonymous logout URL did not return to login.');
    echo "PASS: logout confirmation, CSRF enforcement, token refresh after another write, JSON logout, and expired-session logout.\n";

    // Password changes invalidate an existing session and remembered login.
    $passwordClient = authClient();
    authLogin($passwordClient, $base, true);
    $db->where('id', 1)->update('users', ['password_hash' => password_hash('changed-password', PASSWORD_BCRYPT)]);
    authAssert(strpos(authRequest($passwordClient, $base, '/auth/csrf')['body'], 'id="login-email"') !== false, 'Password change retained old session.');
    authAssert($db->count_all('remember_tokens') === 0, 'Password change retained remember token.');
    $db->where('id', 1)->update('users', ['password_hash' => password_hash('test-password-123', PASSWORD_BCRYPT)]);
    $expiredClient = authClient();
    authLogin($expiredClient, $base, true);
    $expiredCookie = authCookie($expiredClient, 'erp_remember');
    $db->update('remember_tokens', ['expires_at' => time() - 1]);
    $newClient = authClient();
    curl_setopt($newClient, CURLOPT_COOKIELIST, $expiredCookie['line']);
    authAssert(strpos(authRequest($newClient, $base, '/auth/csrf')['body'], 'id="login-email"') !== false, 'Expired remember token restored login.');
    $inactiveClient = authClient();
    authLogin($inactiveClient, $base, true);
    $db->where('id', 1)->update('users', ['status' => 'inactive']);
    authAssert(strpos(authRequest($inactiveClient, $base, '/auth/csrf')['body'], 'id="login-email"') !== false, 'Deactivated user retained access.');
    authAssert($db->count_all('remember_tokens') === 0, 'Deactivated user retained remember token.');
    echo "PASS: 120-minute idle sessions, unchecked login, 30-day HttpOnly remember cookie, hash storage, restoration/rotation, replay rejection, expiry, logout, password changes and deactivation.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($created) { $admin->query('DROP DATABASE `' . $name . '`'); }
    if (is_file($log)) { unlink($log); }
}
