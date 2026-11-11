<?php

/*
 * Turn on portal access - for a portal user whose access was turned off
 * (Admin > Users > Client portal). How they sign in - a password, or their
 * Microsoft 365 account when that is set up - and whether to email them the
 * invite: a link to set their password, never a password.
 */

require_once '../../includes/modal_header.php';

$portal_user = portalUserRow($_GET['id'] ?? 0);

ob_start();

if (!$portal_user) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fas fa-fw fa-user-circle me-2"></i>Portal access</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5">That portal user is gone - their contact may have been deleted.</div>
<?php } else {
    $user_id = intval($portal_user['user_id']);
    $name = escapeHtml($portal_user['contact_name']);
    $methods = portalSignInMethods();
    $archived = !empty($portal_user['client_archived_at']) || !empty($portal_user['contact_archived_at']);
    $mail_ready = portalMailReady() && filter_var($portal_user['user_email'], FILTER_VALIDATE_EMAIL);
    ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-user-check me-2"></i>Turn on portal access for <?= $name ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="user_id" value="<?= $user_id ?>">

        <div class="modal-body">
            <div class="d-flex align-items-center gap-3 mb-3">
                <?= contactAvatar($portal_user['client_id'], $portal_user['contact_id'], $portal_user['contact_name'], $portal_user['contact_photo'], 'lg') ?>
                <div class="min-w-0">
                    <div class="fw-semibold"><?= $name ?></div>
                    <div class="small text-body-secondary text-truncate"><?= escapeHtml($portal_user['user_email']) ?> · <?= escapeHtml($portal_user['client_name']) ?></div>
                </div>
            </div>

            <?php if ($archived) { ?>
                <div class="alert alert-warning small mb-0"><i class="fas fa-exclamation-triangle me-2"></i><?= escapeHtml($portal_user['portal_off_why']) ?> - restore it first, then turn their access on.</div>
            <?php } else { ?>
                <div class="mb-3">
                    <label class="form-label">They sign in</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-sign-in-alt"></i></span>
                        <select class="form-select select2" name="method">
                            <?php foreach ($methods as $method => $label) { ?>
                                <option value="<?= $method ?>"<?= selectOptionData(['icon' => $method === 'azure' ? 'fab fa-microsoft' : 'fas fa-key']) ?>><?= escapeHtml($label) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-text">With a password, they choose their own from an emailed link - the one they had still works too.</div>
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="send_email" value="1" id="portalUserAccessEmail<?= $user_id ?>" <?= $mail_ready ? 'checked' : 'disabled' ?>>
                    <label class="form-check-label" for="portalUserAccessEmail<?= $user_id ?>">Email them how to sign in</label>
                    <div class="form-text mt-0"><?= $mail_ready ? 'A link to set a password, or for Microsoft 365 the sign-in page.' : 'Outgoing mail is not set up, or their address is not one mail can go to.' ?></div>
                </div>
            <?php } ?>
        </div>
        <div class="modal-footer">
            <?php if (!$archived) { ?>
                <button type="submit" name="portal_user_access_on" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Turn on</button>
            <?php } ?>
            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
        </div>
    </form>
<?php }

require_once "../../../includes/modal_footer.php";
