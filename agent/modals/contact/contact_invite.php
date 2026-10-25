<?php

/*
 * Invite people to a client's portal - their addresses (several at once,
 * each with a name if you like) and the roles they get. Each is emailed a
 * link to join, where they put in their own name, phone and password
 * (functions/portal_invite.php). Saved by send_portal_invites in
 * agent/post/contact.php.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client', 2);

$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}
$client = $client_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name, client_archived_at FROM clients WHERE client_id = $client_id LIMIT 1")) : null;

ob_start();

$refuse = '';
if (!$client) {
    $refuse = 'Invite people from a client\'s Contacts.';
} elseif (!portalInvitesReady()) {
    $refuse = 'Inviting people needs the database update - run it from Maintenance > Update first.';
} elseif (intval($config_client_portal_enable) !== 1) {
    $refuse = 'The client portal is off - turn it on in Admin > Settings > Modules.';
} elseif ($client['client_archived_at']) {
    $refuse = 'This client is archived - restore it to invite people.';
} elseif (!portalMailReady()) {
    $refuse = 'Outgoing mail isn\'t set up, so an invite can\'t be sent - set it up in Admin > Settings > Mail.';
}

if ($refuse !== '') { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fas fa-fw fa-user-plus me-2"></i>Invite people</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5"><?= escapeHtml($refuse) ?></div>
<?php } else {
    $field = 'contactInvite' . bin2hex(random_bytes(3)); ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-plus me-2"></i>Invite people to the portal for <strong><?= escapeHtml($client['client_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">
        <p class="small text-body-secondary">Each gets an email with a link to join - they put in their own name, phone and password there, and are added to the contacts when they do. The link works for <?= PORTAL_INVITE_LINK_DAYS ?> days.</p>

        <div class="mb-3">
            <label class="form-label" for="<?= $field ?>Emails">Email addresses <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text align-items-start pt-2"><i class="fa fa-fw fa-envelope"></i></span>
                <textarea class="form-control" id="<?= $field ?>Emails" name="emails" rows="4" maxlength="20000" placeholder="jamie@example.com&#10;Sam Lee &lt;sam@example.com&gt;" required autofocus></textarea>
            </div>
            <div class="form-text">One to a line, or separated by commas - up to <?= PORTAL_INVITE_MAX ?>. A name in front is optional. Someone who is already a contact here is given access when they accept.</div>
        </div>

        <label class="form-label">What else they see</label>
        <div class="row g-2">
            <div class="col-sm-6">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="<?= $field ?>Technical" name="technical" value="1">
                    <label class="form-check-label" for="<?= $field ?>Technical"><i class="fas fa-fw fa-tools text-secondary me-1"></i>Technical</label>
                </div>
                <div class="form-text mt-0">Every ticket at the company, and its documentation</div>
            </div>
            <div class="col-sm-6">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="<?= $field ?>Billing" name="billing" value="1">
                    <label class="form-check-label" for="<?= $field ?>Billing"><i class="fas fa-fw fa-file-invoice-dollar text-secondary me-1"></i>Billing</label>
                </div>
                <div class="form-text mt-0">Invoices, quotes and payments</div>
            </div>
        </div>
        <div class="form-text">Without either, they see the tickets they raise.</div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="send_portal_invites" class="btn btn-primary text-bold"><i class="fas fa-paper-plane me-2"></i>Send invites</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
