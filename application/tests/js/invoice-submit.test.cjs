const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

function setup(request, customer = '1', quantityValue = '1', availableQty = '37', discount = '1') {
  const handlers = {};
  const submit = { disabled: false, textContent: 'Save' };
  const notice = { focus() {}, textContent: '' };
  const quantity = { value: quantityValue, classList: { toggle() {} }, setAttribute() {}, focus() {}, setCustomValidity(message) { this.validationMessage = message; } };
  const fields = { '.quantity': quantity, '.product-id': { value: '5' }, '.discount': { value: discount, classList: { toggle() {} }, setAttribute() {}, setCustomValidity() {}, focus() {} } };
  ['.quantity-stock', '.stock-available', '.stock-feedback', '.unit-price', '.line-subtotal', '.line-total', '.discount-amount', '.unit-after-discount', '.unit-net-price', '.unit-discount-detail', '.unit-discount', '.line-discount-detail', '.discount-error', '.discount-breakdown'].forEach(selector => { fields[selector] = {}; });
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

test('unit savings are distinguished from the full line discount', () => {
  const state = setup(async () => {}, '1', '5', '37', '15');
  assert.equal(state.fields['.unit-discount'].textContent, 'SAR 1.50');
  assert.equal(state.fields['.unit-net-price'].textContent, 'SAR 8.50');
  assert.equal(state.fields['.discount-amount'].textContent, 'SAR 7.50');
  assert.equal(state.fields['.line-total'].textContent, 'SAR 42.50');
  assert.equal(state.fields['.unit-after-discount'].hidden, false);
});

test('zero discount hides redundant discount details', () => {
  const state = setup(async () => {}, '1', '1', '37', '0');
  assert.equal(state.fields['.unit-after-discount'].hidden, true);
  assert.equal(state.fields['.unit-discount-detail'].hidden, true);
  assert.equal(state.fields['.line-discount-detail'].hidden, true);
});

test('empty discount hides before and after amounts and prevents saving', async () => {
  let sent = false;
  const state = setup(async () => { sent = true; }, '1', '1', '37', '');
  assert.equal(state.fields['.line-subtotal'].hidden, true);
  assert.equal(state.fields['.line-total'].hidden, true);
  await state.handlers.submit({ preventDefault() {} });
  assert.equal(sent, false);
});

test('zero and 100 percent show amounts while out-of-range values block saving', async () => {
  for (const discount of ['0', '100']) {
    const state = setup(async () => {}, '1', '1', '37', discount);
    assert.equal(state.fields['.line-subtotal'].hidden, false);
    assert.equal(state.fields['.line-total'].hidden, false);
  }
  for (const discount of ['-1', '100.01']) {
    let sent = false;
    const state = setup(async () => { sent = true; }, '1', '1', '37', discount);
    assert.equal(state.fields['.discount-error'].hidden, false);
    await state.handlers.submit({ preventDefault() {} });
    assert.equal(sent, false);
  }
});
