<?php
namespace App\Services;

use App\Exceptions\ValidationException;
use App\Exceptions\InventoryAdjustmentException;
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
        $values = [];
        $errors = [];
        foreach (['warehouse_id' => 'inventory_warehouse_required', 'product_id' => 'inventory_product_required', 'quantity' => 'inventory_quantity_invalid'] as $field => $message) {
            try {
                $values[$field] = NumericInput::integer($input[$field] ?? null, $field === 'quantity' ? 0 : 1, $field === 'quantity' ? NumericInput::MAX_QUANTITY : PHP_INT_MAX);
            } catch (ValidationException $exception) { $errors[$field] = $message; }
        }
        $mode = $input['mode'] ?? null;
        $reason = $input['reason'] ?? '';
        if (!is_string($mode) || !in_array($mode, ['set', 'add', 'remove'], true)) { $errors['mode'] = 'inventory_mode_invalid'; }
        if (!is_string($reason) || mb_strlen($reason) > 255) {
            $errors['reason'] = 'inventory_reason_invalid';
        } else {
            $reason = trim($reason);
            if (in_array($mode, ['add', 'remove'], true) && $reason === '') { $errors['reason'] = 'inventory_reason_required'; }
        }
        if (in_array($mode, ['add', 'remove'], true) && ($values['quantity'] ?? null) === 0) { $errors['quantity'] = 'inventory_positive_quantity'; }
        if ($errors) { throw new InventoryAdjustmentException($errors); }
        $warehouseId = $values['warehouse_id'];
        $productId = $values['product_id'];
        $quantity = $values['quantity'];
        $this->inventory->transaction(function () use ($warehouseId, $productId, $quantity, $mode, $reason, $userId): void {
            $this->inventory->lockActiveWarehouse($warehouseId);
            $this->inventory->lockActiveProduct($productId);
            $row = $this->inventory->lockStock($warehouseId, $productId);
            if (!$row) {
                if ($mode === 'remove') { throw new InventoryAdjustmentException(['product_id' => 'inventory_not_stocked']); }
                $this->inventory->enrol($warehouseId, $productId);
                $row = $this->inventory->lockStock($warehouseId, $productId);
            }
            try {
                $before = NumericInput::integer($row['quantity'], 0, NumericInput::MAX_QUANTITY);
            } catch (ValidationException $exception) { throw new InventoryAdjustmentException(['quantity' => 'inventory_stock_limit']); }
            $after = $mode === 'set' ? $quantity : ($mode === 'add' ? $before + $quantity : $before - $quantity);
            if ($after < 0) { throw new InventoryAdjustmentException(['quantity' => 'inventory_insufficient_stock']); }
            if ($after > NumericInput::MAX_QUANTITY) { throw new InventoryAdjustmentException(['quantity' => 'inventory_stock_limit']); }
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
