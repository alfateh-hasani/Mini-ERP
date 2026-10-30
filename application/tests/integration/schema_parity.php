<?php
/** Compare fresh SQL, actual migrations, and the live schema without changing live data. */
require __DIR__ . '/bootstrap.php';

function schemaSnapshot($db): array
{
    $result = [];
    foreach ($db->list_tables() as $table) {
        if ($table === 'migrations') { continue; }
        $row = $db->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->row_array();
        $result[$table] = preg_replace('/ AUTO_INCREMENT=\d+/', '', $row['Create Table']);
    }
    ksort($result);
    return $result;
}

function compareSchemas(string $label, array $expected, array $actual): bool
{
    $equal = true;
    foreach (array_unique(array_merge(array_keys($expected), array_keys($actual))) as $table) {
        if (($expected[$table] ?? null) === ($actual[$table] ?? null)) { continue; }
        $equal = false;
        echo $label . ': differs: ' . $table . PHP_EOL;
        if (isset($expected[$table], $actual[$table])) {
            $withoutCollation = static function (string $ddl): string { return preg_replace('/ COLLATE(?:=| )[a-zA-Z0-9_]+/', '', $ddl); };
            if ($withoutCollation($expected[$table]) === $withoutCollation($actual[$table])) {
                echo "  Collation only; columns, indexes, foreign keys and checks match.\n";
                continue;
            }
            foreach (array_diff(explode("\n", $expected[$table]), explode("\n", $actual[$table])) as $line) { echo '  SQL: ' . $line . PHP_EOL; }
            foreach (array_diff(explode("\n", $actual[$table]), explode("\n", $expected[$table])) as $line) { echo '  Actual: ' . $line . PHP_EOL; }
        }
    }
    if ($equal) { echo $label . ': MATCH (' . count($expected) . " application tables)\n"; }
    return $equal;
}

function runIsolatedCli(string $database, string $command): void
{
    global $projectRoot;
    if (!preg_match('/^mini_erp_schema_test_[a-f0-9]{12}_(sql|migrations)$/D', $database)) { throw new RuntimeException('Invalid test database.'); }
    $environment = getenv();
    $environment['DB_DATABASE'] = $database;
    $environment['CI_ENV'] = 'testing';
    $pipes = [];
    $process = proc_open([PHP_BINARY, $projectRoot . 'index.php', 'cli/' . $command], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $projectRoot, $environment);
    if (!is_resource($process)) { throw new RuntimeException('Cannot run isolated CLI.'); }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $marker = $command === 'migrate' ? 'Migration completed.' : 'Demo data is ready:';
    if (proc_close($process) !== 0 || strpos($output, $marker) === false) { throw new RuntimeException('CLI failed: ' . $output . $error); }
}

$admin = integration_database('');
$prefix = 'mini_erp_schema_test_' . bin2hex(random_bytes(6));
$names = [$prefix . '_sql', $prefix . '_migrations'];
$created = [];
try {
    foreach ($names as $name) {
        if (!$admin->query('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) { throw new RuntimeException('Cannot create isolated database.'); }
        $created[] = $name;
    }
    $sql = integration_database($names[0]);
    $schema = preg_replace('/^(?:CREATE DATABASE|USE ).*;\r?\n/m', '', file_get_contents($projectRoot . 'database/schema.sql'));
    foreach (explode(';', $schema) as $statement) {
        if (trim($statement) !== '' && !$sql->query($statement)) { throw new RuntimeException('Schema import failed.'); }
    }
    runIsolatedCli($names[1], 'migrate');
    $expected = schemaSnapshot($sql);
    $migrated = integration_database($names[1]);
    $migrationMatch = compareSchemas('SQL versus migrations', $expected, schemaSnapshot($migrated));
    $liveMatch = compareSchemas('SQL versus configured database (read-only)', $expected, schemaSnapshot(integration_database()));
    echo 'Migration version: ' . $migrated->get('migrations')->row_array()['version'] . PHP_EOL;
    if (in_array('--seed-smoke', $argv, true)) {
        foreach ($names as $name) {
            runIsolatedCli($name, 'seed');
            $db = integration_database($name);
            $counts = [];
            foreach (['warehouses', 'users', 'products', 'inventory', 'customers', 'invoices', 'invoice_items'] as $table) { $counts[$table] = $db->count_all($table); }
            $stock = $db->select('warehouse_id, product_id, quantity')->order_by('warehouse_id, product_id')->get('inventory')->result_array();
            runIsolatedCli($name, 'seed');
            foreach ($counts as $table => $count) {
                if ($db->count_all($table) !== $count) { throw new RuntimeException('Seeder duplicated ' . $table); }
            }
            if ($stock !== $db->select('warehouse_id, product_id, quantity')->order_by('warehouse_id, product_id')->get('inventory')->result_array()) { throw new RuntimeException('Seeder changed stock on rerun.'); }
            echo 'Fresh installation and seed rerun: PASS ' . json_encode($counts) . PHP_EOL;
        }
    }
} finally {
    foreach ($created as $name) { $admin->query('DROP DATABASE `' . $name . '`'); }
}
exit($migrationMatch && $liveMatch ? 0 : 1);
