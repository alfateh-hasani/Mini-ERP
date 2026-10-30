(() => {
  'use strict';
  const form = document.querySelector('[data-stock-adjustment]');
  if (!form) return;
  const mode = form.elements.mode;
  const reason = form.elements.reason;
  const quantity = form.elements.quantity;
  const marker = form.querySelector('[data-reason-required]');
  const update = () => {
    const required = mode.value === 'add' || mode.value === 'remove';
    reason.required = required;
    marker.hidden = !required;
    quantity.min = required ? '1' : '0';
    reason.setCustomValidity('');
    quantity.setCustomValidity('');
  };
  mode.addEventListener('change', update);
  update();

  const warehouse = form.elements.warehouse_id;
  const product = form.elements.product_id;
  const include = form.elements.include_unstocked;
  if (!warehouse || !product || !include) return;
  const enrolment = form.querySelector('[data-enrolment-option]');
  const status = form.querySelector('[data-stock-product-status]');
  const save = form.querySelector('[type="submit"]');
  let request;
  const refresh = () => product.dispatchEvent(new Event('erp:options-updated'));
  const loadProducts = async () => {
    request?.abort();
    const current = new AbortController();
    request = current;
    product.replaceChildren(new Option(form.dataset.productPlaceholder, ''));
    product.disabled = true;
    status.textContent = '';
    save.disabled = true;
    refresh();
    if (!warehouse.value) { save.disabled = false; return; }
    try {
      const url = new URL(form.dataset.productsUrl, window.location.origin);
      url.searchParams.set('warehouse_id', warehouse.value);
      url.searchParams.set('include_unstocked', include.checked && mode.value !== 'remove' ? '1' : '0');
      const response = await fetch(url, { credentials: 'same-origin', signal: current.signal });
      if (!response.ok) throw new Error('Product lookup failed');
      const json = await response.json();
      if (!json.success || !Array.isArray(json.data?.products)) throw new Error('Invalid product response');
      if (request !== current) return;
      const arabic = document.documentElement.lang === 'ar';
      json.data.products.forEach(item => product.add(new Option(`${item.code} — ${arabic ? item.name_ar : item.name}`, item.id)));
      product.disabled = json.data.products.length === 0;
      status.textContent = product.disabled ? form.dataset.emptyProducts : '';
      save.disabled = product.disabled;
    } catch (exception) {
      if (request !== current || exception.name === 'AbortError') return;
      status.textContent = form.dataset.loadError;
    } finally {
      if (request === current) refresh();
    }
  };
  warehouse.addEventListener('change', loadProducts);
  include.addEventListener('change', loadProducts);
  mode.addEventListener('change', () => {
    enrolment.hidden = mode.value === 'remove';
    if (mode.value === 'remove' && include.checked) {
      include.checked = false;
      loadProducts();
    }
  });
  // Server-rendered options preserve submitted selections after validation errors.
  if (warehouse.value && product.options.length === 1) {
    status.textContent = form.dataset.emptyProducts;
    save.disabled = true;
  }
})();
