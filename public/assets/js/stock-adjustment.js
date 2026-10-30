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
})();
