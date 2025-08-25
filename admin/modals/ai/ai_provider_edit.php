<?php

require_once '../../../includes/modal_header_new.php';

$provider_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM ai_providers WHERE ai_provider_id = $provider_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$provider_name = nullable_htmlentities($row['ai_provider_name']);
$url = nullable_htmlentities($row['ai_provider_api_url']);
$key = nullable_htmlentities($row['ai_provider_api_key']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-robot mr-2"></i>Editing: <strong><?php echo $provider_name; ?></strong></h5>
    <button type="button" class="close text-light" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="provider_id" value="<?php echo $provider_id; ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Provider Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-robot"></i></span>
                </div>
                <input type="text" class="form-control" name="provider" value="<?php echo $provider_name; ?>" placeholder="ex OpenAI">
            </div>
        </div>

        <div class="form-group">
            <label>URL <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                </div>
                <input type="url" class="form-control" name="url" value="<?php echo $url; ?>" placeholder="ex https://ai.company.ext/api">
            </div>
        </div>

        <div class="form-group">
            <label>API Key</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                </div>
                <input type="text" class="form-control" name="api_key" value="<?php echo $key; ?>" placeholder="Enter API key here">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_ai_provider" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer_new.php';
