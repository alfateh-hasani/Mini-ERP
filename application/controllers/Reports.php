<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reports extends MY_Controller
{
    private function filters(): array
    {
        $input = $this->input->get(null, false) ?: [];
        foreach (['warehouse_id', 'q', 'page', 'per_page'] as $field) {
            if (isset($input[$field]) && !is_scalar($input[$field])) { show_error(lang('invalid_report_filters'), 400); }
        }
        $warehouseId = null;
        if (isset($input['warehouse_id']) && $input['warehouse_id'] !== '') {
            try {
                $warehouseId = \App\Services\NumericInput::integer($input['warehouse_id']);
            } catch (\App\Exceptions\ValidationException $exception) {
                show_error(lang('invalid_report_filters'), 400);
            }
        }
        return [
            'scope' => $this->warehouseScope($warehouseId, false),
            'search' => trim((string) ($input['q'] ?? '')),
            'page' => max(1, (int) ($input['page'] ?? 1)),
            'per_page' => min(100, max(10, (int) ($input['per_page'] ?? 10))),
        ];
    }

    private function unavailable(?int $scope): bool
    {
        return $this->currentUser['role'] === ROLE_WAREHOUSE && $scope
            && !(new \App\Repositories\InvoiceRepository($this->db))->warehouseIsActive($scope);
    }

    private function localizedRows(array $rows): array
    {
        if ($this->session->userdata('lang') === 'ar') {
            foreach ($rows as &$row) {
                $row['warehouse'] = $row['warehouse_ar'];
                $row['name'] = $row['name_ar'];
            }
            unset($row);
        }
        return $rows;
    }

    public function data(): void
    {
        $this->requireAuth();
        $filters = $this->filters();
        if ($this->unavailable($filters['scope'])) {
            $this->json(false, null, lang('warehouse_inactive'), ['code' => 'WAREHOUSE_INACTIVE']);
            return;
        }
        $repository = new \App\Repositories\LowStockReportRepository($this->db);
        $result = $repository->page($filters['scope'], $filters['search'], $filters['page'], $filters['per_page']);
        $result['rows'] = $this->localizedRows($result['rows']);
        $this->json(true, $result);
    }

    public function low_stock(): void
    {
        $this->requireAuth();
        $filters = $this->filters();
        if ($this->unavailable($filters['scope'])) {
            $this->render('reports/unavailable');
            return;
        }
        $repository = new \App\Repositories\LowStockReportRepository($this->db);
        if ($this->input->get('export') === '1') {
            $this->csv($this->localizedRows($repository->all($filters['scope'], $filters['search'])));
            return;
        }
        $result = $repository->page($filters['scope'], $filters['search'], 1, 10);
        $this->render('reports/low_stock', [
            'rows' => $this->localizedRows($result['rows']),
            'scope' => $filters['scope'],
            'search' => $filters['search'],
            'warehouses' => $repository->warehouses($this->currentUser['role'] === ROLE_WAREHOUSE ? $filters['scope'] : null),
        ]);
    }

    private function csv(array $rows): void
    {
        $this->output->set_content_type('text/csv')->set_header('Content-Disposition: attachment; filename="low-stock.csv"');
        $columns = ['warehouse' => 'warehouse', 'code' => 'code', 'name' => 'product', 'name_ar' => 'arabic_name', 'quantity' => 'quantity', 'alert_quantity' => 'alert_quantity', 'stock_shortfall' => 'stock_shortfall'];
        $stream = fopen('php://temp', 'r+');
        try {
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, array_map('lang', array_values($columns)), ',', '"', '');
            foreach ($rows as $row) {
                $values = [];
                foreach ($columns as $field => $label) { $values[] = csv_safe($row[$field]); }
                fputcsv($stream, $values, ',', '"', '');
            }
            rewind($stream);
            $this->output->set_output(stream_get_contents($stream));
        } finally {
            fclose($stream);
        }
    }
}
