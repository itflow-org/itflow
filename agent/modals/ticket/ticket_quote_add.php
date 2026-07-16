<?php

require_once '../../../includes/modal_header.php';

$ticket_id = intval($_GET['ticket_id']);

$ticket_sql = mysqli_query(
    $mysqli,
    "SELECT * FROM tickets
    LEFT JOIN clients ON ticket_client_id = client_id
    LEFT JOIN contacts ON ticket_contact_id = contact_id
    LEFT JOIN users ON ticket_assigned_to = user_id
    LEFT JOIN locations ON ticket_location_id = location_id
    LEFT JOIN assets ON ticket_asset_id = asset_id
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    LEFT JOIN categories ON ticket_category = category_id
    WHERE ticket_id = $ticket_id
    $access_permission_query
    LIMIT 1"
);

$row = mysqli_fetch_assoc($ticket_sql);
$client_id = intval($row['client_id']);
$client_rate = floatval($row['client_rate']);
$ticket_prefix = escapeHtml($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$ticket_category = intval($row['ticket_category']);
$ticket_category_display = escapeHtml($row['category_name']);
$ticket_subject = escapeHtml($row['ticket_subject']);
$ticket_priority = escapeHtml($row['ticket_priority']);
$ticket_billable = intval($row['ticket_billable']);
$ticket_onsite = intval($row['ticket_onsite']);

$ticket_created_at = escapeHtml($row['ticket_created_at']);
$ticket_created_by = intval($row['ticket_created_by']);
$ticket_date = date('Y-m-d g:i A', strtotime($ticket_created_at));
$ticket_first_response_at = escapeHtml($row['ticket_first_response_at']);
if ($ticket_first_response_at) {
    $ticket_first_response_date_time = date('Y-m-d g:i A', strtotime($ticket_first_response_at));
} else {
    $ticket_first_response_date_time = '';
}
$ticket_resolved_at = escapeHtml($row['ticket_resolved_at']);
if ($ticket_resolved_at) {
    $ticket_resolved_date = date('Y-m-d g:i A', strtotime($ticket_resolved_at));
} else {
    $ticket_resolved_date = '';
}

$ticket_assigned_to = intval($row['ticket_assigned_to']);
if ($ticket_assigned_to) {
    $ticket_assigned_agent = escapeHtml($row['user_name']);
} else {
    $ticket_assigned_agent = '';
}

$contact_id = intval($row['contact_id']);
$contact_name = escapeHtml($row['contact_name']);

$asset_id = intval($row['asset_id']);
$asset_name = escapeHtml($row['asset_name']);
$asset_type = escapeHtml($row['asset_type']);

if ($client_id) {
    enforceClientAccess();
}

ob_start();

?>

    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-comment-dollar mr-2"></i>Quote ticket</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
        </button>
    </div>

    <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">

        <div class="modal-body">

            <!-- Row 1 -->
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Scope</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                            </div>
                            <input type="text" class="form-control" name="scope" placeholder="Quick description" maxlength="255">
                        </div>
                    </div>
                </div>

                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Quote Category <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                            </div>

                            <select class="form-control select2" name="category">
                                <option value="">- Select a Category -</option>
                                <?php
                                $sql = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Income' AND category_archived_at IS NULL ORDER BY category_name ASC");
                                while ($row = mysqli_fetch_assoc($sql)) {
                                    $category_id = intval($row['category_id']);
                                    $category_name = escapeHtml($row['category_name']);
                                    ?>
                                    <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                                <?php } ?>
                            </select>

                            <div class="input-group-append">
                                <button type="button" class="btn btn-secondary ajax-modal" data-modal-url="/admin/modals/category/category_add.php?category=Expense">
                                    <i class="fas fa-fw fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End row 1 -->

            <!-- Row 2 -->
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Quote Date <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            </div>
                            <input type="date" class="form-control" name="date" max="2999-12-31" value="<?php echo date("Y-m-d"); ?>">
                        </div>
                    </div>
                </div>

                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Expire <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            </div>
                            <input type="date" class="form-control" name="expire" min="<?php echo date("Y-m-d"); ?>" max="2999-12-31" value="<?php echo date("Y-m-d", strtotime("+30 days")); ?>" required>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End row 2 -->

            <div class="form-group">
                <label>Item <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-box"></i></span>
                    </div>
                    <input type="text" class="form-control" name="item_name" placeholder="Item" required>
                </div>
            </div>

            <?php $description = "Ticket: {$ticket_prefix}{$ticket_number} - $ticket_subject\n"; ?>

            <div class="form-group">
                <label>Item Description</label>
                <div class="input-group">
                    <textarea class="form-control" rows="10" name="item_description"><?php echo trim($description); ?></textarea>
                </div>
            </div>

            <div class="form-row">
                <div class="col">
                    <div class="form-group">
                        <label>QTY <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                            </div>
                            <input type="text" class="form-control" inputmode="decimal" pattern="-?[0-9]*\.?[0-9]{0,2}" name="qty" required>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="form-group">
                        <label>Price <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                            </div>
                            <input type="text" class="form-control" inputmode="decimal" pattern="-?[0-9]*\.?[0-9]{0,2}" name="price" value="<?php echo number_format($client_rate, 2, '.', ''); ?>" required>
                        </div>
                        <small class="form-text text-muted">
                            Hourly Client rate is <strong><?= numfmt_format_currency($currency_format, $client_rate, $session_company_currency); ?></strong>
                        </small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Tax <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                    </div>

                    <select class="form-control select2" name="tax_id" required>
                        <option value="0">None</option>
                        <?php
                        $taxes_sql = mysqli_query($mysqli, "SELECT * FROM taxes WHERE tax_archived_at IS NULL ORDER BY tax_name ASC");
                        while ($row = mysqli_fetch_assoc($taxes_sql)) {
                            $tax_id_select = intval($row['tax_id']);
                            $tax_name = escapeHtml($row['tax_name']);
                            $tax_percent = floatval($row['tax_percent']);
                            ?>
                            <option value="<?php echo $tax_id_select; ?>"><?php echo "$tax_name $tax_percent%"; ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

        </div>

        <div class="modal-footer">
            <button type="submit" name="add_quote_from_ticket" class="btn btn-primary text-bold">
                <i class="fa fa-check mr-2"></i>Quote
            </button>
            <button type="button" class="btn btn-light" data-dismiss="modal">
                <i class="fa fa-times mr-2"></i>Cancel
            </button>
        </div>
    </form>

<?php
require_once '../../../includes/modal_footer.php';
