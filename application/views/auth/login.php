<div class="card"><div class="card-body">
  <h2><?= lang('login') ?></h2>
  <?php if (!empty($error)): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
  <?= validation_errors('<div class="alert alert-danger" role="alert">', '</div>') ?>
  <?= form_open('login') ?>
    <div class="mb-3">
      <label class="form-label" for="login-email"><?= lang('email') ?></label>
      <input class="form-control" id="login-email" type="email" name="email" value="<?= e(set_value('email')) ?>" autocomplete="username" required>
    </div>
    <div class="mb-3">
      <label class="form-label" for="login-password"><?= lang('password') ?></label>
      <input class="form-control" id="login-password" type="password" name="password" autocomplete="current-password" required>
    </div>
    <div class="mb-3">
      <label class="form-check"><input class="form-check-input" type="checkbox" name="remember" value="1" <?= set_checkbox('remember', '1') ?> aria-describedby="remember-help"><span class="form-check-label"><?= e(lang('remember_me')) ?></span></label>
      <div class="text-muted small mt-1" id="remember-help"><?= e(lang('remember_help')) ?></div>
    </div>
    <button class="btn btn-primary w-100" type="submit"><?= lang('login') ?></button>
  <?= form_close() ?>
</div></div>
