<?php

/*
 * Setup - Your account: the first user, an administrator (add_user in
 * setup/index.php, checked by setupUserForm()). Made once - an install that
 * has one carries on. A mistake comes back with the name and email kept.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

$first_error = array_key_first($user_errors);

?>
<div class="card itflow-setup-card">
    <div class="card-body p-4">
        <h1 class="h4 fw-semibold mb-1"><i class="fas fa-user text-body-secondary me-2"></i>Your account</h1>
        <p class="text-body-secondary mb-4">The first account - an administrator. The rest of your team is added from Admin &gt; Users once you are signed in.</p>

        <?php if ($install_is_live) { ?>
            <div class="d-flex gap-3 align-items-start mb-4" data-step-done>
                <i class="fas fa-check-circle text-success fa-lg mt-1"></i>
                <div class="fw-semibold">This install already has its first account.</div>
            </div>
            <a href="?<?= $resume_step ?>" class="btn btn-primary" data-setup-next>Continue<i class="fas fa-arrow-right ms-2"></i></a>
        <?php } elseif (!$database_ready) { ?>
            <div class="alert alert-info mb-3"><i class="fas fa-info-circle me-2"></i>The database comes first.</div>
            <a href="?<?= $resume_step ?>" class="btn btn-primary">Set up the database<i class="fas fa-arrow-right ms-2"></i></a>
        <?php } else { ?>
            <?php if ($user_errors) { ?>
                <div class="alert alert-danger d-flex gap-2" role="alert" data-step-errors>
                    <i class="fas fa-times-circle mt-1"></i>
                    <div><?= count($user_errors) === 1 ? 'One thing to put right' : count($user_errors) . ' things to put right' ?> - and type the password again, both times.</div>
                </div>
            <?php } ?>
            <form method="post" action="?user" enctype="multipart/form-data" autocomplete="off" data-setup-form novalidate>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="setupUserName">Name <strong class="text-danger">*</strong></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            <input type="text" class="form-control<?= setupFieldInvalid($user_errors, 'name') ?>" id="setupUserName" name="name" value="<?= escapeHtml($user_form['name']) ?>" placeholder="Your full name" maxlength="200" autocomplete="name" required <?= $first_error === null || $first_error === 'name' ? 'autofocus' : '' ?>>
                        </div>
                        <?= setupFieldError($user_errors, 'name') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="setupUserEmail">Email <strong class="text-danger">*</strong></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                            <input type="email" class="form-control<?= setupFieldInvalid($user_errors, 'email') ?>" id="setupUserEmail" name="email" value="<?= escapeHtml($user_form['email']) ?>" placeholder="you@company.com" maxlength="200" autocomplete="email" required <?= $first_error === 'email' ? 'autofocus' : '' ?>>
                        </div>
                        <?= setupFieldError($user_errors, 'email') ?: '<div class="form-text">What you sign in with.</div>' ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="setupUserPassword">Password <strong class="text-danger">*</strong></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                            <input type="password" class="form-control<?= setupFieldInvalid($user_errors, 'password') ?>" id="setupUserPassword" data-toggle="password" name="password" placeholder="At least <?= USER_PASSWORD_MIN ?> characters" autocomplete="new-password" required minlength="<?= USER_PASSWORD_MIN ?>" <?= in_array($first_error, ['password', 'password_confirm', 'file'], true) ? 'autofocus' : '' ?>>
                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                        </div>
                        <?= setupFieldError($user_errors, 'password') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="setupUserPasswordConfirm">Password again <strong class="text-danger">*</strong></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                            <input type="password" class="form-control<?= setupFieldInvalid($user_errors, 'password_confirm') ?>" id="setupUserPasswordConfirm" data-toggle="password" name="password_confirm" placeholder="The same again" autocomplete="new-password" required data-setup-same-as="setupUserPassword">
                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                        </div>
                        <?= setupFieldError($user_errors, 'password_confirm') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="setupUserAvatar">Avatar</label>
                        <div class="d-flex gap-3 align-items-center">
                            <div class="itflow-setup-preview is-round" data-setup-preview="setupUserAvatar"><i class="fas fa-user"></i></div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fa fa-fw fa-image"></i></span>
                                    <input type="file" class="form-control<?= setupFieldInvalid($user_errors, 'file') ?>" id="setupUserAvatar" name="file" accept="image/jpeg,image/png,image/gif,image/webp" data-setup-image="JPG, PNG, GIF or WebP" data-setup-image-max="<?= SETUP_IMAGE_MAX_BYTES ?>">
                                </div>
                                <?= setupFieldError($user_errors, 'file') ?: '<div class="form-text">Optional - JPG, PNG, GIF or WebP, up to 5 MB.</div>' ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" name="add_user" class="btn btn-primary" data-setup-next>Create my account<i class="fas fa-arrow-right ms-2"></i></button>
                </div>
            </form>
        <?php } ?>
    </div>
</div>
