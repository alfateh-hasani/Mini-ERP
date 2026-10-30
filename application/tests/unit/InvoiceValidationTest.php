<?php
namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Repositories\InvoiceRepository;
use App\Services\InvoiceService;
use PHPUnit\Framework\TestCase;

class InvoiceValidationTest extends TestCase
{
    private function repository(string $price = '10.00'): InvoiceRepository
    {
        return new class($price) extends InvoiceRepository {
            public $saved;
            private $price;
            public function __construct(string $price) { $this->price = $price; }
            public function warehouseIsActive(int $id): bool { return true; }
            public function customerExists(int $id): bool { return true; }
            public function numberedTransaction(callable $work) { return $work(); }
            public function lockInventory(int $warehouseId, array $ids): array
            {
                return array_map(function ($id) { return ['product_id' => $id, 'quantity' => 2147483647, 'code' => 'T', 'name' => 'T', 'name_ar' => 'T', 'unit' => 'piece', 'price' => $this->price]; }, $ids);
            }
            public function decrement(int $warehouseId, int $productId, int $quantity): void {}
            public function nextNumber(): string { return 'INV-TEST'; }
            public function insertInvoice(array $invoice, array $items): int { $this->saved = ['invoice' => $invoice, 'items' => $items]; return 1; }
            public function findScoped(int $id, ?int $warehouseId): ?array { return ['id' => $id]; }
        };
    }

    private function payload(): array { return ['warehouse_id' => '1', 'customer_id' => '1', 'items' => [['product_id' => '1', 'quantity' => '2', 'discount_percent' => '10']]]; }

    /** @dataProvider invalidPayloads */
    public function testMalformedInputsReturnValidationErrors(array $changes): void
    {
        $this->expectException(ValidationException::class);
        (new InvoiceService($this->repository()))->create(array_replace($this->payload(), $changes), 1, null);
    }

    public function invalidPayloads(): array
    {
        $line = ['product_id' => '1', 'quantity' => '1', 'discount_percent' => '0'];
        $cases = [[['customer_id' => []]], [['warehouse_id' => '1.9']], [['items' => 'bad']], [['items' => []]], [['items' => ['bad']]], [['items' => ['named' => $line]]]];
        foreach (['1.9', 1.9, true, [], '-1', '0', '2147483648', '999999999999999999999999'] as $quantity) { $cases[] = [['items' => [array_replace($line, ['quantity' => $quantity])]]]; }
        foreach (['-1', '100.01', '', [], '1.234', '999999999999999999999'] as $discount) { $cases[] = [['items' => [array_replace($line, ['discount_percent' => $discount])]]]; }
        $cases[] = [['items' => [$line, array_replace($line, ['discount_percent' => '10'])]]];
        $cases[] = [['items' => [array_replace($line, ['quantity' => '2147483647']), $line]]];
        $cases[] = [['items' => [array_replace($line, ['product_id' => []])]]];
        return $cases;
    }

    public function testEquivalentDuplicateDiscountsAggregateWithoutChangingTheRate(): void
    {
        $repository = $this->repository();
        $payload = $this->payload();
        $payload['items'][] = ['product_id' => 1, 'quantity' => 3, 'discount_percent' => '10.00'];
        (new InvoiceService($repository))->create($payload, 1, null);
        self::assertSame(5, $repository->saved['items'][0]['quantity']);
        self::assertSame('5.00', $repository->saved['items'][0]['discount_amount']);
        self::assertSame('45.00', $repository->saved['invoice']['total']);
    }

    public function testLineAmountCannotExceedDecimalStorage(): void
    {
        $this->expectException(ValidationException::class);
        (new InvoiceService($this->repository('9999999999.99')))->create($this->payload(), 1, null);
    }

    public function testAggregateAmountCannotExceedDecimalStorage(): void
    {
        $payload = $this->payload();
        $payload['items'] = [['product_id' => 1, 'quantity' => 1], ['product_id' => 2, 'quantity' => 1]];
        $this->expectException(ValidationException::class);
        (new InvoiceService($this->repository('5000000000.00')))->create($payload, 1, null);
    }

    public function testMaximumMoneyAndFullDiscountAreExact(): void
    {
        $repository = $this->repository('9999999999.99');
        $payload = $this->payload();
        $payload['items'][0]['quantity'] = 1;
        $payload['items'][0]['discount_percent'] = '100';
        (new InvoiceService($repository))->create($payload, 1, null);
        self::assertSame('9999999999.99', $repository->saved['invoice']['subtotal']);
        self::assertSame('0.00', $repository->saved['invoice']['total']);
    }
}
