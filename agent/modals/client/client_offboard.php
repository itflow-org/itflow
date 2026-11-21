<?php

/*
 * Offboard a client - the steps that are otherwise on a page each, in one window: portal sign-ins
 * off (and invites waiting cancelled), shared links ended, recurring invoices stopped, recurring
 * tickets paused, and the client archived. Each is ticked when there is something for it to do,
 * and says how much. Export first, from the link at the top - it opens Export Data.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client', 2);

$client_id = intval($_GET['client_id'] ?? 0);
enforceClientAccess();

$client = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name, client_archived_at FROM clients WHERE client_id = $client_id"));
if (!$client) {
    echo json_encode(['error' => 'That client no longer exists.']);
    exit;
}

$counts = clientOffboardCounts($client_id);
$can_billing = lookupUserPermission('module_sales') >= 2;
$can_support = lookupUserPermission('module_support') >= 2;

$plural = function ($n, $one, $many) {
    return $n . ' ' . ($n === 1 ? $one : $many);
};
$steps = [
    ['portal_off', 'fa-door-closed', 'Turn off client portal sign-in',
        $counts['portal'] || $counts['invites'] ? $plural($counts['portal'], 'contact signs in', 'contacts sign in') . ($counts['invites'] ? ', ' . $plural($counts['invites'], 'invite is', 'invites are') . ' waiting' : '') : 'Nobody signs in', $counts['portal'] + $counts['invites'], true],
    ['end_shares', 'fa-link', 'End shared links', $counts['shares'] ? $plural($counts['shares'], 'link still works', 'links still work') : 'No links still work', $counts['shares'], true],
    ['stop_recurring_invoices', 'fa-redo-alt', 'Stop recurring invoices', $counts['recurring_invoices'] ? $plural($counts['recurring_invoices'], 'is', 'are') . ' running' : 'None running', $counts['recurring_invoices'], $can_billing],
    ['pause_recurring_tickets', 'fa-calendar-times', 'Pause recurring tickets', $counts['recurring_tickets'] ? $plural($counts['recurring_tickets'], 'is', 'are') . ' running' : 'None running', $counts['recurring_tickets'], $can_support],
];

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-sign-out-alt me-2"></i>Offboard <strong><?= escapeHtml($client['client_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <div class="modal-body">

        <div class="alert alert-info d-flex align-items-center gap-2 py-2">
            <i class="fas fa-download"></i>
            <span class="me-auto">Keep a copy of everything first?</span>
            <a class="btn btn-sm btn-outline-primary ajax-modal" href="#" data-modal-url="modals/client/client_export_data.php?client_id=<?= $client_id ?>" data-modal-size="lg">Export Data</a>
        </div>

        <p class="text-body-secondary small">Ticked steps run together when you click <strong>Offboard</strong>. Nothing is deleted, and all but ended links can be turned back on from where they live.</p>

        <div class="list-group mb-2">
            <?php foreach ($steps as [$name, $icon, $label, $detail, $count, $allowed]) { ?>
                <label class="list-group-item d-flex align-items-center gap-3<?= $allowed ? '' : ' text-body-secondary' ?>">
                    <input class="form-check-input flex-shrink-0 m-0" type="checkbox" name="<?= $name ?>" value="1" <?= $count && $allowed ? 'checked' : '' ?> <?= $count && $allowed ? '' : 'disabled' ?>>
                    <i class="fas fa-fw <?= $icon ?> text-body-secondary"></i>
                    <span class="flex-grow-1">
                        <span class="d-block"><?= $label ?></span>
                        <span class="d-block small text-body-secondary"><?= $allowed ? escapeHtml($detail) : 'Your role can\'t change these' ?></span>
                    </span>
                </label>
            <?php } ?>
            <label class="list-group-item d-flex align-items-center gap-3">
                <input class="form-check-input flex-shrink-0 m-0" type="checkbox" name="archive" value="1" <?= $client['client_archived_at'] ? 'disabled' : 'checked' ?>>
                <i class="fas fa-fw fa-archive text-body-secondary"></i>
                <span class="flex-grow-1">
                    <span class="d-block">Archive the client</span>
                    <span class="d-block small text-body-secondary"><?= $client['client_archived_at'] ? 'Already archived' : 'Off the lists and the client switcher, and contract signing requests withdrawn - Restore brings it back' ?></span>
                </span>
            </label>
        </div>

        <?php if ($counts['open_tickets']) { ?>
            <div class="small text-body-secondary"><i class="fas fa-exclamation-triangle text-warning me-1"></i><?= $plural($counts['open_tickets'], 'ticket is', 'tickets are') ?> still open - <a href="tickets.php?client_id=<?= $client_id ?>">resolve or close them</a> as you finish the work.</div>
        <?php } ?>

    </div>
    <div class="modal-footer">
        <button type="submit" name="offboard_client" class="btn btn-danger text-bold"><i class="fas fa-sign-out-alt me-2"></i>Offboard</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
