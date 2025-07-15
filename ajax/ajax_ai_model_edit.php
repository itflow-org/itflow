<?php

require_once '../includes/ajax_header.php';

$model_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM ai_models WHERE ai_model_id = $model_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$ai_model_ai_provider_id = intval($row['ai_model_ai_provider_id']);
$model_id = intval($row['ai_model_id']);
$model_name = nullable_htmlentities($row['ai_model_name']);
$use_case = nullable_htmlentities($row['ai_model_use_case']);
$prompt = nullable_htmlentities($row['ai_model_prompt']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title"><i class="fa fa-fw fa-robot mr-2"></i>Editing: <strong><?php echo $model_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="model_id" value="<?php echo $model_id; ?>">

    <div class="modal-body bg-white">

        <div class="form-group">
            <label>Provider <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-robot"></i></span>
                </div>
                <select class="form-control select2" name="provider" required>
                    <option value="">- Select an AI Provider -</option>
                    <?php
                        $sql_ai_providers = mysqli_query($mysqli, "SELECT * FROM ai_providers");
                        while ($row = mysqli_fetch_array($sql_ai_providers)) {
                            $ai_provider_id = intval($row['ai_provider_id']);
                            $ai_provider_name = nullable_htmlentities($row['ai_provider_name']);

                        ?>
                        <option <?php if ($ai_provider_id = $ai_model_ai_provider_id) { echo "selected"; } ?> value="<?php echo $ai_provider_id; ?>"><?php echo $ai_provider_name; ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Model Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-robot"></i></span>
                </div>
                <input type="text" class="form-control" name="model" value="<?php echo $model_name; ?>" placeholder="ex gpt-4">
            </div>
        </div>

        <div class="form-group">
            <label>Use Case <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-th-list"></i></span>
                </div>
                <select class="form-control select2" name="use_case">
                    <option <?php if ($use_case == 'General') { echo "selected"; } ?>>General</option>
                    <option <?php if ($use_case == 'Tickets') { echo "selected"; } ?>>Tickets</option>
                    <option <?php if ($use_case == 'Documentation') { echo "selected"; } ?>>Documentation</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <textarea class="form-control" rows="8" name="prompt" placeholder="Enter a model prompt:"><?php echo $prompt; ?></textarea>
        </div>

    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="edit_ai_model" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once "../includes/ajax_footer.php";
