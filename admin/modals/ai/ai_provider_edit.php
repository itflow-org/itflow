<?php

/*
 * Admin > AI Providers - one provider's window: Add (no id) or Edit. Its kind
 * fills in its address and says where its key is made; the key is never put
 * back into the page, so left empty it stays. Test asks the provider for its
 * models, which shows they work and suggests the model to start with. Save
 * asks first (check_ai_provider, admin/post/ai_provider.php): what is wrong
 * shows beside each field and the window stays open - a provider that doesn't
 * answer can still be saved, once said.
 */

require_once '../../includes/modal_header.php';

$provider_id = intval($_GET['id'] ?? 0);
$row = $provider_id ? aiProviderRow($provider_id) : null;
if ($provider_id && !$row) {
    echo json_encode(['error' => 'That AI provider no longer exists.']);
    exit;
}

$kinds = aiProviderKinds();
$kind_key = $row ? (string) $row['ai_provider_kind'] : 'openai';
$kind_key = isset($kinds[$kind_key]) ? $kind_key : 'custom';
$kind = $kinds[$kind_key];
$has_key = $row && trim((string) $row['ai_provider_api_key']) !== '';
$redact = $row ? intval($row['ai_provider_redact']) === 1 : true;
// Adding the first, the model to start with comes along - for everything
$offer_first_model = !$row && !getAiModel('General');

// What the window's script needs of each kind
$kinds_js = [];
foreach ($kinds as $key => $info) {
    $kinds_js[$key] = ['name' => $info['name'], 'url' => $info['url'], 'key' => $info['key'], 'keysUrl' => $info['keys_url'], 'local' => $info['local'], 'example' => $info['example']];
}

// Where what is wrong with a field shows
$error_for = function ($field) {
    return '<div class="invalid-feedback" data-error-for="' . $field . '"></div>';
};

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-robot me-2"></i><?= $row ? '<strong>' . escapeHtml($row['ai_provider_name']) . '</strong>' : 'Add AI Provider' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" novalidate data-ai-provider-form data-kinds="<?= escapeHtml(json_encode($kinds_js)) ?>" data-has-key="<?= $has_key ? '1' : '0' ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="provider_id" value="<?= $provider_id ?>">
    <input type="hidden" name="save_anyway" value="" data-save-anyway>

    <div class="modal-body">

        <div class="mb-3">
            <label class="form-label" for="aiProviderKind">Kind <strong class="text-danger">*</strong></label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-shapes"></i></span>
                <select class="form-select select2" name="kind" id="aiProviderKind" data-ai-kind>
                    <?php foreach ($kinds as $key => $info) { ?>
                        <option value="<?= $key ?>" <?= $key === $kind_key ? 'selected' : '' ?><?= selectOptionData(['icon' => aiProviderIcon($key), 'description' => $info['local'] ? 'Runs on your own network - nothing leaves it' : ($key === 'custom' ? 'Any other service that speaks the OpenAI API' : (string) parse_url($info['url'], PHP_URL_HOST))]) ?>><?= escapeHtml($info['name']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <?= $error_for('kind') ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="aiProviderName">Name <strong class="text-danger">*</strong></label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <input type="text" class="form-control" name="name" id="aiProviderName" maxlength="200" value="<?= escapeHtml($row ? $row['ai_provider_name'] : $kind['name']) ?>" placeholder="What you call it">
            </div>
            <?= $error_for('name') ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="aiProviderUrl">Address <strong class="text-danger">*</strong></label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                <input type="url" class="form-control font-monospace small" name="url" id="aiProviderUrl" maxlength="500" spellcheck="false" value="<?= escapeHtml($row ? $row['ai_provider_api_url'] : $kind['url']) ?>" placeholder="https://.../v1/chat/completions">
            </div>
            <?= $error_for('url') ?>
            <div class="form-text">Its chat completions address - filled in for the kind picked. One ending /v1 has /chat/completions put on.</div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="aiProviderKey">API key <strong class="text-danger <?= $kind['key'] && !$has_key ? '' : 'd-none' ?>" data-ai-key-required>*</strong></label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                <input type="password" class="form-control font-monospace" name="api_key" id="aiProviderKey" maxlength="500" spellcheck="false" autocomplete="new-password"
                    placeholder="<?= $has_key ? 'Saved - type a new one to replace it' : 'Paste its API key' ?>">
            </div>
            <?= $error_for('api_key') ?>
            <div class="form-text" data-ai-key-hint></div>
            <?php if ($has_key) { ?>
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="clear_key" value="1" id="aiProviderClearKey">
                    <label class="form-check-label small" for="aiProviderClearKey">Remove the saved key</label>
                </div>
            <?php } ?>
        </div>

        <div class="mb-3">
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" name="redact" value="1" id="aiProviderRedact" <?= $redact ? 'checked' : '' ?>>
                <label class="form-check-label" for="aiProviderRedact">Take passwords and keys out of what is sent</label>
            </div>
            <div class="form-text ps-5">Passwords, PINs, API keys, private keys, 2FA links and card numbers in a reply or ticket go as placeholders, and are put back in the answer - the provider never sees them.</div>
        </div>

        <?php if ($offer_first_model) { ?>
            <div class="mb-3">
                <label class="form-label" for="aiProviderFirstModel">Model to use</label>
                <div class="input-group has-validation">
                    <span class="input-group-text"><i class="fa fa-fw fa-brain"></i></span>
                    <input type="text" class="form-control font-monospace" name="first_model" id="aiProviderFirstModel" maxlength="200" spellcheck="false" list="aiProviderModelList" placeholder="<?= escapeHtml($kind['example']) ?>" data-ai-first-model>
                </div>
                <?= $error_for('first_model') ?>
                <div class="form-text">For everything - Reword, ticket summaries and document drafts. Test lists the ones it has; more can be added in AI Models.</div>
            </div>
        <?php } ?>
        <datalist id="aiProviderModelList" data-ai-model-list></datalist>

        <div class="d-flex align-items-start gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" data-ai-test><i class="fas fa-fw fa-plug me-1"></i>Test</button>
            <div class="small pt-1" data-ai-test-result></div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="<?= $row ? 'edit_ai_provider' : 'add_ai_provider' ?>" class="btn btn-primary text-bold" data-ai-save>
            <i class="fa fa-check me-2"></i><?= $row ? 'Save' : 'Add' ?>
        </button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
// Its kind fills in the address and name, and says about the key. Test and Save ask first, so what is wrong shows here
(function () {
    var form = document.querySelector('[data-ai-provider-form]:not([data-ready])');
    if (!form) {
        return;
    }
    form.setAttribute('data-ready', '1');
    var kinds = JSON.parse(form.getAttribute('data-kinds'));
    var hasKey = form.getAttribute('data-has-key') === '1';
    var kindSelect = form.querySelector('[data-ai-kind]');
    var name = form.querySelector('[name="name"]');
    var url = form.querySelector('[name="url"]');
    var keyHint = form.querySelector('[data-ai-key-hint]');
    var keyRequired = form.querySelector('[data-ai-key-required]');
    var save = form.querySelector('[data-ai-save]');
    var saveLabel = save.innerHTML;
    var saveAnyway = form.querySelector('[data-save-anyway]');
    var testButton = form.querySelector('[data-ai-test]');
    var testResult = form.querySelector('[data-ai-test-result]');
    var modelList = form.querySelector('[data-ai-model-list]');
    var firstModel = form.querySelector('[data-ai-first-model]');
    var kind = kindSelect.value;
    var checked = false;

    function escapeText(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function isPreset(field, value) {
        return value === '' || Object.keys(kinds).some(function (key) { return kinds[key][field] === value; });
    }

    function showKind() {
        var info = kinds[kind];
        keyRequired.classList.toggle('d-none', !info.key || hasKey);
        if (info.local) {
            keyHint.textContent = info.name + ' runs on your own network and needs no key unless you set one. ITFlow has to be able to reach it - localhost is the ITFlow server itself.';
        } else if (info.keysUrl) {
            keyHint.innerHTML = 'Made in <a href="' + escapeText(info.keysUrl) + '" target="_blank" rel="noopener noreferrer">' + escapeText(info.name) + '\'s console <i class="fas fa-external-link-alt fa-xs"></i></a> - give ITFlow a key of its own, so it can be turned off on its own.';
        } else {
            keyHint.textContent = 'If it needs one - sent as a bearer token.';
        }
        if (firstModel) {
            firstModel.placeholder = info.example;
        }
    }

    // Anything changed - asked again before it is saved
    function reset() {
        checked = false;
        saveAnyway.value = '';
        save.innerHTML = saveLabel;
        save.classList.remove('btn-warning');
        save.classList.add('btn-primary');
    }
    form.querySelectorAll('input:not([type=hidden])').forEach(function (el) {
        el.addEventListener('input', reset);
    });

    kindSelect.addEventListener('change', function () {
        var was = kinds[kind];
        kind = kindSelect.value;
        var info = kinds[kind];
        // What the last kind filled in follows the new one; what was typed stays. Another kind is named by you
        if (isPreset('name', name.value.trim())) {
            name.value = kind === 'custom' ? '' : info.name;
        }
        if (isPreset('url', url.value.trim()) || url.value.trim() === was.url) {
            url.value = info.url;
        }
        showKind();
        reset();
    });
    showKind();

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
        if (first && first.offsetParent !== null) {
            first.focus();
        }
    }

    function showModels(answer) {
        modelList.innerHTML = '';
        (answer.models || []).forEach(function (model) {
            var option = document.createElement('option');
            option.value = model;
            modelList.appendChild(option);
        });
        // As it will be saved - /chat/completions put on
        if (answer.url && answer.url !== url.value.trim()) {
            url.value = answer.url;
        }
        var count = (answer.models || []).length;
        if (!answer.listed) {
            testResult.innerHTML = '<span class="text-body-secondary" data-ai-test-unlisted><i class="fas fa-fw fa-info-circle me-1"></i>' + escapeText(kinds[kind].name) + ' doesn\'t list its models - use Try it on its model once it is added.</span>';
        } else {
            testResult.innerHTML = '<span class="text-success-emphasis" data-ai-test-ok><i class="fas fa-fw fa-check-circle me-1"></i>It answered - ' + count + ' model' + (count === 1 ? '' : 's') + ' there.</span>'
                + (firstModel && count ? ' <span class="text-body-secondary">Pick the one to use above.</span>' : '');
        }
    }

    function ask(test) {
        var data = new FormData(form);
        data.append('check_ai_provider', '1');
        if (test) {
            data.append('test', '1');
        }
        return fetch(form.getAttribute('action'), { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); });
    }

    testButton.addEventListener('click', function () {
        var label = testButton.innerHTML;
        testButton.disabled = true;
        testButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Testing';
        testResult.innerHTML = '';
        ask(true)
            .then(function (answer) {
                clearErrors();
                if (answer.ok) {
                    showModels(answer);
                } else {
                    showErrors(answer.errors || {});
                    testResult.innerHTML = '<span class="text-danger-emphasis" data-ai-test-failed><i class="fas fa-fw fa-times-circle me-1"></i>Not yet - see above.</span>';
                }
            })
            .catch(function () {
                testResult.innerHTML = '<span class="text-danger-emphasis">Couldn\'t test it - try again.</span>';
            })
            .finally(function () {
                testButton.disabled = false;
                testButton.innerHTML = label;
            });
    });

    form.addEventListener('submit', function (e) {
        // Asked already - or said it doesn't answer, and Save anyway pressed: it goes
        if (checked || saveAnyway.value === '1') {
            checked = true;
            return;
        }
        e.preventDefault();
        save.disabled = true;
        save.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking';
        ask(false)
            .then(function (answer) {
                clearErrors();
                save.disabled = false;
                if (answer.ok) {
                    if (answer.asked) {
                        showModels(answer);
                    }
                    checked = true;
                    save.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving';
                    form.requestSubmit(save);
                    save.disabled = true;
                    return;
                }
                save.innerHTML = saveLabel;
                showErrors(answer.errors || {});
                // Only that it didn't answer - it can be saved anyway
                if (answer.soft) {
                    saveAnyway.value = '1';
                    save.innerHTML = '<i class="fa fa-check me-2"></i>Save anyway';
                    save.classList.remove('btn-primary');
                    save.classList.add('btn-warning');
                }
            })
            .catch(function () {
                // Couldn't ask - send it as it is, and the save says what is wrong
                checked = true;
                save.disabled = false;
                save.innerHTML = saveLabel;
                form.requestSubmit(save);
            });
    });
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
