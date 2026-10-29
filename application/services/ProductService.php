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
            'category_id' => (int) $input['category_id'], 'code' => $code,
            'name' => trim($input['name']), 'name_ar' => trim($input['name_ar']),
            'unit' => trim($input['unit']), 'unit_ar' => trim($input['unit_ar']),
            'price' => number_format((float) $input['price'], 2, '.', ''),
            'alert_quantity' => (int) $input['alert_quantity'], 'status' => $input['status'] ?? 'active',
        ];
        if (!$id) { $data['created_by'] = $userId; }
        return $this->products->save($data, $id);
    }

    public function search(string $term, ?int $warehouseId): array
    {
        return array_map(static function (array $product): array {
            return ['id' => (int) $product['id'], 'text' => $product['code'] . ' — ' . $product['name'], 'code' => $product['code'], 'name' => $product['name'], 'name_ar' => $product['name_ar'], 'price' => (float) $product['price'], 'available_qty' => (int) $product['available_qty'], 'unit' => $product['unit'], 'unit_ar' => $product['unit_ar']];
        }, $this->products->activeSearch($term, $warehouseId));
    }
}
