<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory extends MY_Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $scope = $this->warehouseScope($this->input->get('warehouse_id', true));
        $query = $this->db->select('inventory.*, products.code, products.name, products.name_ar, products.alert_quantity, warehouses.name AS warehouse_name')->from('inventory')->join('products', 'products.id=inventory.product_id')->join('warehouses', 'warehouses.id=inventory.warehouse_id')->order_by('products.code');
        if ($scope) { $query->where('inventory.warehouse_id', $scope); }
        $this->render('inventory/index', ['items' => $query->get()->result_array(), 'scope' => $scope, 'warehouses' => $this->currentUser['role'] === ROLE_ADMIN ? $this->db->where('status', STATUS_ACTIVE)->order_by('name')->get('warehouses')->result_array() : [], 'products' => $this->currentUser['role'] === ROLE_ADMIN ? $this->db->where('status', STATUS_ACTIVE)->where('deleted_at IS NULL', null, false)->order_by('code')->get('products')->result_array() : []]);
    }

    /** Server-side list contract used by the inventory table. */
    public function data(): void
    {
        $this->requireAuth();
        $scope = $this->warehouseScope($this->input->get('warehouse_id', true));
        $page = max(1, (int) $this->input->get('page', true));
        $perPage = min(100, max(10, (int) $this->input->get('per_page', true) ?: 10));
        $search = trim((string) $this->input->get('q', true));
        $this->db->start_cache();
        $this->db->from('inventory')->join('products', 'products.id=inventory.product_id')->join('warehouses', 'warehouses.id=inventory.warehouse_id');
        if ($scope) { $this->db->where('inventory.warehouse_id', $scope); }
        if ($search !== '') { $this->db->group_start()->like('products.code', $search)->or_like('products.name', $search)->or_like('products.name_ar', $search)->or_like('warehouses.name', $search)->group_end(); }
        $this->db->stop_cache();
        $total = $this->db->count_all_results();
        $rows = $this->db->select('inventory.quantity, products.code, products.name, products.name_ar, products.alert_quantity, warehouses.name AS warehouse_name, warehouses.name_ar AS warehouse_name_ar')
            ->order_by('products.code')->limit($perPage, ($page - 1) * $perPage)->get()->result_array();
        $this->db->flush_cache(); foreach ($rows as &$row) { if ($this->session->userdata('lang') === 'ar') { $row['warehouse_name'] = $row['warehouse_name_ar']; $row['name'] = $row['name_ar']; } }
        $this->json(true, ['rows' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => max(1, (int) ceil($total / $perPage))]]);
    }

    public function adjust(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $warehouseId = $this->warehouseScope($this->input->post('warehouse_id', true));
        $productId = (int) $this->input->post('product_id', true);
        $quantity = (int) $this->input->post('quantity', true);
        $mode = (string) $this->input->post('mode', true);
        $reason = trim((string) $this->input->post('reason', true));
        if (!$warehouseId || !$productId || $quantity < 0 || !in_array($mode, ['set', 'add', 'remove'], true) || (($mode === 'add' || $mode === 'remove') && $reason === '')) { show_error(lang('invalid_inventory_adjustment'), 422); }
        $this->db->trans_begin();
        $row = $this->db->query('SELECT id, quantity FROM inventory WHERE warehouse_id = ? AND product_id = ? FOR UPDATE', [$warehouseId, $productId])->row_array();
        if (!$row) { $this->db->trans_rollback(); show_error(lang('invalid_inventory_adjustment'), 422); return; }
        $before = (int) $row['quantity'];
        $after = $mode === 'set' ? $quantity : ($mode === 'add' ? $before + $quantity : $before - $quantity);
        if ($after < 0) { $this->db->trans_rollback(); show_error(lang('invalid_inventory_adjustment'), 422); return; }
        $this->db->where('id', $row['id'])->update('inventory', ['quantity' => $after]);
        $this->db->insert('inventory_movements', ['warehouse_id' => $warehouseId, 'product_id' => $productId, 'movement_type' => $mode, 'quantity_before' => $before, 'quantity_change' => $after - $before, 'quantity_after' => $after, 'reason' => $reason ?: null, 'created_by' => (int) $this->currentUser['id']]);
        $this->db->trans_commit();
        $this->session->set_flashdata('flash', ['type' => 'success', 'message' => lang('inventory_updated')]); redirect('inventory');
    }
}
