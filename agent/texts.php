<?php

/*
 * Texts - what came to your texting number that isn't on a ticket (functions/sms.php):
 *   Unmatched - from numbers that aren't a contact's: open a ticket from one, dismiss it, or
 *     mark it spam (which blocks the number)
 *   Codes - verification and MFA codes, for those whose role can view credentials: masked,
 *     revealed one at a time (each reveal in the audit log) for SMS_CODE_MASK_MINUTES after
 *     it came, and its words wiped after SMS_CODE_KEEP_HOURS
 *   Spam - blocked numbers and what looks like bait, deleted after SMS_SPAM_KEEP_DAYS
 * Texts from contacts, and on tickets, go on their tickets and never wait here. The actions
 * are agent/post/texts.php; revealing a code is ajax.php?sms_reveal_code.
 */

// Default Column Sortby Filter
$sort = "sms_message_created_at";
$order = "DESC";

require_once "includes/inc_all.php";

if (lookupUserPermission('module_support') < 1 && lookupUserPermission('module_credential') < 1) {
    enforceUserPermission('module_support');
}

$page_title = 'Texts';

if (!smsProvider()) { ?>
    <div class="card">
        <div class="card-body text-center text-body-secondary py-5">
            <i class="fas fa-sms fa-2x mb-2 d-block opacity-50"></i>
            Texting isn't set up - an admin sets it up in Admin &gt; Integrations &gt; Text Messaging.
        </div>
    </div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

$tabs = array_filter(smsInboxTabs(), function ($tab) {
    return $tab[3];
});
$show = (string) ($_GET['show'] ?? '');
if (!isset($tabs[$show])) {
    $show = (string) array_key_first($tabs);
}
$codes = $show === 'codes';
$can_work = lookupUserPermission('module_support') >= 2;
$can_codes = lookupUserPermission('module_credential') >= 1;

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'sms_message_created_at' => "sms_message_created_at $order",
    'sms_message_number' => "sms_message_number $order, sms_message_created_at DESC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'sms_message_created_at';
}

// A code's words aren't searched - a search that narrows to it would tell what it is
$search = "(sms_message_number LIKE '%$phone_query%' OR sms_number_label LIKE '%$q%' OR contact_name LIKE '%$q%' OR client_name LIKE '%$q%'"
    . ($codes ? '' : " OR sms_message_body LIKE '%$q%'") . ")";
$joins = "LEFT JOIN sms_numbers ON sms_number_phone = sms_message_number
    LEFT JOIN contacts ON contact_id = sms_message_contact_id AND sms_message_contact_id > 0
    LEFT JOIN clients ON client_id = sms_message_client_id AND sms_message_client_id > 0";
$scope = "sms_message_direction = 'in' " . clientScopeSql('sms_message_client_id');

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS sms_message_id, sms_message_number, sms_message_body, sms_message_media, sms_message_code, sms_message_status,
        sms_message_created_at, sms_message_error, sms_number_label, contact_name, client_name
    FROM sms_messages $joins
    WHERE $scope AND $search AND {$tabs[$show][2]}
    ORDER BY {$sortable_columns[$sort]}, sms_message_id DESC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// How many in each tab
$count_sql = [];
foreach ($tabs as $key => $tab) {
    $count_sql[] = "COALESCE(SUM({$tab[2]}), 0)";
}
$counts = array_combine(array_keys($tabs), array_map('intval', mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM sms_messages $joins WHERE $scope"))));
$latest_code = $can_codes ? intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(sms_message_id), 0) FROM sms_messages WHERE sms_message_direction = 'in' AND sms_message_status = 'code'"))[0]) : 0;

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$csrf = $_SESSION['csrf_token'];
$provider = smsProvider();

?>

<div class="card">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-sms me-2"></i>Texts <span class="small text-body-secondary fw-normal ms-1">to <?= escapeHtml(smsNumberText($provider['sms_provider_number'])) ?></span></h3>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="show" value="<?= escapeHtml($show) ?>">
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search texts..." title="<?= $codes ? 'The number, or the name it was given' : 'The number, its name, or the words' ?>">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-7 col-md-6 small text-body-secondary text-md-end">
                    <?php if ($codes) { ?>
                        A code can be revealed for <?= SMS_CODE_MASK_MINUTES ?> minutes after it came - each reveal is in the audit log - and its words are wiped after a day.
                    <?php } elseif ($show === 'unmatched') { ?>
                        Texts from numbers that aren't a contact's. Texts from your contacts go on their tickets.
                    <?php } else { ?>
                        From blocked numbers, or what looks like bait. Deleted after <?= SMS_SPAM_KEEP_DAYS ?> days.
                    <?php } ?>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs flex-nowrap overflow-auto mt-2" aria-label="Which texts">
            <?php foreach ($tabs as $key => [$label, $icon]) {
                $alert = $show !== $key && $counts[$key] && $key === 'unmatched' ? 'text-bg-warning' : ''; ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="?show=<?= $key ?>" data-texts-tab="<?= $key ?>">
                    <i class="fas fa-fw <?= $icon ?> me-1"></i><?= $label ?> <span class="badge rounded-pill ms-1 <?= $alert ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('sms_message_number', 'From') ?></th>
                <?php if ($codes) { ?><th>Code</th><?php } ?>
                <th class="d-none d-md-table-cell">Text</th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('sms_message_created_at', 'When') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                $message_id = intval($row['sms_message_id']);
                $number = (string) $row['sms_message_number'];
                $label = trim((string) $row['sms_number_label']);
                $who = trim((string) $row['contact_name']) !== '' ? $row['contact_name'] . ($row['client_name'] ? ' - ' . $row['client_name'] : '') : $label;
                $body = $codes ? smsLogBody($row) : (string) $row['sms_message_body'];
                $media = $row['sms_message_media'] ? (json_decode($row['sms_message_media'], true) ?: []) : [];
                $fresh = $codes && smsCodeFresh($row);
                ?>
                <tr data-sms-message-id="<?= $message_id ?>">
                    <td class="ps-3 min-w-0">
                        <div class="fw-semibold text-nowrap"><?= escapeHtml($label !== '' && $codes ? $label : smsNumberText($number)) ?></div>
                        <?php if ($codes && $label !== '') { ?>
                            <div class="small text-body-secondary"><?= escapeHtml(smsNumberText($number)) ?></div>
                        <?php } elseif ($who !== '') { ?>
                            <div class="small text-body-secondary text-break"><?= escapeHtml($who) ?></div>
                        <?php } ?>
                        <!-- On smaller screens the text comes along under who it is from -->
                        <div class="d-md-none small text-break mt-1"><?= nl2br(escapeHtml(mb_strimwidth($body, 0, 200, '...'))) ?></div>
                    </td>
                    <?php if ($codes) { ?>
                        <td class="text-nowrap" data-sms-code-cell="<?= $message_id ?>">
                            <?php if ($row['sms_message_code'] === null) { ?>
                                <span class="text-body-secondary small">Wiped</span>
                            <?php } elseif ($fresh) { ?>
                                <span class="font-monospace fs-5 me-2" data-sms-code><?= escapeHtml(smsCodeMasked((string) $row['sms_message_code'])) ?></span>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-sms-reveal="<?= $message_id ?>"><i class="fas fa-fw fa-eye me-1"></i>Reveal</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary clipboardjs d-none" data-sms-copy title="Copy the code"><i class="far fa-fw fa-copy"></i></button>
                            <?php } else { ?>
                                <span class="font-monospace fs-5 me-2 text-body-secondary"><?= escapeHtml(smsCodeMasked((string) $row['sms_message_code'])) ?></span>
                                <span class="badge text-bg-light border" title="Codes can be revealed for <?= SMS_CODE_MASK_MINUTES ?> minutes after they come">Expired</span>
                            <?php } ?>
                        </td>
                    <?php } ?>
                    <td class="d-none d-md-table-cell min-w-0" style="max-width: 36rem;">
                        <div class="text-break small" data-sms-body><?= nl2br(escapeHtml(mb_strimwidth($body, 0, 600, '...'))) ?></div>
                        <?php if ($media) { ?>
                            <div class="small text-body-secondary mt-1"><i class="fas fa-fw fa-paperclip me-1"></i><?= count($media) ?> photo<?= count($media) === 1 ? '' : 's' ?> or file<?= count($media) === 1 ? '' : 's' ?> - fetched onto the ticket if one is opened from it</div>
                        <?php } ?>
                    </td>
                    <td class="d-none d-lg-table-cell text-nowrap small" title="<?= escapeHtml(date('M j, Y g:i:s A', strtotime($row['sms_message_created_at']))) ?>">
                        <?= escapeHtml(timeAgo($row['sms_message_created_at'])) ?>
                        <div class="text-body-secondary"><?= escapeHtml(date('M j, g:i A', strtotime($row['sms_message_created_at']))) ?></div>
                    </td>
                    <td class="text-end pe-3 text-nowrap">
                        <?php if ($show === 'unmatched' && $can_work) { ?>
                            <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="modals/sms/sms_ticket_open.php?id=<?= $message_id ?>"><i class="fas fa-fw fa-life-ring me-1"></i><span class="d-none d-sm-inline">Open ticket</span></button>
                        <?php } ?>
                        <?php if (($show !== 'codes' && $can_work) || ($codes && $can_codes)) { ?>
                            <div class="dropdown dropstart d-inline-block">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <?php if ($show === 'unmatched') { ?>
                                        <a class="dropdown-item" href="post.php?sms_dismiss=<?= $message_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-check text-secondary me-2"></i>Dismiss</a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/sms/sms_label.php?id=<?= $message_id ?>"><i class="fas fa-fw fa-tag text-secondary me-2"></i>Name this number</a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?sms_mark_spam=<?= $message_id ?>&csrf_token=<?= $csrf ?>"
                                            data-confirm-title="Mark as spam?" data-confirm-button="Mark as spam"
                                            data-confirm-text="<?= escapeHtml(smsNumberText($number)) ?> is blocked - what it sends goes straight to Spam, and its texts here go there now."><i class="fas fa-fw fa-ban me-2"></i>Mark as spam</a>
                                    <?php } elseif ($codes) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/sms/sms_label.php?id=<?= $message_id ?>"><i class="fas fa-fw fa-tag text-secondary me-2"></i>Name this sender</a>
                                        <?php if (lookupUserPermission('module_credential') >= 2 && $row['sms_message_body'] !== null) { ?>
                                            <a class="dropdown-item confirm-link" href="post.php?sms_not_code=<?= $message_id ?>&csrf_token=<?= $csrf ?>"
                                                data-confirm-title="Not a code?" data-confirm-button="Move to Unmatched"
                                                data-confirm-text="It moves to Unmatched, where everyone who works tickets sees its words."><i class="fas fa-fw fa-undo text-secondary me-2"></i>Not a code</a>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <a class="dropdown-item" href="post.php?sms_not_spam=<?= $message_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-undo text-secondary me-2"></i>Not spam</a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger" href="post.php?sms_delete=<?= $message_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
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
    <?php
    $empty_state = [
        'thing' => 'texts',
        'icon' => $tabs[$show][1],
        'title' => ['unmatched' => 'Nothing unmatched', 'codes' => 'No codes', 'spam' => 'No spam'][$show] ?? 'No texts',
        'text' => ['unmatched' => 'Texts from numbers that aren\'t a contact\'s wait here - texts from your contacts go on their tickets.',
            'codes' => 'Verification codes texted to ' . smsNumberText($provider['sms_provider_number']) . ' show here as they come.',
            'spam' => 'Texts from blocked numbers, and ones that look like bait, wait here for ' . SMS_SPAM_KEEP_DAYS . ' days.'][$show] ?? '',
    ];
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php if ($codes) { ?>
<script>
(function () {
    var csrf = <?= json_encode($csrf) ?>;

    // Reveal a code - asked of the server each time, so each reveal is logged
    document.querySelectorAll('[data-sms-reveal]').forEach(function (button) {
        button.addEventListener('click', function () {
            var id = button.getAttribute('data-sms-reveal');
            var cell = document.querySelector('[data-sms-code-cell="' + id + '"]');
            var row = button.closest('tr');
            button.disabled = true;
            var data = new FormData();
            data.append('csrf_token', csrf);
            data.append('id', id);
            fetch('ajax.php?sms_reveal_code', { method: 'POST', body: data, credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (answer) {
                    if (answer.error) {
                        button.disabled = false;
                        cell.insertAdjacentHTML('beforeend', '<div class="small text-danger-emphasis mt-1"></div>');
                        cell.lastElementChild.textContent = answer.error;
                        return;
                    }
                    cell.querySelector('[data-sms-code]').textContent = answer.code;
                    var copy = cell.querySelector('[data-sms-copy]');
                    copy.setAttribute('data-clipboard-text', answer.code.replace(/[ -]/g, ''));
                    copy.classList.remove('d-none');
                    button.remove();
                    row.querySelectorAll('[data-sms-body]').forEach(function (el) { el.textContent = answer.body; });
                })
                .catch(function () {
                    button.disabled = false;
                });
        });
    });

    // A new code - the page comes again, so it is in the list as soon as it lands
    var latest = <?= $latest_code ?>;
    setInterval(function () {
        if (document.hidden) {
            return;
        }
        fetch('ajax.php?sms_latest_code', { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (answer) {
                if (answer.latest && answer.latest > latest) {
                    window.location.reload();
                }
            })
            .catch(function () {});
    }, 5000);
})();
</script>
<?php } ?>

<?php
require_once "../includes/footer.php";
