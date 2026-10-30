<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Customers extends MY_Controller
{
    public function index(): void
    {
        $this->requireAdmin();
        $this->render('customers/index', ['customers' => $this->db->order_by('id', 'desc')->get('customers')->result_array()]);
    }

    public function data(): void
    {
        $this->requireAdmin();
        $page = max(1, (int) $this->input->get('page', true)); $perPage = min(100, max(10, (int) $this->input->get('per_page', true) ?: 10)); $search = trim((string) $this->input->get('q', true));
        $this->db->start_cache()->from('customers');
        if ($search !== '') { $this->db->group_start()->like('name', $search)->or_like('phone', $search)->or_like('email', $search)->group_end(); }
        $this->db->stop_cache(); $total = $this->db->count_all_results();
        $rows = $this->db->order_by('id', 'desc')->limit($perPage, ($page - 1) * $perPage)->get()->result_array(); $this->db->flush_cache();
        foreach ($rows as &$row) { $row['edit_url'] = site_url('customers/edit/' . $row['id']); $row['edit_label'] = lang('edit'); }
        $this->json(true, ['rows' => $rows, 'pagination' => ['page'=>$page, 'per_page'=>$perPage, 'total'=>$total, 'total_pages'=>max(1, (int) ceil($total / $perPage))]]);
    }

    public function create(): void { $this->requireAdmin(); $this->form(); }
    public function edit(int $id): void { $this->requireAdmin(); $customer = $this->db->where('id', $id)->get('customers')->row_array(); if (!$customer) { show_404(); } $this->form($customer); }
    public function store(): void { $this->requirePost(); $this->save(); }
    public function update(int $id): void { $this->requirePost(); $this->save($id); }
    public function quick_create(): void { $this->requireAuth(); $this->form(null, true); }
    public function quick_store(): void { $this->requirePost(); $this->save(null, true); }

    private function form(?array $customer = null, bool $invoiceQuick = false): void { $this->render('customers/form', ['customer' => $customer, 'invoice_quick' => $invoiceQuick]); }

    private function save(?int $id = null, bool $invoiceQuick = false): void
    {
        if ($invoiceQuick) { $this->requireAuth(); } else { $this->requireAdmin(); }
        $this->form_validation->set_rules('name', lang('name'), 'required|max_length[150]');
        $this->form_validation->set_rules('phone', lang('phone'), 'max_length[30]');
        $this->form_validation->set_rules('email', lang('email'), 'valid_email|max_length[150]');
        if (!$this->form_validation->run()) { $this->form($id ? $this->db->where('id', $id)->get('customers')->row_array() : null, $invoiceQuick); return; }
        try {
            $customerId = $this->customerService()->save($this->input->post(null, true), (int) $this->currentUser['id'], $id);
            $this->session->set_flashdata('flash', ['type' => 'success', 'message' => lang('customer_saved')]); redirect($invoiceQuick ? 'invoices/create?customer_id=' . $customerId : 'customers');
        } catch (\App\Exceptions\DuplicateCustomerException $exception) {
            $this->session->set_flashdata('flash', ['type' => 'error', 'message' => lang('duplicate_customer')]); redirect($invoiceQuick ? 'customers/quick_create' : ($id ? 'customers/edit/' . $id : 'customers/create'));
        }
    }
}
