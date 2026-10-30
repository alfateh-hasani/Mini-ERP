<?php
$selectedWarehouseInactive = false;
foreach ($filter_warehouses as $filterWarehouse) {
    if ((int) $filterWarehouse['id'] === $scope && $filterWarehouse['status'] === STATUS_INACTIVE) { $selectedWarehouseInactive = true; }
}
?>
<div class="page-header"><h2><?= lang('inventory') ?></h2></div>
<div class="card">
<form class="inventory-filters" id="inventory-filters" data-table-filter-form="inventory-table" data-products-url="<?= site_url('inventory/filter_products') ?>" data-all-products="<?= e(lang('all_products')) ?>" data-invalid-range="<?= e(lang('invalid_inventory_filters')) ?>" data-load-error="<?= e(lang('filter_products_failed')) ?>">
  <div class="inventory-filter-heading"><h3 class="card-title mb-0"><?= e(lang('filter_inventory')) ?></h3><span class="text-muted small"><?= e(lang('quantity_range_help')) ?></span></div>
  <div class="inventory-filter-fields">
    <div><label class="form-label" for="inventory-warehouse"><?= e(lang('warehouse')) ?></label>
      <select class="form-select" id="inventory-warehouse" name="warehouse_id" data-no-search <?= $current_user['role'] === ROLE_WAREHOUSE ? 'disabled' : '' ?>>
        <?php if ($current_user['role'] === ROLE_ADMIN): ?><option value=""><?= e(lang('all_warehouses')) ?></option><?php endif; ?>
        <?php foreach ($filter_warehouses as $warehouse): ?><option data-inactive="<?= $warehouse['status'] === STATUS_INACTIVE ? 'true' : 'false' ?>" value="<?= (int) $warehouse['id'] ?>" <?= (int) $warehouse['id'] === $scope ? 'selected' : '' ?>><?= e(($lang === 'ar' ? $warehouse['name_ar'] : $warehouse['name']) . ($warehouse['status'] === STATUS_INACTIVE ? ' ? ' . lang('inactive') : '')) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="inventory-filter-product"><label class="form-label" for="inventory-product"><?= e(lang('product')) ?></label>
      <select class="form-select" id="inventory-product" name="product_id"><option value=""><?= e(lang('all_products')) ?></option>
        <?php foreach ($filter_products as $product): ?><option value="<?= (int) $product['id'] ?>"><?= e($product['code'] . ' — ' . ($lang === 'ar' ? $product['name_ar'] : $product['name'])) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div><label class="form-label" for="inventory-min"><?= e(lang('quantity_from')) ?></label><input class="form-control" id="inventory-min" name="quantity_min" type="number" min="0" max="2147483647" step="1" placeholder="0" inputmode="numeric"></div>
    <div><label class="form-label" for="inventory-max"><?= e(lang('quantity_to')) ?></label><input class="form-control" id="inventory-max" name="quantity_max" type="number" min="0" max="2147483647" step="1" placeholder="<?= e(lang('no_limit')) ?>" inputmode="numeric"></div>
    <div class="inventory-filter-actions"><button type="submit" class="btn btn-primary"><?= e(lang('apply_filters')) ?></button><button type="reset" class="btn btn-outline-secondary"><?= e(lang('reset_filters')) ?></button></div>
  </div>
  <div class="alert alert-warning mt-3 mb-0" data-inventory-read-only role="status" <?= $selectedWarehouseInactive ? '' : 'hidden' ?>><?= e(lang('inventory_read_only')) ?></div>
  <div class="text-danger small mt-2" data-filter-error role="alert" hidden></div>
</form>
<table id="inventory-table" class="table data-table" data-source="<?= site_url('inventory/data') ?>" data-fields="warehouse_name,code,name,quantity,alert_quantity,stock_shortfall"><thead><tr><th><?= lang('warehouse') ?></th><th><?= lang('code') ?></th><th><?= lang('product') ?></th><th><?= lang('quantity') ?></th><th><?= lang('alert_quantity') ?></th><th><?= lang('stock_shortfall') ?></th></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr class="<?= $item['quantity'] <= $item['alert_quantity'] ? 'table-warning' : '' ?>"><td><?= e($item['warehouse_name']) ?></td><td><?= e($item['code']) ?></td><td><?= e($item['name']) ?></td><td><?= e($item['quantity']) ?></td><td><?= e($item['alert_quantity']) ?></td><td><span class="<?= $item['stock_shortfall'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>"><?= $item['stock_shortfall'] > 0 ? e($item['stock_shortfall']) : '&mdash;' ?></span></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if ($current_user['role'] === ROLE_ADMIN): ?>
  <?php $this->load->view('inventory/_adjustment', compact('adjustment', 'scope', 'selectedWarehouseInactive', 'warehouses', 'products', 'lang')); ?>
<?php endif; ?>

<script src="<?= base_url('public/assets/js/inventory.js') ?>?v=2" defer></script>

<script src="<?= base_url('public/assets/js/stock-adjustment.js') ?>?v=1" defer></script>
