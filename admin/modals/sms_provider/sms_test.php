<?php

/*
 * Admin > Text Messaging - Send a test text, to a number of your own, so the keys, the
 * number and its registration are tried for real (send_sms_test, admin/post/sms_provider.php).
 */

require_once '../../includes/modal_header.php';

$row = smsProviderRow($_GET['id'] ?? 0);
if (!$row) {
    echo json_encode(['error' => 'That texting provider no longer exists.']);
    exit;
}
$company = trim((string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT company_name FROM companies WHERE company_id = 1"))[0] ?? ''));

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-vial me-2"></i>Send a test text</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="provider_id" value="<?= intval($row['sms_provider_id']) ?>">

    <div class="modal-body">
        <p class="small text-body-secondary">From <strong><?= escapeHtml(smsNumberText($row['sms_provider_number'])) ?></strong> through <?= escapeHtml($row['sms_provider_name']) ?> - to your own mobile. Then text it back, and the reply shows in Texts on the agent side.</p>

        <div class="mb-3">
            <label class="form-label" for="smsTestNumber">To <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                <input type="tel" class="form-control" name="number" id="smsTestNumber" maxlength="30" placeholder="+1 412 555 0100" required autofocus>
            </div>
        </div>

        <div class="mb-0">
            <label class="form-label" for="smsTestText">Text</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                <textarea class="form-control" name="text" id="smsTestText" rows="3" maxlength="<?= SMS_TEXT_LIMIT ?>"><?= escapeHtml(($company !== '' ? "$company: " : '') . 'a test text from ITFlow.') ?></textarea>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="send_sms_test" class="btn btn-primary text-bold"><i class="fa fa-paper-plane me-2"></i>Send</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>
<?php
require_once '../../../includes/modal_footer.php';
