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
