<?php

/*
 * An audit log entry's quick look - all of it: when, who (and their email),
 * the client, what kind of record and what was done, the whole description,
 * the record while it still exists, the IP address and device with the
 * browser's own user agent. Then the record's other entries - its history -
 * and what the same person did in the quarter hour either side. Opened from
 * Admin > Audit Logs.
 */

require_once '../../includes/modal_header.php';

$log_id = intval($_GET['id'] ?? 0);
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT logs.*, user_name, user_email, user_avatar, user_type, client_name
    FROM logs " . auditLogJoinsSql() . " WHERE log_id = $log_id"));
if (!$row) {
    echo json_encode(['error' => 'That audit log entry no longer exists.']);
    exit;
}

$type = (string) $row['log_type'];
$type_sql = escapeSql($type);
$entity_id = intval($row['log_entity_id']);
$user_id = intval($row['log_user_id']);
$kinds = auditLogKinds();
$who = auditLogWho($row);
$style = auditLogActionStyle($row['log_action']);
$records = auditLogRecords([$row]);
$link = auditLogRecordLink($row, $records);
$device = auditLogDevice($row['log_user_agent']);
$created = strtotime($row['log_created_at']);
$joins = auditLogJoinsSql();

// The record's other entries - its history here (an entry about no one record - a sign-in, a setting - has none)
$is_record = $entity_id > 0 && isset($kinds[$type]['table']);
$history = [];
$history_count = 0;
if ($is_record) {
    $history_where = "log_entity_id = $entity_id AND log_type = '$type_sql' AND log_id <> $log_id";
    $history_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM logs WHERE $history_where"))[0]);
    $sql = mysqli_query($mysqli, "SELECT log_id, log_type, log_action, log_description, log_created_at, log_user_id, user_name, user_avatar, user_type
        FROM logs $joins WHERE $history_where ORDER BY log_created_at DESC, log_id DESC LIMIT 8");
    while ($history_row = mysqli_fetch_assoc($sql)) {
        $history[] = $history_row;
    }
}

// What the same person did around then - a quarter hour either side
$around = [];
$around_count = 0;
if ($user_id > 0) {
    $around_where = "log_user_id = $user_id AND log_id <> $log_id
        AND log_created_at >= '" . escapeSql($row['log_created_at']) . "' - INTERVAL 15 MINUTE AND log_created_at <= '" . escapeSql($row['log_created_at']) . "' + INTERVAL 15 MINUTE";
    $around_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM logs WHERE $around_where"))[0]);
    $sql = mysqli_query($mysqli, "SELECT log_id, log_type, log_action, log_description, log_created_at, log_entity_id, log_user_id, user_name, user_avatar, user_type
        FROM logs $joins WHERE $around_where ORDER BY log_created_at ASC, log_id ASC LIMIT 10");
    while ($around_row = mysqli_fetch_assoc($sql)) {
        $around[] = $around_row;
    }
}

// One line of a list of entries - when, the action and what it says; each opens its own quick look
$entry_line = function ($entry, $show_when_as = 'date') {
    $time = strtotime($entry['log_created_at']);
    $when = $show_when_as === 'time' ? date('g:i A', $time) : date('M j, Y g:i A', $time);
    return '<a href="#" class="list-group-item list-group-item-action d-flex align-items-start gap-2 small ajax-modal" data-modal-size="lg" data-modal-url="/admin/modals/audit_log/audit_log.php?id=' . intval($entry['log_id']) . '">'
        . '<span class="text-body-secondary text-nowrap flex-shrink-0" style="min-width: 4.5rem;" title="' . escapeHtml($entry['log_created_at']) . '">' . escapeHtml($when) . '</span>'
        . '<span class="flex-shrink-0">' . auditLogActionPill($entry['log_action']) . '</span>'
        . '<span class="text-break">' . escapeHtml($entry['log_description']) . '</span>'
        . '</a>';
};

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
        <span class="itflow-asset-icon itflow-asset-icon-lg bg-<?= $style['tone'] ?>-subtle text-<?= $style['tone'] ?>-emphasis" aria-hidden="true"><i class="fas <?= auditLogTypeIcon($type) ?>"></i></span>
        <div class="min-w-0 flex-grow-1">
            <h5 class="modal-title fw-semibold text-break"><?= escapeHtml($type) ?> &middot; <?= escapeHtml($row['log_action']) ?></h5>
            <div class="small text-body-secondary mt-1"><?= escapeHtml(date('D, M j, Y \a\t g:i:s A', $created)) ?> &middot; <?= escapeHtml(timeAgo($row['log_created_at'])) ?></div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <div class="p-3 rounded border bg-body-tertiary mb-3">
        <div class="text-break" style="white-space: pre-line;" data-audit-full-description><?= escapeHtml($row['log_description']) ?></div>
        <?php if ($link !== '') { ?>
            <div class="small mt-2"><i class="fas fa-fw fa-external-link-alt text-secondary me-1"></i><?= $link ?></div>
        <?php } elseif ($is_record) { ?>
            <div class="small mt-2 text-body-secondary"><i class="fas fa-fw fa-ghost me-1"></i>The <?= escapeHtml(strtolower($type)) ?> this is about has since been deleted</div>
        <?php } ?>
    </div>

    <div class="row g-2 itflow-asset-glance">
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Who">
                <i class="fas fa-fw fa-user"></i>
                <div class="min-w-0">
                    <?= $who['html'] ?>
                    <?php if ($user_id && $row['user_email'] !== null) { ?>
                        <div class="small text-body-secondary text-truncate"><?= escapeHtml($row['user_email']) ?><?= $who['kind'] === 'portal' ? ' - a client contact, in the portal' : '' ?></div>
                    <?php } elseif (!$user_id) { ?>
                        <div class="small text-body-secondary">Not a person - cron, the API or the email parser</div>
                    <?php } ?>
                </div>
            </div>
            <div class="itflow-glance-item" title="Client">
                <i class="fas fa-fw fa-building"></i>
                <?php if ($row['client_name'] !== null) { ?>
                    <a class="text-truncate" href="/agent/client_overview.php?client_id=<?= intval($row['log_client_id']) ?>"><?= escapeHtml($row['client_name']) ?></a>
                <?php } elseif (intval($row['log_client_id'])) { ?>
                    <span class="text-body-secondary">A client since deleted</span>
                <?php } else { ?>
                    <span class="text-body-secondary">Not for a client</span>
                <?php } ?>
            </div>
            <div class="itflow-glance-item" title="Entry">
                <i class="fas fa-fw fa-hashtag"></i>
                <span class="text-body-secondary">Entry <?= $log_id ?><?= $is_record ? ' &middot; ' . escapeHtml($type) . ' #' . $entity_id : '' ?></span>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="IP address">
                <i class="fas fa-fw fa-network-wired"></i>
                <?php if (trim((string) $row['log_ip']) !== '') { ?>
                    <span><span class="font-monospace"><?= escapeHtml($row['log_ip']) ?></span><?= isset(signInNewIpIds([$row])[$log_id]) ? ' ' . signInNewIpBadge() : '' ?> <a class="small ms-1" href="/admin/audit_logs.php?q=<?= urlencode($row['log_ip']) ?>">Everything from it</a></span>
                <?php } else { ?>
                    <span class="text-body-secondary">Not recorded</span>
                <?php } ?>
            </div>
            <div class="itflow-glance-item" title="Device">
                <i class="fas fa-fw fa-laptop"></i>
                <span><?= $device !== '' ? escapeHtml($device) : '<span class="text-body-secondary">Not recorded</span>' ?></span>
            </div>
        </div>
    </div>
    <?php if (trim((string) $row['log_user_agent']) !== '') { ?>
        <div class="small text-body-secondary font-monospace text-break mt-1 ps-4 ms-2" title="The browser's own user agent"><?= escapeHtml($row['log_user_agent']) ?></div>
    <?php } ?>

    <?php if ($is_record) { ?>
        <div class="mt-4" data-audit-history>
            <div class="d-flex align-items-center mb-2">
                <div class="small fw-semibold text-uppercase text-body-secondary">This <?= escapeHtml(strtolower($type)) ?>'s other entries</div>
                <?php if ($history_count > 0) { ?>
                    <a class="small ms-auto" href="/admin/audit_logs.php?type=<?= urlencode($type) ?>&amp;entity=<?= $entity_id ?>">All <?= $history_count + 1 ?> entries for it</a>
                <?php } ?>
            </div>
            <?php if ($history) { ?>
                <div class="list-group">
                    <?php foreach ($history as $entry) { echo $entry_line($entry); } ?>
                </div>
                <?php if ($history_count > count($history)) { ?>
                    <div class="small text-body-secondary mt-1">and <?= $history_count - count($history) ?> more</div>
                <?php } ?>
            <?php } else { ?>
                <div class="small text-body-secondary">None - this is the only one.</div>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if ($user_id > 0) { ?>
        <div class="mt-4" data-audit-around>
            <div class="d-flex align-items-center mb-2">
                <div class="small fw-semibold text-uppercase text-body-secondary"><?= in_array($type, SIGN_IN_TYPES, true) && in_array($row['log_action'], SIGN_IN_FAILED, true) ? escapeHtml($who['name']) . "'s account around then" : 'What ' . escapeHtml($who['name']) . ' did around then' ?></div>
                <?php if ($around_count > 0) { ?>
                    <a class="small ms-auto" href="/admin/audit_logs.php?user=<?= $user_id ?>&amp;canned_date=custom&amp;dtf=<?= date('Y-m-d', $created) ?>&amp;dtt=<?= date('Y-m-d', $created) ?>">All of that day</a>
                <?php } ?>
            </div>
            <?php if ($around) { ?>
                <div class="list-group">
                    <?php foreach ($around as $entry) { echo $entry_line($entry, 'time'); } ?>
                </div>
                <?php if ($around_count > count($around)) { ?>
                    <div class="small text-body-secondary mt-1">and <?= $around_count - count($around) ?> more</div>
                <?php } ?>
            <?php } else { ?>
                <div class="small text-body-secondary">Nothing else in the quarter hour either side.</div>
            <?php } ?>
        </div>
    <?php } ?>

</div>

<div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
