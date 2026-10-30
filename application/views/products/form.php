<?php
$field = static function ($key, $default = '') use ($product): string {
    $submitted = set_value($key, $product[$key] ?? $default, false);
    return is_scalar($submitted) ? (string) $submitted : '';
};
$value = static function ($key, $default = '') use ($field) { return e($field($key, $default)); };
$selectedWarehouse = $field('warehouse_id');
$hasWarehouse = in_array($selectedWarehouse, array_map(static function ($warehouse) { return (string) $warehouse['id']; }, $warehouses), true);
?>
<div class="page-header"><h2><?= $product ? lang('edit_product') : lang('add_product') ?></h2></div>
<div class="card"><div class="card-body">
<?= validation_errors('<div class="alert alert-danger">', '</div>') ?>
<?php if (!empty($form_error)): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php endif; ?>
<?= form_open($product ? 'products/update/' . $product['id'] : 'products/store') ?>
<div class="row">
  <div class="col-md-4 mb-3"><label class="form-label"><?= lang('code') ?></label><input class="form-control" name="code" value="<?= $value('code') ?>" required></div>
  <div class="col-md-4 mb-3"><label class="form-label"><?= lang('english_name') ?></label><input class="form-control" name="name" value="<?= $value('name') ?>" required></div>
  <div class="col-md-4 mb-3"><label class="form-label"><?= lang('arabic_name') ?></label><input class="form-control" name="name_ar" value="<?= $value('name_ar') ?>" required></div>
  <div class="col-md-4 mb-3"><label class="form-label"><?= lang('category') ?></label><select class="form-select" name="category_id"><?php foreach ($categories as $category): ?><option value="<?= $category['id'] ?>" <?= $field('category_id') === (string) $category['id'] ? 'selected' : '' ?>><?= e($lang === 'ar' ? $category['name_ar'] : $category['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-4 mb-3"><label class="form-label"><?= lang('unit') ?></label><select class="form-select" name="unit" required><?php foreach ($units as $code => $labels): ?><option value="<?= e($code) ?>" <?= $value('unit') === $code ? 'selected' : '' ?>><?= e($labels[$lang]) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2 mb-3"><label class="form-label"><?= lang('sar_price') ?></label><input class="form-control" name="price" value="<?= $value('price') ?>" required></div>
  <div class="col-md-2 mb-3"><label class="form-label"><?= lang('alert_quantity') ?></label><input class="form-control" name="alert_quantity" value="<?= $value('alert_quantity') ?>" required></div>
  <?php if (!$product): ?>
    <div class="col-md-6 mb-3"><label class="form-label"><?= lang('warehouse') ?></label><select class="form-select" name="warehouse_id" data-initial-warehouse><option value="" <?= $selectedWarehouse === '' ? 'selected' : '' ?>><?= lang('no_initial_stock') ?></option><?php foreach ($warehouses as $warehouse): ?><option value="<?= $warehouse['id'] ?>" <?= $selectedWarehouse === (string) $warehouse['id'] ? 'selected' : '' ?>><?= e($lang === 'ar' ? $warehouse['name_ar'] : $warehouse['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6 mb-3" data-initial-stock <?= $hasWarehouse ? '' : 'hidden' ?>><label class="form-label"><?= lang('initial_stock') ?></label><input class="form-control" type="number" min="0" max="2147483647" step="1" name="initial_quantity" value="<?= $value('initial_quantity', '0') ?>" <?= $hasWarehouse ? 'required' : 'disabled' ?>></div>
  <?php endif; ?>
</div>
<button class="btn btn-primary"><?= lang('save') ?></button><?= form_close() ?>
</div></div>

<script src="<?= base_url('public/assets/js/product-form.js') ?>?v=1" defer></script>
