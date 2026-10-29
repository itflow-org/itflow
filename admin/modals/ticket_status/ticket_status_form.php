<?php

/*
 * A ticket status's fields - for ticket_status_add.php and
 * ticket_status_edit.php, which set $ticket_status (a ticketStatusRow(), or
 * null when new). A built-in status keeps its name and has fixed answers for
 * the SLA clock and a client's reply - shown, not asked. The preview of its
 * badge and the colour swatches are js/ticket_status_form.js. The server
 * checks it all again (ticketStatusFromInput(), ticketStatusProblem()).
 */

isset($mysqli) || die("Direct file access is not allowed");

$s = $ticket_status ?? null;
$s_id = $s ? intval($s['ticket_status_id']) : 0;
$s_builtin = ticketStatusBuiltIns()[$s_id] ?? null;
$s_name = (string) ($s['ticket_status_name'] ?? '');
$s_color = $s ? ticketStatusColor($s['ticket_status_color']) : ticketStatusNextColor();
$s_description = (string) ($s['ticket_status_description'] ?? '');
$s_paused = $s ? intval($s['ticket_status_pauses_sla']) === 1 : false;
$s_reply = $s ? (intval($s['ticket_status_client_reply'] ?? 1) === 0 ? 0 : 1) : 1;

?>

<div data-ticket-status-form>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 p-2 border rounded bg-body-tertiary">
        <span class="small text-secondary">Looks like</span>
        <span data-ticket-status-preview><?= ticketStatusBadgeHtml($s_name !== '' ? $s_name : 'New status', $s_color, 'list') ?></span>
    </div>

    <div class="mb-3">
        <label class="form-label">Name <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
            <input type="text" class="form-control" name="name" maxlength="200" placeholder="Waiting on Vendor, Scheduled, Parts Ordered" value="<?= escapeHtml($s_name) ?>" required<?= $s_builtin ? ' readonly' : ($s ? '' : ' autofocus') ?> data-ticket-status-name>
        </div>
        <?php if ($s_builtin) { ?>
            <small class="text-secondary"><i class="fas fa-fw fa-lock me-1"></i>ITFlow's own statuses keep their names. <?= escapeHtml($s_builtin['set_by']) ?></small>
        <?php } ?>
    </div>

    <div class="mb-3">
        <label class="form-label">Description</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
            <input type="text" class="form-control" name="description" maxlength="255" placeholder="<?= escapeHtml($s_builtin ? $s_builtin['about'] : 'What it means, and when to use it') ?>" value="<?= escapeHtml($s_description) ?>">
        </div>
        <small class="text-secondary">Shown when the status is hovered - in the pickers, on the board and on tickets.</small>
    </div>

    <div class="mb-3">
        <label class="form-label">Color</label>
        <input type="hidden" name="color" value="<?= $s_color ?>" data-ticket-status-color>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="d-flex flex-wrap gap-1" role="group" aria-label="Colors">
                <?php foreach (CATEGORY_COLORS as $swatch) { ?>
                    <button type="button" class="itflow-tag-swatch<?= $swatch === $s_color ? ' active' : '' ?>" style="--itflow-tag-color: <?= $swatch ?>; --itflow-tag-text: <?= tagTextColor($swatch) ?>" data-ticket-status-swatch="<?= $swatch ?>" title="<?= $swatch ?>" aria-label="Color <?= $swatch ?>" aria-pressed="<?= $swatch === $s_color ? 'true' : 'false' ?>"><i class="fas fa-check" aria-hidden="true"></i></button>
                <?php } ?>
            </div>
            <div class="input-group input-group-sm w-auto" title="Any other color">
                <span class="input-group-text"><i class="fa fa-fw fa-paint-brush"></i></span>
                <input type="color" class="form-control form-control-color" value="<?= $s_color ?>" aria-label="Custom color" data-ticket-status-color-pick>
                <input type="text" class="form-control font-monospace" style="width: 6rem" maxlength="7" value="<?= $s_color ?>" pattern="#?[0-9a-fA-F]{6}" aria-label="Color as hex" data-ticket-status-color-hex>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">SLA resolution clock</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-stopwatch"></i></span>
            <?php if ($s_builtin) { ?>
                <input type="text" class="form-control" value="<?= $s_id === TICKET_STATUS_CLOSED ? 'Stopped - the ticket is finished' : ($s_paused ? 'Paused while a ticket is in it' : 'Keeps running') ?>" readonly>
            <?php } else { ?>
                <select class="form-select" name="pauses_sla">
                    <option value="0"<?= $s_paused ? '' : ' selected' ?>>Keeps running - the ticket is being worked</option>
                    <option value="1"<?= $s_paused ? ' selected' : '' ?>>Paused - waiting on someone else</option>
                </select>
            <?php } ?>
        </div>
        <small class="text-secondary">
            <?php if ($s_builtin) { ?><i class="fas fa-fw fa-lock me-1"></i>Fixed for ITFlow's own statuses. <?php } ?>
            A ticket in a paused status never warns or breaches on resolution - the time already spent is kept, and the deadline moves out when it comes back. The response clock always runs.
        </small>
    </div>

    <div class="mb-1">
        <label class="form-label">When the client replies</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-reply"></i></span>
            <?php if ($s_builtin) { ?>
                <input type="text" class="form-control" value="<?= [TICKET_STATUS_OPEN => 'It stays Open', TICKET_STATUS_RESOLVED => 'It is reopened - back to Open', TICKET_STATUS_CLOSED => 'They can\'t - they are told to raise a new ticket'][$s_id] ?? 'It moves back to Open' ?>" readonly>
            <?php } else { ?>
                <select class="form-select" name="client_reply">
                    <?php foreach (TICKET_STATUS_CLIENT_REPLY as $value => $reply) { ?>
                        <option value="<?= $value ?>"<?= $value === $s_reply ? ' selected' : '' ?>><?= escapeHtml($reply['label']) ?></option>
                    <?php } ?>
                </select>
            <?php } ?>
        </div>
        <small class="text-secondary">
            <?php if ($s_builtin) { ?>
                <i class="fas fa-fw fa-lock me-1"></i>Fixed for ITFlow's own statuses.
            <?php } else { ?>
                By email or in the portal. Back to Open suits a status that waits on the client; leave it for one like Scheduled, where a "thanks, see you then" shouldn't put it back in the queue. Whoever it is assigned to is told either way.
            <?php } ?>
        </small>
    </div>
</div>

<script src="/js/ticket_status_form.js"></script>
