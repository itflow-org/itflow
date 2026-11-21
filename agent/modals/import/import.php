<?php

/*
 * The Import window, for every kind ITFlow imports (functions/import_kinds.php) -
 * ?kind=contacts&client_id=4. Pick a CSV file and it shows each of the file's
 * columns with a few of its values and the field it imports as, matched by its
 * heading, to change; what to do with a record already here (skip it, or update
 * it); and what every row would do - be added, update, be skipped or not import,
 * and why. That check imports the file and rolls it back, so it is what Import
 * will do. It asks agent/post/import.php in JSON and sends the file each time,
 * and once imported shows what happened, the rows that didn't import ready to
 * download with why, to fix and import again.
 */

require_once '../../../includes/modal_header.php';

$def = importKind((string) ($_GET['kind'] ?? ''));
if (!$def) {
    echo json_encode(['error' => "That isn't something ITFlow imports."]);
    exit;
}

$client_id = !empty($def['client']) ? intval($_GET['client_id'] ?? 0) : 0;
enforceUserPermission(importModule($def, $client_id), 2);
$refusal = importRefusal($def, $client_id);
if ($refusal !== '') {
    echo json_encode(['error' => $refusal]);
    exit;
}
$client_name = $client_id ? (string) getFieldById('clients', $client_id, 'client_name') : ($def['client'] === 'optional' ? 'Yours' : '');

// The fields, as the pickers offer them - an asset's details after its fields
$fields = [];
foreach ($def['fields'] as $key => $field) {
    if (empty($field['skip'])) {
        $fields[] = ['key' => $key, 'label' => $field['label'], 'about' => (string) ($field['about'] ?? ''), 'required' => !empty($field['required']),
            'detail' => !empty($field['detail']), 'secret' => in_array($key, ['password', 'otp_secret'], true)];
    }
}
$required = array_column(array_filter($fields, function ($field) {
    return $field['required'];
}), 'label');

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw <?= escapeHtml($def['icon']) ?> me-2"></i>Import <?= escapeHtml($def['title']) ?><?php if ($client_name !== '') { ?> <small class="ms-1 opacity-75"><?= escapeHtml($client_name) ?></small><?php } ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form autocomplete="off" data-import-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="kind" value="<?= escapeHtml($def['kind']) ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">

        <p class="text-body-secondary small"><?= escapeHtml($def['about']) ?></p>

        <div class="mb-3">
            <label class="form-label" for="importFile">CSV file <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-file-csv"></i></span>
                <input type="file" class="form-control" id="importFile" accept=".csv,text/csv" data-import-file>
            </div>
            <div class="form-text">
                A heading on each column, in any order - ITFlow matches them to its fields and shows you what each row will do before anything is imported.
                A file exported from ITFlow imports as it is. Up to <?= number_format(IMPORT_MAX_ROWS) ?> rows. <?= count($required) ? 'Needs ' . escapeHtml(implode(' and ', $required)) . '.' : '' ?>
            </div>
        </div>

        <div class="alert alert-danger d-none" data-import-error></div>

        <div class="d-none" data-import-mapping>
            <div class="table-responsive border rounded mb-3" style="max-height: 21rem;">
                <table class="table table-sm align-middle mb-0">
                    <thead class="sticky-top">
                        <tr>
                            <th class="text-nowrap">Column in the file</th>
                            <th class="d-none d-sm-table-cell">Its values</th>
                            <th style="width: 42%;">Imports as</th>
                        </tr>
                    </thead>
                    <tbody data-import-columns></tbody>
                </table>
            </div>

            <div class="alert alert-warning small d-none" data-import-missing></div>

            <div class="mb-3">
                <label class="form-label" for="importMode">A <?= escapeHtml($def['one']) ?> already here</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-clone"></i></span>
                    <select class="form-select select2" name="mode" id="importMode" data-import-mode>
                        <option value="skip" <?= selectOptionData(['icon' => 'fas fa-forward', 'description' => 'Leave the one here as it is']) ?>>Skip it</option>
                        <option value="update" <?= selectOptionData(['icon' => 'fas fa-pen', 'description' => "Change it to the file's values - an empty cell leaves what it has"]) ?>>Update it</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="d-none" data-import-outcome>
            <div class="fw-semibold mb-2" data-import-said></div>
            <div class="d-flex flex-wrap gap-2 mb-2" data-import-counts></div>
            <div class="small mb-2" data-import-finish></div>
            <div class="table-responsive border rounded d-none" style="max-height: 16rem;" data-import-list-wrap>
                <table class="table table-sm small mb-0">
                    <thead class="sticky-top">
                        <tr>
                            <th style="width: 4rem;">Row</th>
                            <th>Name</th>
                            <th>What happens</th>
                        </tr>
                    </thead>
                    <tbody data-import-list></tbody>
                </table>
            </div>
            <div class="small text-body-secondary mt-1 d-none" data-import-more></div>
        </div>

    </div>
    <div class="modal-footer">
        <a class="btn btn-outline-secondary me-auto" href="post.php?import_template=<?= escapeHtml($def['kind']) ?><?= $client_id ? "&amp;client_id=$client_id" : '' ?>" data-import-template><i class="fas fa-download me-2"></i>Template</a>
        <button type="button" class="btn btn-outline-secondary d-none" data-import-failed><i class="fas fa-download me-2"></i>Rows Not Imported</button>
        <button type="submit" class="btn btn-primary fw-bold" disabled data-import-run><i class="fas fa-upload me-2"></i>Import</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal" data-import-close><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
// Pick a file, see how it maps and what each row will do, import (agent/post/import.php)
(function () {
    var form = document.querySelector('[data-import-form]:not([data-ready])');
    if (!form) {
        return;
    }
    form.setAttribute('data-ready', '1');

    var fields = <?= json_encode($fields, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var title = <?= json_encode($def['title'], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var one = <?= json_encode($def['one'], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var byKey = {};
    fields.forEach(function (field) {
        byKey[field.key] = field;
    });

    var fileInput = form.querySelector('[data-import-file]');
    var error = form.querySelector('[data-import-error]');
    var mapping = form.querySelector('[data-import-mapping]');
    var columns = form.querySelector('[data-import-columns]');
    var missing = form.querySelector('[data-import-missing]');
    var mode = form.querySelector('[data-import-mode]');
    var outcome = form.querySelector('[data-import-outcome]');
    var said = form.querySelector('[data-import-said]');
    var counts = form.querySelector('[data-import-counts]');
    var finish = form.querySelector('[data-import-finish]');
    var listWrap = form.querySelector('[data-import-list-wrap]');
    var list = form.querySelector('[data-import-list]');
    var more = form.querySelector('[data-import-more]');
    var run = form.querySelector('[data-import-run]');
    var failedButton = form.querySelector('[data-import-failed]');
    var closeButton = form.querySelector('[data-import-close]');
    var runLabel = run.innerHTML;

    var file = null;
    var headings = [];
    var samples = [];
    var asked = 0;
    var timer = null;
    var imported = false;
    var failedRows = [];

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }
        return node;
    }

    function showError(text) {
        error.textContent = text;
        error.classList.toggle('d-none', !text);
    }

    // The map the pickers say - column => field
    function currentMap() {
        var map = {};
        columns.querySelectorAll('select[data-import-column]').forEach(function (select) {
            if (select.value !== '') {
                map[select.getAttribute('data-import-column')] = select.value;
            }
        });
        return map;
    }

    function ask(action) {
        var data = new FormData(form);
        data.append(action, '1');
        // Read off the picker - a disabled field isn't in the form's data
        data.set('mode', mode.value);
        data.append('file', file);
        if (headings.length) {
            data.append('map', JSON.stringify(currentMap()));
        }
        return fetch('post.php', { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) {
                return response.json().catch(function () {
                    throw new Error('ITFlow didn\'t answer as expected - reload the page and try again.');
                });
            });
    }

    // Each column, its values and what it imports as
    function drawColumns(answer) {
        headings = answer.headings;
        samples = answer.samples;
        columns.textContent = '';
        headings.forEach(function (heading, index) {
            var tr = el('tr');
            tr.appendChild(el('td', 'fw-semibold text-break', heading !== '' ? heading : 'Column ' + (index + 1)));
            var values = el('td', 'd-none d-sm-table-cell small text-body-secondary text-break');
            values.setAttribute('data-import-values', index);
            tr.appendChild(values);
            var pick = el('td');
            var select = el('select', 'form-select form-select-sm select2');
            select.id = 'importColumn' + index;
            select.setAttribute('data-import-column', index);
            var label = el('label', 'visually-hidden', (heading !== '' ? heading : 'Column ' + (index + 1)) + ' imports as');
            label.htmlFor = select.id;
            pick.appendChild(label);
            select.appendChild(el('option', '', '- Don\'t import -')).value = '';
            var groups = {};
            fields.forEach(function (field) {
                var group = field.detail ? 'Details' : title;
                if (!groups[group]) {
                    groups[group] = el('optgroup');
                    groups[group].label = group;
                    select.appendChild(groups[group]);
                }
                var option = el('option', '', field.label + (field.required ? ' *' : ''));
                option.value = field.key;
                if (field.about) {
                    option.setAttribute('data-description', field.about);
                }
                groups[group].appendChild(option);
            });
            select.value = answer.map[index] && byKey[answer.map[index]] ? answer.map[index] : '';
            pick.appendChild(select);
            tr.appendChild(pick);
            columns.appendChild(tr);
        });
        drawValues();
        mapping.classList.remove('d-none');
    }

    // A column's values - a password's hidden
    function drawValues() {
        var map = currentMap();
        columns.querySelectorAll('[data-import-values]').forEach(function (cell) {
            var index = cell.getAttribute('data-import-values');
            var field = byKey[map[index]];
            var values = samples[index] || [];
            cell.textContent = !values.length ? '(empty)' : (field && field.secret ? values.map(function () { return '••••••'; }) : values).join(', ');
        });
    }

    function badge(count, label, tone) {
        var span = el('span', 'badge text-bg-' + tone + ' fw-normal');
        span.style.fontSize = '.875rem';
        span.appendChild(el('strong', '', count.toLocaleString()));
        span.appendChild(document.createTextNode(' ' + label));
        return span;
    }

    // What the rows do, or did
    function drawOutcome(answer, done) {
        var labels = done
            ? { added: 'added', updated: 'updated', skipped: 'skipped', failed: 'not imported' }
            : { added: 'to add', updated: 'to update', skipped: 'to skip', failed: 'can\'t import' };
        var what = done
            ? { add: ['Added', 'success'], update: ['Updated', 'info'], skip: ['Skipped', 'secondary'], fail: ['Not imported', 'danger'] }
            : { add: ['Adds', 'success'], update: ['Updates', 'info'], skip: ['Skips', 'secondary'], fail: ['Can\'t import', 'danger'] };
        var rows = answer.rows.toLocaleString() + ' row' + (answer.rows === 1 ? '' : 's');
        said.textContent = done ? 'Imported. The file had ' + rows + ':' : 'The file has ' + rows + '. Importing it:';
        counts.textContent = '';
        counts.appendChild(badge(answer.added, labels.added, 'success'));
        if (answer.updated || mode.value === 'update') {
            counts.appendChild(badge(answer.updated, labels.updated, 'info'));
        }
        if (answer.skipped) {
            counts.appendChild(badge(answer.skipped, labels.skipped, 'secondary'));
        }
        if (answer.failed) {
            counts.appendChild(badge(answer.failed, labels.failed, 'danger'));
        }
        finish.innerHTML = done && answer.finish ? answer.finish : '';
        list.textContent = '';
        answer.list.forEach(function (row) {
            var tr = el('tr');
            tr.appendChild(el('td', 'text-body-secondary', row[0]));
            tr.appendChild(el('td', 'text-break', row[1] !== '' ? row[1] : '-'));
            var why = el('td', 'text-break');
            why.appendChild(el('span', 'badge text-bg-' + what[row[2]][1] + ' me-1', what[row[2]][0]));
            why.appendChild(document.createTextNode(row[3]));
            tr.appendChild(why);
            list.appendChild(tr);
        });
        listWrap.classList.toggle('d-none', !answer.list.length);
        more.textContent = 'The first 500 rows with something to say are shown' + (done && failedRows.length ? ' - every row not imported is in Rows not imported.' : '.');
        more.classList.toggle('d-none', answer.list.length < 500);
        outcome.classList.remove('d-none');
    }

    function drawMissing(answer) {
        missing.textContent = answer.missing.length
            ? 'No column imports as ' + answer.missing.join(' or ') + ' - a new ' + one + ' needs ' + (answer.missing.length === 1 ? 'it' : 'them') + ', so only ' + one + 's already here can be updated.'
            : '';
        missing.classList.toggle('d-none', !answer.missing.length);
    }

    function check() {
        var mine = ++asked;
        run.disabled = true;
        said.textContent = 'Checking the file...';
        counts.textContent = '';
        outcome.classList.remove('d-none');
        ask('import_check')
            .then(function (answer) {
                if (mine !== asked) {
                    return;
                }
                if (!answer.ok) {
                    showError(answer.error);
                    outcome.classList.add('d-none');
                    return;
                }
                showError('');
                if (!headings.length) {
                    drawColumns(answer);
                }
                drawMissing(answer);
                drawOutcome(answer, false);
                run.disabled = !(answer.added + answer.updated);
            })
            .catch(function (e) {
                if (mine === asked) {
                    showError(e.message || 'No answer came back from ITFlow - reload the page and try again.');
                    outcome.classList.add('d-none');
                }
            });
    }

    function checkSoon() {
        clearTimeout(timer);
        timer = setTimeout(check, 350);
    }

    fileInput.addEventListener('change', function () {
        file = fileInput.files[0] || null;
        headings = [];
        columns.textContent = '';
        mapping.classList.add('d-none');
        outcome.classList.add('d-none');
        missing.classList.add('d-none');
        showError('');
        run.disabled = true;
        if (file) {
            check();
        }
    });

    // A field picked for one column comes off any other
    columns.addEventListener('change', function (e) {
        var select = e.target.closest('select[data-import-column]');
        if (!select) {
            return;
        }
        if (select.value !== '') {
            columns.querySelectorAll('select[data-import-column]').forEach(function (other) {
                if (other !== select && other.value === select.value) {
                    other.value = '';
                }
            });
        }
        drawValues();
        checkSoon();
    });
    mode.addEventListener('change', function () {
        if (file) {
            checkSoon();
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!file || run.disabled) {
            return;
        }
        clearTimeout(timer);
        asked++;
        run.disabled = true;
        run.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Importing';
        fileInput.disabled = true;
        columns.querySelectorAll('select').forEach(function (select) {
            select.disabled = true;
        });
        mode.disabled = true;
        ask('import_run')
            .then(function (answer) {
                if (!answer.ok) {
                    showError(answer.error);
                    run.disabled = false;
                    fileInput.disabled = false;
                    mode.disabled = false;
                    columns.querySelectorAll('select').forEach(function (select) {
                        select.disabled = false;
                    });
                    return;
                }
                imported = true;
                failedRows = answer.failed_rows || [];
                mapping.classList.add('d-none');
                missing.classList.add('d-none');
                drawOutcome(answer, true);
                run.classList.add('d-none');
                failedButton.classList.toggle('d-none', !failedRows.length);
                closeButton.innerHTML = '<i class="fas fa-check me-2"></i>Done';
            })
            .catch(function (e) {
                showError((e.message || 'No answer came back from ITFlow.') + ' Check the list before importing again - some rows may be in.');
                imported = true;
            })
            .finally(function () {
                run.innerHTML = runLabel;
            });
    });

    // The rows that didn't import, as they were, with why - to fix and import again
    failedButton.addEventListener('click', function () {
        var quote = function (value) {
            value = String(value === undefined || value === null ? '' : value);
            return /[",\r\n]|^\s|\s$/.test(value) ? '"' + value.replace(/"/g, '""') + '"' : value;
        };
        var lines = [headings.concat(['Not imported because']).map(quote).join(',')];
        failedRows.forEach(function (row) {
            var cells = headings.map(function (heading, index) {
                return row[2][index] !== undefined ? row[2][index] : '';
            });
            lines.push(cells.concat([row[1]]).map(quote).join(','));
        });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob(['﻿' + lines.join('\r\n') + '\r\n'], { type: 'text/csv;charset=utf-8' }));
        link.download = title + '-Not-Imported.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(function () {
            URL.revokeObjectURL(link.href);
        }, 1000);
    });

    // Once something is in, the list behind shows it
    var modal = form.closest('.modal');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', function () {
            if (imported) {
                window.location.reload();
            }
        });
    }
})();
</script>
<?php
require_once '../../../includes/modal_footer.php';
