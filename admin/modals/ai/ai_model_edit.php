<?php

/*
 * Admin > AI Models - one model's window: Add (no id - ?provider= picks its
 * provider) or Edit. The model's name, suggested from what its provider lists;
 * what it is for, saying what that use case uses now; its temperature; and
 * the company's instructions that go with every request it handles. Try it
 * sends it a one-line request as it is in the window, unsaved. Save asks first
 * (check_ai_model, admin/post/ai_models.php), so what is wrong shows beside
 * each field.
 */

require_once '../../includes/modal_header.php';

$model_id = intval($_GET['id'] ?? 0);
$row = $model_id ? aiModelRow($model_id) : null;
if ($model_id && !$row) {
    echo json_encode(['error' => 'That AI model no longer exists.']);
    exit;
}

$provider_id = $row ? intval($row['ai_model_ai_provider_id']) : intval($_GET['provider'] ?? 0);
$providers = [];
$sql = mysqli_query($mysqli, "SELECT ai_provider_id, ai_provider_name, ai_provider_kind FROM ai_providers ORDER BY ai_provider_name");
while ($provider = mysqli_fetch_assoc($sql)) {
    $providers[intval($provider['ai_provider_id'])] = $provider;
}
if (!isset($providers[$provider_id])) {
    $provider_id = count($providers) === 1 ? array_key_first($providers) : 0;
}
$use_cases = aiUseCases();
$map = aiUseCaseMap(['model' => $model_id])['map'];
// A new one goes where there is nothing yet - General first
$use_case = $row ? (string) $row['ai_model_use_case'] : 'General';
if (!$row) {
    foreach ($use_cases as $case => $about) {
        if (!$map[$case]['own']) {
            $use_case = $case;
            break;
        }
    }
}
// What each use case has now, without this one - so picking one says what it takes over, or that it won't be used
$taken = [];
foreach ($map as $case => $lands) {
    $taken[$case] = $lands['own'] ? ['name' => $lands['model']['ai_model_name'], 'provider' => $lands['model']['ai_provider_name'], 'first' => intval($lands['model']['ai_model_id']) < ($model_id ?: PHP_INT_MAX)] : null;
}
$temperature = $row && $row['ai_model_temperature'] !== null ? rtrim(rtrim(number_format(floatval($row['ai_model_temperature']), 2, '.', ''), '0'), '.') : '';

$error_for = function ($field) {
    return '<div class="invalid-feedback" data-error-for="' . $field . '"></div>';
};

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-brain me-2"></i><?= $row ? '<strong>' . escapeHtml($row['ai_model_name']) . '</strong>' : 'Add AI Model' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" novalidate data-ai-model-form data-taken="<?= escapeHtml(json_encode($taken)) ?>" data-csrf="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="model_id" value="<?= $model_id ?>">

    <div class="modal-body">

        <?php if (!$providers) { ?>
            <div class="alert alert-info py-2 small"><i class="fas fa-fw fa-info-circle me-1"></i>Add an AI provider first - a model is on one.</div>
        <?php } ?>

        <div class="mb-3">
            <label class="form-label" for="aiModelProvider">Provider <strong class="text-danger">*</strong></label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-robot"></i></span>
                <select class="form-select select2" name="provider" id="aiModelProvider" data-ai-model-provider>
                    <option value="">- Pick a provider -</option>
                    <?php foreach ($providers as $id => $provider) { ?>
                        <option value="<?= $id ?>" <?= $id === $provider_id ? 'selected' : '' ?><?= selectOptionData(['icon' => aiProviderIcon($provider['ai_provider_kind']), 'description' => aiProviderKind($provider['ai_provider_kind'])['name'], 'example' => aiProviderKind($provider['ai_provider_kind'])['example']]) ?>><?= escapeHtml($provider['ai_provider_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <?= $error_for('provider') ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="aiModelName">Model <strong class="text-danger">*</strong></label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-brain"></i></span>
                <input type="text" class="form-control font-monospace" name="model" id="aiModelName" maxlength="200" spellcheck="false" list="aiModelList" value="<?= escapeHtml($row['ai_model_name'] ?? '') ?>" placeholder="As the provider names it" data-ai-model-name>
            </div>
            <?= $error_for('model') ?>
            <datalist id="aiModelList" data-ai-model-list></datalist>
            <div class="form-text" data-ai-model-hint>As the provider names it.</div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="aiModelUseCase">For <strong class="text-danger">*</strong></label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-th-list"></i></span>
                <select class="form-select select2" name="use_case" id="aiModelUseCase" data-ai-model-use-case>
                    <?php foreach ($use_cases as $case => $about) { ?>
                        <option value="<?= escapeHtml($case) ?>" <?= $case === $use_case ? 'selected' : '' ?><?= selectOptionData(['description' => $about]) ?>><?= escapeHtml($case) ?></option>
                    <?php } ?>
                    <?php if ($row && !isset($use_cases[$use_case])) { ?>
                        <option value="<?= escapeHtml($use_case) ?>" selected><?= escapeHtml($use_case) ?> (not used)</option>
                    <?php } ?>
                </select>
            </div>
            <?= $error_for('use_case') ?>
            <div class="form-text" data-ai-use-case-note></div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="aiModelPrompt">Instructions</label>
            <textarea class="form-control" rows="4" name="prompt" id="aiModelPrompt" maxlength="4000" placeholder="Write in US English, friendly and plain. We are PittPC. Never promise a time a fix will be done."><?= escapeHtml($row['ai_model_prompt'] ?? '') ?></textarea>
            <?= $error_for('prompt') ?>
            <div class="form-text">Optional - your house style, sent with everything this model is asked, after what each feature asks of it.</div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="aiModelTemperature">Temperature</label>
            <div class="input-group has-validation" style="max-width: 16rem;">
                <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                <input type="text" class="form-control" name="temperature" id="aiModelTemperature" inputmode="decimal" maxlength="5" value="<?= escapeHtml($temperature) ?>" placeholder="The model's own">
            </div>
            <?= $error_for('temperature') ?>
            <div class="form-text">Leave it empty - newer models, Claude's and OpenAI's reasoning ones among them, turn away any other. Lower is plainer, higher more varied.</div>
        </div>

        <div class="d-flex align-items-start gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" data-ai-try title="Sends it a one-line request, as it is here - recorded in AI Usage as a test"><i class="fas fa-fw fa-paper-plane me-1"></i>Try it</button>
            <div class="small pt-1 min-w-0" data-ai-try-result></div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="<?= $row ? 'edit_ai_model' : 'add_ai_model' ?>" class="btn btn-primary text-bold" data-ai-save <?= $providers ? '' : 'disabled' ?>>
            <i class="fa fa-check me-2"></i><?= $row ? 'Save' : 'Add' ?>
        </button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
// The provider's models suggested, what a use case has now said, Try it, and Save asked first
(function () {
    var form = document.querySelector('[data-ai-model-form]:not([data-ready])');
    if (!form) {
        return;
    }
    form.setAttribute('data-ready', '1');
    var taken = JSON.parse(form.getAttribute('data-taken'));
    var provider = form.querySelector('[data-ai-model-provider]');
    var modelName = form.querySelector('[data-ai-model-name]');
    var modelList = form.querySelector('[data-ai-model-list]');
    var modelHint = form.querySelector('[data-ai-model-hint]');
    var useCase = form.querySelector('[data-ai-model-use-case]');
    var useCaseNote = form.querySelector('[data-ai-use-case-note]');
    var tryButton = form.querySelector('[data-ai-try]');
    var tryResult = form.querySelector('[data-ai-try-result]');
    var save = form.querySelector('[data-ai-save]');
    var checked = false;

    function escapeText(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showUseCase() {
        var now = taken[useCase.value];
        if (!now) {
            useCaseNote.textContent = useCase.value === 'General' ? 'Everything without a model of its own uses it.' : 'Nothing else is for ' + useCase.value + ' - this one will be used for it, instead of General.';
            useCaseNote.className = 'form-text';
        } else if (now.first) {
            useCaseNote.textContent = now.name + ' on ' + now.provider + ' is for ' + useCase.value + ' already, and is the one used - this one won\'t be, while it is.';
            useCaseNote.className = 'form-text text-warning-emphasis';
        } else {
            useCaseNote.textContent = 'This one is used for ' + useCase.value + ' - ' + now.name + ' is for it too, but was added after.';
            useCaseNote.className = 'form-text';
        }
    }
    useCase.addEventListener('change', showUseCase);
    showUseCase();

    // What the provider has - only the answer for the provider picked last counts
    var asking = 0;
    function loadModels() {
        var asked = ++asking;
        modelList.innerHTML = '';
        var option = provider.options[provider.selectedIndex];
        var example = option ? option.getAttribute('data-example') : '';
        modelName.placeholder = example ? 'Like ' + example : 'As the provider names it';
        if (!provider.value) {
            modelHint.textContent = 'As the provider names it.';
            return;
        }
        modelHint.textContent = 'Asking the provider what it has...';
        var data = new FormData();
        data.append('csrf_token', form.getAttribute('data-csrf'));
        data.append('ai_provider_models', provider.value);
        fetch(form.getAttribute('action'), { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (answer) {
                if (asked !== asking) {
                    return;
                }
                modelList.innerHTML = '';
                (answer.models || []).forEach(function (model) {
                    var item = document.createElement('option');
                    item.value = model;
                    modelList.appendChild(item);
                });
                if (!answer.ok) {
                    modelHint.textContent = 'As the provider names it - it couldn\'t be asked what it has: ' + answer.error;
                } else if (!answer.listed) {
                    modelHint.textContent = 'Your deployment\'s name, as it is in Azure.';
                } else {
                    modelHint.textContent = 'As the provider names it - ' + answer.models.length + ' to pick from as you type.';
                }
                modelHint.setAttribute('data-ai-models-loaded', String((answer.models || []).length));
            })
            .catch(function () {
                modelHint.textContent = 'As the provider names it.';
            });
    }
    provider.addEventListener('change', loadModels);
    loadModels();

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

    function ask(tryIt) {
        var data = new FormData(form);
        data.append('check_ai_model', '1');
        if (tryIt) {
            data.append('try', '1');
        }
        return fetch(form.getAttribute('action'), { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); });
    }

    tryButton.addEventListener('click', function () {
        var label = tryButton.innerHTML;
        tryButton.disabled = true;
        tryButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Asking';
        tryResult.innerHTML = '';
        ask(true)
            .then(function (answer) {
                clearErrors();
                if (answer.ok) {
                    tryResult.innerHTML = '<div class="text-success-emphasis" data-ai-try-ok><i class="fas fa-fw fa-check-circle me-1"></i>It answered in ' + (answer.ms / 1000).toFixed(1) + 's:</div>'
                        + '<div class="fst-italic text-body-secondary text-break">' + escapeText(answer.reply) + '</div>';
                } else {
                    showErrors(answer.errors || {});
                    tryResult.innerHTML = '<span class="text-danger-emphasis" data-ai-try-failed><i class="fas fa-fw fa-times-circle me-1"></i>Not yet - see above.</span>';
                }
            })
            .catch(function () {
                tryResult.innerHTML = '<span class="text-danger-emphasis">Couldn\'t try it - try again.</span>';
            })
            .finally(function () {
                tryButton.disabled = false;
                tryButton.innerHTML = label;
            });
    });

    form.addEventListener('submit', function (e) {
        if (checked) {
            return;
        }
        e.preventDefault();
        var label = save.innerHTML;
        save.disabled = true;
        save.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking';
        ask(false)
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
