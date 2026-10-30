<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once APPPATH . 'controllers/Inventory.php';

class InventoryPageTest extends TestCase
{
    public function testAdminCanReadInactiveWarehouseInventory(): void
    {
        if (!defined('ROLE_ADMIN')) { define('ROLE_ADMIN', 'admin'); }
        $controller = new class extends \Inventory {
            public $input;
            public $session;
            public $result;
            public function __construct()
            {
                $this->currentUser = ['role' => 'admin'];
                $this->input = new class { public function get($key, $clean) { return $key === null ? [] : 3; } };
                $this->session = new class { public function userdata($key) { return 'en'; } };
            }
            protected function requireAuth(): void {}
            protected function warehouseScope($id = null, bool $requireActive = true): ?int
            {
                if ($requireActive) { throw new \RuntimeException('Historical reads must include inactive warehouses.'); }
                return 3;
            }
            protected function inventoryService(): \App\Services\InventoryService
            {
                return new class extends \App\Services\InventoryService {
                    public function __construct() {}
                    public function page(array $input, ?int $warehouseId): array
                    {
                        return ['rows' => [], 'warehouse_id' => $warehouseId];
                    }
                };
            }
            protected function json(bool $success, $data = null, string $message = '', array $error = []): void
            {
                $this->result = [$success, $data];
            }
        };
        $controller->data();
        self::assertSame([true, ['rows' => [], 'warehouse_id' => 3]], $controller->result);
    }

    public function testInactiveWarehouseRendersWarningInsideApplication(): void
    {
        if (!defined('ROLE_ADMIN')) { define('ROLE_ADMIN', 'admin'); }
        $controller = new class extends \Inventory {
            public $db;
            public $input;
            public $view;
            public function __construct()
            {
                $this->currentUser = ['role' => 'user_warehouse'];
                $this->input = new class { public function get($key, $clean) { return null; } };
                $this->db = new class {
                    public function where($conditions) { return $this; }
                    public function count_all_results($table) { return 0; }
                };
            }
            protected function requireAuth(): void {}
            protected function warehouseScope($id = null, bool $requireActive = true): ?int
            {
                if ($requireActive) { throw new \RuntimeException('Must render an application warning.'); }
                return 3;
            }
            protected function render(string $view, array $data = [], string $layout = 'app'): void
            {
                $this->view = $view;
            }
        };
        $controller->index();
        self::assertSame('inventory/unavailable', $controller->view);
    }
}
