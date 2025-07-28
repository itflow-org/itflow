<?php

require_once '../includes/ajax_header.php';

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

<div class="modal-header">
    <h5 class="modal-title"><i class="fa fa-fw fa-folder mr-2"></i>Link Document to <strong><?php echo $contact_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
    <div class="modal-body bg-white">

        <div class="form-group">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                </div>
                <select class="form-control select2" name="document_id">
                    <option value="">- Select a Document -</option>
                    <?php
                    $sql_documents_select = mysqli_query($mysqli, "
                        SELECT documents.document_id, documents.document_name
                        FROM documents
                        LEFT JOIN contact_documents
                            ON documents.document_id = contact_documents.document_id
                            AND contact_documents.contact_id = $contact_id
                        WHERE documents.document_client_id = $client_id
                        AND documents.document_archived_at IS NULL
                        AND contact_documents.contact_id IS NULL
                        ORDER BY documents.document_name ASC
                    ");
                    while ($row = mysqli_fetch_array($sql_documents_select)) {
                        $document_id = intval($row['document_id']);
                        $document_name = nullable_htmlentities($row['document_name']);
                        ?>
                        <option value="<?php echo $document_id ?>"><?php echo $document_name; ?></option>
                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="link_contact_to_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../includes/ajax_footer.php";
?>
