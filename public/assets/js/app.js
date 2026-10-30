(() => {
  'use strict';

  const name = document.querySelector('meta[name="csrf-name"]')?.content;
  let hash = document.querySelector('meta[name="csrf-hash"]')?.content;
  const sidebar = document.querySelector('#app-sidebar');
  const toggle = document.querySelector('[data-sidebar-toggle]');
  const close = document.querySelector('[data-sidebar-close]');
  const backdrop = document.querySelector('[data-sidebar-backdrop]');
  const profileMenu = document.querySelector('.profile-menu');

  let requestQueue = Promise.resolve();
  const send = async (url, options = {}, retryCsrf = true) => {
    const { csrfRefreshUrl, ...fetchOptions } = options;
    const body = options.body instanceof FormData ? options.body : new FormData();
    const refreshUrl = csrfRefreshUrl || document.querySelector('meta[name="csrf-refresh"]')?.content;
    // HttpOnly cookies cannot be read by JavaScript; refresh their matching token.
    if (refreshUrl) {
      const fresh = await fetch(refreshUrl, { credentials: 'same-origin', cache: 'no-store' });
      const token = await fresh.json().catch(() => null);
      if (!fresh.ok || !token?.success || !token.csrf_hash) throw new Error(document.documentElement.lang === 'ar' ? 'انتهت الجلسة. سجّل الدخول مرة أخرى.' : 'Your session has expired. Please sign in again.');
      hash = token.csrf_hash;
    }
    // Other tabs may rotate CI3's double-submit cookie after this page loads.
    const cookieName = document.querySelector('meta[name="csrf-cookie"]')?.content;
    const cookie = document.cookie.split('; ').find(value => value.startsWith(`${cookieName}=`));
    if (cookie) hash = decodeURIComponent(cookie.slice(cookieName.length + 1));
    if (name) body.set(name, hash);
    const response = await fetch(url, { credentials: 'same-origin', ...fetchOptions, method: options.method || 'POST', body});
    // Retry only a confirmed CSRF rejection, which occurs before the controller runs.
    if (retryCsrf && refreshUrl && response.status === 403) {
      const rejected = await response.clone().text();
      if (rejected.includes('The action you have requested is not allowed.')) return send(url, options, false);
    }
    const json = await response.json().catch(() => {
      throw new Error(document.documentElement.lang === 'ar'
        ? 'تعذر إكمال الطلب. حدّث الصفحة ثم حاول مرة أخرى.'
        : 'Unable to complete the request. Refresh the page and try again.');
    });
    if (json.csrf_hash) {
      hash = json.csrf_hash;
      document.querySelector('meta[name="csrf-hash"]')?.setAttribute('content', hash);
    }
    return json;
  };
  window.erpFetch = (url, options = {}) => {
    const request = requestQueue.then(() => send(url, options));
    const localized = request.catch(error => {
      if (error.name === 'TypeError') throw new Error(document.documentElement.lang === 'ar' ? 'تعذر الاتصال بالخادم. تحقق من الاتصال وحاول مرة أخرى.' : 'Unable to reach the server. Check your connection and try again.');
      throw error;
    });
    requestQueue = localized.catch(() => {});
    return localized;
  };

  document.querySelectorAll('[data-logout-form]').forEach(form => {
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const button = form.querySelector('button[type="submit"]');
      const error = form.querySelector('[data-logout-error]');
      if (button.disabled) return;
      button.disabled = true;
      if (error) error.hidden = true;
      try {
        const response = await window.erpFetch(form.action, {
          body: new FormData(form), headers: { Accept: 'application/json' },
          csrfRefreshUrl: form.dataset.csrfRefresh
        });
        if (!response.success || !response.data?.redirect) throw new Error(response.error?.message || form.dataset.error);
        window.location.assign(response.data.redirect);
      } catch (failure) {
        button.disabled = false;
        if (error) {
          error.textContent = failure.message || (document.documentElement.lang === 'ar' ? 'تعذر تسجيل الخروج. يرجى المحاولة مرة أخرى.' : 'Could not sign out. Please try again.');
          error.hidden = false;
        }
      }
    });
  });

  const closeSidebar = () => {
    if (!sidebar) return;
    sidebar.classList.remove('is-open');
    backdrop.hidden = true;
    toggle?.setAttribute('aria-expanded', 'false');
  };
  const openSidebar = () => {
    if (!sidebar) return;
    sidebar.classList.add('is-open');
    backdrop.hidden = false;
    toggle?.setAttribute('aria-expanded', 'true');
  };

  toggle?.addEventListener('click', () => sidebar?.classList.contains('is-open') ? closeSidebar() : openSidebar());
  close?.addEventListener('click', closeSidebar);
  backdrop?.addEventListener('click', closeSidebar);
  sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeSidebar));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') { closeSidebar(); profileMenu?.removeAttribute('open'); }
  });
  document.addEventListener('click', (event) => { if (profileMenu && !profileMenu.contains(event.target)) profileMenu.removeAttribute('open'); });
  window.addEventListener('resize', () => { if (window.innerWidth >= 992) closeSidebar(); });
})();
