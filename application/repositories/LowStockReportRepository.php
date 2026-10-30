<?php
namespace App\Repositories;

/** The same filtered dataset supplies the report page and CSV export. */
class LowStockReportRepository extends BaseRepository
{
    private function matchingQuery(?int $warehouseId, string $search)
    {
        $query = $this->db->from('inventory')
            ->join('products', 'products.id = inventory.product_id')
            ->join('warehouses', 'warehouses.id = inventory.warehouse_id')
            ->where('inventory.quantity <= products.alert_quantity', null, false);
        if ($warehouseId !== null) { $query->where('inventory.warehouse_id', $warehouseId); }
        if ($search !== '') {
            $query->group_start()->like('products.code', $search)
                ->or_like('products.name', $search)->or_like('products.name_ar', $search)
                ->or_like('warehouses.name', $search)->or_like('warehouses.name_ar', $search)->group_end();
        }
        return $query;
    }

    private function rowsQuery(?int $warehouseId, string $search)
    {
        return $this->matchingQuery($warehouseId, $search)
            ->select('warehouses.name AS warehouse, warehouses.name_ar AS warehouse_ar, products.code, products.name, products.name_ar, inventory.quantity, products.alert_quantity')
            ->select('(products.alert_quantity - inventory.quantity) AS stock_shortfall', false)
            ->order_by('products.code')->order_by('inventory.warehouse_id');
    }

    public function page(?int $warehouseId, string $search, int $page, int $perPage): array
    {
        $total = $this->matchingQuery($warehouseId, $search)->count_all_results();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $totalPages);
        $rows = $this->rowsQuery($warehouseId, $search)->limit($perPage, ($page - 1) * $perPage)->get()->result_array();
        return ['rows' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $totalPages]];
    }

    /** Export all matches, independently of the currently displayed page. */
    public function all(?int $warehouseId, string $search): array
    {
        return $this->rowsQuery($warehouseId, $search)->get()->result_array();
    }

    public function warehouses(?int $assignedWarehouseId): array
    {
        if ($assignedWarehouseId !== null) { $this->db->where('id', $assignedWarehouseId); }
        return $this->db->select('id, name, name_ar, status')->order_by('name')->get('warehouses')->result_array();
    }
}
