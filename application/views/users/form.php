<?php
$user = $user ?? null;
$errors = $errors ?? [];
$value = static function (string $field, string $default = '') use ($user): string {
    $input = set_value($field, $user[$field] ?? $default, false);
    return is_scalar($input) ? (string) $input : '';
};
$invalid = static function (string $field) use ($errors): string { return empty($errors[$field]) ? '' : ' is-invalid'; };
$aria = static function (string $field) use ($errors): string {
    return empty($errors[$field]) ? '' : 'aria-invalid="true" aria-describedby="user-' . $field . '-error"';
};
$feedback = static function (string $field) use ($errors): void {
    if (!empty($errors[$field])) { echo '<div class="invalid-feedback d-block" id="user-' . $field . '-error" role="alert">' . e($errors[$field]) . '</div>'; }
};
?>
<div class="page-header"><h2><?= $user ? lang('edit') : lang('add_user') ?></h2></div>
<div class="card"><div class="card-body">
  <?= form_open($user ? 'users/update/' . $user['id'] : 'users/store') ?>
    <div class="mb-3">
      <label class="form-label" for="user-name"><?= lang('name') ?></label>
      <input class="form-control<?= $invalid('name') ?>" id="user-name" name="name" value="<?= e($value('name')) ?>" maxlength="150" <?= $aria('name') ?> required>
      <?php $feedback('name'); ?>
    </div>
    <div class="mb-3">
      <label class="form-label" for="user-email"><?= lang('email') ?></label>
      <input class="form-control<?= $invalid('email') ?>" id="user-email" type="email" name="email" value="<?= e($value('email')) ?>" maxlength="150" <?= $aria('email') ?> required>
      <?php $feedback('email'); ?>
    </div>
    <div class="mb-3">
      <label class="form-label" for="user-password"><?= lang('password') ?></label>
      <input class="form-control<?= $invalid('password') ?>" id="user-password" type="password" name="password" autocomplete="new-password" <?= $aria('password') ?> <?= $user ? '' : 'required' ?>>
      <?php $feedback('password'); ?>
    </div>
    <div class="mb-3">
      <label class="form-label" for="user-role"><?= lang('role') ?></label>
      <select class="form-select<?= $invalid('role') ?>" id="user-role" name="role" <?= $aria('role') ?>>
        <option value="admin" <?= $value('role', 'admin') === 'admin' ? 'selected' : '' ?>><?= lang('admin') ?></option>
        <option value="user_warehouse" <?= $value('role') === 'user_warehouse' ? 'selected' : '' ?>><?= lang('warehouse_user') ?></option>
      </select>
      <?php $feedback('role'); ?>
    </div>
    <div class="mb-3">
      <label class="form-label" for="user-warehouse_id"><?= lang('warehouse') ?></label>
      <select class="form-select<?= $invalid('warehouse_id') ?>" id="user-warehouse_id" name="warehouse_id" <?= $aria('warehouse_id') ?>>
        <option value="" <?= $value('warehouse_id') === '' ? 'selected' : '' ?>><?= lang('no_warehouse') ?></option>
        <?php foreach ($warehouses as $warehouse): ?><option value="<?= (int) $warehouse['id'] ?>" <?= $value('warehouse_id') === (string) $warehouse['id'] ? 'selected' : '' ?>><?= e($lang === 'ar' ? $warehouse['name_ar'] : $warehouse['name']) ?></option><?php endforeach; ?>
      </select>
      <?php $feedback('warehouse_id'); ?>
    </div>
    <div class="mb-3">
      <label class="form-label" for="user-preferred_lang"><?= lang('language') ?></label>
      <select class="form-select<?= $invalid('preferred_lang') ?>" id="user-preferred_lang" name="preferred_lang" <?= $aria('preferred_lang') ?>>
        <option value="ar" <?= $value('preferred_lang', 'ar') === 'ar' ? 'selected' : '' ?>>العربية</option>
        <option value="en" <?= $value('preferred_lang') === 'en' ? 'selected' : '' ?>>English</option>
      </select>
      <?php $feedback('preferred_lang'); ?>
    </div>
    <div class="mb-3">
      <label class="form-label" for="user-status"><?= lang('status') ?></label>
      <select class="form-select<?= $invalid('status') ?>" id="user-status" name="status" <?= $aria('status') ?>>
        <option value="active" <?= $value('status', 'active') === 'active' ? 'selected' : '' ?>><?= lang('active') ?></option>
        <option value="inactive" <?= $value('status') === 'inactive' ? 'selected' : '' ?>><?= lang('inactive') ?></option>
      </select>
      <?php $feedback('status'); ?>
    </div>
    <button class="btn btn-primary" type="submit"><?= lang('save') ?></button>
    <a class="btn btn-link" href="<?= site_url('users') ?>"><?= lang('cancel') ?></a>
  <?= form_close() ?>
</div></div>
