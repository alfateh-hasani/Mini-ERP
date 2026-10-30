<?php
$section = (string) $this->uri->segment(1);
if ($section === 'invoices' && in_array($this->uri->segment(2), ['create', 'store'], true)) { $section = 'invoices/create'; }
$isAdmin = $current_user && $current_user['role'] === ROLE_ADMIN;
$navIcon = static function (string $icon): string {
    $paths = [
        'dashboard' => '<path d="M3 12l9-9 9 9v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 22v-6h6v6"/>',
        'inventory' => '<path d="M21 16V8a2 2 0 0 0-1-1l-7-4-7 4a2 2 0 0 0-1 1v8a2 2 0 0 0 1 1l7 4 7-4a2 2 0 0 0 1-1z"/><path d="M3.3 7l9.7 5 9.7-5M13 22V12"/>',
        'customers' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'invoices' => '<path d="M4 3h16v18H4z"/><path d="M8 7h8M8 11h8M8 15h5"/>',
        'new_invoice' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'reports' => '<path d="M4 19V5M4 19h16"/><path d="M8 16v-5M12 16V7M16 16v-8"/>',
        'products' => '<path d="M21 16V8a2 2 0 0 0-1-1l-7-4-7 4a2 2 0 0 0-1 1v8a2 2 0 0 0 1 1l7 4 7-4a2 2 0 0 0 1-1z"/><path d="M3.3 7l9.7 5 9.7-5M13 22V12"/>',
        'categories' => '<path d="M20.6 13.4L12 22l-9-9V4h9z"/><circle cx="7.5" cy="8.5" r="1"/>',
        'warehouses' => '<path d="M3 21V7l9-4 9 4v14"/><path d="M9 21v-6h6v6M7 10h.01M12 10h.01M17 10h.01"/>',
        'users' => '<circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.3 2.3-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.04 1.56v.1h-3.25v-.1a1.7 1.7 0 0 0-1.04-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06-2.3-2.3.06-.06A1.7 1.7 0 0 0 6.43 15a1.7 1.7 0 0 0-1.56-1.04h-.1v-3.25h.1A1.7 1.7 0 0 0 6.43 9.7a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.3-2.3.06.06a1.7 1.7 0 0 0 1.88.34 1.7 1.7 0 0 0 1.04-1.56v-.1h3.25v.1a1.7 1.7 0 0 0 1.04 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.3 2.3-.06.06a1.7 1.7 0 0 0-.34 1.88 1.7 1.7 0 0 0 1.56 1.04h.1v3.25h-.1A1.7 1.7 0 0 0 19.4 15z"/>',
    ];
    return '<svg class="sidebar-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$icon] ?? '') . '</svg>';
};
$mainLinks = [
    ['dashboard', '', lang('dashboard'), 'dashboard'],
    ['inventory', 'inventory', lang('inventory'), 'inventory'],
    ['invoices', 'invoices', lang('invoices'), 'invoices'],
    ['invoices/create', 'invoices/create', lang('new_invoice'), 'new_invoice'],
    ['reports/low_stock', 'reports', lang('reports'), 'reports'],
];
if ($isAdmin) { array_splice($mainLinks, 2, 0, [['customers', 'customers', lang('customers'), 'customers']]); }
$adminLinks = [
    ['products', 'products', lang('products'), 'products'],
    ['categories', 'categories', lang('categories'), 'categories'],
    ['warehouses', 'warehouses', lang('warehouses'), 'warehouses'],
    ['users', 'users', lang('users'), 'users'],
    ['settings', 'settings', lang('settings'), 'settings'],
];
?>
<!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-name" content="<?= e($this->security->get_csrf_token_name()) ?>">
  <meta name="csrf-cookie" content="<?= e($this->config->item('cookie_prefix') . $this->config->item('csrf_cookie_name')) ?>">
  <meta name="csrf-refresh" content="<?= e(site_url('auth/csrf')) ?>">
  <meta name="csrf-hash" content="<?= e($this->security->get_csrf_hash()) ?>">
  <script src="<?= base_url('public/assets/js/validation.js') ?>?v=1" defer></script>
  <title>SmartLife ERP</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/css/tabler<?= $dir === 'rtl' ? '.rtl' : '' ?>.min.css">
  <link rel="stylesheet" href="<?= base_url('public/assets/css/tables.css') ?>?v=12">
</head>
<body data-select-search-placeholder="<?= e(lang('search')) ?>" data-select-no-results="<?= e(lang('no_results')) ?>">
<div class="page">
  <div class="mobile-sidebar-backdrop" data-sidebar-backdrop hidden></div>
  <aside id="app-sidebar" class="navbar navbar-vertical navbar-expand-lg" aria-label="<?= e(lang('dashboard')) ?>">
    <div class="container-fluid">
      <div class="sidebar-brand-row"><h1 class="navbar-brand navbar-brand-autodark"><a href="<?= site_url() ?>">SmartLife ERP</a></h1><button class="btn btn-icon btn-ghost-secondary d-lg-none" type="button" data-sidebar-close aria-label="<?= e(lang('close')) ?>"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"></path></svg></button></div>
      <div class="navbar-collapse" id="sidebar-menu">
        <ul class="navbar-nav pt-lg-3">
          <?php foreach ($mainLinks as [$url, $match, $label, $icon]): ?>
            <li class="nav-item<?= $section === $match ? ' active' : '' ?>"><a class="nav-link" href="<?= site_url($url) ?>"><?= $navIcon($icon) ?><span class="nav-link-title"><?= e($label) ?></span></a></li>
          <?php endforeach; ?>
          <?php if ($isAdmin): ?><li class="nav-item mt-3"><span class="nav-link disabled"><span class="nav-link-title text-uppercase text-muted small"><?= e(lang('settings')) ?></span></span></li><?php endif; ?>
          <?php if ($isAdmin): foreach ($adminLinks as [$url, $match, $label, $icon]): ?>
            <li class="nav-item<?= $section === $match ? ' active' : '' ?>"><a class="nav-link" href="<?= site_url($url) ?>"><?= $navIcon($icon) ?><span class="nav-link-title"><?= e($label) ?></span></a></li>
          <?php endforeach; endif; ?>
        </ul>
      </div>
      <?php if ($current_user): ?><div class="sidebar-user"><span class="avatar avatar-sm bg-primary-lt"><?= e(mb_strtoupper(mb_substr($current_user['name'], 0, 1))) ?></span><span class="sidebar-user-copy"><strong><?= e($current_user['name']) ?></strong><small><?= e($current_user['role'] === ROLE_ADMIN ? lang('admin') : lang('warehouse_user')) ?></small></span></div><?php endif; ?>
    </div>
  </aside>
  <div class="page-wrapper">
    <header class="navbar navbar-expand-md d-print-none app-header">
      <div class="container-xl">
        <div class="app-header-brand d-lg-none">
          <button class="btn btn-icon btn-ghost-secondary" type="button" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false" aria-label="<?= e(lang('menu')) ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h16"></path></svg>
          </button>
          <a class="navbar-brand" href="<?= site_url() ?>">SmartLife ERP</a>
        </div>
        <div class="navbar-nav ms-auto app-header-actions">
          <a class="btn btn-icon btn-ghost-secondary" href="<?= site_url('language/' . ($lang === 'ar' ? 'en' : 'ar')) ?>" aria-label="<?= e(lang('language')) ?>" title="<?= e(lang('language')) ?>"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"></path></svg><span class="visually-hidden"><?= $lang === 'ar' ? 'English' : 'العربية' ?></span></a>
          <details class="profile-menu"><summary class="btn btn-icon btn-ghost-secondary" aria-label="<?= e(lang('profile')) ?>" title="<?= e(lang('profile')) ?>"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg><span class="visually-hidden"><?= e(lang('profile')) ?></span></summary><div class="profile-menu-panel"><a class="profile-menu-link" href="<?= site_url('profile') ?>"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg><?= e(lang('profile')) ?></a><?= form_open('logout', ['class' => 'profile-menu-logout']) ?><button class="profile-menu-link" type="submit"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M21 19V5a2 2 0 0 0-2-2h-5"></path></svg><?= e(lang('logout')) ?></button><?= form_close() ?></div></details>
        </div>
      </div>
    </header>
    <div class="page-body"><div class="container-xl">
      <nav aria-label="breadcrumb" class="pt-3"><ol class="breadcrumb"><?php foreach ($breadcrumbs as $breadcrumb): ?><li class="breadcrumb-item"><a href="<?= e($breadcrumb['url']) ?>"><?= e($breadcrumb['label']) ?></a></li><?php endforeach; ?></ol></nav>
      <?php if ($flash): ?><div class="alert alert-<?= e($flash['type'] ?? 'info') ?>"><?= e($flash['message'] ?? '') ?></div><?php endif; ?>
      <?= $content ?>
    </div></div>
  </div>
</div>
<script src="<?= base_url('public/assets/js/app.js') ?>?v=6"></script>
<script src="<?= base_url('public/assets/js/selects.js') ?>?v=7"></script>
<script src="<?= base_url('public/assets/js/tables.js') ?>?v=9"></script>
</body>
</html>
