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

function authRequest($client, string $base, string $path, ?array $post = null): array
{
    $headers = [];
    curl_setopt($client, CURLOPT_HEADERFUNCTION, static function ($client, $header) use (&$headers) { $headers[] = $header; return strlen($header); });
    curl_setopt($client, CURLOPT_URL, $base . $path);
    curl_setopt($client, CURLOPT_HTTPGET, true);
    if ($post !== null) { curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = curl_exec($client);
    if ($body === false) { throw new RuntimeException('HTTP request failed: ' . curl_error($client)); }
    authAssert(curl_getinfo($client, CURLINFO_HTTP_CODE) === 200, 'Unexpected HTTP status for ' . $path);
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
    $csrf = json_decode(authRequest($logoutClient, $base, '/auth/csrf')['body'], true);
    authRequest($logoutClient, $base, '/logout', ['csrf_mini_erp' => $csrf['csrf_hash']]);
    authAssert(authCookie($logoutClient, 'erp_remember') === null, 'Logout retained remember cookie.');
    authAssert($db->count_all('remember_tokens') === 0, 'Logout retained remember token.');

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
