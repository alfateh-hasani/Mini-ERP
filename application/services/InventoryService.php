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

    public function adjust(array $input, int $userId): void
    {
        $warehouseId = NumericInput::integer($input['warehouse_id'] ?? null);
        $productId = NumericInput::integer($input['product_id'] ?? null);
        $quantity = NumericInput::integer($input['quantity'] ?? null, 0, NumericInput::MAX_QUANTITY);
        $mode = $input['mode'] ?? null;
        $reason = $input['reason'] ?? '';
        if (!is_string($mode) || !in_array($mode, ['set', 'add', 'remove'], true) || !is_string($reason)) {
            throw new ValidationException('Invalid inventory adjustment.');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) > 255 || ($mode !== 'set' && $reason === '')) { throw new ValidationException('Invalid adjustment reason.'); }
        $this->inventory->transaction(function () use ($warehouseId, $productId, $quantity, $mode, $reason, $userId): void {
            $this->inventory->lockActiveWarehouse($warehouseId);
            $this->inventory->lockActiveProduct($productId);
            $row = $this->inventory->lockStock($warehouseId, $productId);
            if (!$row) { throw new ValidationException('Product is not stocked in this warehouse.'); }
            $before = NumericInput::integer($row['quantity'], 0, NumericInput::MAX_QUANTITY);
            $after = $mode === 'set' ? $quantity : ($mode === 'add' ? $before + $quantity : $before - $quantity);
            if ($after < 0 || $after > NumericInput::MAX_QUANTITY) { throw new ValidationException('Invalid resulting stock quantity.'); }
            $this->inventory->writeAdjustment($warehouseId, $productId, $before, $after, $mode, $reason ?: null, $userId);
        });
    }

    public function page(array $input, ?int $warehouseId): array
    {
        foreach (['page', 'per_page', 'q'] as $field) {
            if (isset($input[$field]) && !is_scalar($input[$field])) { throw new ValidationException('Invalid inventory filters.'); }
        }
        $page = max(1, (int) ($input['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($input['per_page'] ?? 10)));
        $result = $this->inventory->page($warehouseId, $this->filters($input), $page, $perPage, trim((string) ($input['q'] ?? '')));
        foreach ($result['rows'] as &$row) {
            $row['stock_shortfall'] = max(0, (int) $row['alert_quantity'] - (int) $row['quantity']);
        }
        unset($row);
        return $result;
    }
}
