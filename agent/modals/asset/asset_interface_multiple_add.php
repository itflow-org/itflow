<?php

/*
 * A run of interfaces on an asset at once - a switch's 24 ports, a server's
 * four NICs: a prefix, the first number, how many and how the numbers are
 * padded, with the names it will make shown as they are typed. Names the
 * asset has already are skipped.
 */

require_once '../../../includes/modal_header.php';

$asset = interfaceAssetForModal($_GET['asset_id'] ?? 0);
$asset_id = intval($asset['asset_id']);
$client_id = intval($asset['asset_client_id']);
$networks = interfaceNetworkOptions($client_id);
$types = interfaceTypes();

// A start that follows on from ports already there - Gi1/0/1 to 24 there, start at 25
$existing = [];
$sql = mysqli_query($mysqli, "SELECT interface_name FROM asset_interfaces WHERE interface_asset_id = $asset_id AND interface_archived_at IS NULL");
while ($row = mysqli_fetch_row($sql)) {
    $existing[] = $row[0];
}
// the numbered run there is most of (Gi1/0/1-24, not mgmt0), carrying on its zero-padding
$prefix = '';
$start = 1;
$pad = 0;
$runs = [];
foreach ($existing as $name) {
    if (preg_match('/^(.*?)(\d+)$/', $name, $match)) {
        $runs[$match[1]][] = $match[2];
    }
}
if ($runs) {
    uasort($runs, function ($a, $b) {
        return count($b) <=> count($a);
    });
    $prefix = (string) array_key_first($runs);
    $numbers = $runs[$prefix];
    $start = max(array_map('intval', $numbers)) + 1;
    foreach ($numbers as $number) {
        if (strlen($number) > 1 && $number[0] === '0') {
            $pad = strlen($number);
        }
    }
}
if (!$existing) {
    $prefix = in_array($asset['asset_type'], ['Switch'], true) ? 'Gi1/0/' : (in_array($asset['asset_type'], ['Firewall/Router', 'Access Point'], true) ? 'Port ' : 'eth');
    $start = $prefix === 'eth' ? 0 : 1;
}
$count = $asset['asset_type'] === 'Switch' ? 24 : 4;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-ethernet me-2"></i>Add ports to <strong><?= escapeHtml($asset['asset_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off" id="interfaceSeriesForm" data-existing="<?= escapeHtml(json_encode(array_values(array_map('mb_strtolower', $existing)))) ?>">
    <input type="hidden" name="asset_id" value="<?= $asset_id ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Name before the number</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-font"></i></span>
                    <input type="text" class="form-control font-monospace" name="name_prefix" maxlength="190" placeholder="Gi1/0/, eth, Port " value="<?= escapeHtml($prefix) ?>" data-series="prefix">
                </div>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label">First number</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-hashtag"></i></span>
                    <input type="number" class="form-control" name="interface_start" min="0" value="<?= $start ?>" required data-series="start">
                </div>
            </div>
            <div class="col-md-4 col-6">
                <label class="form-label">How many</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                    <input type="number" class="form-control" name="interfaces" min="1" max="128" value="<?= $count ?>" required data-series="count">
                </div>
            </div>
            <div class="col-md-5">
                <label class="form-label">Numbers</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-sort-numeric-down"></i></span>
                    <select class="form-select" name="pad" data-series="pad">
                        <option value="0">1, 2 ... 10</option>
                        <option value="2" <?= $pad === 2 ? 'selected' : '' ?>>01, 02 ... 10</option>
                        <option value="3" <?= $pad === 3 ? 'selected' : '' ?>>001, 002 ... 010</option>
                    </select>
                </div>
            </div>
            <div class="col-md-7">
                <label class="form-label">Type</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-plug"></i></span>
                    <select class="form-select select2" name="type">
                        <option value="">- No type -</option>
                        <?php foreach ($types as $type => [$color, $icon]) { ?>
                            <option <?= $type === 'Ethernet' ? 'selected' : '' ?><?= selectOptionColor($color ?? '', $icon ?? '') ?>><?= escapeHtml($type) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-7">
                <label class="form-label">Network</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
                    <select class="form-select select2" name="network_id">
                        <option value="0">- No network -</option>
                        <?php foreach ($networks as $network) { ?>
                            <option value="<?= intval($network['network_id']) ?>"<?= selectOptionNetwork($network) ?>><?= escapeHtml($network['network_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-5">
                <label class="form-label">VLANs</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                    <input type="text" class="form-control" name="vlans" maxlength="200" placeholder="e.g. 10">
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-pen"></i></span>
                    <input type="text" class="form-control" name="notes" placeholder="On every one of them">
                </div>
            </div>
        </div>
        <div class="small mt-3 p-2 rounded bg-body-tertiary" data-series="preview"></div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_asset_multiple_interfaces" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Add</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    (function () {
        // The names it will make, as they are typed - the ones already on the asset struck through
        var form = document.getElementById('interfaceSeriesForm');
        var existing = JSON.parse(form.dataset.existing || '[]');
        function field(name) {
            return form.querySelector('[data-series="' + name + '"]');
        }
        function names() {
            var prefix = field('prefix').value;
            var start = Math.max(0, parseInt(field('start').value, 10) || 0);
            var count = Math.max(0, Math.min(128, parseInt(field('count').value, 10) || 0));
            var pad = parseInt(field('pad').value, 10) || 0;
            var list = [];
            for (var n = start; n < start + count; n++) {
                list.push(prefix + String(n).padStart(pad, '0'));
            }
            return list;
        }
        function preview() {
            var list = names();
            var box = field('preview');
            box.innerHTML = '';
            if (!list.length) {
                box.textContent = 'Nothing to add.';
                return;
            }
            var shown = list.length > 6 ? list.slice(0, 3).concat(['...'], list.slice(-2)) : list;
            var skipped = list.filter(function (name) { return existing.indexOf(name.toLowerCase()) !== -1; }).length;
            var head = document.createElement('div');
            head.className = 'fw-semibold mb-1';
            head.textContent = (list.length - skipped) + ' new port' + (list.length - skipped === 1 ? '' : 's') + (skipped ? ' - ' + skipped + ' already there, skipped' : '');
            var line = document.createElement('div');
            line.className = 'font-monospace';
            shown.forEach(function (name, i) {
                var span = document.createElement('span');
                span.textContent = name;
                if (existing.indexOf(name.toLowerCase()) !== -1) {
                    span.className = 'text-decoration-line-through text-body-secondary';
                }
                line.append(i ? ', ' : '', span);
            });
            box.append(head, line);
        }
        form.querySelectorAll('[data-series]').forEach(function (input) {
            input.addEventListener('input', preview);
            input.addEventListener('change', preview);
        });
        preview();
    })();
</script>

<?php
require_once '../../../includes/modal_footer.php';
