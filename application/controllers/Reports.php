<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reports extends MY_Controller
{
    public function data(): void
    {
        $this->requireAuth(); $scope = $this->warehouseScope($this->input->get('warehouse_id', true));
        $page = max(1, (int) $this->input->get('page', true)); $perPage = min(100, max(10, (int) $this->input->get('per_page', true) ?: 10)); $search = trim((string) $this->input->get('q', true));
        $this->db->start_cache();
        $this->db->from('inventory')->join('products', 'products.id=inventory.product_id')->join('warehouses', 'warehouses.id=inventory.warehouse_id')->where('inventory.quantity <= products.alert_quantity', null, false);
        if ($scope) { $this->db->where('inventory.warehouse_id', $scope); }
        if ($search !== '') { $this->db->group_start()->like('products.code', $search)->or_like('products.name', $search)->or_like('products.name_ar', $search)->or_like('warehouses.name', $search)->group_end(); }
        $this->db->stop_cache(); $total = $this->db->count_all_results();
        $rows = $this->db->select('warehouses.name AS warehouse, warehouses.name_ar AS warehouse_ar, products.code, products.name, products.name_ar, inventory.quantity, products.alert_quantity')->order_by('products.code')->limit($perPage, ($page - 1) * $perPage)->get()->result_array(); $this->db->flush_cache(); foreach ($rows as &$row) { if ($this->session->userdata('lang') === 'ar') { $row['warehouse'] = $row['warehouse_ar']; $row['name'] = $row['name_ar']; } }
        $this->json(true, ['rows' => $rows, 'pagination' => ['page'=>$page, 'per_page'=>$perPage, 'total'=>$total, 'total_pages'=>max(1, (int) ceil($total / $perPage))]]);
    }

    public function low_stock(): void
    {
        $this->requireAuth(); $scope = $this->warehouseScope($this->input->get('warehouse_id', true));
        $query = $this->db->select('warehouses.name AS warehouse, products.code, products.name, products.name_ar, inventory.quantity, products.alert_quantity')->from('inventory')->join('products', 'products.id=inventory.product_id')->join('warehouses', 'warehouses.id=inventory.warehouse_id')->where('inventory.quantity <= products.alert_quantity', null, false)->order_by('products.code');
        if ($scope) { $query->where('inventory.warehouse_id', $scope); }
        $rows = $query->get()->result_array();
        if ($this->input->get('export')) { $this->csv($rows); return; }
        $this->render('reports/low_stock', ['rows' => $rows]);
    }

    private function csv(array $rows): void
    {
        $this->output->set_content_type('text/csv')->set_header('Content-Disposition: attachment; filename="low-stock.csv"');
        $stream = fopen('php://temp', 'r+'); fwrite($stream, "\xEF\xBB\xBF"); fputcsv($stream, ['Warehouse', 'Code', 'Name', 'Arabic name', 'Quantity', 'Alert quantity']);
        foreach ($rows as $row) { fputcsv($stream, array_map('csv_safe', array_values($row))); }
        rewind($stream); $this->output->set_output(stream_get_contents($stream)); fclose($stream);
    }
}
