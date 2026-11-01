<?php
/** Dashboard totals and warehouse scoping against a disposable MySQL database. */
require __DIR__ . '/bootstrap.php';
require APPPATH . 'config/constants.php';

class CI_Controller {}
require APPPATH . 'core/MY_Controller.php';
require APPPATH . 'controllers/Dashboard.php';

class DashboardProbe extends Dashboard
{
    public $db;
    public $input;
    public $data;

    public function __construct($db, string $role, ?int $warehouseId, ?int $requestedId = null)
    {
        $this->db = $db;
        $this->currentUser = ['role' => $role, 'warehouse_id' => $warehouseId];
        $this->input = new class($requestedId) {
            private $requestedId;
            public function __construct(?int $id) { $this->requestedId = $id; }
            public function get($key, $clean) { return $this->requestedId; }
        };
    }

    protected function render(string $view, array $data = [], string $layout = 'app'): void
    {
        $this->data = $data;
    }
}

function dashboardAssert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$admin = integration_database('');
$name = 'mini_erp_dashboard_test_' . bin2hex(random_bytes(6));
$created = false;
try {
    dashboardAssert((bool) $admin->query('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'Cannot create test database.');
    $created = true;
    $db = integration_database($name);
    $schema = preg_replace('/^(?:CREATE DATABASE|USE ).*;\r?\n/m', '', file_get_contents($projectRoot . 'database/schema.sql'));
    foreach (explode(';', $schema) as $statement) {
        if (trim($statement) !== '') { dashboardAssert((bool) $db->query($statement), 'Cannot install schema.'); }
    }
    foreach ([1, 2, 3, 4] as $id) {
        dashboardAssert($db->insert('warehouses', ['id' => $id, 'name' => 'Warehouse ' . $id, 'name_ar' => 'Warehouse ' . $id]), 'Cannot seed warehouse.');
    }
    dashboardAssert($db->insert('users', ['id' => 1, 'name' => 'Test', 'email' => 'dashboard@example.test', 'password_hash' => 'test-only', 'role' => 'admin']), 'Cannot seed user.');
    dashboardAssert($db->insert('customers', ['id' => 1, 'name' => 'Test customer']), 'Cannot seed customer.');
    dashboardAssert($db->insert('categories', ['id' => 1, 'name' => 'Test', 'name_ar' => 'Test']), 'Cannot seed category.');
    dashboardAssert($db->insert('products', ['id' => 1, 'category_id' => 1, 'code' => 'DASH', 'name' => 'Test', 'name_ar' => 'Test', 'unit' => 'piece', 'price' => '10.00']), 'Cannot seed product.');
    dashboardAssert($db->insert('inventory', ['warehouse_id' => 2, 'product_id' => 1, 'quantity' => 4]), 'Cannot seed stock.');
    $previousMonth = date('Y-m-d H:i:s', strtotime('first day of last month'));
    foreach ([[1, 'issued', '10.25', $previousMonth], [2, 'issued', '144.00', $previousMonth], [1, 'issued', '20.50', date('Y-m-d H:i:s')], [2, 'cancelled', '999.00', $previousMonth], [4, 'cancelled', '50.00', $previousMonth]] as $index => $fixture) {
        [$warehouse, $status, $total, $date] = $fixture;
        dashboardAssert($db->insert('invoices', ['invoice_number' => 'DASH-' . $index, 'warehouse_id' => $warehouse, 'customer_id' => 1, 'created_by' => 1, 'status' => $status, 'subtotal' => $total, 'total' => $total, 'created_at' => $date]), 'Cannot seed invoice.');
    }
    foreach ([
        ['admin', null, null, 3, '174.75', 1, 5],
        ['admin', null, 1, 2, '30.75', 0, 2],
        ['admin', null, 2, 1, '144.00', 1, 2],
        ['user_warehouse', 2, null, 1, '144.00', 1, 2],
        ['user_warehouse', 2, 1, 1, '144.00', 1, 2],
        ['user_warehouse', 3, null, 0, '0.00', 0, 0],
        ['user_warehouse', 4, null, 0, '0.00', 0, 1],
    ] as $case) {
        [$role, $warehouseId, $requestedId, $count, $sales, $products, $recent] = $case;
        $controller = new DashboardProbe($db, $role, $warehouseId, $requestedId);
        $controller->index();
        $stats = $controller->data['stats'];
        dashboardAssert($stats['invoices'] === $count, 'Invoice count incorrect for ' . json_encode($case));
        dashboardAssert($stats['sales'] === $sales, 'Sales incorrect for ' . json_encode($case));
        dashboardAssert($stats['products'] === $products, 'Product count incorrect.');
        dashboardAssert(count($controller->data['recentInvoices']) === $recent, 'Recent invoice scope incorrect.');
        $hasStock = $warehouseId === 2 || ($role === 'admin' && ($requestedId === null || $requestedId === 2));
        dashboardAssert($stats['stock'] === ($hasStock ? 4 : 0), 'Stock total changed.');
        if ($warehouseId !== null) {
            $expectedScope = $warehouseId;
            dashboardAssert($controller->data['warehouse_id'] === $expectedScope, 'Requested warehouse overrode assigned scope.');
        }
    }

    // A cancellation must remove both its count and its amount on the next read.
    dashboardAssert($db->where('invoice_number', 'DASH-1')->update('invoices', ['status' => 'cancelled']), 'Cannot cancel fixture.');
    $warehouse = new DashboardProbe($db, 'user_warehouse', 2);
    $warehouse->index();
    dashboardAssert($warehouse->data['stats']['invoices'] === 0 && $warehouse->data['stats']['sales'] === '0.00', 'Cancelled invoice still counted as issued.');
    dashboardAssert(count($warehouse->data['recentInvoices']) === 2, 'Summary filter leaked into recent invoice history.');
    $administrator = new DashboardProbe($db, 'admin', null);
    $administrator->index();
    dashboardAssert($administrator->data['stats']['invoices'] === 2 && $administrator->data['stats']['sales'] === '30.75', 'Admin totals did not reflect cancellation.');
    echo "PASS: issued-only all-time counts and sales, cancellation updates, products, stock, recent history, admin filters, warehouse isolation, empty and cancelled-only warehouses.\n";
} finally {
    if ($created) { $admin->query('DROP DATABASE `' . $name . '`'); }
}
