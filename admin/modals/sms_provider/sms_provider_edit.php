<?php

/*
 * Admin > Text Messaging - one provider's window: Add (no id, or from sms_provider_add.php)
 * or Edit. Its SID and secret - the secret never put back into the page, so left empty it
 * stays - the number texts go from, and whether it is on. Save asks first (check_sms_provider,
 * admin/post/sms_provider.php): what is wrong shows beside each field and the window stays
 * open; once all is right, it saves.
 */

require_once '../../includes/modal_header.php';

$provider_id = intval($_GET['id'] ?? 0);
$row = $provider_id ? smsProviderRow($provider_id) : null;
if ($provider_id && !$row) {
    echo json_encode(['error' => 'That texting provider no longer exists.']);
    exit;
}

$kinds = smsProviderKinds();
$taken = [];
$sql = mysqli_query($mysqli, "SELECT sms_provider_name FROM sms_providers");
while ($taken_row = mysqli_fetch_row($sql)) {
    $taken[$taken_row[0]] = true;
}
$free_kinds = array_diff_key($kinds, $taken);

$name = $row ? (string) $row['sms_provider_name'] : (string) (array_key_first($free_kinds) ?? array_key_first($kinds));
$kind = $kinds[$name] ?? reset($kinds);
$number = $row ? smsNumberText($row['sms_provider_number']) : '';

// Where what is wrong with a field shows
$error_for = function ($field) {
    return '<div class="invalid-feedback" data-error-for="' . $field . '"></div>';
};

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-sms me-2"></i><?= $row ? '<strong>' . escapeHtml($name) . '</strong>' : 'Add Texting Provider' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" novalidate data-sms-provider-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="provider_id" value="<?= $provider_id ?>">

    <div class="modal-body">

        <?php if (!$row && !$free_kinds) { ?>
            <div class="alert alert-info py-2 small mb-3"><i class="fas fa-fw fa-info-circle me-1"></i><?= escapeHtml(implode(' and ', array_keys($kinds))) ?> is set up already - edit that one.</div>
        <?php } ?>

        <?php if (!$row) { ?>
            <div class="mb-3">
                <label class="form-label" for="smsProviderName">Provider <strong class="text-danger">*</strong></label>
                <div class="input-group has-validation">
                    <span class="input-group-text"><i class="fa fa-fw fa-sms"></i></span>
                    <select class="form-select" name="provider" id="smsProviderName">
                        <?php foreach ($kinds as $kind_name => $kind_info) { ?>
                            <option value="<?= escapeHtml($kind_name) ?>" <?= $kind_name === $name ? 'selected' : '' ?> <?= isset($taken[$kind_name]) ? 'disabled' : '' ?>><?= escapeHtml($kind_name) ?><?= isset($taken[$kind_name]) ? ' - set up already' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?= $error_for('provider') ?>
                <div class="form-text"><?= escapeHtml($kind['about']) ?></div>
            </div>
        <?php } else { ?>
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="active" value="1" id="smsProviderActive" <?= intval($row['sms_provider_active']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="smsProviderActive">On - texts go out, and texts to the number come in</label>
                </div>
                <div class="form-text ps-5">Off, nothing is texted and texts to the number aren't taken in. Texts waiting to go wait.</div>
            </div>
        <?php } ?>

        <div class="mb-3">
            <label class="form-label" for="smsProviderDescription">Description</label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" id="smsProviderDescription" maxlength="250" placeholder="What it is for, if you like" value="<?= escapeHtml($row['sms_provider_description'] ?? '') ?>">
            </div>
            <?= $error_for('description') ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="smsProviderNumber">Number texts go from <strong class="text-danger">*</strong></label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                <input type="tel" class="form-control" name="number" id="smsProviderNumber" maxlength="30" placeholder="+1 412 555 0100" value="<?= escapeHtml($number) ?>">
            </div>
            <?= $error_for('number') ?>
            <div class="form-text">SMS-enabled in <?= escapeHtml($name) ?>'s portal - and, for a US number, registered for texting there, or carriers block what it sends.</div>
        </div>

        <h6 class="fw-semibold mt-4 mb-1">Keys</h6>
        <p class="small text-body-secondary mb-2">From <a href="<?= escapeHtml($kind['portal_url']) ?>" target="_blank" rel="noopener noreferrer"><?= escapeHtml($name) ?>'s portal <i class="fas fa-external-link-alt fa-xs"></i></a> - the gear at the top right, then API Keys.</p>

        <div class="mb-3">
            <label class="form-label" for="smsProviderUsername"><?= escapeHtml($kind['username_label']) ?> <strong class="text-danger">*</strong></label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                <input type="text" class="form-control font-monospace" name="username" id="smsProviderUsername" maxlength="250" spellcheck="false" value="<?= escapeHtml($row['sms_provider_username'] ?? '') ?>">
            </div>
            <?= $error_for('username') ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="smsProviderSecret"><?= escapeHtml($kind['secret_label']) ?> <?= $row ? '' : '<strong class="text-danger">*</strong>' ?></label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                <input type="password" class="form-control font-monospace" name="secret" id="smsProviderSecret" maxlength="500" spellcheck="false" autocomplete="new-password"
                    placeholder="<?= $row && trim((string) $row['sms_provider_secret']) !== '' ? 'Saved - type a new one to replace it' : '' ?>">
            </div>
            <?= $error_for('secret') ?>
        </div>

        <?php if ($row) { ?>
            <h6 class="fw-semibold mt-4 mb-1">Texts coming back</h6>
            <p class="small text-body-secondary mb-2">In <?= escapeHtml($name) ?>'s portal, open the number, then SMS: send texts to this Callback URL, by POST.</p>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                <input type="text" class="form-control font-monospace" readonly id="smsProviderInbound" value="<?= escapeHtml(smsInboundUrl($row)) ?>" aria-label="Where texts come back to">
                <button type="button" class="btn btn-outline-secondary clipboardjs" data-clipboard-target="#smsProviderInbound" title="Copy"><i class="far fa-fw fa-copy"></i></button>
            </div>
        <?php } else { ?>
            <div class="alert alert-light border small mb-0"><i class="fas fa-fw fa-info-circle me-1"></i>Once it's added, Text Messaging shows the address to paste into <?= escapeHtml($name) ?>'s portal, so texts to the number come back into ITFlow.</div>
        <?php } ?>

    </div>
    <div class="modal-footer">
        <button type="submit" name="<?= $row ? 'edit_sms_provider' : 'add_sms_provider' ?>" class="btn btn-primary text-bold" data-sms-provider-save <?= !$row && !$free_kinds ? 'disabled' : '' ?>>
            <i class="fa fa-check me-2"></i><?= $row ? 'Save' : 'Add' ?>
        </button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
(function () {
    var form = document.querySelector('[data-sms-provider-form]:not([data-ready])');
    if (!form) {
        return;
    }
    form.setAttribute('data-ready', '1');
    var save = form.querySelector('[data-sms-provider-save]');
    var checked = false;

    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('[data-error-for]').forEach(function (el) {
            el.textContent = '';
            el.classList.remove('d-block');
        });
    }
    function showErrors(errors) {
        var first = null;
        Object.keys(errors).forEach(function (field) {
            var input = form.querySelector('[name="' + field + '"]');
            var box = form.querySelector('[data-error-for="' + field + '"]');
            if (input) {
                input.classList.add('is-invalid');
                first = first || input;
            }
            if (box) {
                box.textContent = errors[field];
                box.classList.add('d-block');
            }
        });
        if (first) {
            first.focus();
        }
    }
    form.addEventListener('submit', function (e) {
        if (checked) {
            return;
        }
        e.preventDefault();
        var label = save.innerHTML;
        save.disabled = true;
        save.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking';
        var data = new FormData(form);
        data.append('check_sms_provider', '1');
        fetch(form.getAttribute('action'), { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (answer) {
                clearErrors();
                save.disabled = false;
                if (answer.ok) {
                    checked = true;
                    save.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving';
                    form.requestSubmit(save);
                    save.disabled = true;
                    return;
                }
                save.innerHTML = label;
                showErrors(answer.errors || {});
            })
            .catch(function () {
                // Couldn't ask - send it as it is, and the save says what is wrong
                checked = true;
                save.disabled = false;
                save.innerHTML = label;
                form.requestSubmit(save);
            });
    });
})();
</script>
<?php
require_once '../../../includes/modal_footer.php';
