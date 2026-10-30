(() => {
  'use strict';
  const warehouse = document.querySelector('[data-initial-warehouse]');
  const stock = document.querySelector('[data-initial-stock]');
  if (!warehouse || !stock) return;
  const quantity = stock.querySelector('[name="initial_quantity"]');
  const update = () => {
    const selected = warehouse.value !== '';
    stock.hidden = !selected;
    quantity.disabled = !selected;
    quantity.required = selected;
  };
  warehouse.addEventListener('change', update);
  update();
})();
