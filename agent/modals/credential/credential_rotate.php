<?php

/*
 * Change a credential's password - the rotation every MSP owes its clients.
 * Make one up (a passphrase, or random characters), copy it to set it on
 * the device or site, and save it: the password and the date it changed are
 * updated and the change is logged. Nothing else about the credential moves.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_credential', 2);

$credential_id = intval($_GET['id'] ?? 0);
$credential = credentialRow($credential_id);
if (!$credential) {
    echo json_encode(['error' => 'That credential no longer exists.']);
    exit;
}
$client_id = intval($credential['credential_client_id']);
enforceClientAccess();

$age = credentialAge($credential['credential_password_changed_at']);
$vault_open = encryptCredentialEntry('') !== false;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-sync-alt me-2"></i>Change password: <strong><?= escapeHtml($credential['credential_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-credential-rotate>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="credential_id" value="<?= $credential_id ?>">
    <div class="modal-body">
        <?php if (!$vault_open) { ?>
            <div class="alert alert-warning small"><i class="fas fa-lock me-2"></i>Your vault session has expired, so the new password cannot be encrypted. Sign out and back in first.</div>
        <?php } ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3 small text-body-secondary">
            <?= credentialAgePill($age) ?>
            <?php $username = credentialUsername($credential); if ($username !== '') { ?>
                <span class="d-inline-flex align-items-center"><i class="fas fa-user me-1"></i><?= credentialUsernameHtml($username) ?></span>
            <?php } ?>
        </div>
        <label class="form-label">New password <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
            <input type="text" class="form-control font-monospace" name="password" maxlength="350" required autocomplete="new-password" data-rotate-password>
            <button class="btn btn-outline-secondary" type="button" title="Copy" data-rotate-copy><i class="far fa-fw fa-copy"></i></button>
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Make one up"><i class="fas fa-fw fa-magic"></i></button>
            <div class="dropdown-menu dropdown-menu-end">
                <a class="dropdown-item" href="#" data-rotate-generate="passphrase"><i class="fas fa-fw fa-font text-secondary me-2"></i>Passphrase</a>
                <a class="dropdown-item" href="#" data-rotate-generate="20"><i class="fas fa-fw fa-random text-secondary me-2"></i>Random, 20 characters</a>
                <a class="dropdown-item" href="#" data-rotate-generate="32"><i class="fas fa-fw fa-random text-secondary me-2"></i>Random, 32 characters</a>
            </div>
        </div>
        <div class="form-text">Set it on the device or site first, then save it here - the old one is gone once you do.</div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="rotate_credential" class="btn btn-primary text-bold" <?= $vault_open ? '' : 'disabled' ?>><i class="fa fa-check me-2"></i>Save new password</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    itflowReady(function () {
        var form = document.querySelector('form[data-credential-rotate]:not([data-wired])');
        if (!form) {
            return;
        }
        form.dataset.wired = '1';
        var input = form.querySelector('[data-rotate-password]');
        var copy = form.querySelector('[data-rotate-copy]');
        function randomPassword(length) {
            var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*-_=+?';
            var bytes = new Uint32Array(length);
            window.crypto.getRandomValues(bytes);
            return Array.prototype.map.call(bytes, function (b) { return chars[b % chars.length]; }).join('');
        }
        form.querySelectorAll('[data-rotate-generate]').forEach(function (item) {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                if (item.dataset.rotateGenerate === 'passphrase') {
                    itflowGet('ajax.php', { get_readable_pass: 'true' }, function (data) {
                        input.value = JSON.parse(data);
                    });
                } else {
                    input.value = randomPassword(parseInt(item.dataset.rotateGenerate, 10));
                }
            });
        });
        copy.addEventListener('click', function () {
            itflowCopyText(input.value, copy).then(
                function () { flashTooltip(copy, 'Copied!'); },
                function () {}
            );
        });
    });
</script>

<?php
require_once '../../../includes/modal_footer.php';
