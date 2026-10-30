<?php
namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Repositories\ProductRepository;
use App\Services\ProductService;
use PHPUnit\Framework\TestCase;

class ProductServiceTest extends TestCase
{
    public function testInvoiceSearchWithoutWarehouseDoesNotQueryTheCatalogue(): void
    {
        $repository = new class extends ProductRepository {
            public function __construct() {}
        };
        self::assertSame([], $repository->activeSearch('GEN', null));
        self::assertSame([], $repository->activeSearch('GEN', 0));
    }
    public function testExistingCodeIsRejectedEvenWhenInputHasDifferentCase(): void
    {
        $repository = new class extends ProductRepository {
            public function __construct() {} public function codeExists(string $code, ?int $exceptId = null): bool { return $code === 'ABC-1' && $exceptId === null; }
        };
        $this->expectException(ValidationException::class);
        (new ProductService($repository))->save(['code'=>'abc-1','category_id'=>1,'name'=>'Name','name_ar'=>'اسم','unit'=>'piece','unit_ar'=>'قطعة','price'=>'1.00','alert_quantity'=>0], 1);
    }

    public function testCurrentProductCodeIsExcludedFromUniquenessCheck(): void
    {
        $repository = new class extends ProductRepository {
            public ?int $except = null; public function __construct() {} public function codeExists(string $code, ?int $exceptId = null): bool { $this->except=$exceptId; return false; } public function save(array $data, ?int $id = null): int { return $id; }
        };
        $result = (new ProductService($repository))->save(['code'=>'abc-1','category_id'=>1,'name'=>'Name','name_ar'=>'اسم','unit'=>'piece','unit_ar'=>'قطعة','price'=>'1.00','alert_quantity'=>0], 1, 7);
        self::assertSame(7, $result); self::assertSame(7, $repository->except);
    }
}
