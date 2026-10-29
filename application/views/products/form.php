<?php $value = static function ($key) use ($product) { return e($product[$key] ?? set_value($key)); }; ?>
<div class="page-header"><h2><?= $product ? lang('edit_product') : lang('add_product') ?></h2></div>
<div class="card"><div class="card-body">
<?= validation_errors('<div class="alert alert-danger">', '</div>') ?>
<?= form_open($product ? 'products/update/' . $product['id'] : 'products/store') ?>
<div class="row">
  <div class="col-md-4 mb-3"><label class="form-label"><?= lang('code') ?></label><input class="form-control" name="code" value="<?= $value('code') ?>" required></div>
  <div class="col-md-4 mb-3"><label class="form-label"><?= lang('english_name') ?></label><input class="form-control" name="name" value="<?= $value('name') ?>" required></div>
  <div class="col-md-4 mb-3"><label class="form-label"><?= lang('arabic_name') ?></label><input class="form-control" name="name_ar" value="<?= $value('name_ar') ?>" required></div>
  <div class="col-md-4 mb-3"><label class="form-label"><?= lang('category') ?></label><select class="form-select" name="category_id"><?php foreach ($categories as $category): ?><option value="<?= $category['id'] ?>" <?= (string) ($product['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>><?= e($lang === 'ar' ? $category['name_ar'] : $category['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-4 mb-3"><label class="form-label"><?= lang('unit') ?></label><select class="form-select" name="unit" required><?php foreach ($units as $code => $labels): ?><option value="<?= e($code) ?>" <?= $value('unit') === $code ? 'selected' : '' ?>><?= e($labels[$lang]) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2 mb-3"><label class="form-label"><?= lang('sar_price') ?></label><input class="form-control" name="price" value="<?= $value('price') ?>" required></div>
  <div class="col-md-2 mb-3"><label class="form-label"><?= lang('alert_quantity') ?></label><input class="form-control" name="alert_quantity" value="<?= $value('alert_quantity') ?>" required></div>
  <?php if (!$product): ?>
    <div class="col-md-6 mb-3"><label class="form-label"><?= lang('warehouse') ?></label><select class="form-select" name="warehouse_id"><option value=""><?= lang('no_initial_stock') ?></option><?php foreach ($warehouses as $warehouse): ?><option value="<?= $warehouse['id'] ?>"><?= e($lang === 'ar' ? $warehouse['name_ar'] : $warehouse['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6 mb-3"><label class="form-label"><?= lang('initial_stock') ?></label><input class="form-control" type="number" min="0" name="initial_quantity" value="0"></div>
  <?php endif; ?>
</div>
<button class="btn btn-primary"><?= lang('save') ?></button><?= form_close() ?>
</div></div>
