<?php

require_once '../../../includes/modal_header.php';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-robot mr-2"></i>New AI Provider</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Provider Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-robot"></i></span>
                </div>
                <input type="text" class="form-control" name="provider" placeholder="ex OpenAI">
            </div>
        </div>

        <div class="form-group">
            <label>URL <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                </div>
                <input type="url" class="form-control" name="url" placeholder="ex https://ai.company.ext/api">
            </div>
        </div>

        <div class="form-group">
            <label>API Key</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                </div>
                <input type="text" class="form-control" name="api_key" placeholder="Enter API key here">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_ai_provider" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
