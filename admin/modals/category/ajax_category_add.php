<?php

require_once '../../includes/modal_header.php';

$category = nullable_htmlentities($_GET['category']);

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-list-ul mr-2"></i>New Category</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="type" value="<?php echo ($category); ?>">

    <div class="modal-body">

        <div class="form-row">

            <div class="form-group col-sm-9">
                <div class="input-group">
                    <input type="text" class="form-control" name="name" placeholder="Category name" maxlength="200" required autofocus>
                </div>
            </div>

            <div class="form-group col-sm-3">
                <div class="input-group">
                    <input type="color" class="form-control" name="color" required>
                </div>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_category" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../includes/modal_footer.php';
