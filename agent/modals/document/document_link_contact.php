<?php

require_once '../../../includes/modal_header.php';

$document_id = intval($_GET['document_id']);

$sql = mysqli_query($mysqli, "SELECT * FROM documents
    WHERE document_id = $document_id
    LIMIT 1
");

$row = mysqli_fetch_assoc($sql);
$document_name = escapeHtml($row['document_name']);
$client_id = intval($row['document_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-user mr-2"></i>Link Contact to <strong><?= $document_name ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_id" value="<?= $document_id ?>">
    <div class="modal-body">

        <div class="form-group">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                </div>
                <select class="form-control select2" name="contact_id">
                    <option value="">- Select a Contact -</option>
                    <?php
                    $sql_contacts_select = mysqli_query($mysqli, "
                        SELECT contacts.contact_id, contact_name
                        FROM contacts
                        LEFT JOIN contact_documents
                            ON contacts.contact_id = contact_documents.contact_id
                            AND contact_documents.document_id = $document_id
                        WHERE contact_client_id = $client_id
                        AND contact_archived_at IS NULL
                        AND contact_documents.contact_id IS NULL
                        ORDER BY contact_name ASC
                    ");
                    while ($row = mysqli_fetch_assoc($sql_contacts_select)) {
                        $contact_id = intval($row['contact_id']);
                        $contact_name = escapeHtml($row['contact_name']);

                        ?>
                        <option value="<?= $contact_id ?>"><?= $contact_name ?></option>
                        <?php
                    }
                    ?>

                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="link_contact_to_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link Contact</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
