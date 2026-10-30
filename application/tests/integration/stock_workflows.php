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
    $productService = new \App\Services\ProductService(new \App\Repositories\ProductRepository($db));
    $product = ['category_id' => '1', 'code' => 'LATER', 'name' => 'Later stock', 'name_ar' => 'Later stock', 'unit' => 'piece', 'unit_ar' => 'Test', 'price' => '10.00', 'alert_quantity' => '1', 'warehouse_id' => '', 'initial_quantity' => ['ignored']];
    $id = $productService->save($product, 1);
    stockAssert($db->where('product_id', $id)->count_all_results('inventory') === 0, 'No warehouse created initial stock.');
    foreach ([1, 2] as $warehouseId) {
        $service->adjust(['warehouse_id' => $warehouseId, 'product_id' => $id, 'quantity' => '7', 'mode' => 'add', 'reason' => 'First delivery'], 1);
        stockAssert((int) $db->where(['warehouse_id' => $warehouseId, 'product_id' => $id])->get('inventory')->row_array()['quantity'] === 7, 'New warehouse enrolment failed.');
    }
    $beforeProducts = $db->count_all('products');
    $db->query("CREATE TRIGGER reject_test_movement BEFORE INSERT ON inventory_movements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Forced movement failure'");
    try {
        // Reject the final movement after product and stock insertion succeed.
        $productService->save(array_replace($product, ['code' => 'ROLLBACK', 'warehouse_id' => '1', 'initial_quantity' => '4']), 1);
        throw new LogicException('Expected product/stock transaction failure.');
    } catch (RuntimeException $exception) {
        if ($exception instanceof LogicException) { throw $exception; }
    }
    $db->query('DROP TRIGGER reject_test_movement');
    stockAssert($db->count_all('products') === $beforeProducts, 'Failed initial stock left a product behind.');
    $initialId = $productService->save(array_replace($product, ['code' => 'INITIAL', 'warehouse_id' => '1', 'initial_quantity' => '4']), 1);
    stockAssert((int) $db->where('product_id', $initialId)->get('inventory')->row_array()['quantity'] === 4, 'Atomic initial stock failed.');
    $db->where('id', 2)->update('warehouses', ['status' => 'inactive']);
    try {
        $service->adjust(['warehouse_id' => 2, 'product_id' => $id, 'quantity' => '1', 'mode' => 'set'], 1);
        throw new LogicException('Inactive warehouse accepted stock changes.');
    } catch (\App\Exceptions\ValidationException $exception) {}
    echo "PASS: adjustment success, movement-write failure rollback, and strict quantity validation.\n";
    echo "PASS: product without stock, later enrolment in two warehouses, atomic initial stock, product rollback and inactive-warehouse protection.\n";
} finally {
    if ($created) { $admin->query('DROP DATABASE `' . $name . '`'); }
}
