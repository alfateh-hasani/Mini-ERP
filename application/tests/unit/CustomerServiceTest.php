<?php
namespace Tests\Unit;

use App\Exceptions\DuplicateCustomerException;
use App\Repositories\CustomerRepository;
use App\Services\CustomerService;
use PHPUnit\Framework\TestCase;

class CustomerServiceTest extends TestCase
{
    public function testPhoneIsNormalizedAndBlankOptionalValuesBecomeNull(): void
    {
        $repository = new class extends CustomerRepository {
            public array $saved = []; public function __construct() {} public function duplicate(string $field, ?string $value, ?int $exceptId = null): bool { return false; } public function database() { return new class { public bool $db_debug = false; public function error(): array { return ['code' => 0]; } }; } public function save(array $data, ?int $id = null): int { $this->saved=$data; return 1; }
        };
        (new CustomerService($repository))->save(['name'=>'Demo','phone'=>'+966 50-123-4567','email'=>' ','address'=>' '], 1);
        self::assertSame('+966501234567', $repository->saved['phone']); self::assertNull($repository->saved['email']); self::assertNull($repository->saved['address']);
    }

    public function testDuplicatePhoneIsRejected(): void
    {
        $repository = new class extends CustomerRepository { public function __construct() {} public function duplicate(string $field, ?string $value, ?int $exceptId = null): bool { return $field === 'phone'; } };
        $this->expectException(DuplicateCustomerException::class);
        (new CustomerService($repository))->save(['name'=>'Demo','phone'=>'123','email'=>''], 1);
    }
}
