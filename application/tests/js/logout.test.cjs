const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

function setup(fetch) {
  let submit;
  const button = { disabled: false };
  const error = { hidden: true, textContent: '' };
  const destinations = [];
  const form = {
    action: '/logout', dataset: { csrfRefresh: '/auth/logout_csrf' },
    querySelector: selector => selector.startsWith('button') ? button : error,
    addEventListener(event, handler) { if (event === 'submit') submit = handler; }
  };
  class BrowserFormData extends FormData {
    constructor() { super(); this.set('csrf_field', 'stale-form-token'); }
  }
  const context = {
    FormData: BrowserFormData, fetch,
    document: {
      cookie: '', documentElement: { lang: 'en' },
      querySelector(selector) {
        const values = { 'meta[name="csrf-name"]': 'csrf_field', 'meta[name="csrf-refresh"]': '/auth/csrf', 'meta[name="csrf-hash"]': 'stale-page-token' };
        return values[selector] ? { content: values[selector], setAttribute() {} } : null;
      },
      querySelectorAll: () => [form], addEventListener() {}
    },
    window: { addEventListener() {}, location: { assign: destination => destinations.push(destination) } }
  };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../../public/assets/js/app.js'), 'utf8'), context);
  return { submit: () => submit({ preventDefault() {} }), button, error, destinations };
}

test('logout replaces a stale form token using the endpoint available after session expiry', async () => {
  const calls = [];
  const ui = setup(async (url, options) => {
    calls.push(url);
    if (url === '/auth/logout_csrf') return { ok: true, json: async () => ({ success: true, csrf_hash: 'fresh-token' }) };
    assert.equal(url, '/logout');
    assert.equal(options.body.get('csrf_field'), 'fresh-token');
    assert.equal(options.headers.Accept, 'application/json');
    assert.equal(options.csrfRefreshUrl, undefined);
    return { json: async () => ({ success: true, data: { redirect: '/login' } }) };
  });
  await ui.submit();
  assert.deepEqual(calls, ['/auth/logout_csrf', '/logout']);
  assert.deepEqual(ui.destinations, ['/login']);
});

test('logout retries once when another tab rotates CSRF immediately before submission', async () => {
  let attempts = 0;
  let refreshes = 0;
  const ui = setup(async (url, options) => {
    if (url === '/auth/logout_csrf') return { ok: true, json: async () => ({ success: true, csrf_hash: `token-${++refreshes}` }) };
    assert.equal(options.body.get('csrf_field'), `token-${refreshes}`);
    if (++attempts === 1) return { status: 403, clone: () => ({ text: async () => 'The action you have requested is not allowed.' }) };
    return { json: async () => ({ success: true, data: { redirect: '/login' } }) };
  });
  await ui.submit();
  assert.equal(attempts, 2);
  assert.equal(refreshes, 2);
  assert.deepEqual(ui.destinations, ['/login']);
});

test('failed logout shows an inline error and permits retry', async () => {
  const ui = setup(async () => { throw new TypeError('network failure'); });
  await ui.submit();
  assert.equal(ui.button.disabled, false);
  assert.equal(ui.error.hidden, false);
  assert.match(ui.error.textContent, /Unable to reach the server/);
  assert.deepEqual(ui.destinations, []);
});
