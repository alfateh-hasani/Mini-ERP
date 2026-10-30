<div class="page-header"><h2><?= lang('profile') ?></h2></div>
<div class="card profile-card"><div class="card-body">
<?= validation_errors('<div class="alert alert-danger">', '</div>') ?>
<?= form_open('profile/update') ?>
<div class="mb-3"><label class="form-label"><?= lang('name') ?></label><input class="form-control" name="name" value="<?= e(set_value('name', $user['name'])) ?>" required></div>
<div class="mb-3"><label class="form-label"><?= lang('email') ?></label><input class="form-control" type="email" name="email" value="<?= e(set_value('email', $user['email'])) ?>" required></div>
<div class="mb-3"><label class="form-label"><?= lang('language') ?></label><select class="form-select" name="preferred_lang"><option value="ar" <?= ($user['preferred_lang'] ?? 'ar') === 'ar' ? 'selected' : '' ?>>العربية</option><option value="en" <?= ($user['preferred_lang'] ?? '') === 'en' ? 'selected' : '' ?>>English</option></select></div>
<hr><h3 class="card-title"><?= lang('change_password') ?></h3><p class="text-muted"><?= lang('password_change_help') ?></p>
<div class="mb-3"><label class="form-label"><?= lang('current_password') ?></label><input class="form-control" type="password" name="current_password" autocomplete="current-password"></div>
<div class="mb-3"><label class="form-label"><?= lang('new_password') ?></label><input class="form-control" type="password" name="new_password" autocomplete="new-password"></div>
<div class="mb-3"><label class="form-label"><?= lang('confirm_password') ?></label><input class="form-control" type="password" name="new_password_confirm" autocomplete="new-password"></div>
<button class="btn btn-primary" type="submit"><?= lang('save') ?></button>
<?= form_close() ?>
</div></div>
