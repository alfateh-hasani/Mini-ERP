<div class="page-header d-flex flex-wrap gap-2 align-items-center">
  <h2 class="page-title"><?= e(lang('low_stock_report')) ?></h2>
  <a id="low-stock-export" class="btn btn-outline-primary ms-auto" href="<?= e(site_url('reports/low_stock') . '?' . http_build_query(['export' => 1, 'warehouse_id' => $scope, 'q' => $search])) ?>"><?= e(lang('export_csv')) ?></a>
</div>
<div class="card">
  <div class="product-filters" data-table-filter-group>
    <label class="form-label" for="report-warehouse"><?= e(lang('warehouse')) ?></label>
    <select class="form-select" id="report-warehouse" data-no-search data-table-filter="low-stock-table" data-filter-key="warehouse_id" <?= $current_user['role'] === ROLE_WAREHOUSE ? 'disabled' : '' ?>>
      <?php if ($current_user['role'] === ROLE_ADMIN): ?><option value=""><?= e(lang('all_warehouses')) ?></option><?php endif; ?>
      <?php foreach ($warehouses as $warehouse): ?>
        <option value="<?= (int) $warehouse['id'] ?>" <?= (int) $warehouse['id'] === $scope ? 'selected' : '' ?>><?= e(($lang === 'ar' ? $warehouse['name_ar'] : $warehouse['name']) . ($warehouse['status'] === STATUS_INACTIVE ? ' — ' . lang('inactive') : '')) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <table id="low-stock-table" class="table table-vcenter data-table" data-source="<?= site_url('reports/data') ?>" data-query="<?= e($search) ?>" data-export-target="low-stock-export" data-fields="warehouse,code,name,name_ar,quantity,alert_quantity,stock_shortfall">
    <thead><tr><th><?= e(lang('warehouse')) ?></th><th><?= e(lang('code')) ?></th><th><?= e(lang('product')) ?></th><th><?= e(lang('arabic_name')) ?></th><th><?= e(lang('quantity')) ?></th><th><?= e(lang('alert_quantity')) ?></th><th><?= e(lang('stock_shortfall')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr class="table-warning">
          <td><?= e($row['warehouse']) ?></td><td><?= e($row['code']) ?></td><td><?= e($row['name']) ?></td><td><?= e($row['name_ar']) ?></td><td><?= e($row['quantity']) ?></td><td><?= e($row['alert_quantity']) ?></td>
          <td><span class="<?= (int) $row['stock_shortfall'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>"><?= (int) $row['stock_shortfall'] > 0 ? e($row['stock_shortfall']) : '&mdash;' ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
