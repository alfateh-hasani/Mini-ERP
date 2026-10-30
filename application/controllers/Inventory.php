<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory extends MY_Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $scope = $this->warehouseScope($this->input->get('warehouse_id', true), false);
        if ($this->currentUser['role'] !== ROLE_ADMIN && $scope && !(new \App\Repositories\InvoiceRepository($this->db))->warehouseIsActive($scope)) {
            $this->render('inventory/unavailable');
            return;
        }
        $repository = new \App\Repositories\InventoryRepository($this->db);
        $page = $this->inventoryService()->page([], $scope);
        $adjustment = $this->session->flashdata('inventory_adjustment') ?: ['values' => [], 'errors' => []];
        $adjustmentWarehouse = (int) ($adjustment['values']['warehouse_id'] ?? $scope ?? 0);
        $this->render('inventory/index', [
            'items' => $page['rows'],
            'scope' => $scope,
            'adjustment' => $adjustment,
            'filter_products' => $repository->productOptions($scope),
            'filter_warehouses' => $this->currentUser['role'] === ROLE_ADMIN
                ? $this->db->order_by('name')->get('warehouses')->result_array()
                : $this->db->where('id', $scope)->get('warehouses')->result_array(),
            'warehouses' => $this->currentUser['role'] === ROLE_ADMIN ? $this->db->where('status', STATUS_ACTIVE)->order_by('name')->get('warehouses')->result_array() : [],
            'products' => $this->currentUser['role'] === ROLE_ADMIN ? $repository->adjustmentProductOptions($adjustmentWarehouse, ($adjustment['values']['include_unstocked'] ?? '') === '1' && ($adjustment['values']['mode'] ?? 'set') !== 'remove') : [],
        ]);
    }

    /** Server-side list contract used by the inventory table. */
    public function data(): void
    {
        $this->requireAuth();
        $scope = $this->warehouseScope($this->input->get('warehouse_id', true), false);
        if ($this->currentUser['role'] !== ROLE_ADMIN && $scope && !(new \App\Repositories\InvoiceRepository($this->db))->warehouseIsActive($scope)) {
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
        if ($this->currentUser['role'] !== ROLE_ADMIN && $scope && !(new \App\Repositories\InvoiceRepository($this->db))->warehouseIsActive($scope)) {
            $this->json(true, ['products' => []]);
            return;
        }
        $this->json(true, ['products' => (new \App\Repositories\InventoryRepository($this->db))->productOptions($scope)]);
    }

    public function adjustment_products(): void
    {
        $this->requireAdmin();
        $warehouseId = filter_var($this->input->get('warehouse_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
        $includeUnstocked = $this->input->get('include_unstocked') === '1';
        $this->json(true, ['products' => (new \App\Repositories\InventoryRepository($this->db))->adjustmentProductOptions($warehouseId, $includeUnstocked)]);
    }

    public function adjust(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $input = $this->input->post(null, true) ?: [];
        $errors = [];
        try {
            $this->inventoryService()->adjust($input, (int) $this->currentUser['id']);
            $flash = ['type' => 'success', 'message' => lang('inventory_updated')];
        } catch (\App\Exceptions\InventoryAdjustmentException $exception) {
            foreach ($exception->fieldErrors() as $field => $key) { $errors[$field] = lang($key); }
            $flash = ['type' => 'error', 'message' => reset($errors)];
        } catch (\App\Exceptions\ValidationException $exception) {
            $flash = ['type' => 'error', 'message' => lang('invalid_inventory_adjustment')];
        } catch (\Throwable $exception) {
            log_message('error', 'Inventory adjustment failed: ' . $exception->getMessage());
            $flash = ['type' => 'error', 'message' => lang('inventory_update_failed')];
        }
        if ($flash['type'] === 'error') {
            $values = [];
            foreach (['warehouse_id', 'product_id', 'mode', 'quantity', 'reason', 'include_unstocked'] as $field) {
                if (isset($input[$field]) && is_scalar($input[$field])) { $values[$field] = mb_substr((string) $input[$field], 0, 1024); }
            }
            $this->session->set_flashdata('inventory_adjustment', ['values' => $values, 'errors' => $errors, 'message' => $flash['message']]);
        }
        $this->session->set_flashdata('flash', $flash);
        redirect($flash['type'] === 'error' ? 'inventory#inventory-adjustment' : 'inventory');
    }
}
