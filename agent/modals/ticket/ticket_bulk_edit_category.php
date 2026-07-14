<?php

require_once '../../../includes/modal_header.php';

$ticket_ids = array_map('intval', $_GET['ticket_ids'] ?? []);

$count = count($ticket_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-layer-group mr-2"></i>Set Category for <strong><?= $count ?></strong> Tickets</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($ticket_ids as $ticket_id) { ?><input type="hidden" name="ticket_ids[]" value="<?= $ticket_id ?>"><?php } ?>

    <div class="modal-body">



        <div class="form-group">
            <label>Category</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                </div>
                <select class="form-control select2" name="bulk_category">
                    <option value="0">- Uncategorized -</option>
                    <?php
                    $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL ORDER BY category_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_categories)) {
                        $category_id = intval($row['category_id']);
                        $category_name = escapeHtml($row['category_name']);

                        ?>
                        <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                    <?php } ?>

                </select>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_ticket_category" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Set</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
