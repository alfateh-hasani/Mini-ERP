<?php
$section = (string) $this->uri->segment(1);
$isAdmin = $current_user && $current_user['role'] === ROLE_ADMIN;
$mainLinks = [
    ['dashboard', '', lang('dashboard')],
    ['inventory', 'inventory', lang('inventory')],
    ['customers', 'customers', lang('customers')],
    ['invoices', 'invoices', lang('invoices')],
    ['invoices/create', 'invoices', lang('new_invoice')],
    ['reports/low_stock', 'reports', lang('reports')],
];
$adminLinks = [
    ['products', 'products', lang('products')],
    ['categories', 'categories', lang('categories')],
    ['warehouses', 'warehouses', lang('warehouses')],
    ['users', 'users', lang('users')],
    ['settings', 'settings', lang('settings')],
];
?>
<!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-name" content="<?= e($this->security->get_csrf_token_name()) ?>">
  <meta name="csrf-hash" content="<?= e($this->security->get_csrf_hash()) ?>">
  <title>SmartLife ERP</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/css/tabler<?= $dir === 'rtl' ? '.rtl' : '' ?>.min.css">
  <link rel="stylesheet" href="<?= base_url('public/assets/css/tables.css') ?>?v=3">
</head>
<body>
<div class="page">
  <aside class="navbar navbar-vertical navbar-expand-lg" aria-label="<?= e(lang('dashboard')) ?>">
    <div class="container-fluid">
      <h1 class="navbar-brand navbar-brand-autodark"><a href="<?= site_url() ?>">SmartLife ERP</a></h1>
      <div class="collapse navbar-collapse show" id="sidebar-menu">
        <ul class="navbar-nav pt-lg-3">
          <?php foreach ($mainLinks as [$url, $match, $label]): ?>
            <li class="nav-item<?= $section === $match ? ' active' : '' ?>"><a class="nav-link" href="<?= site_url($url) ?>"><span class="nav-link-title"><?= e($label) ?></span></a></li>
          <?php endforeach; ?>
          <?php if ($isAdmin): ?><li class="nav-item mt-3"><span class="nav-link disabled"><span class="nav-link-title text-uppercase text-muted small"><?= e(lang('settings')) ?></span></span></li><?php endif; ?>
          <?php if ($isAdmin): foreach ($adminLinks as [$url, $match, $label]): ?>
            <li class="nav-item<?= $section === $match ? ' active' : '' ?>"><a class="nav-link" href="<?= site_url($url) ?>"><span class="nav-link-title"><?= e($label) ?></span></a></li>
          <?php endforeach; endif; ?>
        </ul>
      </div>
    </div>
  </aside>
  <div class="page-wrapper">
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <div class="navbar-nav ms-auto">
          <a class="nav-link" href="<?= site_url('language/' . ($lang === 'ar' ? 'en' : 'ar')) ?>"><?= $lang === 'ar' ? 'English' : 'العربية' ?></a>
          <?= form_open('logout', ['class' => 'nav-item']) ?><button class="nav-link btn btn-link" type="submit"><?= lang('logout') ?></button><?= form_close() ?>
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
<script src="<?= base_url('public/assets/js/app.js') ?>"></script>
<script src="<?= base_url('public/assets/js/tables.js') ?>?v=3"></script>
</body>
</html>
