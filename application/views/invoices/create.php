<div class="page-header"><h2><?= e(lang('create_invoice')) ?></h2></div>
<div class="card"><div class="card-body">
  <form id="invoice-form" action="<?= site_url('invoices/store') ?>" data-product-search="<?= site_url('products/search') ?>">
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label required" for="customer_id"><?= e(lang('customer')) ?></label>
        <div class="d-flex gap-2 invoice-customer-picker">
          <select class="form-select" id="customer_id" required data-placeholder="<?= e(lang('select_customer')) ?>">
            <option value="" <?= !$selected_customer_id ? 'selected' : '' ?>><?= e(lang('select_customer')) ?></option>
            <?php foreach ($customers as $customer): ?><option value="<?= (int) $customer['id'] ?>" <?= (int) $customer['id'] === (int) $selected_customer_id ? 'selected' : '' ?>><?= e($customer['name']) ?></option><?php endforeach; ?>
          </select>
          <a class="btn btn-icon btn-outline-primary" href="<?= site_url('customers/quick_create') ?>" aria-label="<?= e(lang('add_customer')) ?>" title="<?= e(lang('add_customer')) ?>">+</a>
        </div>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label" for="warehouse_id"><?= e(lang('warehouse')) ?></label>
        <select class="form-select" id="warehouse_id" <?= $scope ? 'disabled' : '' ?>><?php foreach ($warehouses as $warehouse): ?><option value="<?= (int) $warehouse['id'] ?>"><?= e($lang === 'ar' ? $warehouse['name_ar'] : $warehouse['name']) ?></option><?php endforeach; ?></select>
      </div>
    </div>
    <table class="table table-vcenter invoice-editor-table">
      <thead><tr><th scope="col"><?= e(lang('product')) ?></th><th scope="col"><?= e(lang('unit_price')) ?></th><th scope="col"><?= e(lang('quantity')) ?></th><th scope="col"><?= e(lang('discount')) ?> %</th><th scope="col"><?= e(lang('before_discount')) ?></th><th scope="col"><?= e(lang('after_discount')) ?></th><th scope="col"><span class="visually-hidden"><?= e(lang('remove')) ?></span></th></tr></thead>
      <tbody id="invoice-lines"><?php $this->load->view('invoices/_line'); ?></tbody>
    </table>
    <div class="invoice-footer">
      <button class="btn btn-outline-primary align-self-start" type="button" id="add-line">+ <?= e(lang('add_line')) ?></button>
      <div class="invoice-summary">
        <h3 class="card-title"><?= e(lang('invoice_summary')) ?></h3>
        <div><span><?= e(lang('before_discount')) ?></span><output id="invoice-subtotal">SAR 0.00</output></div>
        <div class="text-muted"><span><?= e(lang('discount_amount')) ?></span><output id="invoice-discount">SAR 0.00</output></div>
        <div class="invoice-grand-total"><strong><?= e(lang('after_discount')) ?></strong><output id="invoice-total" aria-live="polite">SAR 0.00</output></div>
        <button class="btn btn-primary w-100 mt-3" type="submit"><?= e(lang('save_invoice')) ?></button>
      </div>
    </div>
  </form>
  <div id="invoice-message" class="mt-3" role="alert" aria-live="assertive" tabindex="-1"></div>
</div></div>
<template id="invoice-line-template"><?php $this->load->view('invoices/_line'); ?></template>
<script src="<?= base_url('public/assets/js/invoice-totals.js') ?>?v=2"></script>
<script src="<?= base_url('public/assets/js/invoice.js') ?>?v=6"></script>
