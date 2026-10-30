<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Products extends MY_Controller
{
    public function index(): void
    {
        $this->requireAdmin();
        $data['products'] = $this->db->select('products.*, categories.name AS category')->from('products')->join('categories', 'categories.id=products.category_id')->where('products.deleted_at IS NULL', null, false)->order_by('products.id', 'desc')->get()->result_array();
        $data['categories'] = $this->db->order_by('name')->get('categories')->result_array();
        $this->render('products/index', $data);
    }

    public function data(): void
    {
        $this->requireAdmin();
        $page = max(1, (int) $this->input->get('page', true));
        $perPage = min(100, max(10, (int) $this->input->get('per_page', true) ?: 10));
        $search = trim((string) $this->input->get('q', true));
        $categoryId = (int) $this->input->get('category_id', true);
        $this->db->start_cache();
        $this->db->from('products')->join('categories', 'categories.id=products.category_id')->where('products.deleted_at IS NULL', null, false);
        if ($categoryId > 0) { $this->db->where('products.category_id', $categoryId); }
        if ($search !== '') { $this->db->group_start()->like('products.code', $search)->or_like('products.name', $search)->or_like('products.name_ar', $search)->or_like('categories.name', $search)->group_end(); }
        $this->db->stop_cache();
        $total = $this->db->count_all_results();
        $rows = $this->db->select('products.id, products.code, products.name, products.name_ar, products.price, products.status, categories.name AS category, categories.name_ar AS category_ar')->order_by('products.id', 'desc')->limit($perPage, ($page - 1) * $perPage)->get()->result_array();
        $this->db->flush_cache();
        $arabic = $this->session->userdata('lang') === 'ar';
        foreach ($rows as &$row) { $row['category'] = $arabic ? $row['category_ar'] : $row['category']; $row['price'] = sar($row['price']); $row['status_value'] = $row['status']; $row['status'] = lang($row['status']); $row['status_url'] = site_url('products/toggle_status/' . $row['id']); $row['edit_url'] = site_url('products/edit/' . $row['id']); $row['edit_label'] = lang('edit'); }
        $this->json(true, ['rows' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => max(1, (int) ceil($total / $perPage))]]);
    }

    public function create(): void
    {
        $this->requireAdmin();
        $this->form();
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();
        $product = $this->db->where('id', $id)->where('deleted_at IS NULL', null, false)->get('products')->row_array();
        if (!$product) { show_404(); }
        $this->form($product);
    }

    public function store(): void { $this->requirePost(); $this->save(); }
    public function update(int $id): void { $this->requirePost(); $this->save($id); }
    public function toggle_status(int $id): void
    {
        $this->requirePost(); $this->requireAdmin();
        $product = $this->db->where('id', $id)->where('deleted_at IS NULL', null, false)->get('products')->row_array();
        if (!$product) { show_404(); }
        $status = $product['status'] === STATUS_ACTIVE ? STATUS_INACTIVE : STATUS_ACTIVE;
        $this->db->where('id', $id)->update('products', ['status' => $status]);
        $this->json(true, ['id' => $id, 'status' => $status, 'label' => lang($status)], lang('status_updated'));
    }

    public function search(): void
    {
        $this->requireAuth();
        $scope = $this->warehouseScope($this->input->get('warehouse_id', true), false);
        if (!$scope || !(new \App\Repositories\InvoiceRepository($this->db))->warehouseIsActive($scope)) {
            $this->json(true, ['results' => []]);
            return;
        }
        $this->json(true, ['results' => $this->productService()->search((string) $this->input->get('q', true), $scope)]);
    }

    private function form(?array $product = null): void
    {
        $this->config->load('units');
        $this->render('products/form', ['product' => $product, 'categories' => $this->db->where('status', 'active')->order_by('name')->get('categories')->result_array(), 'warehouses' => $this->db->where('status', 'active')->order_by('name')->get('warehouses')->result_array(), 'units' => $this->config->item('units')]);
    }

    private function save(?int $id = null): void
    {
        $this->requireAdmin();
        $this->config->load('units');
        $units = $this->config->item('units');
        foreach (['code' => 'required|alpha_dash|max_length[50]', 'name' => 'required|max_length[200]', 'name_ar' => 'required|max_length[200]', 'category_id' => 'required|is_natural_no_zero', 'unit' => 'required|in_list[' . implode(',', array_keys($units)) . ']', 'price' => 'required|numeric|greater_than_equal_to[0]', 'alert_quantity' => 'required|is_natural'] as $field => $rules) { $this->form_validation->set_rules($field, lang($field) ?: $field, $rules); }
        if (!$this->form_validation->run()) { $this->form($id ? $this->db->where('id', $id)->get('products')->row_array() : null); return; }
        try {
            $input = $this->input->post(null, true);
            $input['unit_ar'] = $units[$input['unit']]['ar'];
            $this->productService()->save($input, (int) $this->currentUser['id'], $id);
            $this->session->set_flashdata('flash', ['type' => 'success', 'message' => lang('product_saved')]);
            redirect('products');
        } catch (\App\Exceptions\ValidationException $exception) {
            $this->session->set_flashdata('flash', ['type' => 'error', 'message' => lang('product_save_invalid')]);
            redirect($id ? 'products/edit/' . $id : 'products/create');
        } catch (\Throwable $exception) {
            log_message('error', 'Product save failed: ' . $exception->getMessage());
            $this->session->set_flashdata('flash', ['type' => 'error', 'message' => lang('product_save_failed')]);
            redirect($id ? 'products/edit/' . $id : 'products/create');
        }
    }
}
