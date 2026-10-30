const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

function setup(initial) {
  let change;
  const mode = { value: initial, addEventListener: (event, listener) => { change = listener; } };
  const reason = { required: false, setCustomValidity() {} };
  const quantity = { min: '0', setCustomValidity() {} };
  const marker = { hidden: true };
  const form = { elements: { mode, reason, quantity }, querySelector: () => marker };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../../public/assets/js/stock-adjustment.js'), 'utf8'), { document: { querySelector: () => form } });
  return { mode, reason, quantity, marker, change: value => { mode.value = value; change(); } };
}

test('adding and removing stock require a reason and a positive quantity', () => {
  const form = setup('set');
  for (const mode of ['add', 'remove']) {
    form.change(mode);
    assert.equal(form.reason.required, true);
    assert.equal(form.marker.hidden, false);
    assert.equal(form.quantity.min, '1');
  }
});

test('setting stock permits zero and does not require a reason', () => {
  const form = setup('add');
  assert.equal(form.reason.required, true);
  form.change('set');
  assert.equal(form.reason.required, false);
  assert.equal(form.marker.hidden, true);
  assert.equal(form.quantity.min, '0');
});

test('warehouse changes scope products and removal clears explicit catalogue inclusion', async () => {
  const listeners = new Map();
  const field = value => ({ value, addEventListener(event, callback) {
    const list = listeners.get(this) || []; list.push(callback); listeners.set(this, list);
  }, setCustomValidity() {} });
  const warehouse = field('');
  const mode = field('set');
  const include = field('1'); include.checked = false;
  const product = { options: [{}], replaceChildren(option) { this.options = [option]; }, add(option) { this.options.push(option); }, dispatchEvent() {} };
  const status = {}; const save = {}; const enrolment = {};
  const controls = { '[data-reason-required]': {}, '[data-enrolment-option]': enrolment, '[data-stock-product-status]': status, '[type="submit"]': save };
  const form = { elements: { warehouse_id: warehouse, product_id: product, include_unstocked: include, mode, reason: field(''), quantity: field('') },
    dataset: { productsUrl: '/inventory/adjustment_products', productPlaceholder: 'Select', emptyProducts: 'Empty' }, querySelector: key => controls[key] };
  const requests = [];
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../../public/assets/js/stock-adjustment.js'), 'utf8'), {
    document: { querySelector: () => form, documentElement: { lang: 'en' } }, window: { location: { origin: 'http://localhost' } }, URL, AbortController,
    Event: class {}, Option: class { constructor(text, value) { this.text = text; this.value = value; } },
    fetch: async url => { requests.push(url); return { ok: true, json: async () => ({ success: true, data: { products: [{ id: 9, code: 'TEST', name: 'Scoped' }] } }) }; }
  });
  const change = async field => { await Promise.all(listeners.get(field).map(callback => callback())); await new Promise(resolve => setImmediate(resolve)); };
  assert.equal(product.disabled, true);
  assert.equal(include.disabled, true);
  assert.equal(requests.length, 0);
  warehouse.value = '2'; await change(warehouse);
  assert.equal(include.disabled, false);
  assert.equal(requests[0].searchParams.get('warehouse_id'), '2');
  assert.equal(requests[0].searchParams.get('include_unstocked'), '0');
  assert.equal(product.options.length, 2);
  include.checked = true; await change(include);
  assert.equal(requests[1].searchParams.get('include_unstocked'), '1');
  mode.value = 'remove'; await change(mode);
  assert.equal(include.checked, false);
  assert.equal(enrolment.hidden, true);
  assert.equal(requests[2].searchParams.get('include_unstocked'), '0');
  warehouse.value = ''; await change(warehouse);
  assert.equal(product.disabled, true);
  assert.equal(product.options.length, 1);
  assert.equal(requests.length, 3);
});
