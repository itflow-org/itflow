<?php

/*
 * Your account > Profile - your picture, name, email and the signature that
 * goes on your ticket replies. Your email signs you in, so changing it takes
 * your current password, and the old address gets a notice.
 */

require_once "includes/inc_all_user.php";

$me = userRow($session_user_id);
$has_avatar = trim((string) $me['user_avatar']) !== '';

?>

<div class="card">
    <div class="card-header bg-dark">
        <h3 class="card-title"><i class="fas fa-fw fa-id-badge me-2"></i>Profile</h3>
    </div>
    <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off" id="profileForm" data-autofill>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-3">
                    <div class="d-flex flex-lg-column align-items-center gap-3 text-lg-center">
                        <?= pictureField('avatar', $has_avatar ? 'remove_avatar' : '', userAvatar($session_user_id, $me['user_name'], $me['user_avatar'], 'xl'), userAvatar($session_user_id, $me['user_name'], '', 'xl'),
                            ['round' => true, 'title' => 'Your picture', 'class' => 'itflow-contact-avatar itflow-contact-avatar-xl', 'name' => 'name']) ?>
                        <div class="form-text mt-0">Click your picture to change it.</div>
                    </div>
                </div>

                <div class="col-lg-9">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                                <input type="text" class="form-control" name="name" placeholder="First and last name" maxlength="200" value="<?= escapeHtml($me['user_name']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fw fa-envelope"></i></span>
                                <input type="email" class="form-control" name="email" placeholder="You sign in with this" maxlength="200" value="<?= escapeHtml($me['user_email']) ?>" required data-profile-email data-original="<?= escapeHtml($me['user_email']) ?>">
                            </div>
                        </div>
                        <div class="col-12" data-profile-current hidden>
                            <div class="border border-warning-subtle bg-warning-subtle rounded p-3">
                                <label class="form-label">Your current password <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                                    <input type="password" class="form-control" data-toggle="password" name="current_password" placeholder="To change the email you sign in with" autocomplete="current-password">
                                    <span class="input-group-text"><i class="fas fa-fw fa-eye"></i></span>
                                </div>
                                <div class="form-text">You'll sign in with the new email from now on - your old address gets a notice that it changed.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fw <?= intval($me['role_is_admin']) ? 'fa-user-shield' : 'fa-user-tag' ?>"></i></span>
                                <input type="text" class="form-control" value="<?= escapeHtml($me['role_name'] ?? 'No role') ?>" disabled>
                            </div>
                            <div class="form-text">Set by an administrator.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sign in</label>
                            <div class="d-flex flex-wrap align-items-center gap-2 pt-1">
                                <?= userMfaPill($me['user_token'], $me['user_config_force_mfa']) ?>
                                <a class="small" href="user_security.php">Password and MFA</a>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Signature</label>
                            <textarea class="form-control tinymceTicket" name="signature" rows="4" placeholder="Added to the end of your ticket replies"><?= escapeHtml($me['user_config_signature'] ?? '') ?></textarea>
                            <div class="form-text">Added to the end of your ticket replies and the emails they send.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" name="edit_your_user_details" class="btn btn-primary"><i class="fas fa-check me-2"></i>Save</button>
        </div>
    </form>
</div>

<script>
(function () {
    var form = document.getElementById('profileForm');

    // A new email takes your current password
    var email = form.querySelector('[data-profile-email]');
    var current = form.querySelector('[data-profile-current]');
    var currentInput = current.querySelector('input');
    function emailChanged() {
        var changed = email.value.trim().toLowerCase() !== email.dataset.original.toLowerCase();
        current.hidden = !changed;
        currentInput.required = changed;
    }
    email.addEventListener('input', emailChanged);
    emailChanged();
})();
</script>

<?php
require_once "../../includes/footer.php";
