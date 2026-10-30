<?php
namespace Tests\Unit;

use App\Repositories\BaseRepository;
use PHPUnit\Framework\TestCase;

class TransactionTest extends TestCase
{
    /** @dataProvider failures */
    public function testFailedTransactionsNeverReportSuccess(string $stage): void
    {
        $db = new class($stage) {
            public $db_debug = true;
            public $rolledBack = false;
            private $stage;
            public function __construct($stage) { $this->stage = $stage; }
            public function trans_begin() { return $this->stage !== 'begin'; }
            public function trans_status() { return $this->stage !== 'write'; }
            public function trans_commit() { return $this->stage !== 'commit'; }
            public function trans_rollback() { $this->rolledBack = true; }
        };
        try {
            (new BaseRepository($db))->transaction(static function () { return 'success'; });
            self::fail('Expected transaction failure.');
        } catch (\RuntimeException $exception) {
            self::assertTrue($db->rolledBack);
            self::assertTrue($db->db_debug);
        }
    }

    public function failures(): array { return [['begin'], ['write'], ['commit']]; }
}
