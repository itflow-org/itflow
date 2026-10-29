<?php

/*
 * Export the trips - pre-filled from the trips list's filters, and editable:
 * the export runs whatever this form posts. Agents without Financial access
 * export the trips they drove or logged.
 */

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

$q_filter = $_GET['q'] ?? '';
$driver_filter = intval($_GET['driver'] ?? 0);
$client_filter = intval($_GET['client'] ?? 0);
$location_filter = intval($_GET['location_id'] ?? 0);
$ticket_filter = intval($_GET['ticket_id'] ?? 0);
$shows = tripShows();
$show_filter = isset($shows[$_GET['show'] ?? '']) ? (string) ($_GET['show'] ?? '') : '';
$see_all = tripCanSeeAll();

// The all-time sentinels from filter_header.php leave the dates blank
$date_from_filter = (!empty($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') ? escapeHtml($_GET['dtf']) : '';
$date_to_filter = (!empty($_GET['dtt']) && $_GET['dtt'] !== '2099-12-31') ? escapeHtml($_GET['dtt']) : '';

$location_name = $location_filter ? getFieldById('locations', $location_filter, 'location_name') : null;
$ticket_row = $ticket_filter ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject FROM tickets WHERE ticket_id = $ticket_filter")) : null;

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download me-2"></i>Export Trips</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<?php exportTabsNav(); ?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">

        <?php exportTabsFiltersOpen(); ?>

        <?php if (!$see_all) { ?>
            <div class="small text-body-secondary mb-3"><i class="fas fa-user me-1"></i>The trips you drove or logged</div>
        <?php } ?>
        <?php if ($location_name !== null) { ?>
            <input type="hidden" name="location_id" value="<?= $location_filter ?>">
            <div class="small text-body-secondary mb-3"><i class="fas fa-map-marker-alt me-1"></i>Only the trips to <?= escapeHtml($location_name) ?></div>
        <?php } ?>
        <?php if ($ticket_row) { ?>
            <input type="hidden" name="ticket_id" value="<?= $ticket_filter ?>">
            <div class="small text-body-secondary mb-3"><i class="fas fa-life-ring me-1"></i>Only the trips for <?= escapeHtml(ticketReference($ticket_row) . ' - ' . $ticket_row['ticket_subject']) ?></div>
        <?php } ?>

        <div class="mb-3">
            <label class="form-label">Search</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-search"></i></span>
                <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="Purpose, from, to, client, site, driver, ticket">
            </div>
        </div>

        <div class="row g-3 mb-3">
            <?php if ($see_all) { ?>
                <div class="col-md-6">
                    <label class="form-label">Driver</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        <select class="form-select select2" name="driver">
                            <option value="">- Anyone -</option>
                            <?php
                            $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE EXISTS (SELECT 1 FROM trips WHERE trip_user_id = user_id) ORDER BY user_name ASC");
                            while ($row = mysqli_fetch_assoc($sql)) {
                                $option_id = intval($row['user_id']); ?>
                                <option <?= $driver_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionUser($option_id, $row['user_name'], $row['user_avatar'] ?? '') ?>><?= escapeHtml($row['user_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            <?php } ?>
            <?php if (!$client_id) { ?>
                <div class="col-md-6">
                    <label class="form-label">Client</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                        <select class="form-select select2" name="client">
                            <option value="">- Any client -</option>
                            <?php
                            $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE EXISTS (SELECT 1 FROM trips WHERE trip_client_id = client_id) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_assoc($sql)) {
                                $option_id = intval($row['client_id']); ?>
                                <option <?= $client_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            <?php } ?>
            <div class="col-md-6">
                <label class="form-label">Showing</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-filter"></i></span>
                    <select class="form-select select2" name="show">
                        <?php foreach ($shows as $key => $s) { ?>
                            <option <?= $show_filter === $key ? 'selected' : '' ?> value="<?= escapeHtml($key) ?>"><?= escapeHtml($key === '' ? 'All trips' : $s['label']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Dated from</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    <input type="date" class="form-control" name="dtf" value="<?= $date_from_filter ?>" max="2999-12-31">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Dated to</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    <input type="date" class="form-control" name="dtt" value="<?= $date_to_filter ?>" max="2999-12-31">
                </div>
            </div>
        </div>

        <?php exportTabsColumns('trips'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_trips'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
