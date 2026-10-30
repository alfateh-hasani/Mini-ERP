<?php
namespace {
    if (!class_exists('CI_Controller')) { class CI_Controller {} }
    if (!function_exists('lang')) { function lang($key) { return $key; } }
    require_once APPPATH . 'core/MY_Controller.php';
    require_once APPPATH . 'controllers/Auth.php';
}

namespace Tests\Unit {
    use PHPUnit\Framework\TestCase;

    class AuthRateLimitTest extends TestCase
    {
        public function testRecentFailedAttemptsAreRetainedAndBlockLogin(): void
        {
            $controller = new class extends \Auth {
                public $db;
                public $input;
                public $rendered;
                public function __construct()
                {
                    $this->input = new class {
                        public function method() { return 'post'; }
                        public function ip_address() { return '127.0.0.1'; }
                    };
                    $this->db = new class {
                        public $deleteCondition;
                        private $condition;
                        public function where($key, $value) { $this->condition = [$key, $value]; return $this; }
                        public function delete($table) { $this->deleteCondition = $this->condition; }
                        public function count_all_results($table) { return 5; }
                    };
                }
                protected function render(string $view, array $data = [], string $layout = 'app'): void
                {
                    $this->rendered = $data;
                }
            };
            $controller->login();
            self::assertSame('attempted_at <', $controller->db->deleteCondition[0]);
            self::assertSame('too_many_login_attempts', $controller->rendered['error']);
        }
    }
}
