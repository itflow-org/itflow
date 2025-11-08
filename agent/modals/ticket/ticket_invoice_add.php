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

$row = mysqli_fetch_array($ticket_sql);
$client_id = intval($row['client_id']);
$client_rate = floatval($row['client_rate']);
$ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$ticket_category = intval($row['ticket_category']);
$ticket_category_display = nullable_htmlentities($row['category_name']);
$ticket_subject = nullable_htmlentities($row['ticket_subject']);
$ticket_priority = nullable_htmlentities($row['ticket_priority']);
$ticket_billable = intval($row['ticket_billable']);
$ticket_onsite = intval($row['ticket_onsite']);

$ticket_created_at = nullable_htmlentities($row['ticket_created_at']);
$ticket_created_by = intval($row['ticket_created_by']);
$ticket_date = date('Y-m-d', strtotime($ticket_created_at));
$ticket_first_response_at = nullable_htmlentities($row['ticket_first_response_at']);
if ($ticket_first_response_at) {
    $ticket_first_response_date_time = date('Y-m-d H:i', strtotime($ticket_first_response_at));
} else {
    $ticket_first_response_date_time = '';
}
$ticket_resolved_at = nullable_htmlentities($row['ticket_resolved_at']);
if ($ticket_resolved_at) {
    $ticket_resolved_date = date('Y-m-d', strtotime($ticket_resolved_at));
} else {
    $ticket_resolved_date = '';
}

$ticket_assigned_to = intval($row['ticket_assigned_to']);
if ($ticket_assigned_to) {
    $ticket_assigned_agent = nullable_htmlentities($row['user_name']);
} else {
    $ticket_assigned_agent = '';
}

$contact_id = intval($row['contact_id']);
$contact_name = nullable_htmlentities($row['contact_name']);

$asset_id = intval($row['asset_id']);
$asset_name = nullable_htmlentities($row['asset_name']);
$asset_type = nullable_htmlentities($row['asset_type']);

$location_id = intval($row['location_id']);
$location_name = nullable_htmlentities($row['location_name']);
$location_address = nullable_htmlentities($row['location_address']);
$location_city = nullable_htmlentities($row['location_city']);
$location_state = nullable_htmlentities($row['location_state']);
$location_zip = nullable_htmlentities($row['location_zip']);
$location_phone = formatPhoneNumber($row['location_phone']);

//Get Total Ticket Time
$ticket_total_reply_time = mysqli_query($mysqli, "SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(ticket_reply_time_worked))) AS ticket_total_reply_time FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND ticket_reply_ticket_id = $ticket_id");
$row = mysqli_fetch_array($ticket_total_reply_time);
$ticket_total_reply_time = nullable_htmlentities($row['ticket_total_reply_time']);

$sql_invoices = mysqli_query($mysqli, "SELECT * FROM invoices WHERE invoice_status LIKE 'Draft' AND invoice_client_id = $client_id ORDER BY invoice_number ASC");

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-invoice-dollar mr-2"></i>Invoice ticket</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
    <div class="modal-body">
        <?php if (mysqli_num_rows($sql_invoices) > 0) { ?>

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-add-to-invoice"><i class="fa fa-fw fa-plus mr-2"></i>Add to Existing Invoice</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-create-invoice"><i class="fa fa-fw fa-check mr-2"></i>Create New Invoice</a>
            </li>
            
        </ul>

        <hr>

        <?php } ?>

        <div class="tab-content">

                <?php
            
                if (mysqli_num_rows($sql_invoices) > 0) { ?>

                <div class="tab-pane fade <?php if (mysqli_num_rows($sql_invoices) > 0) { echo "active show"; } ?>" id="pills-add-to-invoice">
                    <div class="form-group">
                        <label>Existing Invoice</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-file-invoice-dollar"></i></span>
                            </div>
                            <select class="form-control" name="invoice_id">
                                <option value="0">- Select an Existing Invoice -</option>
                                <?php

                                while ($row = mysqli_fetch_array($sql_invoices)) {
                                    $invoice_id = intval($row['invoice_id']);
                                    $invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
                                    $invoice_number = intval($row['invoice_number']);
                                    $invoice_scope = nullable_htmlentities($row['invoice_scope']);
                                    $invoice_status = nullable_htmlentities($row['invoice_status']);
                                    $invoice_date = nullable_htmlentities($row['invoice_date']);
                                    $invoice_due = nullable_htmlentities($row['invoice_due']);
                                    $invoice_amount = floatval($row['invoice_amount']);
                                    ?>
                                    <option value="<?php echo $invoice_id; ?>"><?php echo "$invoice_prefix$invoice_number | $invoice_scope"; ?></option>
                                <?php } ?>

                            </select>
                        </div>
                    </div>
                </div>

                <?php } ?>

                <div class="tab-pane fade <?php if (mysqli_num_rows($sql_invoices) == 0) { echo "active show"; } ?>" id="pills-create-invoice">

                <div class="row">
                    <div class="col-sm-6">
                
                        <div class="form-group">
                            <label>Invoice Date <strong class="text-danger">*</strong></label>
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
                            <label>Invoice Category <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                                </div>
                                <select class="form-control select2" name="category">
                                    <option value="">- Select a Category -</option>
                                    <?php

                                    $sql = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Income' AND category_archived_at IS NULL ORDER BY category_name ASC");
                                    while ($row = mysqli_fetch_array($sql)) {
                                        $category_id = intval($row['category_id']);
                                        $category_name = nullable_htmlentities($row['category_name']);
                                        ?>
                                        <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>

                                        <?php
                                    }
                                    ?>
                                </select>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-secondary ajax-modal" data-modal-url="/admin/modals/category/category_add.php?category=Expense"><i class="fas fa-fw fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Invoice Title</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                        </div>
                        <select class="form-control select2" name="scope" data-tags="true" data-placeholder="- Enter or Select an Invoice Title -">
                            <option value=""></option>
                            <option><?= date('F Y'); ?> Tickets</option>
                            <option><?= "Ticket $ticket_prefix$ticket_number - $ticket_subject" ?></option>
                        </select>
                    </div>
                </div>

            </div>
            
        </div>

        <div class="form-group">
            <label>Item <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-box"></i></span>
                </div>
                <input type="text" class="form-control" name="item_name" placeholder="Item" value="Support [Hourly]" required>
            </div>
        </div>

        <div class="form-group">
            <label>Item Description</label>
            <div class="input-group">
                <textarea class="form-control" rows="10" name="item_description"><?php
                    // Build description text cleanly in PHP, not mixed with HTML
                    $description = "#Ticket: {$ticket_prefix}{$ticket_number} - $ticket_subject\n";
                    $description .= "Priority: {$ticket_priority}\n";
                    $description .= "Opened at: {$ticket_date}\n";
                    if ($ticket_first_response_date_time) {
                        $description .= "Initial Response: {$ticket_first_response_date_time}\n";
                    }
                    if ($ticket_resolved_date) {
                        $description .= "Resolved at: {$ticket_resolved_date}\n";
                    }
                    if ($ticket_assigned_agent) {
                        $description .= "Agent: {$ticket_assigned_agent}\n";
                    }
                    if ($location_id) {
                        $description .= "Location: {$location_name}\n";
                    }
                    if ($contact_id) {
                        $description .= "Contact: {$contact_name}\n";
                    }
                    if ($asset_id) {
                        $description .= "Asset: {$asset_name}\n";
                    }
                    if ($ticket_total_reply_time) {
                        $description .= "Agent Time Spent: {$ticket_total_reply_time}";
                    }

                    echo trim($description); // Trim any leading/trailing spaces/newlines
                ?></textarea>
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
                        <input type="text" class="form-control" inputmode="numeric" pattern="-?[0-9]*\.?[0-9]{0,2}" name="qty" value="<?php echo roundToNearest15($ticket_total_reply_time); ?>" required>
                    </div>
                    <small class="form-text text-muted">
                        Based off Ticket time spent <strong><?= $ticket_total_reply_time ?></strong> in 15 Min Increments rounded up.
                    </small>
                </div>

            </div>

            <div class="col">

                <div class="form-group">
                    <label>Price <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                        </div>
                        <input type="text" class="form-control" inputmode="numeric" pattern="-?[0-9]*\.?[0-9]{0,2}" name="price" value="<?php echo number_format($client_rate, 2, '.', ''); ?>" required>
                    </div>
                    <small class="form-text text-muted">
                        Based off Hourly Client rate of <strong><?= numfmt_format_currency($currency_format, $client_rate, $session_company_currency); ?></strong>
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
                    while ($row = mysqli_fetch_array($taxes_sql)) {
                        $tax_id_select = intval($row['tax_id']);
                        $tax_name = nullable_htmlentities($row['tax_name']);
                        $tax_percent = floatval($row['tax_percent']);
                        ?>
                        <option value="<?php echo $tax_id_select; ?>"><?php echo "$tax_name $tax_percent%"; ?></option>
                    <?php } ?>
                </select>

            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_invoice_from_ticket" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Invoice</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
