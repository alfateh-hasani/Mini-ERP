<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
    protected $currentUser;

    public function __construct()
    {
        parent::__construct();
        $lang = $this->session->userdata('lang') ?: 'ar';
        $this->config->set_item('language', $lang === 'en' ? 'english' : 'arabic');
        $this->lang->load('app', $this->config->item('language'));
        $this->currentUser = $this->session->userdata('user');
    }

    protected function requireAuth(): void
    {
        if (!$this->currentUser) {
            redirect('login');
        }
    }

    protected function requireAdmin(): void
    {
        $this->requireAuth();
        if ($this->currentUser['role'] !== ROLE_ADMIN) {
            show_error(lang('forbidden'), 403);
        }
    }

    protected function requirePost(): void
    {
        if ($this->input->method() !== 'post') {
            show_error(lang('method_not_allowed'), 405);
        }
    }

    /** Warehouse users cannot widen scope; historical reads may include inactive warehouses. */
    protected function warehouseScope($requestedId = null, bool $requireActive = true): ?int
    {
        $this->requireAuth();
        if ($this->currentUser['role'] === ROLE_WAREHOUSE) {
            $warehouseId = (int) $this->currentUser['warehouse_id'];
            if (!$warehouseId) { show_error(lang('warehouse_inactive'), 403); return null; }
            $query = $this->db->where('id', $warehouseId);
            if ($requireActive) { $query->where('status', STATUS_ACTIVE); }
            if ($query->count_all_results('warehouses') !== 1) {
                show_error(lang('warehouse_inactive'), 403);
            }
            return $warehouseId;
        }
        $warehouseId = $requestedId ? (int) $requestedId : null;
        if ($warehouseId) {
            $query = $this->db->where('id', $warehouseId);
            if ($requireActive) { $query->where('status', STATUS_ACTIVE); }
            if ($query->count_all_results('warehouses') !== 1) { show_error(lang('forbidden'), 403); }
        }
        return $warehouseId;
    }

    protected function render(string $view, array $data = [], string $layout = 'app'): void
    {
        $data['lang'] = $this->session->userdata('lang') ?: 'ar';
        $data['dir'] = $data['lang'] === 'ar' ? 'rtl' : 'ltr';
        $data['current_user'] = $this->currentUser;
        $data['flash'] = $this->session->flashdata('flash');
        $route = $this->router->class;
        $data['breadcrumbs'] = [['url' => site_url(), 'label' => lang('dashboard')]];
        if ($route !== 'dashboard') {
            $data['breadcrumbs'][] = ['url' => current_url(), 'label' => lang($route) ?: ucfirst($route)];
        }
        $data['content'] = $this->load->view($view, $data, true);
        $this->load->view('layouts/' . $layout, $data);
    }

    protected function json(bool $success, $data = null, string $message = '', array $error = []): void
    {
        $payload = ['success' => $success, 'csrf_hash' => $this->security->get_csrf_hash()];
        if ($success) {
            $payload['data'] = $data;
            $payload['message'] = $message;
            // Keep paginated endpoints compatible with the DataTables server-side
            // protocol as well as the lightweight local table client.
            if ($this->input->get('draw') !== null && is_array($data) && isset($data['rows'], $data['pagination'])) {
                $payload['draw'] = (int) $this->input->get('draw', true);
                $payload['recordsTotal'] = (int) $data['pagination']['total'];
                $payload['recordsFiltered'] = (int) $data['pagination']['total'];
            }
        } else {
            $payload['error'] = $error + ['code' => 'INTERNAL_ERROR', 'message' => $message];
        }
        $this->output->set_content_type('application/json')->set_output(json_encode($payload));
    }

    protected function productService(): \App\Services\ProductService
    {
        return new \App\Services\ProductService(new \App\Repositories\ProductRepository($this->db));
    }

    protected function inventoryService(): \App\Services\InventoryService
    {
        return new \App\Services\InventoryService(new \App\Repositories\InventoryRepository($this->db));
    }

    protected function invoiceService(): \App\Services\InvoiceService
    {
        return new \App\Services\InvoiceService(new \App\Repositories\InvoiceRepository($this->db));
    }

    protected function customerService(): \App\Services\CustomerService
    {
        return new \App\Services\CustomerService(new \App\Repositories\CustomerRepository($this->db));
    }
}
