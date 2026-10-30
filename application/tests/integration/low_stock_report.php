<?php
/** Report/CSV parity and authorization checks using a disposable database. */
require __DIR__ . '/bootstrap.php';

function reportAssert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function reportRequest($client, string $base, string $path, ?array $post = null, int $status = 200): string
{
    curl_setopt($client, CURLOPT_URL, $base . $path);
    curl_setopt($client, CURLOPT_HTTPGET, true);
    if ($post !== null) { curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = curl_exec($client);
    reportAssert($body !== false, 'HTTP request failed.');
    reportAssert(curl_getinfo($client, CURLINFO_HTTP_CODE) === $status, 'Unexpected HTTP status: ' . $path);
    return $body;
}

function reportLogin(string $base, int $user)
{
    $client = curl_init();
    curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 10]);
    $form = reportRequest($client, $base, '/login');
    reportAssert((bool) preg_match('/name="(csrf_[^"]+)" value="([^"]+)"/', $form, $csrf), 'Login CSRF missing.');
    reportRequest($client, $base, '/login', [$csrf[1] => $csrf[2], 'email' => 'report' . $user . '@example.test', 'password' => 'Report-test-123']);
    return $client;
}

function reportCsv(string $body): array
{
    reportAssert(substr($body, 0, 3) === "\xEF\xBB\xBF", 'CSV UTF-8 BOM missing.');
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, substr($body, 3)); rewind($stream);
    $rows = [];
    while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) { $rows[] = $row; }
    fclose($stream);
    return $rows;
}

$admin = integration_database('');
$name = 'mini_erp_report_test_' . bin2hex(random_bytes(6));
$created = false;
$server = null;
$log = tempnam(sys_get_temp_dir(), 'erp-report-');
try {
    reportAssert((bool) $admin->query('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'Cannot create test database.');
    $created = true;
    $db = integration_database($name);
    $schema = preg_replace('/^(?:CREATE DATABASE|USE ).*;\r?\n/m', '', file_get_contents($projectRoot . 'database/schema.sql'));
    foreach (explode(';', $schema) as $statement) {
        if (trim($statement) !== '') { reportAssert((bool) $db->query($statement), 'Cannot install schema.'); }
    }
    foreach ([1 => 'Alpha', 2 => 'Beta', 3 => 'Inactive'] as $id => $label) {
        $db->insert('warehouses', ['id' => $id, 'name' => $label, 'name_ar' => 'مستودع ' . $label, 'status' => $id === 3 ? 'inactive' : 'active']);
    }
    foreach ([1 => 'admin', 2 => 'user_warehouse', 3 => 'user_warehouse'] as $id => $role) {
        $db->insert('users', ['id' => $id, 'name' => 'Report user', 'email' => 'report' . $id . '@example.test', 'password_hash' => password_hash('Report-test-123', PASSWORD_BCRYPT), 'role' => $role, 'warehouse_id' => $id === 1 ? null : $id, 'preferred_lang' => 'en']);
    }
    $db->insert('categories', ['id' => 1, 'name' => 'Test', 'name_ar' => 'اختبار']);
    for ($id = 1; $id <= 12; $id++) {
        $db->insert('products', ['id' => $id, 'category_id' => 1, 'code' => sprintf('LOW-%02d', $id), 'name' => $id === 1 ? '=Special & "item"' : 'Product ' . $id, 'name_ar' => 'منتج ' . $id, 'unit' => 'piece', 'price' => '10.00', 'alert_quantity' => 5]);
        $db->insert('inventory', ['warehouse_id' => 1, 'product_id' => $id, 'quantity' => $id === 12 ? 6 : ($id === 11 ? 5 : 2)]);
    }
    $db->insert('inventory', ['warehouse_id' => 2, 'product_id' => 1, 'quantity' => 0]);
    $db->insert('inventory', ['warehouse_id' => 3, 'product_id' => 2, 'quantity' => 1]);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $error);
    reportAssert(is_resource($socket), 'Cannot allocate test port.');
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $base = 'http://' . $address;
    $environment = getenv();
    $environment['DB_DATABASE'] = $name;
    $environment['APP_BASE_URL'] = $base . '/';
    $environment['CI_ENV'] = 'testing';
    $server = proc_open([PHP_BINARY, '-S', $address, $projectRoot . 'index.php'], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $projectRoot, $environment);
    reportAssert(is_resource($server), 'Cannot start isolated server.'); fclose($pipes[0]);
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $connection = @stream_socket_client('tcp://' . $address, $errorCode, $error, 0.1);
        if ($connection) { fclose($connection); break; }
        usleep(100000);
    }

    $client = reportLogin($base, 1);
    $data = json_decode(reportRequest($client, $base, '/reports/data?warehouse_id=1'), true)['data'];
    reportAssert($data['pagination']['total'] === 11 && count($data['rows']) === 10, 'Threshold or pagination incorrect.');
    reportAssert((int) $data['rows'][0]['stock_shortfall'] === 3, 'Shortfall incorrect.');
    $last = json_decode(reportRequest($client, $base, '/reports/data?warehouse_id=1&page=99'), true)['data'];
    reportAssert($last['pagination']['page'] === 2 && (int) $last['rows'][0]['stock_shortfall'] === 0, 'Equality or page clamping incorrect.');
    $csv = reportCsv(reportRequest($client, $base, '/reports/low_stock?export=1&warehouse_id=1&page=2&per_page=10'));
    reportAssert(count($csv) === 12 && count($csv[0]) === 7, 'CSV must include all matches, with shortfall.');
    reportAssert($csv[1][2] === "'=Special & \"item\"", 'CSV quoting or formula protection failed.');
    foreach (['LOW-01', 'Special & "item"', 'منتج 1', 'no-match', '100%'] as $search) {
        $query = http_build_query(['warehouse_id' => 2, 'q' => $search]);
        $rows = json_decode(reportRequest($client, $base, '/reports/data?' . $query), true)['data']['rows'];
        $export = reportCsv(reportRequest($client, $base, '/reports/low_stock?export=1&' . $query));
        array_shift($export);
        reportAssert(array_column($rows, 'code') === array_column($export, 1), 'Filtered table/export differ: ' . $search);
    }
    $inactive = json_decode(reportRequest($client, $base, '/reports/data?warehouse_id=3'), true)['data'];
    reportAssert(count($inactive['rows']) === 1, 'Admin cannot inspect inactive history.');
    foreach (['q[]=bad', 'warehouse_id[]=1', 'warehouse_id=-1', 'per_page[]=10'] as $invalid) {
        reportRequest($client, $base, '/reports/data?' . $invalid, null, 400);
        reportRequest($client, $base, '/reports/low_stock?export=1&' . $invalid, null, 400);
    }
    reportRequest($client, $base, '/language/ar');
    $arabic = reportCsv(reportRequest($client, $base, '/reports/low_stock?export=1&warehouse_id=2'));
    reportAssert($arabic[0][0] === 'المستودع' && $arabic[0][6] === 'النقص عن حد التنبيه', 'CSV headers are not localized.');
    reportAssert($arabic[1][0] === 'مستودع Beta' && $arabic[1][2] === 'منتج 1' && $arabic[1][6] === '5', 'Localized CSV values incorrect.');
    echo "PASS: thresholds, shortfall, pagination, full filtered CSV, escaping, Arabic and invalid input.\n";

    $scoped = reportLogin($base, 2);
    $rows = json_decode(reportRequest($scoped, $base, '/reports/data?warehouse_id=1'), true)['data']['rows'];
    reportAssert(count($rows) === 1 && $rows[0]['warehouse'] === 'Beta', 'Forged filter widened warehouse scope.');
    $export = reportCsv(reportRequest($scoped, $base, '/reports/low_stock?export=1&warehouse_id=1'));
    reportAssert(count($export) === 2 && $export[1][0] === 'Beta', 'CSV escaped warehouse scope.');
    $disabled = reportLogin($base, 3);
    $json = json_decode(reportRequest($disabled, $base, '/reports/data'), true);
    reportAssert(!$json['success'] && $json['error']['code'] === 'WAREHOUSE_INACTIVE', 'Inactive warehouse data accessible.');
    reportAssert(substr(reportRequest($disabled, $base, '/reports/low_stock?export=1'), 0, 3) !== "\xEF\xBB\xBF", 'Inactive warehouse exported data.');
    echo "PASS: table/CSV warehouse authorization and inactive-account restrictions.\n";

} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($created) { $admin->query('DROP DATABASE `' . $name . '`'); }
    if (is_file($log)) { unlink($log); }
}
