<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory extends MY_Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $scope = $this->warehouseScope($this->input->get('warehouse_id', true), false);
        if ($scope && !(new \App\Repositories\InvoiceRepository($this->db))->warehouseIsActive($scope)) {
            $this->render('inventory/unavailable');
            return;
        }
        $repository = new \App\Repositories\InventoryRepository($this->db);
        $page = $this->inventoryService()->page([], $scope);
        $this->render('inventory/index', [
            'items' => $page['rows'],
            'scope' => $scope,
            'filter_products' => $repository->productOptions($scope),
            'filter_warehouses' => $scope
                ? $this->db->where('id', $scope)->get('warehouses')->result_array()
                : $this->db->where('status', STATUS_ACTIVE)->order_by('name')->get('warehouses')->result_array(),
            'warehouses' => $this->currentUser['role'] === ROLE_ADMIN ? $this->db->where('status', STATUS_ACTIVE)->order_by('name')->get('warehouses')->result_array() : [],
            'products' => $this->currentUser['role'] === ROLE_ADMIN ? $this->db->where('status', STATUS_ACTIVE)->where('deleted_at IS NULL', null, false)->order_by('code')->get('products')->result_array() : [],
        ]);
    }

    /** Server-side list contract used by the inventory table. */
    public function data(): void
    {
        $this->requireAuth();
        $scope = $this->warehouseScope($this->input->get('warehouse_id', true), false);
        if ($scope && !(new \App\Repositories\InvoiceRepository($this->db))->warehouseIsActive($scope)) {
            $this->json(false, null, lang('warehouse_inactive'), ['code' => 'WAREHOUSE_INACTIVE']);
            return;
        }
        try {
            $result = $this->inventoryService()->page($this->input->get(null, true) ?: [], $scope);
            if ($this->session->userdata('lang') === 'ar') {
                foreach ($result['rows'] as &$row) {
                    $row['warehouse_name'] = $row['warehouse_name_ar'];
                    $row['name'] = $row['name_ar'];
                }
            }
            $this->json(true, $result);
        } catch (\App\Exceptions\ValidationException $exception) {
            $this->json(false, null, lang('invalid_inventory_filters'), ['code' => 'VALIDATION_FAILED']);
        }
    }

    public function filter_products(): void
    {
        $this->requireAuth();
        $scope = $this->warehouseScope($this->input->get('warehouse_id', true), false);
        if ($scope && !(new \App\Repositories\InvoiceRepository($this->db))->warehouseIsActive($scope)) {
            $this->json(true, ['products' => []]);
            return;
        }
        $this->json(true, ['products' => (new \App\Repositories\InventoryRepository($this->db))->productOptions($scope)]);
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
