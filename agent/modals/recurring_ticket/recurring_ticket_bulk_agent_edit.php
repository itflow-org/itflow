<?php

require_once '../../../includes/modal_header.php';

$selected_ids = array_map('intval', $_GET['selected_ids'] ?? []);

$count = count($selected_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-user-check mr-2"></i>Assign Agent to <strong><?= $count ?></strong> Tickets</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($selected_ids as $id) { ?><input type="hidden" name="recurring_ticket_ids[]" value="<?= $id ?>"><?php } ?>
    <div class="modal-body">

        <div class="form-group">
            <label>Agent</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                </div>
                <select class="form-control select2" name="assign_to">
                    <option value="0">Not Assigned</option>
                    <?php
                    $sql_users_select = mysqli_query($mysqli, "SELECT user_id, user_name FROM users
                        WHERE user_type = 1
                        AND user_status = 1
                        AND user_archived_at IS NULL 
                        ORDER BY user_name DESC"
                    );
                    while ($row = mysqli_fetch_array($sql_users_select)) {
                        $user_id_select = intval($row['user_id']);
                        $user_name_select = nullable_htmlentities($row['user_name']);

                        ?>
                        <option value="<?= $user_id_select ?>"><?= $user_name_select ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_assign_recurring_ticket" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Assign Agent</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
