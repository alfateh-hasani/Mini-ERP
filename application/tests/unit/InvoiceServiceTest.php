<?php
namespace Tests\Unit;

use App\Repositories\InvoiceRepository;
use App\Services\InvoiceService;
use App\Exceptions\ValidationException;
use App\Exceptions\UnauthorizedException;
use PHPUnit\Framework\TestCase;

class InvoiceServiceTest extends TestCase
{
    public function testCreateAggregatesLinesAndUsesSortedLocks(): void
    {
        $repository = new class extends InvoiceRepository {
            public array $locked = []; public array $decrements = []; public array $invoice = [];
            public function __construct() {} public function warehouseIsActive(int $id): bool { return true; } public function customerExists(int $id): bool { return true; }
            public function transaction(callable $work) { return $work(); }
            public function lockInventory(int $warehouseId, array $ids): array { $this->locked=$ids; return [['product_id'=>2,'quantity'=>10,'code'=>'B','name'=>'B','name_ar'=>'ب','unit'=>'piece','price'=>'10.00'],['product_id'=>5,'quantity'=>10,'code'=>'A','name'=>'A','name_ar'=>'أ','unit'=>'piece','price'=>'20.00']]; }
            public function decrement(int $warehouseId, int $productId, int $quantity): void { $this->decrements[$productId]=$quantity; }
            public function nextNumber(): string { return 'INV-20261029-0001'; }
            public function insertInvoice(array $invoice, array $items): int { $this->invoice=['header'=>$invoice,'items'=>$items]; return 9; }
            public function findScoped(int $id, ?int $scope): ?array { return ['id'=>$id]; }
        };
        $service = new InvoiceService($repository);
        $service->create(['warehouse_id'=>1,'customer_id'=>1,'items'=>[['product_id'=>5,'quantity'=>1,'discount_percent'=>'0'],['product_id'=>2,'quantity'=>2,'discount_percent'=>'0'],['product_id'=>5,'quantity'=>3,'discount_percent'=>'0']]], 1, 1);
        self::assertSame([2,5], $repository->locked); self::assertSame([2=>2,5=>4], $repository->decrements); self::assertSame('100.00', $repository->invoice['header']['subtotal']);
    }

    public function testCancelLocksInventoryInAscendingOrderAndRestoresOnce(): void
    {
        $repository = new class extends InvoiceRepository {
            public array $locked=[]; public array $restored=[]; public bool $cancelled=false;
            public function __construct() {} public function transaction(callable $work) { return $work(); } public function lockInvoiceScoped(int $id, ?int $scope): ?array { return ['id'=>$id,'warehouse_id'=>3,'status'=>'issued']; }
            public function findScoped(int $id, ?int $scope): ?array { return ['items'=>[['product_id'=>9,'quantity'=>2],['product_id'=>3,'quantity'=>1]]]; }
            public function lockInventoryRows(int $warehouse, array $ids): void { $this->locked=$ids; } public function restore(int $warehouse, int $product, int $quantity): void { $this->restored[$product]=$quantity; } public function markCancelled(int $id, int $user): void { $this->cancelled=true; }
        };
        (new InvoiceService($repository))->cancel(8, 1, 3);
        self::assertSame([3,9], $repository->locked); self::assertSame([9=>2,3=>1], $repository->restored); self::assertTrue($repository->cancelled);
    }

    public function testCancelRejectsAnAlreadyCancelledInvoice(): void
    {
        $repository = new class extends InvoiceRepository {
            public function __construct() {} public function transaction(callable $work) { return $work(); }
            public function lockInvoiceScoped(int $id, ?int $scope): ?array { return ['id'=>$id, 'warehouse_id'=>1, 'status'=>'cancelled']; }
        };
        $this->expectException(ValidationException::class);
        (new InvoiceService($repository))->cancel(8, 1, 1);
    }

    public function testCancellationRejectsAnInvoiceOutsideTheWarehouseScope(): void
    {
        $repository = new class extends InvoiceRepository {
            public function __construct() {} public function transaction(callable $work) { return $work(); }
            public function lockInvoiceScoped(int $id, ?int $scope): ?array { return null; }
        };
        $this->expectException(UnauthorizedException::class);
        (new InvoiceService($repository))->cancel(8, 1, 2);
    }

    public function testCreateRejectsAnUnscopedOrIncompletePayload(): void
    {
        $repository = new class extends InvoiceRepository { public function __construct() {} };
        $this->expectException(ValidationException::class);
        (new InvoiceService($repository))->create(['warehouse_id' => 0, 'customer_id' => 1, 'items' => []], 1, null);
    }
}
