<?php

/*
 * Link things to a document or a file - or a document or file to an asset or
 * contact, devices, users and credentials to a license. One modal for every
 * pair in LINK_RELATIONS (functions/links.php):
 * ?from=document&id=81&to=asset opens on Assets; the pills along the top
 * switch to anything else this record can be linked to. Pick as many as you
 * like; only the client's own, unarchived, not-yet-linked records are offered.
 *
 * The old one-pair modals (document_link_asset.php, asset_link_file.php...)
 * are thin wrappers that set from/id/to and include this.
 */

require_once '../../../includes/modal_header.php';

$link_from = (string) ($_GET['from'] ?? '');
$link_id = intval($_GET['id'] ?? 0);
$link_source = linkRecord($link_from, $link_id);
if (!$link_source) {
    echo json_encode(['error' => 'That record no longer exists.']);
    exit;
}

$client_id = intval($link_source['client_id']);
enforceClientAccess();

// Only the kinds this agent may change
$link_types = array_values(array_filter(linkTypesFor($link_from), function ($to) use ($link_from) {
    return linkCanEdit($link_from, $to);
}));
if (!$link_types) {
    echo json_encode(['error' => 'You do not have permission to change these links.']);
    exit;
}
$link_to = in_array($_GET['to'] ?? '', $link_types, true) ? $_GET['to'] : $link_types[0];

$from_entity = LINK_ENTITIES[$link_from];
$link_name = escapeHtml($link_source['name']);

/** The option extras for one candidate - its icon or avatar, and a line under it. */
function linkOptionData($to, $row, $client_id) {
    switch ($to) {
        case 'asset':
            return selectOptionAsset($row['asset_type'], trim($row['asset_make'] . ' ' . $row['asset_model']));
        case 'contact':
            return selectOptionContact($row['contact_client_id'], $row['name'], $row['contact_photo'], $row['contact_title'], $row['id']);
        case 'file':
            $info = fileTypeInfo($row['file_ext']);
            return selectOptionData(['icon' => 'fas fa-' . $info['icon'], 'description' => ($row['folder_name'] ?: 'Top level') . ' · ' . formatBytes(intval($row['file_size'])), 'keywords' => $row['file_description']]);
        case 'document':
            return selectOptionData(['icon' => 'fas fa-file-alt', 'description' => $row['document_description'] ?: 'Updated ' . timeAgo($row['document_updated_at'] ?: $row['document_created_at'])]);
        case 'software':
            // How many seats are left, so a full license is seen before it is picked
            $seats = softwareSeats($row['seats_used'], $row['software_seats']);
            $seat_text = $seats['state'] === 'unlimited' ? '' : ($seats['state'] === 'over' || $seats['state'] === 'full'
                ? 'Full - ' . $seats['used'] . ' of ' . $seats['total'] . ' seats'
                : $seats['free'] . ' of ' . $seats['total'] . ' seats free');
            return selectOptionData(['icon' => 'fas ' . softwareTypeStyle($row['software_type'])['icon'], 'description' => implode(' · ', array_filter([$row['software_license_type'], $seat_text, $row['software_version']]))]);
        case 'credential':
            return selectOptionData(['icon' => 'fas fa-key', 'description' => $row['credential_description'] ?: $row['credential_uri']]);
        case 'vendor':
            return selectOptionData(['icon' => 'fas fa-building', 'description' => $row['vendor_description']]);
    }
    return '';
}

$panes = [];
foreach ($link_types as $to) {
    $panes[$to] = [
        'entity' => LINK_ENTITIES[$to],
        'linked' => count(linkedRecords($link_from, $link_id, $to)),
        'candidates' => linkCandidates($link_from, $link_id, $to, $client_id),
    ];
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-link me-2"></i>Link to <strong><?= $link_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-link-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="from" value="<?= escapeHtml($link_from) ?>">
    <input type="hidden" name="id" value="<?= $link_id ?>">
    <input type="hidden" name="to" value="<?= escapeHtml($link_to) ?>" data-link-to>

    <div class="modal-body">

        <?php if (count($panes) > 1) { ?>
            <nav class="nav nav-pills itflow-asset-pills itflow-link-types mb-3" role="tablist">
                <?php foreach ($panes as $to => $pane) { ?>
                    <button type="button" class="nav-link <?= $to === $link_to ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#linkPane-<?= $to ?>" data-link-type="<?= $to ?>" role="tab">
                        <i class="fas fa-fw <?= $pane['entity']['icon'] ?> me-1"></i><?= $pane['entity']['plural'] ?>
                        <?php if ($pane['linked']) { ?><span class="badge rounded-pill ms-1" title="Already linked"><?= $pane['linked'] ?></span><?php } ?>
                    </button>
                <?php } ?>
            </nav>
        <?php } ?>

        <div class="tab-content">
            <?php foreach ($panes as $to => $pane) {
                $entity = $pane['entity']; ?>
                <div class="tab-pane fade <?= $to === $link_to ? 'show active' : '' ?>" id="linkPane-<?= $to ?>" role="tabpanel">
                    <?php if ($pane['candidates']) { ?>
                        <label class="form-label">Which <?= strtolower($entity['plural']) ?>?</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw <?= $entity['icon'] ?>"></i></span>
                            <select class="form-select select2" name="ids[]" multiple data-placeholder="Pick one or more <?= strtolower($entity['plural']) ?>" <?= $to === $link_to ? '' : 'disabled' ?> data-link-select="<?= $to ?>">
                                <?php foreach ($pane['candidates'] as $row) { ?>
                                    <option value="<?= intval($row['id']) ?>"<?= linkOptionData($to, $row, $client_id) ?>><?= escapeHtml($row['name']) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-text"><?= count($pane['candidates']) ?> <?= count($pane['candidates']) === 1 ? $entity['label'] : strtolower($entity['plural']) ?> not linked yet<?= $pane['linked'] ? ', ' . $pane['linked'] . ' already linked' : '' ?>.</div>
                    <?php } else { ?>
                        <div class="itflow-asset-empty py-4">
                            <i class="fas <?= $entity['icon'] ?> fa-2x mb-2"></i>
                            <div><?= $pane['linked'] ? 'Every ' . $entity['label'] . ' this client has is already linked.' : 'This client has no ' . strtolower($entity['plural']) . ' to link.' ?></div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="link_items" class="btn btn-primary text-bold"><i class="fas fa-link me-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    (function () {
        // Only the pane on show posts its picks
        var form = document.querySelector('form[data-link-form]:not([data-wired])');
        if (!form) {
            return;
        }
        form.dataset.wired = '1';
        var to = form.querySelector('[data-link-to]');
        form.querySelectorAll('[data-link-type]').forEach(function (pill) {
            pill.addEventListener('shown.bs.tab', function () {
                to.value = pill.dataset.linkType;
                form.querySelectorAll('[data-link-select]').forEach(function (select) {
                    select.disabled = select.dataset.linkSelect !== to.value;
                });
            });
        });
        form.addEventListener('submit', function (e) {
            var select = form.querySelector('[data-link-select="' + to.value + '"]');
            if (!select || !select.selectedOptions.length) {
                e.preventDefault();
                if (select && select.itflowSelect) {
                    select.itflowSelect.open();
                }
            }
        });
    })();
</script>

<?php
require_once '../../../includes/modal_footer.php';
