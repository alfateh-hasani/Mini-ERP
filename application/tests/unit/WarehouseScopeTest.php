<?php
namespace {
    if (!class_exists('CI_Controller')) {
        class CI_Controller {}
    }
    if (!function_exists('show_error')) {
        function show_error($message, $status = 500) { throw new \RuntimeException($message, $status); }
    }
    if (!function_exists('lang')) {
        function lang($key) { return $key; }
    }
    require_once APPPATH . 'core/MY_Controller.php';
}

namespace Tests\Unit {
    use PHPUnit\Framework\TestCase;

    class WarehouseScopeTest extends TestCase
    {
        private function controller(string $role, bool $active): \MY_Controller
        {
            return new class($role, $active) extends \MY_Controller {
                public $db;
                public function __construct(string $role, bool $active)
                {
                    $this->currentUser = ['role' => $role, 'warehouse_id' => 7];
                    $this->db = new class($active) {
                        private $active;
                        private $conditions = [];
                        public function __construct(bool $active) { $this->active = $active; }
                        public function where($key, $value) { $this->conditions[$key] = $value; return $this; }
                        public function count_all_results($table)
                        {
                            $exists = $this->conditions['id'] === 7 && (!isset($this->conditions['status']) || $this->active);
                            $this->conditions = [];
                            return $exists ? 1 : 0;
                        }
                    };
                }
                protected function requireAuth(): void {}
                public function scope($requested, bool $active): ?int { return $this->warehouseScope($requested, $active); }
            };
        }

        protected function setUp(): void
        {
            if (!defined('ROLE_WAREHOUSE')) { define('ROLE_WAREHOUSE', 'user_warehouse'); }
            if (!defined('STATUS_ACTIVE')) { define('STATUS_ACTIVE', 'active'); }
        }

        public function testInactiveWarehouseHistoryStaysWithinAssignedScope(): void
        {
            self::assertSame(7, $this->controller('user_warehouse', false)->scope(99, false));
        }

        public function testInactiveWarehouseOperationsRemainBlocked(): void
        {
            $this->expectExceptionCode(403);
            $this->controller('user_warehouse', false)->scope(7, true);
        }

        public function testAdminCanReadInactiveWarehouseHistory(): void
        {
            self::assertSame(7, $this->controller('admin', false)->scope(7, false));
        }

        public function testHistoricalReadStillRejectsUnknownWarehouse(): void
        {
            $this->expectExceptionCode(403);
            $this->controller('admin', false)->scope(99, false);
        }
    }
}
