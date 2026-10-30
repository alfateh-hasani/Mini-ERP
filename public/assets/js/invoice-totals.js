/* Integer halalas and positive half-up rounding, matching InvoiceService. */
((root, factory) => {
  const calculator = factory();
  if (typeof module === 'object' && module.exports) module.exports = calculator;
  else root.InvoiceTotals = calculator;
})(typeof window === 'object' ? window : globalThis, () => {
  const fixed = value => {
    const match = /^(\d+)(?:\.(\d+))?$/.exec(String(value).trim());
    if (!match) return null;
    const fraction = (match[2] || '').padEnd(3, '0');
    return BigInt(match[1]) * 100n + BigInt(fraction.slice(0, 2)) + (Number(fraction[2]) >= 5 ? 1n : 0n);
  };
  const line = (price, quantity, percentage) => {
    const unit = fixed(price), rate = fixed(percentage);
    if (unit === null || rate === null || rate > 10000n || !/^\d+$/.test(String(quantity)) || BigInt(quantity) < 1n || BigInt(quantity) > 2147483647n) return null;
    const subtotal = unit * BigInt(quantity);
    const discount = (subtotal * rate + 5000n) / 10000n;
    return { unit, subtotal, discount, total: subtotal - discount };
  };
  const format = halalas => {
    const integer = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(halalas / 100n);
    const fraction = (halalas % 100n).toString().padStart(2, '0');
    return `SAR ${integer}.${fraction}`;
  };
  return { line, format };
});
