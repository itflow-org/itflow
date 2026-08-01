<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support');

// Pre-filled from the tickets page's current filters. Everything here is editable -
// the export runs whatever this form posts, not whatever the page happened to show.
$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

// Search Filter
$q_filter = $_GET['q'] ?? '';

// Status Filter - the page uses an ID set, or the Open / Closed shorthand
$status_filter = (isset($_GET['status']) && is_array($_GET['status'])) ? array_map('intval', $_GET['status']) : [];
// Coarse open / closed / all state. `status` scalar is the pre-rework name.
$state_filter = $_GET['state'] ?? '';
if (!in_array($state_filter, array('open', 'closed', 'all'), true)) {
    $state_filter = (isset($_GET['status']) && !is_array($_GET['status']) && strtolower($_GET['status']) === 'closed') ? 'closed' : 'open';
}

// Client Filter
$client_filter = intval($_GET['client'] ?? 0);

// Category Filter
$category_filter = intval($_GET['category'] ?? 0);

// Assigned To Filter
$assigned_filter = intval($_GET['assigned'] ?? 0);

// SLA Filter
$sla_filter = $_GET['sla'] ?? '';

// Billing Filter
$billing_filter = $_GET['billing'] ?? '';

// Date Filter - the all-time sentinels from filter_header.php leave the fields blank
$date_from_filter = (!empty($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') ? escapeHtml($_GET['dtf']) : '';
$date_to_filter = (!empty($_GET['dtt']) && $_GET['dtt'] !== '2099-12-31') ? escapeHtml($_GET['dtt']) : '';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download mr-2"></i>Export Tickets</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<?php exportTabsNav(); ?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">

        <?php exportTabsFiltersOpen(); ?>

        <div class="form-group">
            <label>Search</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-search"></i></span>
                </div>
                <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="Number, subject, client, contact">
            </div>
        </div>

        <div class="form-group">
            <label>Status</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tasks"></i></span>
                </div>
                <select class="form-control select2" name="status[]" data-placeholder="- Open tickets -" multiple>
                    <?php
                    $sql_statuses_filter = mysqli_query($mysqli, "SELECT ticket_status_id, ticket_status_name FROM ticket_statuses ORDER BY ticket_status_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_statuses_filter)) {
                        $filter_status_id = intval($row['ticket_status_id']);
                        $filter_status_name = escapeHtml($row['ticket_status_name']);
                    ?>
                        <option <?php if (in_array($filter_status_id, $status_filter, true)) { echo "selected"; } ?> value="<?= $filter_status_id ?>"><?= $filter_status_name ?></option>
                    <?php
                    }
                    ?>
                </select>
            </div>
            <small class="form-text text-muted">Leave empty for open tickets only.</small>
        </div>

        <div class="form-group">
            <label>Resolution</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-check"></i></span>
                </div>
                <select class="form-control select2" name="resolution">
                    <option <?php if ($state_filter === 'open') { echo "selected"; } ?> value="">Open</option>
                    <option <?php if ($state_filter === 'closed') { echo "selected"; } ?> value="Closed">Closed</option>
                    <option <?php if ($state_filter === 'all') { echo "selected"; } ?> value="All">Open and closed</option>
                </select>
            </div>
        </div>

        <?php if ($config_module_enable_accounting && lookupUserPermission("module_sales") >= 2) { ?>
        <div class="form-group">
            <label>Billing</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                </div>
                <select class="form-control select2" name="billing">
                    <option value="">- Any -</option>
                    <option <?php if ($billing_filter === 'unbilled') { echo "selected"; } ?> value="unbilled">Billable, not invoiced</option>
                    <option <?php if ($billing_filter === 'invoiced') { echo "selected"; } ?> value="invoiced">Invoiced</option>
                    <option <?php if ($billing_filter === 'nonbillable') { echo "selected"; } ?> value="nonbillable">Not billable</option>
                </select>
            </div>
        </div>
        <?php } ?>

        <?php if (!$client_id) { ?>
        <div class="form-group">
            <label>Client</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                </div>
                <select class="form-control select2" name="client">
                    <option value="">- All Clients -</option>
                    <?php
                    $sql_clients_filter = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE EXISTS (SELECT 1 FROM tickets WHERE ticket_client_id = client_id) ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                        $filter_client_id = intval($row['client_id']);
                        $filter_client_name = escapeHtml($row['client_name']);
                    ?>
                        <option <?php if ($client_filter == $filter_client_id) { echo "selected"; } ?> value="<?= $filter_client_id ?>"><?= $filter_client_name ?></option>
                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>
        <?php } ?>

        <div class="form-group">
            <label>Category</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                </div>
                <select class="form-control select2" name="category">
                    <option value="">- All Categories -</option>
                    <?php
                    $sql_category_filter = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' ORDER BY category_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_category_filter)) {
                        $filter_option_id = intval($row['category_id']);
                        $filter_option_name = escapeHtml($row['category_name']);
                    ?>
                        <option <?php if ($category_filter == $filter_option_id) { echo "selected"; } ?> value="<?= $filter_option_id ?>"><?= $filter_option_name ?></option>
                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Assigned To</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-tie"></i></span>
                </div>
                <select class="form-control select2" name="assigned">
                    <option value="">- Anyone -</option>
                    <?php
                    $sql_assigned_filter = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_type = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_assigned_filter)) {
                        $filter_option_id = intval($row['user_id']);
                        $filter_option_name = escapeHtml($row['user_name']);
                    ?>
                        <option <?php if ($assigned_filter == $filter_option_id) { echo "selected"; } ?> value="<?= $filter_option_id ?>"><?= $filter_option_name ?></option>
                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>SLA</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-stopwatch"></i></span>
                </div>
                <select class="form-control select2" name="sla">
                    <option value="">- Any SLA state -</option>
                    <option <?php if ($sla_filter === 'breached') { echo "selected"; } ?> value="breached">Breached</option>
                    <option <?php if ($sla_filter === 'at_risk') { echo "selected"; } ?> value="at_risk">At risk</option>
                    <option <?php if ($sla_filter === 'paused') { echo "selected"; } ?> value="paused">Paused</option>
                    <option <?php if ($sla_filter === 'met') { echo "selected"; } ?> value="met">Met</option>
                    <option <?php if ($sla_filter === 'none') { echo "selected"; } ?> value="none">No SLA</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Opened From</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="dtf" value="<?= $date_from_filter ?>" max="2999-12-31">
            </div>
        </div>

        <div class="form-group">
            <label>Opened To</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="dtt" value="<?= $date_to_filter ?>" max="2999-12-31">
            </div>
        </div>

        <?php exportTabsColumns('tickets'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_tickets'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
