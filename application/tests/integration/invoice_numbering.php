<?php
/** Optional MySQL test; uses and removes an isolated temporary database. */
require __DIR__ . '/bootstrap.php';

use App\Repositories\InvoiceRepository;
use App\Services\InvoiceService;

if (($argv[1] ?? '') === 'worker') {
    $name = $argv[2] ?? '';
    if (!preg_match('/^mini_erp_numbering_test_[a-f0-9]{12}$/D', $name)) { exit(2); }
    $db = integration_database($name);
    $repository = new class($db) extends InvoiceRepository {
        public function nextNumber(): string { $number = parent::nextNumber(); usleep(100000); return $number; }
    };
    $invoice = (new InvoiceService($repository))->create(['warehouse_id' => 1, 'customer_id' => 1, 'items' => [['product_id' => 1, 'quantity' => 1, 'discount_percent' => '0']]], 1, 1);
    echo $invoice['invoice_number'];
    exit;
}

$admin = integration_database('');
$name = 'mini_erp_numbering_test_' . bin2hex(random_bytes(6));
$workers = [];
try {
    if (!$admin->query('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4')) { throw new RuntimeException('Cannot create isolated test database.'); }
    $db = integration_database($name);
    $schema = file_get_contents($projectRoot . 'database/schema.sql');
    $schema = preg_replace('/^(?:CREATE DATABASE|USE ).*;\r?\n/m', '', $schema);
    foreach (explode(';', $schema) as $statement) {
        if (trim($statement) !== '' && !$db->query($statement)) { throw new RuntimeException('Fresh schema failed to install.'); }
    }
    if ($db->table_exists('invoice_counters')) { throw new RuntimeException('Fresh schema still contains counters.'); }
    $db->insert('warehouses', ['id' => 1, 'name' => 'Test', 'name_ar' => 'Test']);
    $db->insert('categories', ['id' => 1, 'name' => 'Test', 'name_ar' => 'Test']);
    $db->insert('users', ['id' => 1, 'name' => 'Test', 'email' => 'numbering@example.test', 'password_hash' => 'test-only', 'role' => 'admin']);
    $db->insert('customers', ['id' => 1, 'name' => 'Test']);
    $db->insert('products', ['id' => 1, 'category_id' => 1, 'code' => 'TEST', 'name' => 'Test', 'name_ar' => 'Test', 'unit' => 'piece', 'unit_ar' => 'Test', 'price' => '10.00']);
    $db->insert('inventory', ['warehouse_id' => 1, 'product_id' => 1, 'quantity' => 100]);
    $db->insert('settings', ['setting_key' => 'invoice_prefix', 'setting_value' => 'inv']);
    $stem = 'INV-' . date('Ymd') . '-';
    $db->insert('invoices', ['invoice_number' => $stem . '9999', 'status' => 'cancelled', 'warehouse_id' => 1, 'customer_id' => 1, 'created_by' => 1, 'subtotal' => '0.00', 'total' => '0.00', 'cancelled_by' => 1, 'cancelled_at' => date('Y-m-d H:i:s')]);

    for ($index = 0; $index < 4; $index++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $name], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Cannot start numbering worker.'); }
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $numbers = [];
    foreach ($workers as [$process, $pipes]) {
        $numbers[] = trim(stream_get_contents($pipes[1]));
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) { throw new RuntimeException('Numbering worker failed: ' . $error); }
    }
    $workers = [];
    sort($numbers);
    $expected = array_map(static function ($sequence) use ($stem) { return $stem . $sequence; }, range(10000, 10003));
    if ($numbers !== $expected) { throw new RuntimeException('Concurrent numbering, cancelled history, or suffix rollover failed.'); }
    if ((int) $db->get('inventory')->row_array()['quantity'] !== 96 || $db->count_all('invoice_items') !== 4) { throw new RuntimeException('Concurrent stock/item writes failed.'); }

    $repository = new InvoiceRepository($db);
    try {
        $repository->numberedTransaction(function () use ($repository, $db) {
            $db->insert('invoices', ['invoice_number' => $repository->nextNumber(), 'warehouse_id' => 1, 'customer_id' => 1, 'created_by' => 1, 'subtotal' => '0.00', 'total' => '0.00']);
            throw new RuntimeException('Intentional rollback');
        });
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Intentional rollback') { throw $exception; }
    }
    $next = $repository->numberedTransaction(function () use ($repository) { return $repository->nextNumber(); });
    if ($next !== $stem . '10004') { throw new RuntimeException('Rollback or lock release failed.'); }
    $lock = 'mini-erp:' . sha1($name . ':invoice-number');
    if ((int) $db->query('SELECT IS_FREE_LOCK(?) AS available', [$lock])->row_array()['available'] !== 1) { throw new RuntimeException('Numbering lock was leaked.'); }
    $issued = $db->where('status', 'issued')->get('invoices')->row_array();
    (new InvoiceService($repository))->cancel((int) $issued['id'], 1, 1);
    if ((int) $db->get('inventory')->row_array()['quantity'] !== 97) { throw new RuntimeException('Cancellation failed to restore stock.'); }
    echo "PASS: canonical schema, four concurrent invoice saves, stock/items, cancelled numbers, gaps, 9999 rollover, rollback, cancellation and lock release.\n";
} finally {
    foreach ($workers as [$process, $pipes]) {
        if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    }
    $admin->query('DROP DATABASE IF EXISTS `' . $name . '`');
}
