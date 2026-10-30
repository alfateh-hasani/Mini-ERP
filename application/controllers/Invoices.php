<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Invoices extends MY_Controller
{
    public function create(): void
    {
        $this->requireAuth();
        $scope = $this->warehouseScope(null, false);
        if ($scope && !(new \App\Repositories\InvoiceRepository($this->db))->warehouseIsActive($scope)) {
            $this->render('invoices/unavailable');
            return;
        }
        $warehouses = $scope
            ? $this->db->where('id', $scope)->get('warehouses')->result_array()
            : $this->db->where('status', STATUS_ACTIVE)->order_by('name')->get('warehouses')->result_array();
        if (!$warehouses) {
            $this->render('invoices/unavailable');
            return;
        }
        $data = [
            'customers' => $this->db->order_by('name')->get('customers')->result_array(),
            'selected_customer_id' => (int) $this->input->get('customer_id', true),
            'warehouses' => $warehouses,
            'scope' => $scope,
        ];
        $this->render('invoices/create', $data);
    }

    public function index(): void
    {
        $this->requireAuth(); $scope = $this->warehouseScope($this->input->get('warehouse_id', true), false);
        $query = $this->db->select('invoices.*, customers.name AS customer_name, warehouses.name AS warehouse_name')->from('invoices')->join('customers', 'customers.id=invoices.customer_id')->join('warehouses', 'warehouses.id=invoices.warehouse_id')->order_by('invoices.id', 'desc');
        if ($scope) { $query->where('invoices.warehouse_id', $scope); }
        $this->render('invoices/index', ['invoices' => $query->get()->result_array()]);
    }

    public function data(): void
    {
        $this->requireAuth(); $scope = $this->warehouseScope($this->input->get('warehouse_id', true), false);
        $page = max(1, (int) $this->input->get('page', true)); $perPage = min(100, max(10, (int) $this->input->get('per_page', true) ?: 10)); $search = trim((string) $this->input->get('q', true));
        $this->db->start_cache()->from('invoices')->join('customers', 'customers.id=invoices.customer_id')->join('warehouses', 'warehouses.id=invoices.warehouse_id');
        if ($scope) { $this->db->where('invoices.warehouse_id', $scope); }
        if ($search !== '') { $this->db->group_start()->like('invoices.invoice_number', $search)->or_like('customers.name', $search)->or_like('warehouses.name', $search)->or_like('invoices.status', $search)->group_end(); }
        $this->db->stop_cache(); $total = $this->db->count_all_results();
        $rows = $this->db->select('invoices.id, invoices.invoice_number, invoices.status, invoices.total, customers.name AS customer_name, warehouses.name AS warehouse_name, warehouses.name_ar AS warehouse_name_ar')->order_by('invoices.id', 'desc')->limit($perPage, ($page - 1) * $perPage)->get()->result_array(); $this->db->flush_cache();
        foreach ($rows as &$row) { if ($this->session->userdata('lang') === 'ar') { $row['warehouse_name'] = $row['warehouse_name_ar']; } $row['status'] = lang($row['status']); $row['total'] = 'SAR ' . number_format((float) $row['total'], 2); $row['show_url'] = site_url('invoices/show/' . $row['id']); }
        $this->json(true, ['rows' => $rows, 'pagination' => ['page'=>$page, 'per_page'=>$perPage, 'total'=>$total, 'total_pages'=>max(1, (int) ceil($total / $perPage))]]);
    }

    public function show(int $id): void
    {
        $this->requireAuth(); $invoice = (new \App\Repositories\InvoiceRepository($this->db))->findScoped($id, $this->warehouseScope(null, false));
        if (!$invoice) { show_error(lang('forbidden'), 403); }
        $this->render('invoices/show', ['invoice' => $invoice]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requirePost();
        $payload = json_decode((string) $this->input->post('payload', false), true);
        if (!is_array($payload)) { $this->json(false, null, lang('invalid_invoice'), ['code' => 'VALIDATION_FAILED']); return; }
        $scope = $this->warehouseScope($payload['warehouse_id'] ?? null, false);
        $warehouseId = $scope ?: (int) ($payload['warehouse_id'] ?? 0);
        if ($warehouseId && !(new \App\Repositories\InvoiceRepository($this->db))->warehouseIsActive($warehouseId)) {
            $this->json(false, null, lang('warehouse_inactive'), ['code' => 'WAREHOUSE_INACTIVE']);
            return;
        }
        try { $this->json(true, $this->invoiceService()->create($payload, (int) $this->currentUser['id'], $scope), lang('invoice_saved')); }
        catch (\App\Exceptions\InsufficientStockException $exception) { $this->json(false, null, lang('insufficient_stock'), ['code' => 'INSUFFICIENT_STOCK']); }
        catch (\App\Exceptions\AppException $exception) { $this->json(false, null, lang('invalid_invoice'), ['code' => 'VALIDATION_FAILED']); }
    }

    public function cancel(int $id): void
    {
        $this->requireAuth();
        $this->requirePost();
        try { $this->invoiceService()->cancel($id, (int) $this->currentUser['id'], $this->warehouseScope()); $this->session->set_flashdata('flash', ['type' => 'success', 'message' => lang('invoice_cancelled_restored')]); }
        catch (\Throwable $exception) { $this->session->set_flashdata('flash', ['type' => 'error', 'message' => lang('invoice_cancel_failed')]); }
        redirect('invoices/show/' . $id);
    }
}
