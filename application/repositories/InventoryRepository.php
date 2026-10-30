<?php
namespace App\Repositories;

class InventoryRepository extends BaseRepository
{
    public function lockActiveWarehouse(int $id): void
    {
        $row = $this->db->query('SELECT id FROM warehouses WHERE id = ? AND status = ? FOR UPDATE', [$id, 'active'])->row_array();
        if (!$row) { throw new \App\Exceptions\InventoryAdjustmentException(['warehouse_id' => 'inventory_warehouse_required']); }
    }

    public function lockActiveProduct(int $id): void
    {
        $row = $this->db->query('SELECT id FROM products WHERE id = ? AND status = ? AND deleted_at IS NULL FOR UPDATE', [$id, 'active'])->row_array();
        if (!$row) { throw new \App\Exceptions\InventoryAdjustmentException(['product_id' => 'inventory_product_required']); }
    }

    public function lockStock(int $warehouseId, int $productId): ?array
    {
        return $this->db->query('SELECT id, quantity FROM inventory WHERE warehouse_id = ? AND product_id = ? FOR UPDATE', [$warehouseId, $productId])->row_array();
    }

    public function enrol(int $warehouseId, int $productId): void
    {
        if (!$this->db->query('INSERT INTO inventory (warehouse_id, product_id, quantity) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE id = id', [$warehouseId, $productId])) {
            throw new \RuntimeException('Cannot enrol product in warehouse.');
        }
    }

    public function writeAdjustment(int $warehouseId, int $productId, int $before, int $after, string $mode, ?string $reason, int $userId): void
    {
        if (!$this->db->where(['warehouse_id' => $warehouseId, 'product_id' => $productId])->update('inventory', ['quantity' => $after])) {
            throw new \RuntimeException('Stock write failed.');
        }
        if (!$this->db->insert('inventory_movements', [
            'warehouse_id' => $warehouseId, 'product_id' => $productId, 'movement_type' => $mode,
            'quantity_before' => $before, 'quantity_change' => $after - $before, 'quantity_after' => $after,
            'reason' => $reason, 'created_by' => $userId,
        ])) { throw new \RuntimeException('Stock movement write failed.'); }
    }

    public function page(?int $warehouseId, array $filters, int $page, int $perPage, string $search): array
    {
        $this->db->start_cache();
        $this->db->from('inventory')->join('products', 'products.id=inventory.product_id')
            ->join('warehouses', 'warehouses.id=inventory.warehouse_id');
        if ($warehouseId) { $this->db->where('inventory.warehouse_id', $warehouseId); }
        if ($filters['product_id']) { $this->db->where('inventory.product_id', $filters['product_id']); }
        if ($filters['quantity_min'] !== null) { $this->db->where('inventory.quantity >=', $filters['quantity_min']); }
        if ($filters['quantity_max'] !== null) { $this->db->where('inventory.quantity <=', $filters['quantity_max']); }
        if ($search !== '') {
            $this->db->group_start()->like('products.code', $search)->or_like('products.name', $search)
                ->or_like('products.name_ar', $search)->or_like('warehouses.name', $search)
                ->or_like('warehouses.name_ar', $search)->group_end();
        }
        $this->db->stop_cache();
        try {
            $total = $this->db->count_all_results();
            $totalPages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $totalPages);
            $rows = $this->db->select('inventory.quantity, products.code, products.name, products.name_ar, products.alert_quantity, warehouses.name AS warehouse_name, warehouses.name_ar AS warehouse_name_ar')
                ->order_by('products.code')->order_by('inventory.warehouse_id')
                ->limit($perPage, ($page - 1) * $perPage)->get()->result_array();
            return ['rows' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $totalPages]];
        } finally {
            $this->db->flush_cache();
        }
    }

    public function adjustmentProductOptions(int $warehouseId, bool $includeUnstocked = false): array
    {
        if ($warehouseId < 1) { return []; }
        $query = $this->db->select('products.id, products.code, products.name, products.name_ar')
            ->from('products')->where('products.status', 'active')->where('products.deleted_at', null);
        $query->join('warehouses', 'warehouses.id = ' . (int) $warehouseId . " AND warehouses.status = 'active'");
        if (!$includeUnstocked) {
            $query->join('inventory', 'inventory.product_id = products.id AND inventory.warehouse_id = warehouses.id');
        }
        return $query->order_by('products.code')->get()->result_array();
    }

    public function productOptions(?int $warehouseId): array
    {
        $query = $this->db->distinct()->select('products.id, products.code, products.name, products.name_ar')
            ->from('products')->join('inventory', 'inventory.product_id=products.id');
        if ($warehouseId) { $query->where('inventory.warehouse_id', $warehouseId); }
        return $query->order_by('products.code')->get()->result_array();
    }
}
