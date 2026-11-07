<?php

/*
 * Admin > Text Messaging - the texting service ITFlow texts through (functions/sms.php), one
 * number for every client. Each row says whether it is on, the number, the address its texts
 * come back to (pasted into the service's portal), and its texts in the last 30 days. Send a
 * test text tries it; Opted out and blocked lists the numbers that replied STOP or were
 * marked spam. Add and Edit are one window (modals/sms_provider/sms_provider_edit.php); the
 * rest are admin/post/sms_provider.php.
 */

// Default Column Sortby Filter
$sort = "sms_provider_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

// The tables arrive with the 3.6.0 database update
try {
    mysqli_query($mysqli, "SELECT 1 FROM sms_providers LIMIT 1");
} catch (Throwable $e) { ?>
    <div class="alert alert-warning"><i class="fas fa-fw fa-exclamation-triangle me-2"></i>Text messaging arrives with a database update - run it in <a href="update.php">Maintenance &gt; Update</a> first.</div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'sms_provider_name' => "sms_provider_name $order",
    'sms_provider_number' => "sms_provider_number $order, sms_provider_name ASC",
    'sms_provider_last_inbound_at' => "sms_provider_last_inbound_at IS NULL, sms_provider_last_inbound_at $order, sms_provider_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'sms_provider_name';
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS * FROM sms_providers
    WHERE (sms_provider_name LIKE '%$q%' OR sms_provider_description LIKE '%$q%' OR sms_provider_number LIKE '%$phone_query%')
    ORDER BY {$sortable_columns[$sort]}, sms_provider_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$set_up = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(DISTINCT sms_provider_name) FROM sms_providers"))[0]);
$can_add = $set_up < count(smsProviderKinds());
$activity = smsProviderActivity();
$numbers = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(sms_number_opted_out_at IS NOT NULL), 0), COALESCE(SUM(sms_number_blocked_at IS NOT NULL), 0) FROM sms_numbers"));
$opted_out = intval($numbers[0]);
$blocked = intval($numbers[1]);

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

$add_url = 'modals/sms_provider/sms_provider_add.php';
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-sms me-2"></i>Text Messaging</h3>
        <div class="card-tools">
            <a class="btn btn-outline-light me-1 ajax-modal" href="#" data-modal-url="modals/sms_provider/sms_numbers.php" title="Numbers that replied STOP, and numbers blocked as spam">
                <i class="fas fa-fw fa-ban"></i><span class="d-none d-lg-inline ms-2">Opted out and blocked</span><?php if ($opted_out + $blocked) { ?> <span class="badge text-bg-light ms-1"><?= $opted_out + $blocked ?></span><?php } ?>
            </a>
            <a class="btn btn-outline-light me-1" href="text_messages.php"><i class="fas fa-fw fa-list"></i><span class="d-none d-lg-inline ms-2">Text Messages</span></a>
            <?php if ($can_add) { ?>
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= $add_url ?>"><i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">Add Provider</span></button>
            <?php } ?>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search texting providers..." title="Name, description or number">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-7 col-md-6 small text-body-secondary text-md-end" data-sms-activity>
                    In the last 30 days: <?= $activity['sent'] ?> sent<?php if ($activity['failed']) { ?>, <a class="text-danger-emphasis" href="text_messages.php?show=failed"><?= $activity['failed'] ?> failed</a><?php } ?>, <?= $activity['received'] ?> received
                </div>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('sms_provider_name', 'Provider') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('sms_provider_number', 'Number') ?></th>
                <th class="d-none d-lg-table-cell">Texts come back to</th>
                <th class="d-none d-md-table-cell"><?= $sort_link('sms_provider_last_inbound_at', 'Last text in') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                $provider_id = intval($row['sms_provider_id']);
                $name = (string) $row['sms_provider_name'];
                $kind = smsProviderKinds()[$name] ?? null;
                $on = intval($row['sms_provider_active']) === 1;
                $edit_url = "modals/sms_provider/sms_provider_edit.php?id=$provider_id";
                $inbound_url = smsInboundUrl($row);
                ?>
                <tr data-sms-provider-id="<?= $provider_id ?>" class="<?= $on ? '' : 'text-body-secondary' ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="d-none d-sm-inline-flex itflow-asset-icon itflow-asset-tone-other" aria-hidden="true"><i class="<?= escapeHtml($kind['icon'] ?? 'fas fa-sms') ?>"></i></span>
                            <div class="min-w-0">
                                <a class="fw-semibold text-body ajax-modal" href="#" data-modal-url="<?= $edit_url ?>"><?= escapeHtml($name) ?></a>
                                <?php if (!$on) { ?>
                                    <span class="badge text-bg-secondary ms-1" data-sms-provider-state="off" title="No texts go out, and texts sent to the number aren't taken in">Off</span>
                                <?php } ?>
                                <?php if (trim((string) $row['sms_provider_description']) !== '') { ?>
                                    <div class="small text-body-secondary"><?= escapeHtml($row['sms_provider_description']) ?></div>
                                <?php } ?>
                                <!-- On smaller screens the number comes along under the name -->
                                <div class="d-md-none small text-body-secondary"><?= escapeHtml(smsNumberText($row['sms_provider_number'])) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell text-nowrap"><?= escapeHtml(smsNumberText($row['sms_provider_number'])) ?></td>
                    <td class="d-none d-lg-table-cell small" style="max-width: 28rem;">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace" readonly value="<?= escapeHtml($inbound_url) ?>" id="smsInbound<?= $provider_id ?>" aria-label="Where texts come back to">
                            <button type="button" class="btn btn-outline-secondary clipboardjs" data-clipboard-target="#smsInbound<?= $provider_id ?>" title="Copy"><i class="far fa-fw fa-copy"></i></button>
                        </div>
                        <div class="text-body-secondary mt-1">In <?= escapeHtml($name) ?>'s portal: the number &gt; SMS &gt; Callback URL, by POST.</div>
                    </td>
                    <td class="d-none d-md-table-cell small text-nowrap" data-sms-last-in>
                        <?php if ($row['sms_provider_last_inbound_at']) { ?>
                            <span title="<?= escapeHtml($row['sms_provider_last_inbound_at']) ?>"><?= escapeHtml(timeAgo($row['sms_provider_last_inbound_at'])) ?></span>
                        <?php } else { ?>
                            <span class="text-body-secondary">None yet - text the number to try it</span>
                        <?php } ?>
                    </td>
                    <td class="text-end pe-3">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $edit_url ?>">
                                    <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                </a>
                                <?php if ($on) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/sms_provider/sms_test.php?id=<?= $provider_id ?>">
                                        <i class="fas fa-fw fa-vial text-secondary me-2"></i>Send a test text
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item confirm-link" href="post.php?new_sms_inbound_key=<?= $provider_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Make a new address for texts to come back to?" data-confirm-button="Make a new one"
                                    data-confirm-text="The one <?= escapeHtml($name) ?> has stops working - paste the new one into its portal, or texts to the number won't reach ITFlow.">
                                    <i class="fas fa-fw fa-sync-alt text-secondary me-2"></i>New inbound address
                                </a>
                                <?php if ($on) { ?>
                                    <a class="dropdown-item confirm-link" href="post.php?turn_sms_provider_off=<?= $provider_id ?>&csrf_token=<?= $csrf ?>"
                                        data-confirm-title="Turn <?= escapeHtml($name) ?> off?" data-confirm-text="No texts go out, and texts to the number aren't taken in until it is on again. Waiting texts wait." data-confirm-button="Turn off">
                                        <i class="fas fa-fw fa-toggle-off text-secondary me-2"></i>Turn off
                                    </a>
                                <?php } else { ?>
                                    <a class="dropdown-item" href="post.php?turn_sms_provider_on=<?= $provider_id ?>&csrf_token=<?= $csrf ?>">
                                        <i class="fas fa-fw fa-toggle-on text-secondary me-2"></i>Turn on
                                    </a>
                                <?php } ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_sms_provider=<?= $provider_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Delete <?= escapeHtml($name) ?>?" data-confirm-button="Delete"
                                    data-confirm-text="Nothing more is texted, and texts to the number stop reaching ITFlow. The texts already sent and received stay in the log and on their tickets.">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'texting providers',
        'icon' => 'fa-sms',
        'text' => 'Set up Skyetel and your agents can reply to tickets by text, texts from your clients open tickets, and verification codes texted to your number land in Texts.',
        'actions' => $can_add ? [['label' => 'Add Provider', 'modal' => $add_url]] : [],
    ];
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php
require_once "../includes/footer.php";
