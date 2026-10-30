<tr>
  <td class="invoice-product-cell" data-label="<?= e(lang('product')) ?>"><div class="position-relative product-picker">
    <input class="form-control product-search" type="search" autocomplete="off" placeholder="<?= e(lang('product')) ?>" aria-label="<?= e(lang('product')) ?>">
    <input class="product-id" type="hidden">
    <div class="list-group product-results position-absolute w-100 d-none"></div>
  </div>
    <div class="quantity-stock" aria-live="polite"><span class="stock-available"><?= e(lang('select_product_for_stock')) ?></span><span class="stock-feedback" hidden></span></div>

  </td>
  <td data-label="<?= e(lang('unit_price')) ?>">
    <output class="unit-price invoice-money">—</output>
  </td>
  <td class="invoice-quantity-cell" data-label="<?= e(lang('quantity')) ?>">
    <input class="form-control quantity" type="number" min="1" max="2147483647" step="1" value="1" required disabled aria-label="<?= e(lang('quantity')) ?>">
  </td>
  <td data-label="<?= e(lang('discount')) ?> %"><div class="input-group invoice-discount-input"><input class="form-control discount" type="number" min="0" max="100" step="0.01" value="0" required aria-label="<?= e(lang('discount')) ?> %"><span class="input-group-text">%</span></div><small class="discount-error text-danger" role="status" hidden></small></td>
  <td data-label="<?= e(lang('line_discount')) ?>"><output class="discount-amount invoice-money fw-bold">?</output></td>
  <td data-label="<?= e(lang('before_discount')) ?>"><output class="line-subtotal invoice-money">—</output></td>
  <td data-label="<?= e(lang('after_discount')) ?>"><output class="line-total invoice-money fw-bold">—</output></td>
  <td class="invoice-remove-cell"><button type="button" class="btn btn-sm btn-outline-danger remove-line"><?= e(lang('remove')) ?></button></td>
</tr>
