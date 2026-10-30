(() => {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('#inventory-filters');
    if (!form) return;
    const table = document.querySelector('#inventory-table');
    const warehouse = form.elements.warehouse_id;
    const product = form.elements.product_id;
    const minimum = form.elements.quantity_min;
    const maximum = form.elements.quantity_max;
    const apply = form.querySelector('[type="submit"]');
    const error = form.querySelector('[data-filter-error]');
    let request;
    let failed = false;

    const updateReadOnly = () => {
      const inactive = warehouse.selectedOptions[0]?.dataset.inactive === 'true';
      const adjustment = document.querySelector('[data-inventory-adjustment]');
      const warning = form.querySelector('[data-inventory-read-only]');
      if (adjustment) adjustment.disabled = inactive;
      if (warning) warning.hidden = !inactive;
    };
    updateReadOnly();

    const showError = message => {
      error.textContent = message;
      error.hidden = !message;
    };
    const updateProduct = () => product.dispatchEvent(new Event('erp:options-updated'));
    const loadProducts = async () => {
      request?.abort();
      request = new AbortController();
      const current = request;
      failed = false;
      apply.disabled = true;
      product.disabled = true;
      product.replaceChildren(new Option(form.dataset.allProducts, ''));
      updateProduct();
      showError('');
      try {
        const url = new URL(form.dataset.productsUrl, window.location.origin);
        url.searchParams.set('warehouse_id', warehouse.value);
        const response = await fetch(url, { credentials: 'same-origin', signal: current.signal });
        if (!response.ok) throw new Error(form.dataset.loadError);
        const json = await response.json();
        if (!json.success) throw new Error(form.dataset.loadError);
        if (request !== current) return false;
        const arabic = document.documentElement.lang === 'ar';
        (json.data.products || []).forEach(item => product.add(new Option(`${item.code} — ${arabic ? item.name_ar : item.name}`, item.id)));
      } catch (exception) {
        if (exception.name === 'AbortError') return false;
        failed = true;
        showError(form.dataset.loadError);
      } finally {
        if (request === current) {
          apply.disabled = false;
          product.disabled = failed;
          updateProduct();
        }
      }
      return !failed;
    };
    const applyFilters = resetSearch => {
      const filters = {};
      new FormData(form).forEach((value, key) => { filters[key] = value; });
      table.dispatchEvent(new CustomEvent('table:filters', { detail: { filters, resetSearch } }));
    };
    warehouse.addEventListener('change', () => { updateReadOnly(); loadProducts(); });
    [minimum, maximum].forEach(input => input.addEventListener('input', () => {
      maximum.setCustomValidity('');
      showError('');
    }));
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (minimum.value !== '' && maximum.value !== '' && Number(minimum.value) > Number(maximum.value)) {
        maximum.setCustomValidity(form.dataset.invalidRange);
        showError(form.dataset.invalidRange);
        maximum.reportValidity();
        return;
      }
      if (failed && !(await loadProducts())) return;
      showError('');
      applyFilters(false);
    });
    form.addEventListener('reset', event => {
      event.preventDefault();
      if (!warehouse.disabled) warehouse.value = '';
      updateReadOnly();
      minimum.value = ''; maximum.value = '';
      maximum.setCustomValidity('');
      loadProducts().then(ok => { if (ok) applyFilters(true); });
    });
  });
})();
