<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once APPPATH . 'controllers/Invoices.php';

class InvoiceCreatePageTest extends TestCase
{
    public function testInactiveWarehouseShowsApplicationMessageInsteadOfRawError(): void
    {
        $controller = new class extends \Invoices {
            public $db;
            public $view;
            public function __construct()
            {
                $this->db = new class {
                    public function where($conditions) { return $this; }
                    public function count_all_results($table) { return 0; }
                };
            }
            protected function requireAuth(): void {}
            protected function warehouseScope($id = null, bool $requireActive = true): ?int
            {
                if ($requireActive) { throw new \RuntimeException('History scope must resolve before availability.'); }
                return 3;
            }
            protected function render(string $view, array $data = [], string $layout = 'app'): void
            {
                $this->view = $view;
            }
        };
        $controller->create();
        self::assertSame('invoices/unavailable', $controller->view);
    }
}
