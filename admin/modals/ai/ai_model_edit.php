<?php

require_once '../../includes/modal_header.php';

$model_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT ai_model_ai_provider_id, ai_model_id, ai_model_name, ai_model_prompt, ai_model_use_case, ai_model_temperature FROM ai_models WHERE ai_model_id = $model_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$ai_model_ai_provider_id = intval($row['ai_model_ai_provider_id']);
$model_id = intval($row['ai_model_id']);
$model_name = escapeHtml($row['ai_model_name']);
$use_case = escapeHtml($row['ai_model_use_case']);
$temperature = escapeHtml($row['ai_model_temperature']);
$prompt = escapeHtml($row['ai_model_prompt']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-robot mr-2"></i>Editing: <strong><?= $model_name ?></strong></h5>
    <button type="button" class="close text-light" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="model_id" value="<?= $model_id ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Provider <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-robot"></i></span>
                </div>
                <select class="form-control select2" name="provider" required>
                    <option value="">- Select an AI Provider -</option>
                    <?php
                        $sql_ai_providers = mysqli_query($mysqli, "SELECT ai_provider_id, ai_provider_name FROM ai_providers");
                        while ($row = mysqli_fetch_assoc($sql_ai_providers)) {
                            $ai_provider_id = intval($row['ai_provider_id']);
                            $ai_provider_name = escapeHtml($row['ai_provider_name']);

                        ?>
                        <option <?php if ($ai_provider_id = $ai_model_ai_provider_id) { echo "selected"; } ?> value="<?= $ai_provider_id ?>"><?= $ai_provider_name ?></option>
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
                <input type="text" class="form-control" name="model" value="<?= $model_name ?>" placeholder="ex gpt-4" maxlength="200">
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
            <label>Temperature</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                </div>
                <input type="number" class="form-control" name="temperature" step="0.1" min="0" max="2" value="<?= $temperature ?>" placeholder="Provider default">
            </div>
            <small class="form-text text-muted">Optional. Leave blank to let the provider use its default - some newer models reject every other value.</small>
        </div>
        <div class="form-group">
            <textarea class="form-control" rows="8" name="prompt" placeholder="Enter a model prompt:"><?= $prompt ?></textarea>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_ai_model" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
