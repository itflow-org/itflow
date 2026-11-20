<?php

/*
 * Invite several agents at once - one per line, all with the same role (and
 * office, and whether two-step sign-in is required). Each gets their own invite
 * email to join and choose a password (functions/user_invite.php); client
 * access, for a role without admin, is set on each afterwards. Needs email set
 * up - one at a time from New User gives a link to pass on instead.
 */

require_once '../../includes/modal_header.php';

$role_id = intval($_GET['role'] ?? 0);
$roles = userRoles(0);
$ready = userInvitesReady() && userInviteMailReady();

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-users me-2"></i>Invite Several Users</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php if (!$ready) { ?>
            <div class="alert alert-warning small mb-0">
                <i class="fas fa-fw fa-envelope me-1"></i>Inviting several at once emails each of them their link, and email isn't set up. Set it up in <a href="settings_mail.php">Admin &gt; Mail</a>, or invite them one at a time from New User to get each link to pass on.
            </div>
        <?php } else { ?>
            <?php if (agentUserLimitReached()) { ?>
                <div class="alert alert-danger small"><?= agentUserLimitMessage() ?></div>
            <?php } ?>
            <div class="mb-3">
                <label class="form-label">Who <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text align-items-start pt-2"><i class="fas fa-fw fa-user-plus"></i></span>
                    <textarea class="form-control" name="people" rows="7" required maxlength="20000" placeholder="Jane Doe, jane@example.com&#10;Sam Lee <sam@example.com>&#10;alex.morgan@example.com"></textarea>
                </div>
                <div class="form-text">One per line - their name and email, or just the email, and the name is made from it. Up to <?= USER_INVITE_BULK_MAX ?> at a time. Anyone who already has an account is skipped.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Role <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-user-shield"></i></span>
                    <select class="form-select select2" name="role" required>
                        <?php if (!isset($roles[$role_id])) { ?>
                            <option value="">- Pick a role -</option>
                        <?php } ?>
                        <?php foreach ($roles as $id => $role) {
                            if ($role['role_archived_at']) {
                                continue;
                            } ?>
                            <option value="<?= $id ?>" <?= $role_id === $id ? 'selected' : '' ?><?= selectOptionRole($role) ?>><?= escapeHtml($role['role_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">The same for all of them. A role without admin sees every client until you limit one - in their Client access, once they've joined.</div>
            </div>
            <?= officeUserField(0) ?>
            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" role="switch" name="force_mfa" value="1" id="inviteBulkForceMfa">
                <label class="form-check-label" for="inviteBulkForceMfa">Require multi-factor authentication</label>
                <div class="form-text mt-0">They're asked to set it up when they join, and can't turn it off.</div>
            </div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if ($ready) { ?>
            <button type="submit" name="invite_users_bulk" class="btn btn-primary text-bold"><i class="fas fa-paper-plane me-2"></i>Send invites</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
