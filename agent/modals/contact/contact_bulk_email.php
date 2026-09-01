<?php

require_once '../../../includes/modal_header.php';

$contact_ids = array_map('intval', $_GET['contact_ids'] ?? []);

$count = count($contact_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-envelope-open me-2"></i>Send Email to <strong><?= $count ?></strong> Contacts</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($contact_ids as $contact_id) { ?><input type="hidden" name="contact_ids[]" value="<?= $contact_id ?>"><?php } ?>
    <div class="modal-body">

        <label>From Email / Display Name</label>
        <div class="row g-2">

            <div class="mb-3 col-sm-6">
                <select type="text" class="form-select select2" name="mail_from">
                    <option value="<?= escapeHtml($config_mail_from_email) ?>">
                        <?= escapeHtml("$config_mail_from_name - $config_mail_from_email") ?></option>
                    <option value="<?= escapeHtml($config_invoice_from_email) ?>">
                        <?= escapeHtml("$config_invoice_from_name - $config_invoice_from_email") ?></option>
                    <option value="<?= escapeHtml($config_quote_from_email) ?>">
                        <?= escapeHtml("$config_quote_from_name - $config_quote_from_email") ?></option>
                    <option value="<?= escapeHtml($config_ticket_from_email) ?>">
                        <?= escapeHtml("$config_ticket_from_name - $config_ticket_from_email") ?></option>
                </select>
            </div>

            <div class="mb-3 col-sm-6">
                <input type="text" class="form-control" name="mail_from_name" placeholder="From Name" maxlength="255"
                    value="<?= escapeHtml($config_mail_from_name) ?>">
            </div>
        </div>

        <div class="mb-3">
            <input type="text" class="form-control" name="subject" placeholder="Subject" maxlength="255">
        </div>

        <div class="mb-3">
            <textarea class="form-control tinymce" name="body"
                placeholder="Type an email in here"></textarea>
        </div>

        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="datetime-local" class="form-control" name="queued_at">
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="send_bulk_mail_now" class="btn btn-primary text-bold"><i class="fas fa-paper-plane me-2"></i>Send Emails</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
