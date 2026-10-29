<div class="page-header"><h2><?= lang('settings') ?></h2></div>
<div class="card"><div class="card-body">
<?= validation_errors('<div class="alert alert-danger">', '</div>') ?>
<?= form_open('settings/update') ?>
<div class="mb-3"><label class="form-label" for="company_name"><?= lang('company_name') ?></label><input class="form-control" id="company_name" name="company_name" value="<?= e(set_value('company_name', $settings['company_name'])) ?>" required></div>
<div class="mb-3"><label class="form-label" for="invoice_prefix"><?= lang('invoice_prefix') ?></label><input class="form-control" id="invoice_prefix" name="invoice_prefix" value="<?= e(set_value('invoice_prefix', $settings['invoice_prefix'])) ?>" required></div>
<button class="btn btn-primary" type="submit"><?= lang('save') ?></button>
<?= form_close() ?>
</div></div>
