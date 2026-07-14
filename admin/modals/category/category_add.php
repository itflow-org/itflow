<?php

require_once '../../../includes/modal_header.php';

$category = escapeHtml($_GET['category'] ?? '');

$category_types_array = ['Expense', 'Income', 'Referral', 'Ticket'];

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-list-ul mr-2"></i>New <strong><?= escapeHtml(ucwords(str_replace('_', ' ', $category))); ?></strong> Category</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <?php if ($category) { ?>
        <input type="hidden" name="type" value="<?= $category ?>">
        <?php } else { ?>

        <div class="form-group">
            <label>Type <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                </div>
                <select class="form-control select2" name="type" required>
                    <option value="">- Select Type -</option>
                    <?php foreach ($category_types_array as $type_select) { ?>
                        <option><?= $type_select ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <?php } ?>

        <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-list-ul"></i></span>
                </div>
                <input type="text" class="form-control" name="name" placeholder="Category name" maxlength="200" required autofocus>
            </div>
        </div>

        <div class="form-group">
            <label>Color <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-paint-brush"></i></span>
                </div>
                <input type="color" class="form-control col-3" name="color" required>
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-fw fa-align-left"></i></span>
                </div>
                <input type="text" class="form-control" name="description" placeholder="Enter a description" maxlength="200">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_category" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create Category</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
