<div class="invoice-detail">
  <div class="invoice-detail-header">
    <div><div class="text-muted mb-1"><?= e(lang('invoice_details')) ?></div><h2 class="page-title"><bdi dir="ltr"><?= e($invoice['invoice_number']) ?></bdi></h2></div>
    <div class="invoice-detail-actions d-print-none">
      <a class="btn btn-outline-secondary" href="<?= site_url('invoices') ?>"><?= e(lang('back_to_invoices')) ?></a>
      <button class="btn btn-primary" type="button" data-invoice-print><?= e(lang('print_invoice')) ?></button>
      <?php if ($invoice['status'] === 'issued' && $invoice['warehouse_status'] === STATUS_ACTIVE): ?>
        <?= form_open('invoices/cancel/' . $invoice['id'], ['data-invoice-cancel' => '', 'data-confirm' => lang('confirm_cancel_invoice')]) ?><button class="btn btn-outline-danger" type="submit"><?= e(lang('cancel_invoice')) ?></button><?= form_close() ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-body invoice-detail-overview">
      <div class="invoice-detail-status"><span class="badge <?= $invoice['status'] === 'cancelled' ? 'bg-red-lt' : 'bg-green-lt' ?>"><?= e(lang($invoice['status'])) ?></span><span class="text-muted small"><?= e(lang('created_at')) ?>: <bdi dir="ltr"><?= e($invoice['created_at']) ?></bdi></span></div>
      <dl class="invoice-metadata">
        <div><dt><?= e(lang('customer')) ?></dt><dd><?= e($invoice['customer_name']) ?><?php if ($invoice['customer_phone']): ?><small><bdi dir="ltr"><?= e($invoice['customer_phone']) ?></bdi></small><?php endif; ?></dd></div>
        <div><dt><?= e(lang('warehouse')) ?></dt><dd><?= e($lang === 'ar' ? $invoice['warehouse_name_ar'] : $invoice['warehouse_name']) ?><?php if ($invoice['warehouse_status'] !== STATUS_ACTIVE): ?> <span class="badge bg-secondary-lt"><?= e(lang('inactive')) ?></span><?php endif; ?></dd></div>
        <div><dt><?= e(lang('created_by')) ?></dt><dd><?= e($invoice['created_by_name'] ?: '—') ?></dd></div>
        <?php if ($invoice['status'] === 'cancelled'): ?>
          <div><dt><?= e(lang('cancelled_by')) ?></dt><dd><?= e($invoice['cancelled_by_name'] ?: '—') ?><small><bdi dir="ltr"><?= e($invoice['cancelled_at']) ?></bdi></small></dd></div>
        <?php endif; ?>
      </dl>
    </div>
    <?php if ($invoice['status'] === 'cancelled'): ?><div class="invoice-cancelled-note" role="status"><?= e(lang('invoice_cancelled_restored')) ?></div><?php endif; ?>
    <div class="card-header"><h3 class="card-title"><?= e(lang('invoice_items')) ?></h3><span class="badge bg-secondary-lt ms-auto"><?= count($invoice['items']) ?> <?= e(lang('invoice_lines')) ?></span></div>
    <div class="card-body p-0">
      <table class="table table-vcenter invoice-read-table mb-0">
        <thead><tr><th scope="col"><?= e(lang('product')) ?></th><th scope="col"><?= e(lang('quantity')) ?></th><th scope="col"><?= e(lang('unit_price')) ?></th><th scope="col"><?= e(lang('line_discount')) ?></th><th scope="col"><?= e(lang('total')) ?></th></tr></thead>
        <tbody><?php foreach ($invoice['items'] as $item): ?><tr>
          <td class="invoice-read-product" data-label="<?= e(lang('product')) ?>"><strong><?= e($lang === 'ar' ? ($item['product_name_ar'] ?: $item['product_name']) : $item['product_name']) ?></strong><small class="text-muted"><bdi dir="ltr"><?= e($item['product_code']) ?></bdi></small></td>
          <td data-label="<?= e(lang('quantity')) ?>"><?= (int) $item['quantity'] ?><small class="text-muted"><?= e($units[$item['unit']][$lang] ?? $item['unit']) ?></small></td>
          <td data-label="<?= e(lang('unit_price')) ?>"><bdi dir="ltr"><?= e(sar($item['unit_price'])) ?></bdi></td>
          <td data-label="<?= e(lang('line_discount')) ?>"><bdi dir="ltr"><?= e(sar($item['discount_amount'])) ?></bdi></td>
          <td class="fw-bold" data-label="<?= e(lang('total')) ?>"><bdi dir="ltr"><?= e(sar($item['line_total'])) ?></bdi></td>
        </tr><?php endforeach; ?></tbody>
      </table>
    </div>
    <div class="card-body invoice-detail-footer">
      <div class="invoice-summary">
        <div><span><?= e(lang('before_discount')) ?></span><output><?= e(sar($invoice['subtotal'])) ?></output></div>
        <div class="text-muted"><span><?= e(lang('discount_amount')) ?></span><output><?= e(sar($invoice['discount_amount'])) ?></output></div>
        <div class="invoice-grand-total"><strong><?= e(lang('total')) ?></strong><output><?= e(sar($invoice['total'])) ?></output></div>
      </div>
    </div>
  </div>
</div>
<script src="<?= base_url('public/assets/js/invoice-details.js') ?>?v=1"></script>
