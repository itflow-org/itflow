<?php

/*
 * True up the seats on licenses made from a template - each license's seats
 * bought set to what is on it now, or to a number you type. ?id= the
 * template; &software_ids[]= the licenses ticked on its page, or - without
 * any - every one whose seats bought aren't what is in use. Each starts on
 * the seats in use, ticked when that is a change. Saved by
 * true_up_software_template (admin/post/software_template.php). A recurring
 * invoice line counting a license's seats bought follows on its next run.
 */

require_once '../../includes/modal_header.php';

$template = softwareTemplateRow($_GET['id'] ?? 0);

ob_start();

if (!$template) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-balance-scale me-2"></i>True up seats</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5">That license template no longer exists.</div>
<?php } else {
    $software_template_id = intval($template['software_template_id']);
    $all = softwareTemplateLicenses($software_template_id);
    $ids = array_filter(array_map('intval', (array) ($_GET['software_ids'] ?? [])));
    $licenses = $ids ? array_intersect_key($all, array_flip($ids)) : array_filter($all, function ($row) {
        return $row['seats']['total'] > 0 && $row['seats']['used'] !== $row['seats']['total'];
    });
    ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-balance-scale me-2"></i>True up seats <small class="ms-1 opacity-75"><?= escapeHtml($template['software_template_name']) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="software_template_id" value="<?= $software_template_id ?>">

    <div class="modal-body">
        <?php if (!$licenses) { ?>
            <div class="text-center text-secondary py-4"><i class="fas fa-check text-success me-1"></i>Every license made from it has the seats it is using.</div>
        <?php } else { ?>
            <p class="small text-secondary">Each starts on the seats in use. Untick a license to leave it as it is, or type the seats it should have - blank for no limit.</p>
            <div class="table-responsive border rounded">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light text-nowrap">
                        <tr><th class="ps-2" style="width: 2rem;"></th><th>Client</th><th class="text-end">In use</th><th class="text-end">Seats now</th><th class="pe-2" style="width: 9rem;">Seats</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($licenses as $software_id => $row) {
                        $seats = $row['seats'];
                        $now = $row['software_seats'] === null ? '' : (string) intval($row['software_seats']);
                        // Unlimited ones stay unlimited unless a number is typed
                        $to = $seats['total'] > 0 ? (string) $seats['used'] : $now;
                        ?>
                        <tr data-true-up-id="<?= $software_id ?>">
                            <td class="ps-2"><input class="form-check-input" type="checkbox" name="software_ids[]" value="<?= $software_id ?>" aria-label="True up <?= escapeHtml($row['client_name']) ?>" <?= $to !== $now ? 'checked' : '' ?> data-true-up-tick></td>
                            <td class="min-w-0">
                                <div class="fw-semibold text-truncate"><?= escapeHtml($row['client_name']) ?></div>
                                <?php if ($row['software_name'] !== $template['software_template_name']) { ?><div class="small text-secondary text-truncate"><?= escapeHtml($row['software_name']) ?></div><?php } ?>
                            </td>
                            <td class="text-end"><?= $seats['used'] ?></td>
                            <td class="text-end text-nowrap"><?= $seats['total'] > 0 ? $seats['total'] . ($seats['state'] === 'over' ? ' <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis fw-normal">' . $seats['over'] . ' over</span>' : '') : '<span class="text-secondary">No limit</span>' ?></td>
                            <td class="pe-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                                    <input type="number" class="form-control" name="seats[<?= $software_id ?>]" min="0" max="1000000" step="1" placeholder="No limit" value="<?= escapeHtml($to) ?>" data-now="<?= escapeHtml($now) ?>" data-true-up-seats>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="small text-secondary mt-3">A recurring invoice line counting a license's seats bought follows on its next run.</div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if ($licenses) { ?>
            <button type="submit" name="true_up_software_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>True up</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i><?= $licenses ? 'Cancel' : 'Close' ?></button>
    </div>
</form>

<script>
    (function () {
        // Typing a number ticks the license when it is a change, unticks it when it isn't
        document.querySelectorAll('.modal [data-true-up-seats]').forEach(function (input) {
            input.addEventListener('input', function () {
                var tick = input.closest('tr').querySelector('[data-true-up-tick]');
                tick.checked = input.value.trim() !== input.dataset.now;
            });
        });
    })();
</script>

<?php }

require_once '../../../includes/modal_footer.php';
