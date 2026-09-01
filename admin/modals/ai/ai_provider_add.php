<?php

require_once '../../includes/modal_header.php';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-robot me-2"></i>New AI Provider</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Provider Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-robot"></i></span>
                <input type="text" class="form-control" name="provider" placeholder="ex OpenAI" maxlength="200">
            </div>
        </div>

        <div class="mb-3">
            <label>URL <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                <input type="url" class="form-control" name="url" placeholder="ex https://ai.company.ext/api" maxlength="200">
            </div>
        </div>

        <div class="mb-3">
            <label>API Key</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                <input type="text" class="form-control" name="api_key" placeholder="Enter API key here" maxlength="200">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_ai_provider" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
