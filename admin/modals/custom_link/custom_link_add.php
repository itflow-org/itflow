<?php

require_once '../../includes/modal_header.php';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-external-link-alt me-2"></i>New Custom Link</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-list-ul"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Link name" maxlength="200" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label>Order</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-sort-numeric-down"></i></span>
                <input type="number" class="form-control" name="order" placeholder="Leave blank for no order">
            </div>
        </div>

        <div class="mb-3">
            <label>URI <strong class="text-danger">*</strong></label> / <span class="text-secondary">Open New Tab</span>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-external-link-alt"></i></span>
                <input type="text" class="form-control" name="uri" placeholder="Enter Link" maxlength="500" required>
                    <div class="input-group-text">
                        <input type="checkbox" name="new_tab" value="1">
                    </div>
            </div>
        </div>

        <div class="mb-3">
            <label>Icon</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-image"></i></span>
                <input type="text" class="form-control" name="icon" placeholder="Icon ex handshake" maxlength="200">
            </div>
        </div>

        <div class="mb-3">
            <label>Location <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-home"></i></span>
                <select class="form-control select2" name="location" required>
                    <option value="1">Main Side Nav</option>
                    <option value="2">Top Nav (Icon Required)</option>
                    <option value="3">Client Portal Nav</option>
                    <option value="4">Admin Nav</option>
                    <option value="5">Reports Nav</option>
                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_custom_link" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
