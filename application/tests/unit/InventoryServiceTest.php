<?php
namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Repositories\InventoryRepository;
use App\Services\InventoryService;
use PHPUnit\Framework\TestCase;

class InventoryServiceTest extends TestCase
{
    private function service(): InventoryService
    {
        return new InventoryService(new class extends InventoryRepository { public function __construct() {} });
    }

    public function testZeroBoundsAndExactRangesArePreserved(): void
    {
        self::assertSame(['product_id' => 9, 'quantity_min' => 0, 'quantity_max' => 0], $this->service()->filters(['product_id' => '9', 'quantity_min' => '0', 'quantity_max' => '0']));
        self::assertSame(['product_id' => null, 'quantity_min' => null, 'quantity_max' => null], $this->service()->filters([]));
    }

    /** @dataProvider invalidFilters */
    public function testInvalidQuantityRangesAreRejected(array $input): void
    {
        $this->expectException(ValidationException::class);
        $this->service()->filters($input);
    }

    public function invalidFilters(): array
    {
        return [
            [['quantity_min' => '11', 'quantity_max' => '10']],
            [['quantity_min' => '-1']],
            [['quantity_max' => '1.5']],
            [['quantity_min' => ['1']]],
            [['product_id' => 'bad']],
            [['quantity_max' => '99999999999999999999']],
        ];
    }

    public function testPaginationAndEffectiveWarehouseReachTheRepository(): void
    {
        $repository = new class extends InventoryRepository {
            public $received;
            public function __construct() {}
            public function page(?int $warehouseId, array $filters, int $page, int $perPage, string $search): array
            {
                $this->received = [$warehouseId, $filters, $page, $perPage, $search];
                return ['rows' => [], 'pagination' => []];
            }
        };
        (new InventoryService($repository))->page(['warehouse_id' => '99', 'quantity_min' => '0', 'page' => '-1', 'per_page' => '200', 'q' => ' GEN '], 3);
        self::assertSame([3, ['product_id' => null, 'quantity_min' => 0, 'quantity_max' => null], 1, 100, 'GEN'], $repository->received);
    }
}
