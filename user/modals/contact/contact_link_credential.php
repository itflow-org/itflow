<?php

require_once '../../../includes/modal_header_new.php';

$contact_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM contacts
    WHERE contact_id = $contact_id
    LIMIT 1
");

$row = mysqli_fetch_array($sql);
$contact_name = nullable_htmlentities($row['contact_name']);
$client_id = intval($row['contact_client_id']);

// Generate the HTML form content using output buffering.
ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-key mr-2"></i>Link Credential to <strong><?php echo $contact_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
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
                        SELECT credential_id, credential_name
                        FROM credentials
                        WHERE credential_client_id = $client_id
                        AND credential_contact_id = 0
                        AND credential_archived_at IS NULL
                        ORDER BY credential_name ASC
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
        <button type="submit" name="link_contact_to_credential" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer_new.php';
?>
