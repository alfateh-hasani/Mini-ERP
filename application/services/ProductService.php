<?php
namespace App\Services;

use App\Exceptions\ValidationException;
use App\Repositories\ProductRepository;

class ProductService
{
    private $products;

    public function __construct(ProductRepository $products)
    {
        $this->products = $products;
    }

    public function save(array $input, int $userId, ?int $id = null): int
    {
        $code = strtoupper(trim($input['code']));
        if ($this->products->codeExists($code, $id)) {
            throw new ValidationException('Product code is already in use.');
        }
        $data = [
            'category_id' => NumericInput::integer($input['category_id']), 'code' => $code,
            'name' => trim($input['name']), 'name_ar' => trim($input['name_ar']),
            'unit' => trim($input['unit']),
            'price' => NumericInput::decimal(NumericInput::hundredths($input['price'], NumericInput::MAX_MONEY)),
            'alert_quantity' => NumericInput::integer($input['alert_quantity'], 0, NumericInput::MAX_QUANTITY), 'status' => $input['status'] ?? 'active',
        ];
        if (!$id) { $data['created_by'] = $userId; }
        if ($id) { return $this->products->save($data, $id); }
        $warehouse = $input['warehouse_id'] ?? '';
        $warehouseId = $warehouse === '' || $warehouse === null ? null : NumericInput::integer($warehouse);
        // Hidden initial stock is ignored when no warehouse is selected.
        $quantity = $warehouseId ? NumericInput::integer($input['initial_quantity'] ?? '0', 0, NumericInput::MAX_QUANTITY) : 0;
        return $this->products->createWithStock($data, $warehouseId, $quantity, $userId);
    }

    public function search(string $term, ?int $warehouseId): array
    {
        return array_map(static function (array $product): array {
            return ['id' => (int) $product['id'], 'text' => $product['code'] . ' — ' . $product['name'], 'code' => $product['code'], 'name' => $product['name'], 'name_ar' => $product['name_ar'], 'price' => (float) $product['price'], 'available_qty' => (int) $product['available_qty'], 'unit' => $product['unit']];
        }, $this->products->activeSearch($term, $warehouseId));
    }
}
