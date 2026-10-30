const { test } = require('node:test');
const assert = require('node:assert/strict');
const totals = require('../../../public/assets/js/invoice-totals.js');

test('prices, quantities and discounts are calculated in integer halalas', () => {
  assert.deepEqual(totals.line('24.50', '3', '10'), { unit: 2450n, subtotal: 7350n, discount: 735n, total: 6615n });
});
test('discounts round positive half values up like the server', () => {
  assert.equal(totals.line('0.05', '1', '10').discount, 1n);
  assert.equal(totals.line('0.05', '2', '10').total, 9n);
  assert.equal(totals.line('19.99', '3', '12.5').total, 5247n);
});
test('zero prices and full discounts remain valid', () => {
  assert.equal(totals.line('0.00', '1', '0').total, 0n);
  assert.equal(totals.line('100', '2', '100').total, 0n);
});
test('invalid in-progress input produces no misleading totals', () => {
  for (const values of [['10', '', '0'], ['10', '0', '0'], ['10', '1.5', '0'], ['10', '1', '101'], ['10', '1', '-1'], ['', '1', '0']]) {
    assert.equal(totals.line(...values), null);
  }
});
test('formatting preserves halalas without floating point loss', () => {
  assert.equal(totals.format(6615n, 'en-US'), 'SAR 66.15');
  assert.equal(totals.format(1n, 'en-US'), 'SAR 0.01');
});
