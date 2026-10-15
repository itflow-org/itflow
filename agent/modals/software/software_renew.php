<?php

/*
 * Renew one license (?id=) or the ones ticked on the list (?software_ids[]=).
 * Move each renewal date on by a term - from its current date, or from today
 * if there is none or it has passed - or set one date for all of them. The
 * new date for each shows before you save. Optionally record the new order
 * or invoice reference.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$ids = isset($_GET['id']) ? [intval($_GET['id'])] : array_map('intval', (array) ($_GET['software_ids'] ?? []));

$licenses = [];
foreach (array_unique($ids) as $software_id) {
    $row = softwareRow($software_id);
    if (!$row) {
        continue;
    }
    $client_id = intval($row['software_client_id']);
    enforceClientAccess();
    $licenses[] = $row;
}
if (!$licenses) {
    echo json_encode(['error' => 'Tick the licenses to renew first.']);
    exit;
}

$single = count($licenses) === 1;
$default_term = '1y';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-redo-alt me-2"></i>Renew <?= $single ? '<strong>' . escapeHtml($licenses[0]['software_name']) . '</strong>' : '<strong>' . count($licenses) . '</strong> licenses' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-renew-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($licenses as $row) { ?><input type="hidden" name="software_ids[]" value="<?= intval($row['software_id']) ?>"><?php } ?>
    <input type="hidden" name="renew_mode" value="extend" data-renew-mode>

    <div class="modal-body">

        <label class="form-label">Renew for</label>
        <div class="d-flex flex-wrap gap-2 mb-2" role="group" aria-label="Term">
            <?php foreach (SOFTWARE_RENEWAL_TERMS as $term => $term_label) { ?>
                <input type="radio" class="btn-check" name="renew_term" id="renewTerm<?= $term ?>" value="<?= $term ?>" <?= $term === $default_term ? 'checked' : '' ?>>
                <label class="btn btn-outline-primary btn-sm" for="renewTerm<?= $term ?>"><?= $term_label ?></label>
            <?php } ?>
            <input type="radio" class="btn-check" name="renew_term" id="renewTermDate" value="date">
            <label class="btn btn-outline-secondary btn-sm" for="renewTermDate"><i class="fas fa-calendar-alt me-1"></i>A date</label>
        </div>
        <div class="mb-3" data-renew-date-box hidden>
            <input type="date" class="form-control" name="renew_date" max="2999-12-31" data-renew-date>
        </div>
        <div class="form-text mb-3">Each date moves on from its current renewal date, or from today if it has already passed.</div>

        <div class="table-responsive border rounded mb-3">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light text-nowrap"><tr><th class="ps-2">License</th><th>Renews now</th><th class="pe-2">New date</th></tr></thead>
                <tbody>
                <?php foreach ($licenses as $row) {
                    $renewal = softwareRenewal($row['software_expire']);
                    $next = [];
                    foreach (array_keys(SOFTWARE_RENEWAL_TERMS) as $term) {
                        $next[$term] = softwareRenewalExtended($row['software_expire'], $term);
                    } ?>
                    <tr>
                        <td class="ps-2">
                            <div class="d-flex align-items-center gap-2">
                                <?= softwareTile($row['software_type'], 'sm') ?>
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate"><?= escapeHtml($row['software_name']) ?></div>
                                    <?php if (!$single) { ?><div class="small text-body-secondary text-truncate"><?= escapeHtml($row['client_name']) ?></div><?php } ?>
                                </div>
                            </div>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($renewal) { ?>
                                <?= escapeHtml($renewal['date']) ?>
                                <div class="small text-<?= $renewal['tone'] === 'success' ? 'body-secondary' : $renewal['tone'] . '-emphasis' ?>"><?= escapeHtml($renewal['text']) ?></div>
                            <?php } else { ?>
                                <span class="text-body-secondary">No date</span>
                            <?php } ?>
                        </td>
                        <td class="text-nowrap fw-semibold text-success-emphasis pe-2" data-renew-next='<?= escapeHtml(json_encode($next)) ?>'><?= $next[$default_term] ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>

        <label class="form-label">New order / invoice reference</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-receipt"></i></span>
            <input type="text" class="form-control" name="purchase_reference" maxlength="200" placeholder="Leave blank to keep <?= $single && $licenses[0]['software_purchase_reference'] ? escapeHtml($licenses[0]['software_purchase_reference']) : 'what each has' ?>">
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="renew_software" class="btn btn-primary text-bold"><i class="fas fa-redo-alt me-2"></i>Renew</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    (function () {
        var form = document.querySelector('form[data-renew-form]:not([data-wired])');
        if (!form) {
            return;
        }
        form.dataset.wired = '1';
        var mode = form.querySelector('[data-renew-mode]');
        var dateBox = form.querySelector('[data-renew-date-box]');
        var date = form.querySelector('[data-renew-date]');
        function update() {
            var term = form.querySelector('[name="renew_term"]:checked').value;
            var byDate = term === 'date';
            mode.value = byDate ? 'date' : 'extend';
            dateBox.hidden = !byDate;
            date.required = byDate;
            form.querySelectorAll('[data-renew-next]').forEach(function (cell) {
                cell.textContent = byDate ? (date.value || '-') : JSON.parse(cell.dataset.renewNext)[term];
            });
        }
        form.querySelectorAll('[name="renew_term"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                update();
                if (radio.value === 'date') {
                    date.focus();
                }
            });
        });
        date.addEventListener('input', update);
        update();
    })();
</script>

<?php
require_once '../../../includes/modal_footer.php';
