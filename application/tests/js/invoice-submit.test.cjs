const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

function setup(request, customer = '1') {
  const handlers = {};
  const submit = { disabled: false, textContent: 'Save' };
  const notice = { focus() {}, textContent: '' };
  const row = { dataset: { unitPrice: '10.00' }, querySelector: selector => ({ value: selector === '.product-id' ? '5' : '1' }) };
  const lines = { addEventListener() {}, querySelectorAll: () => [row] };
  const form = {
    action: 'http://localhost/invoices/store', dataset: {},
    querySelector: () => submit,
    addEventListener: (name, handler) => { handlers[name] = handler; }
  };
  const nodes = {
    '#invoice-form': form, '#invoice-lines': lines, '#invoice-message': notice,
    '#warehouse_id': { value: '3', addEventListener() {} },
    '#customer_id': { value: customer, addEventListener() {} }, '#add-line': { addEventListener() {} }
  };
  ['#invoice-subtotal', '#invoice-discount', '#invoice-total'].forEach(id => { nodes[id] = {}; });
  const context = {
    FormData, WeakMap, setTimeout, clearTimeout,
    document: { documentElement: { lang: 'en' }, querySelector: selector => nodes[selector] },
    window: { InvoiceTotals: require('../../../public/assets/js/invoice-totals.js'), erpFetch: request, location: { assign: url => { context.redirect = url; } } }
  };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../../public/assets/js/invoice.js'), 'utf8'), context);
  return { handlers, notice, submit, context };
}

test('failed submission displays an error and restores the save button', async () => {
  const state = setup(async () => { throw new Error('Request failed'); });
  await state.handlers.submit({ preventDefault() {} });
  assert.equal(state.notice.textContent, 'Request failed');
  assert.equal(state.submit.disabled, false);
  assert.equal(state.submit.textContent, 'Save');
});

test('successful submission navigates to the saved invoice', async () => {
  const state = setup(async () => ({ success: true, data: { id: 42 } }));
  await state.handlers.submit({ preventDefault() {} });
  assert.equal(state.context.redirect, 'http://localhost/invoices/show/42');
});

test('business validation errors are visible', async () => {
  const state = setup(async () => ({ success: false, error: { message: 'Warehouse inactive' } }));
  await state.handlers.submit({ preventDefault() {} });
  assert.equal(state.notice.textContent, 'Warehouse inactive');
});

test('an invoice cannot be submitted without explicitly choosing a customer', async () => {
  let called = false;
  const state = setup(async () => { called = true; }, '');
  await state.handlers.submit({ preventDefault() {} });
  assert.equal(called, false);
  assert.equal(state.notice.textContent, 'Select a customer before saving the invoice.');
});
