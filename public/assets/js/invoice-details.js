(() => {
  'use strict';
  document.querySelector('[data-invoice-print]')?.addEventListener('click', () => window.print());
  const cancellation = document.querySelector('[data-invoice-cancel]');
  cancellation?.addEventListener('submit', event => {
    if (!window.confirm(cancellation.dataset.confirm)) event.preventDefault();
  });
})();
