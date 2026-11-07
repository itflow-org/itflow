<?php

/*
 * Texts - Name this number (or sender): what a number is called in Texts, the log and its
 * codes - "Microsoft", "Duo", "The front desk" (sms_label_number, agent/post/texts.php).
 */

require_once '../../../includes/modal_header.php';

$row = smsMessageRow($_GET['id'] ?? 0);
$code = $row && $row['sms_message_status'] === 'code';
if (!$row || $row['sms_message_direction'] !== 'in' || lookupUserPermission($code ? 'module_credential' : 'module_support') < ($code ? 1 : 2)) {
    echo json_encode(['error' => 'That text is gone.']);
    exit;
}
$number = (string) $row['sms_message_number'];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-tag me-2"></i>Name <?= $code ? 'this sender' : 'this number' ?> <small class="ms-1 opacity-75"><?= escapeHtml(smsNumberText($number)) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="id" value="<?= intval($row['sms_message_id']) ?>">
    <div class="modal-body">
        <label class="form-label" for="smsNumberLabel">Name</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
            <input type="text" class="form-control" name="label" id="smsNumberLabel" maxlength="100" autofocus placeholder="<?= $code ? 'Microsoft, Duo, Google...' : 'Who it is' ?>" value="<?= escapeHtml(smsNumberLabel($number)) ?>">
        </div>
        <div class="form-text">Shown in Texts and the text log<?= $code ? ', with its codes' : '' ?>. Empty to take the name off.</div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="sms_label_number" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>
<?php require_once '../../../includes/modal_footer.php'; ?>
