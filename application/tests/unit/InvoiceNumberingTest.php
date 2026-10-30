<?php
namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Repositories\InvoiceRepository;
use PHPUnit\Framework\TestCase;

class InvoiceNumberingTest extends TestCase
{
    private function database(bool $available = true)
    {
        return new class($available) {
            public $database = 'numbering_test';
            public $events = [];
            private $available;
            public function __construct(bool $available) { $this->available = $available; }
            public function query($sql, $bindings)
            {
                $this->events[] = strpos($sql, 'GET_LOCK') !== false ? 'acquire' : 'release';
                return new class($this->available) {
                    private $available;
                    public function __construct(bool $available) { $this->available = $available; }
                    public function row_array() { return ['acquired' => $this->available ? 1 : 0]; }
                };
            }
            public function trans_begin() { $this->events[] = 'begin'; }
            public function trans_status() { return true; }
            public function trans_commit() { $this->events[] = 'commit'; }
            public function trans_rollback() { $this->events[] = 'rollback'; }
        };
    }

    public function testNumberingLockIsHeldUntilCommit(): void
    {
        $db = $this->database();
        self::assertSame(7, (new InvoiceRepository($db))->numberedTransaction(static function () { return 7; }));
        self::assertSame(['acquire', 'begin', 'commit', 'release'], $db->events);
    }

    public function testFailureRollsBackBeforeReleasingNumberingLock(): void
    {
        $db = $this->database();
        try {
            (new InvoiceRepository($db))->numberedTransaction(static function () { throw new \RuntimeException('failed'); });
            self::fail('Expected transaction failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame(['acquire', 'begin', 'rollback', 'release'], $db->events);
        }
    }

    public function testBusyNumberingLockDoesNotStartWork(): void
    {
        $this->expectException(ValidationException::class);
        (new InvoiceRepository($this->database(false)))->numberedTransaction(static function () { self::fail('Busy operation must not run.'); });
    }

    public function testUnlockedNumberAllocationIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        (new InvoiceRepository($this->database()))->nextNumber();
    }
}
