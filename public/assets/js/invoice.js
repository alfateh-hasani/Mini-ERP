(() => {
  'use strict';
  const form = document.querySelector('#invoice-form');
  if (!form) return;
  const lines = document.querySelector('#invoice-lines');
  const notice = document.querySelector('#invoice-message');
  const warehouse = document.querySelector('#warehouse_id');
  const submit = form.querySelector('[type="submit"]');
  const arabic = document.documentElement.lang === 'ar';
  const labels = arabic ? {
    failed: 'تعذر حفظ الفاتورة. حدّث الصفحة وحاول مرة أخرى.',
    product: 'اختر منتجاً من نتائج البحث لكل بند.',
    saving: 'جارٍ الحفظ…', search: 'تعذر البحث عن المنتجات. حاول مرة أخرى.'
  } : {
    failed: 'Unable to save the invoice. Refresh the page and try again.',
    product: 'Select a product from the search results for every line.',
    saving: 'Saving…', search: 'Unable to search products. Please try again.'
  };
  const showError = message => {
    notice.className = 'alert alert-danger mt-3';
    notice.textContent = message;
    notice.focus();
  };
  const hide = picker => picker.querySelector('.product-results').classList.add('d-none');
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
          input.value = button.textContent;
          picker.querySelector('.product-id').value = product.id;
          hide(picker);
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
    if (!input.classList.contains('product-search')) return;
    // Invalidate the selected ID immediately, before the debounce fires.
    input.closest('.product-picker').querySelector('.product-id').value = '';
    hide(input.closest('.product-picker'));
    pending.get(input)?.abort();
    clearTimeout(timers.get(input));
    timers.set(input, setTimeout(() => find(input), 250));
  });
  lines.addEventListener('click', event => {
    if (event.target.classList.contains('remove-line') && lines.children.length > 1) event.target.closest('tr').remove();
  });
  warehouse.addEventListener('change', () => lines.querySelectorAll('.product-picker').forEach(picker => {
    const input = picker.querySelector('.product-search');
    pending.get(input)?.abort(); clearTimeout(timers.get(input));
    input.value = ''; picker.querySelector('.product-id').value = ''; hide(picker);
  }));
  document.querySelector('#add-line').addEventListener('click', () => lines.append(document.querySelector('#invoice-line-template').content.cloneNode(true)));
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (submit.disabled) return;
    const items = [...lines.querySelectorAll('tr')].map(row => ({
      product_id: row.querySelector('.product-id').value,
      quantity: row.querySelector('.quantity').value,
      discount_percent: row.querySelector('.discount').value
    }));
    if (!items.length || items.some(item => !item.product_id)) { showError(labels.product); return; }
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
})();
