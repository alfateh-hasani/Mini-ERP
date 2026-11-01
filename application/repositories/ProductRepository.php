<?php
namespace App\Repositories;

class ProductRepository extends BaseRepository
{
    /** Count catalogue products, optionally limited to an assigned warehouse. */
    public function countForWarehouse(?int $warehouseId = null): int
    {
        $query = $this->db->from('products')->where('products.deleted_at', null);
        if ($warehouseId !== null) {
            $query->join('inventory', 'inventory.product_id = products.id')
                ->where('inventory.warehouse_id', $warehouseId);
        }
        // The unique warehouse/product key prevents counting a product twice.
        return (int) $query->count_all_results();
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $query = $this->db->where('code', $code);
        if ($exceptId) { $query->where('id !=', $exceptId); }
        return $query->count_all_results('products') > 0;
    }

    public function save(array $data, ?int $id = null): int
    {
        if ($id) {
            if (!$this->db->where('id', $id)->update('products', $data)) { throw new \RuntimeException('Product update failed.'); }
            return $id;
        }
        if (!$this->db->insert('products', $data)) { throw new \RuntimeException('Product creation failed.'); }
        return (int) $this->db->insert_id();
    }

    public function createWithStock(array $data, ?int $warehouseId, int $quantity, int $userId): int
    {
        return $this->transaction(function () use ($data, $warehouseId, $quantity, $userId): int {
            $inventory = new InventoryRepository($this->db);
            if ($warehouseId) { $inventory->lockActiveWarehouse($warehouseId); }
            $category = $this->db->query('SELECT id FROM categories WHERE id = ? AND status = ? FOR UPDATE', [$data['category_id'], 'active'])->row_array();
            if (!$category) { throw new \App\Exceptions\ValidationException('Category is inactive or missing.'); }
            $id = $this->save($data);
            if ($warehouseId) {
                $inventory->enrol($warehouseId, $id);
                $inventory->writeAdjustment($warehouseId, $id, 0, $quantity, 'set', null, $userId);
            }
            return $id;
        });
    }

    public function activeSearch(string $term, ?int $warehouseId): array
    {
        if (!$warehouseId) { return []; }
        $this->db->select('products.*, inventory.quantity AS available_qty')
            ->from('products')->join('inventory', 'inventory.product_id = products.id')
            ->join('warehouses', 'warehouses.id = inventory.warehouse_id')
            ->where('warehouses.status', 'active')
            ->where('inventory.warehouse_id', $warehouseId)
            ->where('products.status', 'active')->where('products.deleted_at IS NULL', null, false)
            ->group_start()->like('products.code', $term)->or_like('products.name', $term)->or_like('products.name_ar', $term)->group_end()
            ->order_by('products.code')->limit(20);
        return $this->db->get()->result_array();
    }
}
