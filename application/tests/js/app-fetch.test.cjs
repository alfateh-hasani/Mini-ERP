const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

function setup(fetch, refresh = false) {
  const context = {
    FormData, fetch,
    document: {
      cookie: refresh ? '' : 'csrf_cookie=rotated-token',
      documentElement: { lang: 'en' },
      querySelector(selector) {
        const metadata = {
          'meta[name="csrf-refresh"]': refresh ? '/auth/csrf' : '',
          'meta[name="csrf-name"]': 'csrf_field',
          'meta[name="csrf-hash"]': 'old-token',
          'meta[name="csrf-cookie"]': 'csrf_cookie'
        };
        return metadata[selector] ? { content: metadata[selector], setAttribute() {} } : null;
      },
      addEventListener() {}
    },
    window: { addEventListener() {} }
  };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../../public/assets/js/app.js'), 'utf8'), context);
  return context.window.erpFetch;
}

test('submissions use the current cookie after another tab rotates the token', async () => {
  const request = setup(async (url, options) => {
    assert.equal(options.body.get('csrf_field'), 'rotated-token');
    return { json: async () => ({ success: true, csrf_hash: 'new-token' }) };
  });
  assert.equal((await request('/invoices/store')).success, true);
});

test('HttpOnly cookies use a fresh server token for each queued write', async () => {
  let sequence = 0;
  const calls = [];
  const request = setup(async (url, options) => {
    calls.push(url);
    if (url === '/auth/csrf') return { ok: true, json: async () => ({ success: true, csrf_hash: `token-${++sequence}` }) };
    assert.equal(options.body.get('csrf_field'), `token-${sequence}`);
    return { json: async () => ({ success: true }) };
  }, true);
  await Promise.all([request('/toggle/1'), request('/toggle/2')]);
  assert.deepEqual(calls, ['/auth/csrf', '/toggle/1', '/auth/csrf', '/toggle/2']);
});

test('HTML errors produce a readable exception for the invoice error handler', async () => {
  const request = setup(async () => ({ json: async () => { throw new SyntaxError('HTML response'); } }));
  await assert.rejects(request('/invoices/store'), /Refresh the page/);
});
