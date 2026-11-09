<?php

/*
 * Maintenance > Text Messages - every text ITFlow sent and received (functions/sms.php), and
 * what came of it: waiting, sent or failed going out; on a ticket, in Texts (unmatched, a
 * code, spam) or a keyword coming in. Verification codes are masked here - Texts > Codes
 * reveals them, logged. A text can be retried, cancelled or deleted, one at a time or several
 * (admin/post/text_messages.php).
 */

// Default Column Sortby Filter
$sort = "sms_message_created_at";
$order = "DESC";

require_once "includes/inc_all_admin.php";

$page_title = 'Text Messages';

try {
    mysqli_query($mysqli, "SELECT 1 FROM sms_messages LIMIT 1");
} catch (Throwable $e) { ?>
    <div class="alert alert-warning"><i class="fas fa-fw fa-exclamation-triangle me-2"></i>Text messaging arrives with a database update - run it in <a href="update.php">Maintenance &gt; Update</a> first.</div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

$tabs = smsLogTabs();
$show = (string) ($_GET['show'] ?? '');
$show = isset($tabs[$show]) ? $show : '';

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'sms_message_created_at' => "sms_message_created_at $order",
    'sms_message_number' => "sms_message_number $order, sms_message_created_at DESC",
    'sms_message_status' => "sms_message_status $order, sms_message_created_at DESC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'sms_message_created_at';
}

$search = "(sms_message_number LIKE '%$phone_query%' OR (sms_message_status <> 'code' AND sms_message_body LIKE '%$q%') OR sms_message_error LIKE '%$q%' OR contact_name LIKE '%$q%' OR client_name LIKE '%$q%'
        OR CONCAT(ticket_prefix, ticket_number) LIKE '%$q%')
    AND DATE(sms_message_created_at) BETWEEN '$dtf' AND '$dtt'";
$joins = "LEFT JOIN contacts ON contact_id = sms_message_contact_id AND sms_message_contact_id > 0
    LEFT JOIN clients ON client_id = sms_message_client_id AND sms_message_client_id > 0
    LEFT JOIN tickets ON ticket_id = sms_message_ticket_id AND sms_message_ticket_id > 0
    LEFT JOIN users ON user_id = sms_message_user_id AND sms_message_user_id > 0";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS sms_messages.*, contact_name, client_name, ticket_prefix, ticket_number, ticket_client_id, user_name
    FROM sms_messages $joins
    WHERE $search AND {$tabs[$show][2]}
    ORDER BY {$sortable_columns[$sort]}, sms_message_id DESC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// How many in each tab, as searched
$count_sql = [];
foreach ($tabs as $key => [, , $where]) {
    $count_sql[] = "COALESCE(SUM($where), 0)";
}
$counts = array_combine(array_keys($tabs), array_map('intval', mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM sms_messages $joins WHERE $search"))));

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$pill_url = function ($show) {
    return '?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => $show, 'canned_date' => $_GET['canned_date'] ?? '', 'dtf' => $_GET['dtf'] ?? '', 'dtt' => $_GET['dtt'] ?? '']));
};
$csrf = $_SESSION['csrf_token'];
$filtered_dates = isset($_GET['dtf']) && $_GET['dtf'] !== '' && $_GET['dtf'] !== '1970-01-01';
$ready = smsReady();

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-sms me-2"></i>Text Messages</h3>
        <div class="card-tools">
            <a class="btn btn-outline-light" href="text_messaging.php"><i class="fas fa-fw fa-cog"></i><span class="d-none d-lg-inline ms-2">Text Messaging</span></a>
        </div>
    </div>

    <?php if (!$ready) { ?>
        <div class="alert alert-warning rounded-0 border-0 border-bottom mb-0 d-flex flex-wrap align-items-center gap-2" data-sms-health="off">
            <i class="fas fa-fw fa-exclamation-triangle"></i>
            <div class="me-auto"><strong>Texting isn't on</strong> - nothing goes out, and texts to your number aren't taken in.</div>
            <a class="btn btn-sm btn-warning" href="text_messaging.php">Text Messaging</a>
        </div>
    <?php } ?>

    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show !== '') { ?><input type="hidden" name="show" value="<?= escapeHtml($show) ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search text messages..." title="The number, the words, the contact or client, the ticket, or why it failed">
                        <button class="btn btn-secondary" title="When" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilter"><i class="fas fa-calendar-alt"></i></button>
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-7 col-md-6">
                    <div class="d-flex justify-content-end gap-2">
                        <div class="dropdown" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_retry_texts" title="Failed or cancelled ones - at the next run">
                                    <i class="fas fa-fw fa-redo-alt text-secondary me-2"></i>Retry
                                </button>
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_cancel_texts" title="Ones still waiting to go">
                                    <i class="fas fa-fw fa-ban text-secondary me-2"></i>Cancel
                                </button>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_delete_texts"
                                    data-confirm-title="Delete these texts?" data-confirm-text="Any waiting to go never will. Replies on tickets stay on their tickets." data-confirm-button="Delete">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="collapse mt-3 <?= $filtered_dates ? 'show' : '' ?>" id="advancedFilter">
                <div class="row g-3">
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label" for="dateFilter">When</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-calendar-alt"></i></span>
                            <input type="text" id="dateFilter" class="form-control" autocomplete="off">
                        </div>
                        <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date'] ?? '') ?>">
                        <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                        <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs flex-nowrap overflow-auto mt-2" aria-label="Which texts">
            <?php foreach ($tabs as $key => [$label, $icon]) {
                $alert = $show !== $key && $counts[$key] && $key === 'failed' ? 'text-bg-danger' : ''; ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($pill_url($key)) ?>" data-sms-log-tab="<?= $key === '' ? 'all' : $key ?>">
                    <i class="fas fa-fw <?= $icon ?> me-1"></i><?= $label ?> <span class="badge rounded-pill ms-1 <?= $alert ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th class="checkbox-column border-end">
                        <div class="form-check">
                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" title="Select all on this page">
                        </div>
                    </th>
                    <th class="d-none d-sm-table-cell"><?= $sort_link('sms_message_status', 'Status') ?></th>
                    <th><?= $sort_link('sms_message_number', 'With') ?></th>
                    <th class="d-none d-md-table-cell">Text</th>
                    <th class="d-none d-lg-table-cell"><?= $sort_link('sms_message_created_at', 'When') ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php while ($row = mysqli_fetch_assoc($sql)) {
                    $message_id = intval($row['sms_message_id']);
                    $state = smsLogState($row);
                    $out = $row['sms_message_direction'] === 'out';
                    $number = (string) $row['sms_message_number'];
                    $label = smsNumberLabel($number);
                    $who = trim((string) $row['contact_name']) !== '' ? $row['contact_name'] : $label;
                    $body = smsLogBody($row);
                    $media = $row['sms_message_media'] ? (json_decode($row['sms_message_media'], true) ?: []) : [];
                    ?>
                    <tr data-sms-message-id="<?= $message_id ?>" data-sms-state="<?= escapeHtml($state['key']) ?>">
                        <td class="checkbox-column bg-light border-end">
                            <?php if (smsLogCan($row, 'delete')) { ?>
                                <div class="form-check">
                                    <input class="form-check-input bulk-select" type="checkbox" name="sms_message_ids[]" value="<?= $message_id ?>" aria-label="Select text <?= $message_id ?>">
                                </div>
                            <?php } ?>
                        </td>
                        <td class="text-nowrap d-none d-sm-table-cell">
                            <span class="badge <?= $state['badge'] ?>" data-sms-status><i class="fas fa-fw <?= $state['icon'] ?> me-1"></i><?= $state['label'] ?></span>
                            <?php if ($state['error'] !== '') { ?>
                                <div class="small text-wrap <?= in_array($state['key'], ['failed', 'blocked'], true) ? 'text-danger-emphasis' : 'text-body-secondary' ?>" style="max-width: 18rem;" title="<?= escapeHtml($state['error']) ?>" data-sms-error><?= escapeHtml(mb_strimwidth($state['error'], 0, 160, '...')) ?></div>
                            <?php } ?>
                        </td>
                        <td class="min-w-0">
                            <div class="text-nowrap">
                                <i class="fas fa-fw <?= $out ? 'fa-arrow-right text-primary' : 'fa-arrow-left text-success' ?> me-1" title="<?= $out ? 'Sent' : 'Received' ?>"></i><?= escapeHtml(smsNumberText($number)) ?>
                            </div>
                            <?php if ($who !== '' || $row['client_name']) { ?>
                                <div class="small text-body-secondary text-break"><?= escapeHtml(trim($who . ($row['client_name'] ? ' - ' . $row['client_name'] : ''), ' -')) ?></div>
                            <?php } ?>
                            <?php if ($row['ticket_number']) { ?>
                                <div class="small"><a href="/agent/ticket.php?<?= intval($row['ticket_client_id']) ? 'client_id=' . intval($row['ticket_client_id']) . '&amp;' : '' ?>ticket_id=<?= intval($row['sms_message_ticket_id']) ?>"><i class="fas fa-fw fa-life-ring me-1"></i><?= escapeHtml($row['ticket_prefix'] . $row['ticket_number']) ?></a></div>
                            <?php } ?>
                            <!-- On smaller screens the text comes along under who it is with -->
                            <div class="d-md-none small text-break mt-1"><?= nl2br(escapeHtml(mb_strimwidth($body, 0, 200, '...'))) ?></div>
                            <div class="d-sm-none small mt-1"><span class="badge <?= $state['badge'] ?>"><?= $state['label'] ?></span></div>
                        </td>
                        <td class="d-none d-md-table-cell min-w-0" style="max-width: 32rem;">
                            <div class="text-break small"><?= nl2br(escapeHtml(mb_strimwidth($body, 0, 400, '...'))) ?></div>
                            <?php if ($media) { ?>
                                <div class="small text-body-secondary mt-1"><i class="fas fa-fw fa-paperclip me-1"></i><?= count($media) ?> photo<?= count($media) === 1 ? '' : 's' ?> or file<?= count($media) === 1 ? '' : 's' ?></div>
                            <?php } ?>
                            <?php if ($out && $row['user_name']) { ?>
                                <div class="small text-body-secondary">By <?= escapeHtml($row['user_name']) ?></div>
                            <?php } elseif ($out && $row['sms_message_purpose'] !== '' && $row['sms_message_purpose'] !== 'reply') { ?>
                                <div class="small text-body-secondary"><?= escapeHtml(smsPurposeText((string) $row['sms_message_purpose'])) ?></div>
                            <?php } ?>
                        </td>
                        <td class="d-none d-lg-table-cell text-nowrap small" title="<?= escapeHtml(date('M j, Y g:i:s A', strtotime($row['sms_message_created_at']))) ?>">
                            <?= escapeHtml(date('M j, g:i A', strtotime($row['sms_message_created_at']))) ?>
                            <div class="text-body-secondary">#<?= $message_id ?><?= intval($row['sms_message_attempts']) > 1 ? ' &middot; tried ' . intval($row['sms_message_attempts']) . ' times' : '' ?></div>
                        </td>
                        <td class="text-end">
                            <?php if (smsLogCan($row, 'retry') || smsLogCan($row, 'cancel') || smsLogCan($row, 'delete')) { ?>
                                <div class="dropdown dropstart">
                                    <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu">
                                        <?php if (smsLogCan($row, 'retry')) { ?>
                                            <a class="dropdown-item" href="post.php?retry_text=<?= $message_id ?>&csrf_token=<?= $csrf ?>" data-sms-retry><i class="fas fa-fw fa-redo-alt text-secondary me-2"></i>Retry</a>
                                        <?php } ?>
                                        <?php if (smsLogCan($row, 'cancel')) { ?>
                                            <a class="dropdown-item" href="post.php?cancel_text=<?= $message_id ?>&csrf_token=<?= $csrf ?>" data-sms-cancel><i class="fas fa-fw fa-ban text-secondary me-2"></i>Cancel</a>
                                        <?php } ?>
                                        <?php if (smsLogCan($row, 'delete')) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?delete_text=<?= $message_id ?>&csrf_token=<?= $csrf ?>" data-sms-delete
                                                data-confirm-title="Delete this text?" data-confirm-text="<?= $out && $row['sms_message_status'] === 'queued' ? 'It won\'t be sent.' : 'It comes off the log - a reply on a ticket stays on the ticket.' ?>" data-confirm-button="Delete"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </form>

    <?php
    $empty_state = [
        'thing' => 'texts',
        'icon' => 'fa-sms',
        'title' => $show !== '' ? 'Nothing ' . strtolower($tabs[$show][0]) : 'No texts yet',
        'text' => 'Every text ITFlow sends and receives is kept here for a year - spam and dismissed texts for ' . SMS_SPAM_KEEP_DAYS . ' days, and a verification code\'s words for a day.',
    ];
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<script src="<?= staticFileUrl('/js/bulk_actions.js') ?>"></script>

<?php
require_once "../includes/footer.php";
