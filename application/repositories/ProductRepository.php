<?php
namespace App\Repositories;

class ProductRepository extends BaseRepository
{
    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $query = $this->db->where('code', $code)->where('deleted_at IS NULL', null, false);
        if ($exceptId) { $query->where('id !=', $exceptId); }
        return $query->count_all_results('products') > 0;
    }

    public function save(array $data, ?int $id = null): int
    {
        if ($id) {
            $this->db->where('id', $id)->update('products', $data);
            return $id;
        }
        $this->db->insert('products', $data);
        return (int) $this->db->insert_id();
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
