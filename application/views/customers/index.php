<div class="page-header d-flex"><h2><?= lang('customers') ?></h2><a class="btn btn-primary ms-auto" href="<?= site_url('customers/create') ?>"><?= lang('add') ?></a></div>
<div class="card"><table class="table data-table" data-source="<?= site_url('customers/data') ?>" data-fields="name,phone,email,action"><thead><tr><th><?= lang('name') ?></th><th><?= lang('phone') ?></th><th><?= lang('email') ?></th><th></th></tr></thead><tbody>
<?php foreach ($customers as $customer): ?><tr><td><?= e($customer['name']) ?></td><td><?= e($customer['phone']) ?></td><td><?= e($customer['email']) ?></td><td><a href="<?= site_url('customers/edit/' . $customer['id']) ?>"><?= lang('edit') ?></a></td></tr><?php endforeach; ?>
</tbody></table></div>
