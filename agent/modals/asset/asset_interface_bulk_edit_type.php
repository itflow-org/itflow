<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);
$interface_ids = array_map('intval', $_GET['interface_ids'] ?? []);

$count = count($interface_ids);

ob_start();

?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($interface_ids as $interface_id) { ?><input type="hidden" name="interface_ids[]" value="<?= $interface_id ?>"><?php } ?>

    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-plug me-2"></i>Type of <strong><?= $count ?></strong> interface<?= $count === 1 ? '' : 's' ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>

    <div class="modal-body">

        <!-- Type -->
        <div class="mb-3">
            <label for="interface_type">Interface Type</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-plug"></i></span>
                <select class="form-select select2" id="interface_type" name="bulk_type">
                    <option value="">- No type -</option>
                    <?= categoryOptionsHtml('network_interface') ?>
                </select>
                <?= categoryAddButtonHtml('network_interface', 'btn btn-secondary') ?>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_asset_interface_type" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Set</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
