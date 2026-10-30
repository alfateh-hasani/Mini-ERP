const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

function setup(request, customer = '1', quantityValue = '1', availableQty = '37') {
  const handlers = {};
  const submit = { disabled: false, textContent: 'Save' };
  const notice = { focus() {}, textContent: '' };
  const quantity = { value: quantityValue, classList: { toggle() {} }, setAttribute() {}, focus() {}, setCustomValidity(message) { this.validationMessage = message; } };
  const fields = { '.quantity': quantity, '.product-id': { value: '5' }, '.discount': { value: '1' } };
  ['.quantity-stock', '.stock-available', '.stock-feedback', '.unit-price', '.line-subtotal', '.line-total', '.discount-amount'].forEach(selector => { fields[selector] = {}; });
  const row = { dataset: { unitPrice: '10.00', availableQty }, querySelector: selector => fields[selector] };
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
  return { handlers, notice, submit, context, quantity, fields };
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

test('available stock is visible and exceeding it prevents submission', async () => {
  let called = false;
  const state = setup(async () => { called = true; }, '1', '38', '37');
  assert.equal(state.quantity.max, '37');
  assert.equal(state.fields['.stock-available'].textContent, 'Available: 37');
  assert.equal(state.fields['.stock-feedback'].textContent, 'Over stock by: 1');
  await state.handlers.submit({ preventDefault() {} });
  assert.equal(called, false);
  assert.equal(state.notice.textContent, 'Quantity exceeds available stock. Maximum: 37.');
});

test('the exact available quantity can be submitted with zero remaining', async () => {
  const state = setup(async () => ({ success: true, data: { id: 42 } }), '1', '37', '37');
  assert.equal(state.fields['.stock-feedback'].textContent, 'Remaining: 0');
  await state.handlers.submit({ preventDefault() {} });
  assert.equal(state.context.redirect, 'http://localhost/invoices/show/42');
});

test('zero stock blocks submission with an explicit message', async () => {
  let called = false;
  const state = setup(async () => { called = true; }, '1', '1', '0');
  await state.handlers.submit({ preventDefault() {} });
  assert.equal(called, false);
  assert.equal(state.notice.textContent, 'Out of stock.');
});
