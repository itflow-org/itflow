<?php

require_once '../../../includes/modal_header.php';

$contact_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT contact_name FROM contacts WHERE contact_id = $contact_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$contact_name = escapeHtml($row['contact_name']);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class='fa fa-fw fa-sticky-note mr-2'></i>Creating note: <strong><?php echo $contact_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Type</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                </div>
                <select class="form-control select2" name="type">
                    <?php
                    $sql_contact_note_types_select = mysqli_query($mysqli, "
                        SELECT category_name FROM categories
                        WHERE category_type = 'contact_note_type'
                        AND category_archived_at IS NULL
                        ORDER BY category_order ASC, category_name ASC
                    ");
                    while ($row = mysqli_fetch_assoc($sql_contact_note_types_select)) {
                        $contact_note_type_select = escapeHtml($row['category_name']);
                        ?>
                        <option><?= $contact_note_type_select ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <textarea class="form-control" rows="6" name="note" placeholder="Notes, eg Personal tidbits to spark convo, temperment, etc"></textarea>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="add_contact_note" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
