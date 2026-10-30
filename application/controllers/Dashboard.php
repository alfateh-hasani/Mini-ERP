<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $scope = $this->warehouseScope($this->input->get('warehouse_id', true), false);
        $data = ['warehouse_id' => $scope, 'stats' => ['products' => 0, 'stock' => 0, 'invoices' => 0, 'sales' => '0.00'], 'recentInvoices' => [], 'lowStockCount' => 0];
        if ($this->db->table_exists('products')) {
            $data['stats']['products'] = $this->db->where('deleted_at', null)->count_all_results('products');
        }
        if ($this->db->table_exists('inventory')) {
            $stock = $this->db->select_sum('quantity');
            if ($scope) {
                $stock->where('warehouse_id', $scope);
            }
            $data['stats']['stock'] = (int) ($stock->get('inventory')->row()->quantity ?? 0);
            $lowStock = $this->db->from('inventory')->join('products', 'products.id=inventory.product_id')->where('inventory.quantity <= products.alert_quantity', null, false);
            if ($scope) { $lowStock->where('inventory.warehouse_id', $scope); }
            $data['lowStockCount'] = $lowStock->count_all_results();
        }
        if ($this->db->table_exists('invoices')) {
            $month = date('Y-m-01 00:00:00');
            $invoices = $this->db->where('created_at >=', $month)->where('status', 'issued');
            if ($scope) { $invoices->where('warehouse_id', $scope); }
            $data['stats']['invoices'] = $invoices->count_all_results('invoices');
            $sales = $this->db->select_sum('total')->where('created_at >=', $month)->where('status', 'issued');
            if ($scope) { $sales->where('warehouse_id', $scope); }
            $data['stats']['sales'] = $sales->get('invoices')->row()->total ?? '0.00';
            $recent = $this->db->select('invoices.id, invoices.invoice_number, invoices.total, invoices.status, customers.name AS customer_name')->from('invoices')->join('customers', 'customers.id=invoices.customer_id')->order_by('invoices.id', 'desc')->limit(5);
            if ($scope) { $recent->where('invoices.warehouse_id', $scope); }
            $data['recentInvoices'] = $recent->get()->result_array();
        }
        $this->render('dashboard/index', $data);
    }
}
