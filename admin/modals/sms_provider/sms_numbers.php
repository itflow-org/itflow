<?php

/*
 * Admin > Text Messaging - Opted out and blocked: the numbers that replied STOP (nothing is
 * texted to them until they reply START) and the ones blocked as spam (their texts go
 * straight to Spam). Each can be put back - opting someone back in is for when they ask, by
 * any other way than texting START (admin/post/sms_provider.php).
 */

require_once '../../includes/modal_header.php';

$rows = [];
try {
    $sql = mysqli_query($mysqli, "SELECT sms_number_phone, sms_number_label, sms_number_opted_out_at, sms_number_blocked_at, user_name
        FROM sms_numbers LEFT JOIN users ON user_id = sms_number_blocked_by
        WHERE sms_number_opted_out_at IS NOT NULL OR sms_number_blocked_at IS NOT NULL
        ORDER BY GREATEST(COALESCE(sms_number_opted_out_at, 0), COALESCE(sms_number_blocked_at, 0)) DESC LIMIT 500");
    $rows = mysqli_fetch_all($sql, MYSQLI_ASSOC);
} catch (Throwable $e) {
    // Before the 3.6.0 database update
}
$csrf = $_SESSION['csrf_token'];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-ban me-2"></i>Opted out and blocked</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body p-0">
    <?php if (!$rows) { ?>
        <div class="text-center text-body-secondary py-5 px-3">
            <i class="fas fa-ban fa-2x mb-2 d-block opacity-50"></i>
            No number has replied STOP, and none is blocked as spam.
        </div>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-nowrap">
                <tr>
                    <th class="ps-3">Number</th>
                    <th>What</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row) {
                    $number = (string) $row['sms_number_phone'];
                    $label = trim((string) $row['sms_number_label']);
                    $contacts = smsContactsFor($number);
                    ?>
                    <tr data-sms-number="<?= escapeHtml($number) ?>">
                        <td class="ps-3">
                            <div class="fw-semibold"><?= escapeHtml(smsNumberText($number)) ?></div>
                            <?php if ($label !== '' || $contacts) { ?>
                                <div class="small text-body-secondary"><?= escapeHtml($label !== '' ? $label : $contacts[0]['contact_name'] . ($contacts[0]['client_name'] ? ' - ' . $contacts[0]['client_name'] : '')) ?></div>
                            <?php } ?>
                        </td>
                        <td class="small">
                            <?php if ($row['sms_number_opted_out_at']) { ?>
                                <div><i class="fas fa-fw fa-hand-paper text-secondary me-1"></i>Replied STOP <?= escapeHtml(timeAgo($row['sms_number_opted_out_at'])) ?></div>
                            <?php } ?>
                            <?php if ($row['sms_number_blocked_at']) { ?>
                                <div><i class="fas fa-fw fa-ban text-secondary me-1"></i>Blocked as spam <?= escapeHtml(timeAgo($row['sms_number_blocked_at'])) ?><?= $row['user_name'] ? ' by ' . escapeHtml($row['user_name']) : '' ?></div>
                            <?php } ?>
                        </td>
                        <td class="text-end pe-3 text-nowrap">
                            <?php if ($row['sms_number_opted_out_at']) { ?>
                                <a class="btn btn-sm btn-light border confirm-link" href="post.php?sms_number_opt_in=<?= urlencode($number) ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Text <?= escapeHtml(smsNumberText($number)) ?> again?" data-confirm-button="Opt back in"
                                    data-confirm-text="Only when they've asked for texts again - by phone, email or in person. Otherwise they text START themselves.">Opt back in</a>
                            <?php } ?>
                            <?php if ($row['sms_number_blocked_at']) { ?>
                                <a class="btn btn-sm btn-light border" href="post.php?sms_number_unblock=<?= urlencode($number) ?>&csrf_token=<?= $csrf ?>">Unblock</a>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>
<?php
require_once '../../../includes/modal_footer.php';
