<?php

/*
 * Your account > Profile - your picture, name, email and the signature that
 * goes on your ticket replies. Your email signs you in, so changing it takes
 * your current password, and the old address is told.
 */

require_once "includes/inc_all_user.php";

$me = userRow($session_user_id);
$has_avatar = trim((string) $me['user_avatar']) !== '';

?>

<div class="card">
    <div class="card-header bg-dark">
        <h3 class="card-title"><i class="fas fa-fw fa-id-badge me-2"></i>Profile</h3>
    </div>
    <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off" id="profileForm">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-3">
                    <div class="d-flex flex-lg-column align-items-center gap-3 text-lg-center">
                        <span data-profile-avatar-preview><?= userAvatar($session_user_id, $me['user_name'], $me['user_avatar'], 'xl') ?></span>
                        <div class="w-100 min-w-0">
                            <label class="form-label small mb-1" for="profileAvatar">Picture</label>
                            <input type="file" class="form-control form-control-sm" id="profileAvatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" data-profile-avatar-input>
                            <div class="form-text">JPG, PNG, GIF or WebP, up to 5 MB.</div>
                            <?php if ($has_avatar) { ?>
                                <div class="form-check small mt-1 d-inline-block text-start">
                                    <input class="form-check-input" type="checkbox" name="remove_avatar" value="1" id="profileRemoveAvatar" data-profile-avatar-remove>
                                    <label class="form-check-label" for="profileRemoveAvatar">Remove it - show my initials</label>
                                </div>
                            <?php } ?>
                        </div>
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
                                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                </div>
                                <div class="form-text">You'll sign in with the new email from now on - your old address is told it changed.</div>
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

    // The picture - shown as soon as it is picked
    var input = form.querySelector('[data-profile-avatar-input]');
    var preview = form.querySelector('[data-profile-avatar-preview]');
    var original = preview.innerHTML;
    var remove = form.querySelector('[data-profile-avatar-remove]');
    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (file && /^image\//.test(file.type)) {
            var img = document.createElement('img');
            img.className = 'itflow-contact-avatar itflow-contact-avatar-xl';
            img.alt = '';
            img.src = URL.createObjectURL(file);
            preview.replaceChildren(img);
            preview.classList.remove('opacity-25');
            if (remove) {
                remove.checked = false;
            }
        } else {
            preview.innerHTML = original;
        }
    });
    if (remove) {
        remove.addEventListener('change', function () {
            if (remove.checked) {
                input.value = '';
                preview.innerHTML = original;
            }
            preview.classList.toggle('opacity-25', remove.checked);
        });
    }

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
