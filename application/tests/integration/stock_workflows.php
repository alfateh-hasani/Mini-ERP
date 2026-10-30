<?php
/** Real MySQL stock rollback checks in a disposable database. */
require __DIR__ . '/bootstrap.php';

use App\Repositories\InventoryRepository;
use App\Services\InventoryService;

function stockAssert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$admin = integration_database('');
$name = 'mini_erp_stock_test_' . bin2hex(random_bytes(6));
$created = false;
try {
    stockAssert((bool) $admin->query('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'Cannot create test database.');
    $created = true;
    $db = integration_database($name);
    $schema = preg_replace('/^(?:CREATE DATABASE|USE ).*;\r?\n/m', '', file_get_contents($projectRoot . 'database/schema.sql'));
    foreach (explode(';', $schema) as $statement) {
        if (trim($statement) !== '') { stockAssert((bool) $db->query($statement), 'Cannot import schema.'); }
    }
    $db->insert('warehouses', ['id' => 1, 'name' => 'One', 'name_ar' => 'One']);
    $db->insert('warehouses', ['id' => 2, 'name' => 'Two', 'name_ar' => 'Two']);
    $db->insert('categories', ['id' => 1, 'name' => 'Test', 'name_ar' => 'Test']);
    $db->insert('users', ['id' => 1, 'name' => 'Test', 'email' => 'stock@example.test', 'password_hash' => 'test-only', 'role' => 'admin']);
    $db->insert('products', ['id' => 1, 'category_id' => 1, 'code' => 'STOCK', 'name' => 'Test', 'name_ar' => 'Test', 'unit' => 'piece', 'unit_ar' => 'Test', 'price' => '10.00']);
    $db->insert('inventory', ['warehouse_id' => 1, 'product_id' => 1, 'quantity' => 10]);
    $service = new InventoryService(new InventoryRepository($db));
    $input = ['warehouse_id' => '1', 'product_id' => '1', 'quantity' => '3', 'mode' => 'add', 'reason' => 'Delivery'];
    try {
        // The stock update succeeds, but the movement's actor foreign key fails.
        $service->adjust($input, 99999);
        throw new LogicException('Expected movement insertion failure.');
    } catch (RuntimeException $exception) {
        if ($exception instanceof LogicException) { throw $exception; }
    }
    stockAssert((int) $db->get('inventory')->row_array()['quantity'] === 10, 'Failed movement changed stock.');
    stockAssert($db->count_all('inventory_movements') === 0, 'Failed adjustment left a movement.');
    $service->adjust($input, 1);
    stockAssert((int) $db->get('inventory')->row_array()['quantity'] === 13, 'Successful adjustment failed.');
    stockAssert($db->count_all('inventory_movements') === 1, 'Successful adjustment has no movement.');
    foreach (['1.9', '-1', [], '999999999999999999999'] as $invalid) {
        try {
            $service->adjust(array_replace($input, ['quantity' => $invalid]), 1);
            throw new LogicException('Invalid quantity accepted.');
        } catch (\App\Exceptions\ValidationException $exception) {}
    }
    stockAssert((int) $db->get('inventory')->row_array()['quantity'] === 13, 'Validation changed stock.');
    echo "PASS: adjustment success, movement-write failure rollback, and strict quantity validation.\n";
} finally {
    if ($created) { $admin->query('DROP DATABASE `' . $name . '`'); }
}
