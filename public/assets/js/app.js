(() => {
  'use strict';

  const name = document.querySelector('meta[name="csrf-name"]')?.content;
  let hash = document.querySelector('meta[name="csrf-hash"]')?.content;
  const sidebar = document.querySelector('#app-sidebar');
  const toggle = document.querySelector('[data-sidebar-toggle]');
  const backdrop = document.querySelector('[data-sidebar-backdrop]');

  window.erpFetch = async (url, options = {}) => {
    const body = options.body instanceof FormData ? options.body : new FormData();
    if (name) body.set(name, hash);
    const response = await fetch(url, {...options, method: options.method || 'POST', body});
    const json = await response.json();
    if (json.csrf_hash) {
      hash = json.csrf_hash;
      document.querySelector('meta[name="csrf-hash"]')?.setAttribute('content', hash);
    }
    return json;
  };

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
  backdrop?.addEventListener('click', closeSidebar);
  sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeSidebar));
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeSidebar(); });
  window.addEventListener('resize', () => { if (window.innerWidth >= 992) closeSidebar(); });
})();
