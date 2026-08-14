<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_credential', 2);

$asset_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT asset_client_id, asset_name FROM assets
    WHERE asset_id = $asset_id
    LIMIT 1
");

$row = mysqli_fetch_assoc($sql);
$asset_name = escapeHtml($row['asset_name']);
$client_id = intval($row['asset_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-key me-2"></i>Link Credential to <strong><?= $asset_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="asset_id" value="<?= $asset_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                <select class="form-select select2" name="credential_id">
                    <option value="">- Select a Credential -</option>
                    <?php
                    $sql_credentials_select = mysqli_query($mysqli, "
                        SELECT credentials.credential_id, credentials.credential_name
                        FROM credentials
                        LEFT JOIN assets ON credentials.credential_asset_id = assets.asset_id
                        AND credentials.credential_asset_id = $asset_id
                        WHERE credentials.credential_client_id = $client_id
                        AND credentials.credential_asset_id = 0
                        AND credentials.credential_archived_at IS NULL
                        ORDER BY credentials.credential_name ASC
                    ");
                    while ($row = mysqli_fetch_assoc($sql_credentials_select)) {
                        $credential_id = intval($row['credential_id']);
                        $credential_name = escapeHtml($row['credential_name']);
                        ?>
                        <option value="<?= $credential_id ?>"><?= $credential_name ?></option>
                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="link_asset_to_credential" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
