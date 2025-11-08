<?php

require_once '../../../includes/modal_header.php';

$type_display = '';

if (isset($_GET['type'])) {
    $type = intval($_GET['type']);

    if ($type === 1) {
        $type_display = "Client";
    } elseif($type === 2) {
        $type_display = "Location";
    } elseif ($type === 3) {
        $type_display = "Contact";
    } elseif ($type === 4) {
        $type_display = "Credential";
    }
}
ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-tag mr-2"></i>New <strong><?= $type_display ?></strong> Tag</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="type" value="<?php echo $type; ?>">
    <div class="modal-body">
        <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                </div>
                <input type="text" class="form-control" name="name" placeholder="Tag name" maxlength="200" required autofocus>
            </div>
        </div>
        
        <?php if (isset($_GET['type'])) { ?>
        
        <input type="hidden" name="type" value="<?= $type ?>">
        
        <?php } else { ?>
        
        <div class="form-group">
            <label>Type <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-th"></i></span>
                </div>
                <select class="form-control select2" name="type" required>
                    <option value="">- Type -</option>
                    <option value="1">Client Tag</option>
                    <option value="2">Location Tag</option>
                    <option value="3">Contact Tag</option>
                    <option value="4">Credential Tag</option>
                </select>
            </div>
        </div>
    
        <?php } ?>

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
            <label>Icon</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-image"></i></span>
                </div>
                <input type="text" class="form-control" name="icon" placeholder="Icon ex handshake">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_tag" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
