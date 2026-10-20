<?php

/*
 * Maintenance > Mail Queue - every email ITFlow sends, from being queued to
 * sent, and what came of it (functions/mail_queue.php says what each status
 * is). The tabs pick Waiting, Sending, Retrying, Failed, Sent or Cancelled,
 * each with how many; at the top, anything stopping the queue moving -
 * sending switched off, cron not running, the job off, or email waiting
 * longer than it should. An email can be retried from the start, cancelled
 * or deleted, one at a time or several (admin/post/mail_queue.php), and
 * opened to see it (modals/mail_queue/mail_queue_message_view.php).
 */

// Default Column Sortby Filter
$sort = "email_queued_at";
$order = "DESC";

require_once "includes/inc_all_admin.php";

$page_title = 'Mail Queue';

$tabs = mailQueueTabs();
$show = (string) ($_GET['show'] ?? '');
$show = isset($tabs[$show]) ? $show : '';

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'email_queued_at' => "email_queued_at $order",
    'email_recipient' => "email_recipient $order, email_queued_at DESC",
    'email_subject' => "email_subject $order, email_queued_at DESC",
    'email_status' => "FIELD(email_status, 2, 1, 0, 4, 3) $order, email_attempts DESC, email_queued_at DESC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'email_queued_at';
}

$search = "(email_id LIKE '%$q%' OR email_from LIKE '%$q%' OR email_from_name LIKE '%$q%' OR email_recipient LIKE '%$q%' OR email_recipient_name LIKE '%$q%' OR email_subject LIKE '%$q%'"
    . (mailQueueHasError() ? " OR email_error LIKE '%$q%'" : '') . ")
    AND DATE(email_queued_at) BETWEEN '$dtf' AND '$dtt'";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS email_id, email_status, email_attempts, email_from, email_from_name, email_recipient, email_recipient_name,
        email_subject, email_queued_at, email_failed_at, email_sent_at, " . mailQueueErrorColumn() . ", COALESCE(email_attachments, '') <> '' AS has_attachments, COALESCE(email_cal_str, '') <> '' AS has_invite
    FROM email_queue
    WHERE $search AND {$tabs[$show][2]}
    ORDER BY {$sortable_columns[$sort]}, email_id DESC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// How many in each tab, as searched
$count_sql = [];
foreach ($tabs as $key => [, , $where]) {
    $count_sql[] = "COALESCE(SUM($where), 0)";
}
$counts = array_combine(array_keys($tabs), array_map('intval', mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM email_queue WHERE $search"))));

$health = mailQueueHealth();

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$pill_url = function ($show) {
    return '?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => $show, 'canned_date' => $_GET['canned_date'] ?? '', 'dtf' => $_GET['dtf'] ?? '', 'dtt' => $_GET['dtt'] ?? '']));
};
$person = function ($name, $address) {
    $name = trim((string) $name);
    $address = (string) $address;
    if ($address === '') {
        return ($name !== '' ? '<span class="text-break">' . escapeHtml($name) . '</span> ' : '') . '<span class="small text-danger-emphasis">no address</span>';
    }
    if ($name === '' || strcasecmp($name, $address) === 0) {
        return '<span class="text-break">' . escapeHtml($address) . '</span>';
    }
    return '<span class="text-break">' . escapeHtml($name) . '</span> <span class="small text-body-secondary text-break">' . escapeHtml($address) . '</span>';
};
$csrf = $_SESSION['csrf_token'];
$filtered_dates = isset($_GET['dtf']) && $_GET['dtf'] !== '' && $_GET['dtf'] !== '1970-01-01';

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-inbox me-2"></i>Mail Queue</h3>
        <div class="card-tools">
            <a class="btn btn-outline-light" href="settings_mail.php?tab=tests" title="Send a test email"><i class="fas fa-vial"></i><span class="d-none d-lg-inline ms-2">Send a test</span></a>
        </div>
    </div>

    <?php if ($health['level'] !== '') { ?>
        <div class="alert alert-<?= $health['level'] ?> rounded-0 border-0 border-bottom mb-0 d-flex flex-wrap align-items-center gap-2" data-mail-queue-health="<?= $health['key'] ?>">
            <i class="fas fa-fw fa-exclamation-triangle"></i>
            <div class="me-auto"><strong><?= escapeHtml($health['title']) ?></strong> - <?= escapeHtml($health['text']) ?></div>
            <a class="btn btn-sm btn-<?= $health['level'] ?>" href="<?= escapeHtml($health['url']) ?>"><?= escapeHtml($health['action']) ?></a>
        </div>
    <?php } ?>

    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show !== '') { ?><input type="hidden" name="show" value="<?= escapeHtml($show) ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search the mail queue..." title="Who it is to or from, the subject, why it failed, or its number">
                        <button class="btn btn-secondary" title="When it was queued" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilter"><i class="fas fa-calendar-alt"></i></button>
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
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_retry_emails" title="Failed or cancelled ones - from the start, at the next run">
                                    <i class="fas fa-fw fa-redo-alt text-secondary me-2"></i>Retry now
                                </button>
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_cancel_emails" title="Ones still to be sent or tried again">
                                    <i class="fas fa-fw fa-ban text-secondary me-2"></i>Cancel
                                </button>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_delete_emails"
                                    data-confirm-title="Delete these emails?" data-confirm-text="Any not sent yet never will be." data-confirm-button="Delete">
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
                        <label class="form-label" for="dateFilter">Queued</label>
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
        <nav class="nav nav-pills itflow-asset-tabs flex-nowrap overflow-auto mt-2" aria-label="Where the email is">
            <?php foreach ($tabs as $key => [$label, $icon]) {
                $alert = '';
                if ($show !== $key && $counts[$key]) {
                    $alert = ['failed' => 'text-bg-danger', 'retrying' => 'text-bg-warning'][$key] ?? '';
                } ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($pill_url($key)) ?>" data-mail-queue-tab="<?= $key === '' ? 'all' : $key ?>">
                    <i class="fas fa-fw <?= $icon ?> me-1"></i><?= $label ?> <span class="badge rounded-pill ms-1 <?= $alert ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
        <?php if ($health['level'] === '') { ?>
            <div class="small text-body-secondary mt-2" data-mail-queue-health="ok"><i class="fas fa-fw fa-check-circle text-success me-1"></i><?= escapeHtml($health['text']) ?></div>
        <?php } ?>
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
                    <th class="d-none d-sm-table-cell"><?= $sort_link('email_status', 'Status') ?></th>
                    <th><?= $sort_link('email_recipient', 'To') ?></th>
                    <th class="d-none d-md-table-cell"><?= $sort_link('email_subject', 'Subject') ?></th>
                    <th class="d-none d-lg-table-cell"><?= $sort_link('email_queued_at', 'Queued') ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php while ($row = mysqli_fetch_assoc($sql)) {
                    $email_id = intval($row['email_id']);
                    $state = mailQueueState($row);
                    $subject = trim((string) $row['email_subject']) !== '' ? escapeHtml($row['email_subject']) : '<span class="text-body-secondary">(no subject)</span>';
                    $view = 'data-modal-size="lg" data-modal-url="modals/mail_queue/mail_queue_message_view.php?id=' . $email_id . '"';
                    $from = $person($row['email_from_name'], $row['email_from']);
                    ?>
                    <tr data-email-id="<?= $email_id ?>" data-email-state="<?= $state['key'] ?>">
                        <td class="checkbox-column bg-light border-end">
                            <?php if (mailQueueCan($row, 'delete')) { ?>
                                <div class="form-check">
                                    <input class="form-check-input bulk-select" type="checkbox" name="email_ids[]" value="<?= $email_id ?>" aria-label="Select email <?= $email_id ?>">
                                </div>
                            <?php } ?>
                        </td>
                        <td class="text-nowrap d-none d-sm-table-cell">
                            <span class="badge <?= $state['badge'] ?>" data-email-status><i class="fas fa-fw <?= $state['icon'] ?> me-1"></i><?= $state['label'] ?></span>
                            <?php if ($state['detail'] !== '') { ?>
                                <div class="small text-body-secondary mt-1" title="<?= escapeHtml($state['title']) ?>" data-email-detail><?= escapeHtml($state['detail']) ?></div>
                            <?php } ?>
                            <?php if ($state['error'] !== '') { ?>
                                <div class="small text-wrap itflow-mail-queue-why <?= $state['key'] === 'cancelled' ? 'text-body-secondary' : 'text-danger-emphasis' ?>" title="<?= escapeHtml($state['error']) ?>" data-email-error><?= escapeHtml(mb_strimwidth($state['error'], 0, 160, '...')) ?></div>
                            <?php } ?>
                        </td>
                        <td class="min-w-0">
                            <div><?= $person($row['email_recipient_name'], $row['email_recipient']) ?></div>
                            <!-- On smaller screens the subject comes along under who it is to, and on a phone where it stands -->
                            <div class="d-md-none small"><a class="ajax-modal" href="#" <?= $view ?>><?= $subject ?></a></div>
                            <div class="d-sm-none small mt-1"><span class="badge <?= $state['badge'] ?>"><?= $state['label'] ?></span> <span class="text-body-secondary"><?= escapeHtml($state['detail']) ?></span></div>
                            <?php if ($state['error'] !== '') { ?><div class="d-sm-none small text-danger-emphasis"><?= escapeHtml(mb_strimwidth($state['error'], 0, 160, '...')) ?></div><?php } ?>
                        </td>
                        <td class="d-none d-md-table-cell min-w-0">
                            <a class="ajax-modal fw-semibold text-body" href="#" <?= $view ?>><?= $subject ?></a>
                            <?php if ($row['has_attachments']) { ?><i class="fas fa-fw fa-paperclip text-body-secondary ms-1" title="With attachments"></i><?php } ?>
                            <?php if ($row['has_invite']) { ?><i class="fas fa-fw fa-calendar-plus text-body-secondary ms-1" title="With a calendar file"></i><?php } ?>
                            <div class="small text-body-secondary">From <?= $from ?></div>
                        </td>
                        <td class="d-none d-lg-table-cell text-nowrap small" title="<?= escapeHtml(date('M j, Y g:i:s A', strtotime($row['email_queued_at']))) ?>">
                            <?= escapeHtml(date('M j, g:i A', strtotime($row['email_queued_at']))) ?>
                            <div class="text-body-secondary">#<?= $email_id ?></div>
                        </td>
                        <td class="text-end">
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" <?= $view ?>><i class="fas fa-fw fa-envelope-open text-secondary me-2"></i>View</a>
                                    <?php if (mailQueueCan($row, 'retry')) { ?>
                                        <a class="dropdown-item" href="post.php?retry_mail=<?= $email_id ?>&csrf_token=<?= $csrf ?>" title="From the start, at the next run" data-email-retry><i class="fas fa-fw fa-redo-alt text-secondary me-2"></i>Retry now</a>
                                    <?php } ?>
                                    <?php if (mailQueueCan($row, 'cancel')) { ?>
                                        <a class="dropdown-item" href="post.php?cancel_mail=<?= $email_id ?>&csrf_token=<?= $csrf ?>" data-email-cancel><i class="fas fa-fw fa-ban text-secondary me-2"></i>Cancel</a>
                                    <?php } ?>
                                    <?php if (mailQueueCan($row, 'delete')) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?delete_mail=<?= $email_id ?>&csrf_token=<?= $csrf ?>" data-email-delete
                                            data-confirm-title="Delete this email?" data-confirm-text="<?= intval($row['email_status']) === 3 ? 'It has been sent - this only takes it off the list.' : 'It won\'t be sent.' ?>" data-confirm-button="Delete"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </form>

    <?php
    $empty_state = [
        'thing' => 'emails',
        'icon' => 'fa-inbox',
        'title' => $show !== '' ? 'Nothing ' . strtolower($tabs[$show][0]) : 'Nothing in the mail queue',
        'text' => 'The email ITFlow sends waits here until it goes, and stays ' . MAIL_QUEUE_KEEP_DAYS . ' days after.',
    ];
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
