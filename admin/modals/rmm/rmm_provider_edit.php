<?php

/*
 * Admin > RMM Providers - one provider's window: Add (no id) or Edit. Which RMM it is is
 * picked when it is added and stays; each kind brings its own fields (rmmProviderKinds() in
 * functions/rmm.php) - addresses, a key, a secret. A key or secret is never put back into the
 * page, so left empty it stays. A kind that has alerts can have them open tickets, from the
 * level picked, in a category; one that has device actions can have them turned on, off until
 * they are. Save asks first (check_rmm_provider, admin/post/rmm.php): what
 * is wrong shows beside each field and the window stays open; once all is right it saves, and
 * tries the connection.
 */

require_once '../../includes/modal_header.php';

$provider_id = intval($_GET['id'] ?? 0);
$row = $provider_id ? rmmProviderRow($provider_id) : null;
if ($provider_id && !$row) {
    echo json_encode(['error' => 'That RMM provider no longer exists.']);
    exit;
}
if ($row && !rmmKind($row)) {
    echo json_encode(['error' => 'This ITFlow doesn\'t know that provider\'s kind of RMM.']);
    exit;
}

$kinds = rmmProviderKinds();
$kind_key = $row ? (string) $row['rmm_provider_kind'] : (string) array_key_first($kinds);
$kind = $kinds[$kind_key];

// What the window's script needs of each kind - its name, to follow the kind picked, and whether it has alerts and device actions
$kinds_js = array_map(function ($info) {
    return ['name' => $info['name'], 'alerts' => isset($info['alerts'], $info['alert']), 'alertsHelp' => (string) ($info['alerts_help'] ?? ''),
        'actions' => !empty($info['actions']), 'actionsHelp' => (string) ($info['actions_help'] ?? '')];
}, $kinds);

// Device actions - once the 3.6.9 database update is in
$actions_offered = rmmActionsReady();

// Alerts open tickets - once the 3.6.8 database update is in, with ticketing on
$alerts_offered = rmmAlertsReady() && !empty($config_module_enable_ticketing);
$alert_level = (string) ($row['rmm_provider_alert_level'] ?? '');
$alert_category_id = intval($row['rmm_provider_alert_category_id'] ?? 0);

// Where what is wrong with a field shows
$error_for = function ($field) {
    return '<div class="invalid-feedback" data-error-for="' . $field . '"></div>';
};

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="<?= escapeHtml($kind['icon']) ?> fa-fw me-2" data-rmm-kind-icon></i><?= $row ? '<strong>' . escapeHtml(rmmProviderName($row)) . '</strong>' : 'Add RMM Provider' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" novalidate data-rmm-provider-form data-kinds="<?= escapeHtml(json_encode($kinds_js)) ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="provider_id" value="<?= $provider_id ?>">

    <div class="modal-body">

        <?php if ($row) { ?>
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="active" value="1" id="rmmProviderActive" <?= intval($row['rmm_provider_active']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="rmmProviderActive">On - the linked clients' devices come in</label>
                </div>
                <div class="form-text ps-5">Off, nothing is brought in, and assets keep what it last said about them.</div>
            </div>
        <?php } ?>

        <div class="mb-3">
            <label class="form-label" for="rmmProviderKind">RMM <?= $row ? '' : '<strong class="text-danger">*</strong>' ?></label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-shapes"></i></span>
                <select class="form-select select2" name="kind" id="rmmProviderKind" data-rmm-kind <?= $row ? 'disabled' : '' ?>>
                    <?php foreach ($kinds as $key => $info) { ?>
                        <option value="<?= escapeHtml($key) ?>" <?= $key === $kind_key ? 'selected' : '' ?><?= selectOptionData(['icon' => $info['icon'], 'description' => $info['about']]) ?>><?= escapeHtml($info['name']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <?= $error_for('kind') ?>
            <?php if ($row) { ?><div class="form-text">Which RMM it is stays - add another provider for another one.</div><?php } ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="rmmProviderName">Name <strong class="text-danger">*</strong></label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <input type="text" class="form-control" name="name" id="rmmProviderName" maxlength="100" value="<?= escapeHtml($row ? $row['rmm_provider_name'] : $kind['name']) ?>" placeholder="What you call it">
            </div>
            <?= $error_for('name') ?>
            <div class="form-text">What assets and the audit log call it. Two of the same RMM need names of their own.</div>
        </div>

        <?php foreach ($kinds as $key => $info) {
            $shown = $key === $kind_key; ?>
            <div data-rmm-kind-fields="<?= escapeHtml($key) ?>" class="<?= $shown ? '' : 'd-none' ?>">
                <?php foreach ($info['fields'] as $field => $about) {
                    $id = 'rmmProvider' . ucfirst($key) . str_replace('_', '', ucwords($field, '_'));
                    $saved = $row && $shown ? (string) $row["rmm_provider_$field"] : '';
                    $secret = $about['type'] === 'secret';
                    $placeholder = $secret ? ($saved !== '' ? 'Saved - type a new one to replace it' : '') : $about['placeholder'];
                    if ($field === 'web_url' && $row && $shown && isset($info['dashboard'])) {
                        // The address it works out when this is left empty
                        $placeholder = (string) call_user_func($info['dashboard'], array_merge($row, ['rmm_provider_web_url' => ''])) ?: $placeholder;
                    } ?>
                    <div class="mb-3">
                        <label class="form-label" for="<?= $id ?>"><?= escapeHtml($about['label']) ?> <?= !empty($about['required']) && !($secret && $saved !== '') ? '<strong class="text-danger">*</strong>' : '' ?></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="fa fa-fw <?= escapeHtml($about['icon']) ?>"></i></span>
                            <?php if ($secret) { ?>
                                <input type="password" class="form-control font-monospace" name="<?= $field ?>" id="<?= $id ?>" maxlength="500" spellcheck="false" autocomplete="new-password" placeholder="<?= escapeHtml($placeholder) ?>" <?= $shown ? '' : 'disabled' ?>>
                            <?php } else { ?>
                                <input type="url" class="form-control" name="<?= $field ?>" id="<?= $id ?>" maxlength="500" spellcheck="false" placeholder="<?= escapeHtml($placeholder) ?>" value="<?= escapeHtml($saved) ?>" <?= $shown ? '' : 'disabled' ?>>
                            <?php } ?>
                        </div>
                        <?= $error_for($field) ?>
                        <div class="form-text"><?= escapeHtml($about['help']) ?>
                            <?php if ($secret && $info['docs_url'] !== '') { ?><a href="<?= escapeHtml($info['docs_url']) ?>" target="_blank" rel="noopener noreferrer"><?= escapeHtml($info['short']) ?>'s API keys <i class="fas fa-external-link-alt fa-xs"></i></a><?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if ($alerts_offered) { ?>
            <div data-rmm-alerts class="<?= isset($kind['alerts'], $kind['alert']) ? '' : 'd-none' ?>">
                <div class="mb-3">
                    <label class="form-label" for="rmmProviderAlertLevel">Alerts open tickets</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text"><i class="fa fa-fw fa-bell"></i></span>
                        <select class="form-select select2" name="alert_level" id="rmmProviderAlertLevel" data-rmm-alert-level>
                            <?php foreach (rmmAlertLevels() as $level_key => $level_label) { ?>
                                <option value="<?= $level_key ?>" <?= $level_key === $alert_level ? 'selected' : '' ?>><?= escapeHtml($level_label) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <?= $error_for('alert_level') ?>
                    <div class="form-text">A new alert on a device of a linked client opens a ticket on its asset - High for an error, Medium for a warning, Low for the rest - and the same alert again goes on that ticket while it is open. When the alert clears, the ticket is resolved unless someone has written on it. Alerts already open when this is turned on don't open tickets.
                        <span data-rmm-alerts-help><?= escapeHtml((string) ($kind['alerts_help'] ?? '')) ?></span></div>
                </div>
                <div class="mb-3 <?= $alert_level === '' ? 'd-none' : '' ?>" data-rmm-alert-more>
                    <label class="form-label" for="rmmProviderAlertCategory">Ticket category</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                        <select class="form-select select2" name="alert_category_id" id="rmmProviderAlertCategory">
                            <option value="0">- Not Categorized -</option>
                            <?= categoryOptionsHtml('Ticket', $alert_category_id ?: null) ?>
                        </select>
                        <?= categoryAddButtonHtml('Ticket', 'btn btn-secondary') ?>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php if ($actions_offered) { ?>
            <div class="mb-3 <?= !empty($kind['actions']) ? '' : 'd-none' ?>" data-rmm-actions>
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="actions" value="1" id="rmmProviderActions" <?= intval($row['rmm_provider_actions'] ?? 0) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="rmmProviderActions">Device actions - reboot, install patches, maintenance mode and scripts from ITFlow</label>
                </div>
                <div class="form-text ps-5">Technicians who can edit assets get them on a device's card on its asset and beside it on its tickets. Each goes in the asset's history and the audit log, and on the ticket it was done from - a script's output with it.
                    <span data-rmm-actions-help><?= escapeHtml((string) ($kind['actions_help'] ?? '')) ?></span></div>
            </div>
        <?php } ?>

    </div>
    <div class="modal-footer">
        <button type="submit" name="<?= $row ? 'edit_rmm_provider' : 'add_rmm_provider' ?>" class="btn btn-primary text-bold" data-rmm-provider-save>
            <i class="fa fa-check me-2"></i><?= $row ? 'Save' : 'Add' ?>
        </button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
// The kind picked shows its fields, and names it if it hasn't been named. Save asks first, so what is wrong shows here
(function () {
    var form = document.querySelector('[data-rmm-provider-form]:not([data-ready])');
    if (!form) {
        return;
    }
    form.setAttribute('data-ready', '1');
    var kinds = JSON.parse(form.getAttribute('data-kinds'));
    var kindSelect = form.querySelector('[data-rmm-kind]');
    var name = form.querySelector('[name="name"]');
    var save = form.querySelector('[data-rmm-provider-save]');
    var kind = kindSelect.value;
    var checked = false;

    kindSelect.addEventListener('change', function () {
        var was = kinds[kind];
        kind = kindSelect.value;
        if (!kinds[kind]) {
            return;
        }
        if (name.value.trim() === '' || (was && name.value.trim() === was.name)) {
            name.value = kinds[kind].name;
        }
        form.querySelectorAll('[data-rmm-kind-fields]').forEach(function (block) {
            var shown = block.getAttribute('data-rmm-kind-fields') === kind;
            block.classList.toggle('d-none', !shown);
            block.querySelectorAll('input').forEach(function (input) { input.disabled = !shown; });
        });
        // Alerts only for a kind that has them
        var alerts = form.querySelector('[data-rmm-alerts]');
        if (alerts) {
            alerts.classList.toggle('d-none', !kinds[kind].alerts);
            alerts.querySelector('[data-rmm-alerts-help]').textContent = kinds[kind].alertsHelp;
        }
        // Device actions too
        var actions = form.querySelector('[data-rmm-actions]');
        if (actions) {
            actions.classList.toggle('d-none', !kinds[kind].actions);
            actions.querySelector('[data-rmm-actions-help]').textContent = kinds[kind].actionsHelp;
        }
    });

    // The category goes with alerts that open tickets
    var alertLevel = form.querySelector('[data-rmm-alert-level]');
    if (alertLevel) {
        alertLevel.addEventListener('change', function () {
            form.querySelector('[data-rmm-alert-more]').classList.toggle('d-none', alertLevel.value === '');
        });
    }

    // A field's input and where what is wrong with it shows - in the kind's fields that are showing, or the name and kind
    function find(field, what) {
        var shown = form.querySelector('[data-rmm-kind-fields="' + kind + '"]');
        var selector = what === 'box' ? '[data-error-for="' + field + '"]' : '[name="' + field + '"]';
        return (shown && shown.querySelector(selector)) || form.querySelector(selector);
    }

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
            var input = find(field, 'input');
            var box = find(field, 'box');
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
        data.append('check_rmm_provider', '1');
        fetch(form.getAttribute('action'), { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (answer) {
                clearErrors();
                save.disabled = false;
                if (answer.ok) {
                    checked = true;
                    save.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Connecting';
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
