<?php
namespace App\Services;

use App\Exceptions\ValidationException;
use App\Repositories\InventoryRepository;

class InventoryService
{
    private $inventory;

    public function __construct(InventoryRepository $inventory)
    {
        $this->inventory = $inventory;
    }

    public function filters(array $input): array
    {
        $filters = [];
        foreach (['product_id', 'quantity_min', 'quantity_max'] as $field) {
            $value = $input[$field] ?? '';
            if ($value === '' || $value === null) { $filters[$field] = null; continue; }
            if (!is_scalar($value) || !preg_match('/^\d+$/D', (string) $value) || (float) $value > 2147483647) {
                throw new ValidationException('Invalid inventory filters.');
            }
            $filters[$field] = (int) $value;
        }
        if ($filters['quantity_min'] !== null && $filters['quantity_max'] !== null && $filters['quantity_min'] > $filters['quantity_max']) {
            throw new ValidationException('Invalid inventory filters.');
        }
        return $filters;
    }

    public function page(array $input, ?int $warehouseId): array
    {
        foreach (['page', 'per_page', 'q'] as $field) {
            if (isset($input[$field]) && !is_scalar($input[$field])) { throw new ValidationException('Invalid inventory filters.'); }
        }
        $page = max(1, (int) ($input['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($input['per_page'] ?? 10)));
        return $this->inventory->page($warehouseId, $this->filters($input), $page, $perPage, trim((string) ($input['q'] ?? '')));
    }
}
