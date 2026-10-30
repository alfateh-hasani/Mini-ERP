const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

function setup(lang) {
  const handlers = {};
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../../public/assets/js/validation.js'), 'utf8'), {
    document: { documentElement: { lang }, addEventListener(type, handler) { handlers[type] = handler; } }
  });
  return handlers;
}

test('Arabic pages override English browser minimum validation and clear it after editing', () => {
  const handlers = setup('ar');
  const field = { min: '1', validity: { rangeUnderflow: true }, setCustomValidity(value) { this.message = value; } };
  handlers.invalid({ target: field });
  assert.equal(field.message, 'يجب أن تكون القيمة أكبر من أو تساوي 1.');
  handlers.input({ target: field });
  assert.equal(field.message, '');
});

test('English pages retain English minimum validation', () => {
  const field = { min: '1', validity: { rangeUnderflow: true }, setCustomValidity(value) { this.message = value; } };
  setup('en').invalid({ target: field });
  assert.equal(field.message, 'Value must be greater than or equal to 1.');
});
