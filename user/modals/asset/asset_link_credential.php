<?php

require_once '../../../includes/modal_header_new.php';

$asset_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM assets
    WHERE asset_id = $asset_id
    LIMIT 1
");

$row = mysqli_fetch_array($sql);
$asset_name = nullable_htmlentities($row['asset_name']);
$client_id = intval($row['asset_client_id']);

// Generate the HTML form content using output buffering.
ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-key mr-2"></i>Link Credential to <strong><?php echo $asset_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="asset_id" value="<?php echo $asset_id; ?>">
    <div class="modal-body">

        <div class="form-group">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                </div>
                <select class="form-control select2" name="credential_id">
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
                    while ($row = mysqli_fetch_array($sql_credentials_select)) {
                        $credential_id = intval($row['credential_id']);
                        $credential_name = nullable_htmlentities($row['credential_name']);
                        ?>
                        <option value="<?php echo $credential_id ?>"><?php echo $credential_name; ?></option>
                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="link_asset_to_credential" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer_new.php';
?>
