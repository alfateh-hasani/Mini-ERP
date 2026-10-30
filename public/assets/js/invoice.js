(() => {
  'use strict';
  const form = document.querySelector('#invoice-form');
  if (!form) return;
  const lines = document.querySelector('#invoice-lines');
  const notice = document.querySelector('#invoice-message');
  const warehouse = document.querySelector('#warehouse_id');
  const submit = form.querySelector('[type="submit"]');
  const arabic = document.documentElement.lang === 'ar';
  const calculator = window.InvoiceTotals;
  const money = value => calculator.format(value, arabic ? 'ar' : 'en');
  const labels = arabic ? {
    failed: 'تعذر حفظ الفاتورة. حدّث الصفحة وحاول مرة أخرى.',
    product: 'اختر منتجاً من نتائج البحث لكل بند.',
    customer: 'اختر العميل قبل حفظ الفاتورة.', duplicate: 'المنتج مضاف بالفعل. عدّل كميته في البند الموجود.',
    stockPrompt: 'اختر المنتج لعرض المخزون المتاح.', available: 'المتاح', remaining: 'المتبقي', excess: 'تجاوزت المتاح بـ', outOfStock: 'لا يوجد مخزون متاح.', stockLimit: maximum => `الكمية تتجاوز المخزون المتاح. الحد الأقصى ${maximum}.`,
    discountRange: 'أدخل خصماً من 0 إلى 100%.',
    saving: 'جارٍ الحفظ…', search: 'تعذر البحث عن المنتجات. حاول مرة أخرى.'
  } : {
    failed: 'Unable to save the invoice. Refresh the page and try again.',
    product: 'Select a product from the search results for every line.',
    customer: 'Select a customer before saving the invoice.', duplicate: 'This product is already added. Update its quantity in the existing line.',
    stockPrompt: 'Select a product to see available stock.', available: 'Available', remaining: 'Remaining', excess: 'Over stock by', outOfStock: 'Out of stock.', stockLimit: maximum => `Quantity exceeds available stock. Maximum: ${maximum}.`,
    discountRange: 'Enter a discount from 0 to 100%.',
    saving: 'Saving…', search: 'Unable to search products. Please try again.'
  };
  const showError = message => {
    notice.className = 'alert alert-danger mt-3';
    notice.textContent = message;
    notice.focus();
  };
  const clearMessage = () => { notice.textContent = ''; notice.className = 'mt-3'; };
  document.querySelector('#customer_id').addEventListener('change', clearMessage);
  const hide = picker => picker.querySelector('.product-results').classList.add('d-none');
  let stockSequence = 0;
  const updateStock = row => {
    const quantity = row.querySelector('.quantity');
    const hint = row.querySelector('.quantity-stock');
    const selected = !!row.querySelector('.product-id').value;
    const available = selected ? Number(row.dataset.availableQty) : null;
    if (!hint.id) hint.id = `invoice-stock-${++stockSequence}`;
    quantity.setAttribute('aria-describedby', hint.id);
    quantity.disabled = !selected;
    quantity.max = selected ? String(available) : '2147483647';
    quantity.setCustomValidity('');
    const requested = Number(quantity.value);
    const exceeds = selected && (available === 0 || requested > available);
    quantity.classList.toggle('is-invalid', exceeds);
    quantity.setAttribute('aria-invalid', String(exceeds));
    row.querySelector('.stock-available').textContent = selected ? `${labels.available}: ${available}` : labels.stockPrompt;
    const feedback = row.querySelector('.stock-feedback');
    feedback.className = 'stock-feedback' + (exceeds ? ' text-danger' : ' text-muted');
    feedback.textContent = !selected ? '' : available === 0 ? labels.outOfStock : exceeds ? `${labels.excess}: ${requested - available}` : quantity.value !== '' && Number.isInteger(requested) && requested > 0 ? `${labels.remaining}: ${available - requested}` : '';
    feedback.hidden = !feedback.textContent;
    if (exceeds) quantity.setCustomValidity(available === 0 ? labels.outOfStock : labels.stockLimit(available));
    return !exceeds;
  };
  const validDiscount = value => /^(?:\d+)(?:\.\d{1,2})?$/.test(value.trim()) && Number(value) <= 100;
  const recalculate = () => {
    let subtotal = 0n, discount = 0n;
    let complete = true;
    lines.querySelectorAll('tr').forEach(row => {
      const stockValid = updateStock(row);
      const discountInput = row.querySelector('.discount');
      const discountValue = discountInput.value.trim();
      const discountValid = validDiscount(discountValue);
      const discountInvalid = discountValue !== '' && !discountValid;
      discountInput.setCustomValidity(discountInvalid ? labels.discountRange : '');
      discountInput.classList.toggle('is-invalid', discountInvalid);
      discountInput.setAttribute('aria-invalid', String(discountInvalid));
      const discountError = row.querySelector('.discount-error');
      discountError.textContent = discountInvalid ? labels.discountRange : '';
      discountError.hidden = !discountInvalid;
      const selected = !!row.querySelector('.product-id').value;
      const amount = selected && stockValid && discountValid ? calculator.line(row.dataset.unitPrice, row.querySelector('.quantity').value, discountValue) : null;
      const unit = selected ? calculator.line(row.dataset.unitPrice, '1', '0') : null;
      row.querySelector('.unit-price').textContent = unit ? money(unit.unit) : '—';
      row.querySelector('.line-subtotal').textContent = amount ? money(amount.subtotal) : '—';
      row.querySelector('.line-total').textContent = amount ? money(amount.total) : '—';
      row.querySelector('.discount-amount').textContent = amount ? money(amount.discount) : '?';
      row.querySelector('.discount-amount').hidden = !discountValid;
      row.querySelector('.line-subtotal').hidden = !discountValid;
      row.querySelector('.line-total').hidden = !discountValid;
      if (amount) { subtotal += amount.subtotal; discount += amount.discount; }
      if (selected && !amount) complete = false;
    });
    document.querySelector('#invoice-subtotal').textContent = complete ? money(subtotal) : '—';
    document.querySelector('#invoice-discount').textContent = complete ? money(discount) : '—';
    document.querySelector('#invoice-total').textContent = complete ? money(subtotal - discount) : '—';
  };
  const pending = new WeakMap();
  form.addEventListener('invalid', event => showError(event.target.validationMessage || labels.failed), true);
  const find = async input => {
    const picker = input.closest('.product-picker');
    const results = picker.querySelector('.product-results');
    const query = input.value.trim();
    const selectedWarehouse = warehouse.value;
    if (!selectedWarehouse) { results.replaceChildren(); hide(picker); return; }
    if (query.length < 2) { results.replaceChildren(); hide(picker); return; }
    const request = new AbortController();
    pending.set(input, request);
    const url = new URL(form.dataset.productSearch, window.location.origin);
    url.searchParams.set('q', query);
    url.searchParams.set('warehouse_id', selectedWarehouse);
    try {
      const response = await fetch(url, { credentials: 'same-origin', signal: request.signal });
      if (!response.ok) throw new Error(labels.search);
      const json = await response.json();
      if (!json.success) throw new Error(json.error?.message || labels.search);
      if (input.value.trim() !== query || warehouse.value !== selectedWarehouse || !input.isConnected) return;
      results.replaceChildren();
      (json.data?.results || []).forEach(product => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'list-group-item list-group-item-action';
        button.textContent = `${product.code} — ${arabic ? product.name_ar : product.name} (${product.available_qty})`;
        button.addEventListener('click', () => {
          const row = input.closest('tr');
          const existing = [...lines.querySelectorAll('tr')].find(other => other !== row && other.querySelector('.product-id').value === String(product.id));
          if (existing) {
            showError(labels.duplicate);
            hide(picker);
            existing.querySelector('.quantity').focus();
            return;
          }
          input.value = `${product.code} — ${arabic ? product.name_ar : product.name}`;
          picker.querySelector('.product-id').value = product.id;
          row.dataset.unitPrice = String(product.price);
          row.dataset.availableQty = String(product.available_qty);
          hide(picker);
          clearMessage();
          recalculate();
        });
        results.appendChild(button);
      });
      results.classList.toggle('d-none', !results.children.length);
    } catch (error) {
      if (error.name !== 'AbortError') showError(labels.search);
    }
  };
  const timers = new WeakMap();
  lines.addEventListener('input', event => {
    const input = event.target;
    if (!input.classList.contains('product-search')) { recalculate(); return; }
    // Invalidate the selected ID immediately, before the debounce fires.
    input.closest('.product-picker').querySelector('.product-id').value = '';
    delete input.closest('tr').dataset.unitPrice;
    delete input.closest('tr').dataset.availableQty;
    recalculate();
    hide(input.closest('.product-picker'));
    pending.get(input)?.abort();
    clearTimeout(timers.get(input));
    timers.set(input, setTimeout(() => find(input), 250));
  });
  lines.addEventListener('click', event => {
    if (!event.target.classList.contains('remove-line')) return;
    const row = event.target.closest('tr');
    const input = row.querySelector('.product-search');
    pending.get(input)?.abort(); clearTimeout(timers.get(input));
    if (lines.children.length > 1) row.remove();
    else row.replaceWith(document.querySelector('#invoice-line-template').content.cloneNode(true));
    recalculate();
  });
  warehouse.addEventListener('change', () => {
    lines.querySelectorAll('.product-picker').forEach(picker => {
    const input = picker.querySelector('.product-search');
    pending.get(input)?.abort(); clearTimeout(timers.get(input));
    input.value = ''; picker.querySelector('.product-id').value = ''; hide(picker);
    delete picker.closest('tr').dataset.unitPrice;
    delete picker.closest('tr').dataset.availableQty;
    });
    recalculate();
  });
  document.querySelector('#add-line').addEventListener('click', () => {
    lines.append(document.querySelector('#invoice-line-template').content.cloneNode(true));
    recalculate();
    lines.lastElementChild.querySelector('.product-search').focus();
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (submit.disabled) return;
    if (!document.querySelector('#customer_id').value) {
      showError(labels.customer);
      document.querySelector('#customer_id-input')?.focus();
      return;
    }
    const invalidDiscount = [...lines.querySelectorAll('tr')].find(row => !validDiscount(row.querySelector('.discount').value));
    if (invalidDiscount) {
      showError(labels.discountRange);
      invalidDiscount.querySelector('.discount').focus();
      return;
    }
    const items = [...lines.querySelectorAll('tr')].map(row => ({
      product_id: row.querySelector('.product-id').value,
      quantity: row.querySelector('.quantity').value,
      discount_percent: row.querySelector('.discount').value
    }));
    if (!items.length || items.some(item => !item.product_id)) { showError(labels.product); return; }
    const invalidStock = [...lines.querySelectorAll('tr')].find(row => !updateStock(row));
    if (invalidStock) {
      const quantity = invalidStock.querySelector('.quantity');
      showError(quantity.validationMessage);
      quantity.focus();
      return;
    }
    const originalLabel = submit.textContent;
    submit.disabled = true; submit.textContent = labels.saving;
    notice.textContent = ''; notice.className = 'mt-3';
    try {
      const body = new FormData();
      body.append('payload', JSON.stringify({ customer_id: document.querySelector('#customer_id').value, warehouse_id: warehouse.value, discount_percent: 0, items }));
      const response = await window.erpFetch(form.action, { method: 'POST', body });
      if (!response.success || !response.data?.id) throw new Error(response.error?.message || labels.failed);
      window.location.assign(form.action.replace(/\/store$/, '/show/') + response.data.id);
    } catch (error) {
      showError(error.message || labels.failed);
    } finally {
      submit.disabled = false; submit.textContent = originalLabel;
    }
  });
  recalculate();
})();
