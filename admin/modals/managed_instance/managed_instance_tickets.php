<?php

/*
 * Problem Tickets (Admin > Managed Instances) - which of the instances' problems open tickets,
 * and the category they get (managerIssueTickets() in functions/managed.php). Posts
 * edit_managed_instance_tickets (admin/post/managed_instances.php).
 */

require_once '../../includes/modal_header.php';

enforceAdminPermission();

$settings = managerTicketSettings();

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-ticket-alt me-2"></i>Problem Tickets</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<?php if (!managerEnabled()) { ?>
    <div class="modal-body text-center text-secondary py-5">Managing other ITFlow installs is off in this install's config.php.</div>
<?php } elseif (!managerTicketsReady()) { ?>
    <div class="modal-body text-center text-secondary py-5">This needs this install's database update - apply it from Maintenance &gt; Update first.</div>
<?php } else { ?>
<form action="post.php" method="post" autocomplete="off" data-managed-tickets-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <?php if (!$settings['ticketing']) { ?>
            <div class="alert alert-warning small"><i class="fas fa-fw fa-exclamation-triangle me-2"></i>Ticketing is turned off, so no tickets open until it's back on.</div>
        <?php } ?>

        <div class="mb-3">
            <label class="form-label" for="managedTicketLevel">Problems open tickets</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-bell"></i></span>
                <select class="form-select select2" name="ticket_level" id="managedTicketLevel" data-managed-ticket-level>
                    <?php foreach (managerTicketLevels() as $level_key => $level_label) { ?>
                        <option value="<?= $level_key ?>" <?= $level_key === $settings['level'] ? 'selected' : '' ?>><?= escapeHtml($level_label) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-text">
                A problem that lasts <?= MANAGED_ISSUE_GRACE_MINUTES ?> minutes opens a ticket on the instance's client, or on your own company when it has none.
                <strong>Problems</strong>, at High: it has stopped checking in, cron isn't running, an update failed or its database update isn't applied, and a security update when it isn't updated automatically.
                <strong>Warnings</strong>, at Medium: backups failing or more than three days old, and errors being logged.
                The same problem again goes on its ticket while that's open. When it clears, the ticket is resolved unless someone has written on it. Suspended instances open none.
            </div>
        </div>

        <div class="mb-0 <?= $settings['level'] === '' ? 'd-none' : '' ?>" data-managed-ticket-more>
            <label class="form-label" for="managedTicketCategory">Ticket category</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-layer-group"></i></span>
                <select class="form-select select2" name="ticket_category_id" id="managedTicketCategory">
                    <option value="0">- Not categorized -</option>
                    <?= categoryOptionsHtml('Ticket', $settings['category_id'] ?: null) ?>
                </select>
                <?= categoryAddButtonHtml('Ticket') ?>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_managed_instance_tickets" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
// The category goes with problems that open tickets
(function () {
    var form = document.querySelector('[data-managed-tickets-form]:not([data-ready])');
    if (!form) {
        return;
    }
    form.setAttribute('data-ready', '1');
    var level = form.querySelector('[data-managed-ticket-level]');
    level.addEventListener('change', function () {
        form.querySelector('[data-managed-ticket-more]').classList.toggle('d-none', level.value === '');
    });
})();
</script>
<?php }

require_once '../../../includes/modal_footer.php';
