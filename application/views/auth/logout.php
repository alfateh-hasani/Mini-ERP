<div class="page-header"><h2><?= e(lang('logout')) ?></h2></div>
<div class="card"><div class="card-body">
  <p><?= e(lang('logout_confirmation')) ?></p>
  <?= form_open('logout', ['data-logout-form' => '', 'data-csrf-refresh' => site_url('auth/logout_csrf')]) ?>
    <button class="btn btn-primary" type="submit"><?= e(lang('logout')) ?></button>
    <a class="btn btn-outline-secondary" href="<?= site_url() ?>"><?= e(lang('cancel')) ?></a>
    <div class="text-danger small mt-2" data-logout-error role="alert" hidden></div>
  <?= form_close() ?>
</div></div>
