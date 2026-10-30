<?php
$values = $adjustment['values'] ?? [];
$errors = $adjustment['errors'] ?? [];
$value = static function (string $field, string $default = '') use ($values): string { return (string) ($values[$field] ?? $default); };
$invalid = static function (string $field) use ($errors): string { return empty($errors[$field]) ? '' : ' is-invalid'; };
$aria = static function (string $field) use ($errors): string { return empty($errors[$field]) ? '' : 'aria-invalid="true" aria-describedby="stock-' . $field . '-error"'; };
$feedback = static function (string $field) use ($errors): void {
    if (!empty($errors[$field])) { echo '<div class="invalid-feedback d-block" id="stock-' . $field . '-error" role="alert">' . e($errors[$field]) . '</div>'; }
};
$needsReason = in_array($value('mode', 'set'), ['add', 'remove'], true);
?>
<div class="card mt-3" id="inventory-adjustment"><div class="card-body">
  <h3 class="card-title"><?= e(lang('inventory_adjustment')) ?></h3>
  <?php if (!empty($adjustment['message'])): ?><div class="alert alert-danger" role="alert"><?= e($adjustment['message']) ?></div><?php endif; ?>
  <?= form_open('inventory/adjust', ['data-stock-adjustment' => '', 'data-products-url' => site_url('inventory/adjustment_products'), 'data-product-placeholder' => lang('inventory_product_required'), 'data-load-error' => lang('stock_products_failed'), 'data-empty-products' => lang('stock_products_empty')]) ?>
    <fieldset data-inventory-adjustment <?= $selectedWarehouseInactive ? 'disabled' : '' ?>>
      <div class="row">
        <div class="col-md-3 mb-2">
          <label class="form-label" for="stock-warehouse_id"><?= e(lang('warehouse')) ?></label>
          <select class="form-select<?= $invalid('warehouse_id') ?>" id="stock-warehouse_id" name="warehouse_id" <?= $aria('warehouse_id') ?> required>
            <option value=""><?= e(lang('inventory_warehouse_required')) ?></option>
            <?php foreach ($warehouses as $warehouse): ?><option value="<?= (int) $warehouse['id'] ?>" <?= $value('warehouse_id', $scope ? (string) $scope : '') === (string) $warehouse['id'] ? 'selected' : '' ?>><?= e($lang === 'ar' ? $warehouse['name_ar'] : $warehouse['name']) ?></option><?php endforeach; ?>
          </select>
          <?php $feedback('warehouse_id'); ?>
        </div>
        <div class="col-md-3 mb-2">
          <label class="form-label" for="stock-product_id"><?= e(lang('product')) ?></label>
          <select class="form-select<?= $invalid('product_id') ?>" id="stock-product_id" name="product_id" <?= $aria('product_id') ?> <?= $products ? '' : 'disabled' ?> required>
            <option value=""><?= e(lang('inventory_product_required')) ?></option>
            <?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>" <?= $value('product_id') === (string) $product['id'] ? 'selected' : '' ?>><?= e($product['code'] . ' — ' . ($lang === 'ar' ? $product['name_ar'] : $product['name'])) ?></option><?php endforeach; ?>
          </select>
          <?php $feedback('product_id'); ?>
          <label class="form-check mt-2" data-enrolment-option <?= $value('mode', 'set') === 'remove' ? 'hidden' : '' ?>>
            <input class="form-check-input" type="checkbox" name="include_unstocked" value="1" <?= $value('include_unstocked') === '1' && $value('mode', 'set') !== 'remove' ? 'checked' : '' ?>>
            <span class="form-check-label"><?= e(lang('stock_include_unstocked')) ?></span>
          </label>
          <div class="small text-muted mt-1" data-stock-product-status role="status" aria-live="polite"></div>
        </div>
        <div class="col-md-2 mb-2">
          <label class="form-label" for="stock-mode"><?= e(lang('adjustment_type')) ?></label>
          <select class="form-select<?= $invalid('mode') ?>" id="stock-mode" name="mode" <?= $aria('mode') ?>>
            <?php foreach (['set' => 'set_final_quantity', 'add' => 'add_stock', 'remove' => 'remove_stock'] as $mode => $label): ?><option value="<?= $mode ?>" <?= $value('mode', 'set') === $mode ? 'selected' : '' ?>><?= e(lang($label)) ?></option><?php endforeach; ?>
          </select>
          <?php $feedback('mode'); ?>
        </div>
        <div class="col-md-2 mb-2">
          <label class="form-label" for="stock-quantity"><?= e(lang('quantity')) ?></label>
          <input class="form-control<?= $invalid('quantity') ?>" id="stock-quantity" name="quantity" value="<?= e($value('quantity')) ?>" type="number" min="<?= $needsReason ? 1 : 0 ?>" max="2147483647" step="1" <?= $aria('quantity') ?> required>
          <?php $feedback('quantity'); ?>
        </div>
        <div class="col-md-2 mb-2">
          <label class="form-label" for="stock-reason"><?= e(lang('adjustment_reason')) ?> <span class="text-danger" data-reason-required <?= $needsReason ? '' : 'hidden' ?>>*</span></label>
          <input class="form-control<?= $invalid('reason') ?>" id="stock-reason" name="reason" value="<?= e($value('reason')) ?>" maxlength="255" placeholder="<?= e(lang('required_for_add_remove')) ?>" <?= $aria('reason') ?> <?= $needsReason ? 'required' : '' ?>>
          <?php $feedback('reason'); ?>
        </div>
        <div class="col-12">
          <p class="text-muted mb-2"><?= e(lang('inventory_adjustment_help')) ?> <?= e(lang('stock_enrolment_help')) ?></p>
          <button class="btn btn-primary" type="submit"><?= e(lang('save')) ?></button>
        </div>
      </div>
    </fieldset>
  <?= form_close() ?>
</div></div>
