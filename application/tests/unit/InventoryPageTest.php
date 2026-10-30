<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once APPPATH . 'controllers/Inventory.php';

class InventoryPageTest extends TestCase
{
    public function testInactiveWarehouseRendersWarningInsideApplication(): void
    {
        $controller = new class extends \Inventory {
            public $db;
            public $input;
            public $view;
            public function __construct()
            {
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
