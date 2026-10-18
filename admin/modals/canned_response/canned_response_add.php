<?php

require_once '../../includes/modal_header.php';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-comment-dots me-2"></i>New Canned Response</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-comment-dots"></i></span>
                <input type="text" class="form-control" name="name" placeholder="What an agent picks it by" maxlength="200" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label>Ticket Category</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <select class="form-select select2" name="category">
                    <option value="0">- All ticket categories -</option>
                    <?= categoryOptionsHtml('Ticket') ?>
                </select>
                <?= categoryAddButtonHtml('Ticket', 'btn btn-secondary') ?>
            </div>
            <small class="text-secondary">Leave this on all categories for a response that should be offered on every ticket.</small>
        </div>

        <div class="mb-3">
            <label>Response</label>
            <textarea class="form-control tinymceTicket" name="body"></textarea>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_canned_response" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create Canned Response</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
