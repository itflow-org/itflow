<?php

/*
 * Run a script on a device from an RMM (functions/rmm.php, Device actions) - one of the RMM's
 * scripts that runs on the device's system, with its arguments (the script's own to start with)
 * and how long to wait for it. Run asks in JSON (rmm_run_script, agent/post/rmm.php), so the
 * window stays open and shows the output. The ticket picked gets it as an internal note - the
 * one it was opened from, to start with. Not waiting leaves the output in the RMM's history.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$device_id = intval($_GET['id'] ?? 0);
$row = rmmDeviceById($device_id);
if (!$row) {
    echo json_encode(['error' => 'That device, or its asset, is no longer in ITFlow.']);
    exit;
}

$client_id = intval($row['asset_client_id']);

enforceClientAccess();

if (!isset(rmmDeviceActions($row)['script'])) {
    echo json_encode(['error' => rmmActionHost($row) . ' can\'t have scripts run from ITFlow now.']);
    exit;
}

$name = rmmProviderName($row);
$host = rmmActionHost($row);
$answer = rmmDeviceScripts($row);
$scripts = $answer['scripts'];

// The tickets it can go on - the one it was opened from first
$ticket_id = intval($_GET['ticket_id'] ?? 0);
$tickets = rmmActionTickets(intval($row['rmm_device_asset_id']), $client_id, $ticket_id);
if (!isset($tickets[$ticket_id])) {
    $ticket_id = 0;
}

// Grouped as the RMM has them - its favorites, then each category
$groups = [];
foreach ($scripts as $script) {
    $groups[$script['favorite'] ? 'Favorites' : ($script['category'] !== '' ? $script['category'] : 'Other')][] = $script;
}
$first = $scripts[0] ?? null;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-scroll me-2"></i>Run a script <small class="ms-1 opacity-75"><?= escapeHtml($host) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-rmm-script-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="rmm_run_script" value="<?= $device_id ?>">

    <div class="modal-body">

        <?php if ($row['rmm_device_status'] !== 'online') { ?>
            <div class="alert alert-warning small"><i class="fas fa-fw fa-exclamation-triangle me-2"></i><?= escapeHtml($name) ?> last said <?= escapeHtml($host) ?> is <?= escapeHtml(mb_strtolower(rmmDeviceState($row)['label'])) ?> - a script can only run on it while it's online.</div>
        <?php } ?>

        <?php if (!$answer['ok']) { ?>
            <div class="alert alert-danger mb-0"><i class="fas fa-fw fa-exclamation-circle me-2"></i>Couldn't get <?= escapeHtml($name) ?>'s scripts. <?= escapeHtml($answer['error']) ?></div>
        <?php } elseif (!$scripts) { ?>
            <div class="alert alert-info mb-0"><i class="fas fa-fw fa-info-circle me-2"></i><?= escapeHtml($name) ?> has no scripts that run on <?= escapeHtml(rmmPlatformName((string) $row['rmm_device_platform'])) ?>, as <?= escapeHtml($host) ?> does.</div>
        <?php } else { ?>

            <div class="mb-3">
                <label class="form-label" for="rmmScript">Script <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-scroll"></i></span>
                    <select class="form-select select2" name="script_id" id="rmmScript" data-rmm-script required>
                        <?php foreach ($groups as $group => $in_group) { ?>
                            <optgroup label="<?= escapeHtml($group) ?>">
                                <?php foreach ($in_group as $script) { ?>
                                    <option value="<?= intval($script['id']) ?>" data-args="<?= escapeHtml(json_encode($script['args'])) ?>" data-timeout="<?= intval($script['timeout']) ?>"
                                        <?= selectOptionData(['icon' => 'fas fa-scroll', 'description' => $script['description'], 'keywords' => $script['category']]) ?>><?= escapeHtml($script['name']) ?></option>
                                <?php } ?>
                            </optgroup>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">The scripts in <?= escapeHtml($name) ?> that run on <?= escapeHtml(rmmPlatformName((string) $row['rmm_device_platform'])) ?>.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="rmmScriptArgs">Arguments</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-terminal"></i></span>
                    <textarea class="form-control font-monospace" name="args" id="rmmScriptArgs" rows="2" spellcheck="false" maxlength="5000" placeholder="One a line" data-rmm-script-args><?= escapeHtml(implode("\n", $first['args'] ?? [])) ?></textarea>
                </div>
                <div class="form-text">One a line - the script's own to start with.</div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-5">
                    <label class="form-label" for="rmmScriptTimeout">Timeout</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-hourglass-half"></i></span>
                        <input type="number" class="form-control" name="timeout" id="rmmScriptTimeout" min="5" max="600" step="1" value="<?= intval($first['timeout'] ?? 90) ?>" data-rmm-script-timeout>
                        <span class="input-group-text">seconds</span>
                    </div>
                </div>
                <div class="col-sm-7 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input type="checkbox" class="form-check-input" name="wait" value="1" id="rmmScriptWait" checked>
                        <label class="form-check-label" for="rmmScriptWait">Wait for its output</label>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="rmmScriptTicket">Note it on</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
                    <select class="form-select select2" name="ticket_id" id="rmmScriptTicket">
                        <option value="0">- No ticket -</option>
                        <?php foreach ($tickets as $id => $ticket) { ?>
                            <option value="<?= $id ?>" <?= $id === $ticket_id ? 'selected' : '' ?>><?= escapeHtml($ticket['ticket_prefix'] . $ticket['ticket_number'] . ' - ' . $ticket['ticket_subject']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">The ticket gets what was run and its output as an internal note. Either way it goes in the asset's history.</div>
            </div>

            <div class="d-none" data-rmm-script-result>
                <div class="d-flex align-items-center mb-1">
                    <span class="fw-semibold small" data-rmm-script-said></span>
                    <button type="button" class="itflow-copy clipboardjs ms-auto d-none" title="Copy the output" data-rmm-script-copy><i class="far fa-copy"></i></button>
                </div>
                <pre class="border rounded bg-body-tertiary p-2 mb-0 small d-none" style="max-height: 22rem; overflow: auto; white-space: pre-wrap;" data-rmm-script-output></pre>
            </div>

        <?php } ?>

    </div>
    <div class="modal-footer">
        <?php if ($scripts) { ?>
            <button type="submit" class="btn btn-primary text-bold" data-rmm-script-run><i class="fas fa-play me-2"></i>Run</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
    </div>
</form>

<script>
// A script picked brings its own arguments and timeout. Run asks, and shows what came back here
(function () {
    var form = document.querySelector('[data-rmm-script-form]:not([data-ready])');
    if (!form || !form.querySelector('[data-rmm-script]')) {
        return;
    }
    form.setAttribute('data-ready', '1');
    var script = form.querySelector('[data-rmm-script]');
    var args = form.querySelector('[data-rmm-script-args]');
    var timeout = form.querySelector('[data-rmm-script-timeout]');
    var run = form.querySelector('[data-rmm-script-run]');
    var result = form.querySelector('[data-rmm-script-result]');
    var said = form.querySelector('[data-rmm-script-said]');
    var output = form.querySelector('[data-rmm-script-output]');
    var copy = form.querySelector('[data-rmm-script-copy]');
    var label = run.innerHTML;

    script.addEventListener('change', function () {
        var option = script.options[script.selectedIndex];
        if (!option) {
            return;
        }
        try {
            args.value = JSON.parse(option.getAttribute('data-args') || '[]').join('\n');
        } catch (e) {
            args.value = '';
        }
        timeout.value = option.getAttribute('data-timeout') || 90;
    });

    function show(text, tone, out) {
        result.classList.remove('d-none');
        said.textContent = text;
        said.className = 'fw-semibold small text-' + tone + '-emphasis';
        output.classList.toggle('d-none', out === null);
        copy.classList.toggle('d-none', out === null);
        output.textContent = out === null ? '' : (out.trim() === '' ? '(no output)' : out);
        copy.setAttribute('data-clipboard-text', out || '');
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var seconds = parseInt(timeout.value, 10) || 90;
        var waiting = form.querySelector('[name="wait"]').checked;
        run.disabled = true;
        run.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>' + (waiting ? 'Running - up to ' + seconds + 's' : 'Starting');
        show(waiting ? 'Running...' : 'Starting...', 'secondary', null);
        fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (answer) {
                if (!answer.ok) {
                    show(answer.error || 'It didn\'t run.', 'danger', null);
                    return;
                }
                show(answer.text, answer.timed_out ? 'warning' : 'success', typeof answer.output === 'string' ? answer.output : null);
            })
            .catch(function () {
                show('No answer came back from ITFlow - reload the page and try again. If it ran, its output is in the device\'s history in the RMM.', 'danger', null);
            })
            .finally(function () {
                run.disabled = false;
                run.innerHTML = label;
            });
    });
})();
</script>
<?php
require_once '../../../includes/modal_footer.php';
