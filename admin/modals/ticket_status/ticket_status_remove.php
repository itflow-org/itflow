<?php

/*
 * Switch off or delete one of your own ticket statuses (?action=off or
 * delete) - saying first how many tickets are in it, and where they go:
 * another status, picked here. Switching off can leave them where they are
 * (they stay in it, but it can't be picked again); deleting can't. Canned
 * responses that set it are said too - after a delete they leave the status
 * as it is. The work is ticketStatusSetActive() and ticketStatusDelete().
 */

require_once '../../includes/modal_header.php';

$ticket_status = ticketStatusRow(intval($_GET['id'] ?? 0));
$delete = ($_GET['action'] ?? '') === 'delete';

ob_start();

if (!$ticket_status || ticketStatusIsBuiltIn($ticket_status['ticket_status_id'])) { ?>
    <div class="modal-body text-center text-secondary py-5"><?= $ticket_status ? 'ITFlow\'s own statuses can\'t be switched off or deleted.' : 'That status is gone - it may have been deleted.' ?></div>
<?php } else {
    $ticket_status_id = intval($ticket_status['ticket_status_id']);
    $name = escapeHtml($ticket_status['ticket_status_name']);
    $counts = ticketStatusTicketCounts()[$ticket_status_id] ?? ['all' => 0, 'open' => 0, 'shown' => 0];
    $canned = ticketStatusCannedCount($ticket_status_id);
    $targets = ticketStatusMoveTargets($ticket_status_id);
    $tickets = $counts['all'];
    $plural = function ($n, $one, $many) {
        return number_format($n) . ' ' . ($n === 1 ? $one : $many);
    }; ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-<?= $delete ? 'trash' : 'power-off' ?> me-2"></i><?= $delete ? 'Delete' : 'Switch off' ?> <strong><?= $name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_status_id" value="<?= $ticket_status_id ?>">

    <div class="modal-body">
        <p class="mb-3">
            <?= ticketStatusBadgeHtml($ticket_status['ticket_status_name'], $ticket_status['ticket_status_color'], 'detail') ?>
            <?php if ($delete) { ?>
                goes for good. Tickets' history keeps its name.
            <?php } else { ?>
                stays here, switched off - it can't be picked, and comes off the board. You can switch it on again.
            <?php } ?>
        </p>

        <?php if ($tickets) { ?>
            <div class="alert alert-<?= $delete ? 'warning' : 'secondary' ?> small py-2">
                <i class="fas fa-fw fa-life-ring me-1"></i><strong><?= $plural($tickets, 'ticket is', 'tickets are') ?></strong> in it<?= $counts['open'] !== $tickets ? ' - ' . $plural($counts['open'], 'open one', 'open ones') : '' ?>.
                <a href="/agent/tickets.php?status[]=<?= $ticket_status_id ?>" target="_blank">See them</a>
            </div>
            <div class="mb-3">
                <label class="form-label">Move <?= $tickets === 1 ? 'it' : 'them' ?> to <?php if ($delete) { ?><strong class="text-danger">*</strong><?php } ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-exchange-alt"></i></span>
                    <select class="form-select" name="move_to"<?= $delete ? ' required' : '' ?>>
                        <?php if (!$delete) { ?>
                            <option value="0">Leave them in <?= $name ?></option>
                        <?php } ?>
                        <?php foreach ($targets as $target_id => $target) { ?>
                            <option value="<?= $target_id ?>"<?= $target_id === TICKET_STATUS_OPEN && $delete ? ' selected' : '' ?> title="<?= escapeHtml(ticketStatusAbout($target)) ?>"><?= escapeHtml($target['ticket_status_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <small class="text-secondary">Each says so in its history, and its SLA clock follows the status it goes to.</small>
            </div>
        <?php } else { ?>
            <p class="small text-secondary"><i class="fas fa-fw fa-check me-1"></i>No tickets are in it.</p>
        <?php } ?>

        <?php if ($canned) { ?>
            <p class="small text-secondary mb-0">
                <i class="fas fa-fw fa-comment-dots me-1"></i><?= $plural($canned, 'canned response sets', 'canned responses set') ?> this status.
                <?= $delete ? 'They will leave the status as it is instead.' : 'Until it is switched on again, they leave the status as it is.' ?>
            </p>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if ($delete) { ?>
            <button type="submit" name="delete_ticket_status" class="btn btn-danger text-bold"><i class="fas fa-trash me-2"></i>Delete</button>
        <?php } else { ?>
            <button type="submit" name="switch_off_ticket_status" class="btn btn-primary text-bold"><i class="fas fa-power-off me-2"></i>Switch off</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
