<?php

/*
 * The fields of a documented address - included by network_ip_add.php and
 * network_ip_edit.php, which set $network (the row), $form (the values, raw)
 * and $is_new. The subnet's fixed part is shown in front, so a /24 takes only
 * the last number; a full address pasted in works as well. A new address can
 * be a run of them (a block held back for a printer pool). Checked as it is
 * typed (itflowWatchNetworkIp, js/app.js) and again on saving
 * (agent/post/network_ip.php).
 */

isset($mysqli) || die("Direct file access is not allowed");

// The part of the address this subnet fixes. Empty for IPv6, a prefix under
// /8, or a subnet that won't parse - then the field takes a whole address. An
// address that sits outside it is shown in full so it can be corrected.
$ip_fixed_octets = ipSubnetFixedOctets($network['network']);
$ip_host_octets = $ip_fixed_octets ? 4 - substr_count($ip_fixed_octets, '.') : 0;
$ip_value = (string) ($form['ip_address'] ?? '');
$ip_host_value = $ip_value === '' ? '' : ipSuffixForDisplay($ip_value, $network['network']);
$ip_split = $ip_fixed_octets !== '' && ($ip_value === '' || $ip_host_value !== $ip_value);
$ip_placeholder = $ip_split ? ($ip_host_octets === 1 ? '10' : str_repeat('0.', $ip_host_octets - 1) . '10') : 'Inside ' . $network['network'];
$form_type = isset(NETWORK_IP_TYPES[$form['ip_type'] ?? '']) ? $form['ip_type'] : 'Static';
$pool = networkParseRange($network['network_dhcp_range'] ?? '', networkSubnetInfo($network['network']));

$ip_input = function ($name, $id, $value, $required) use ($ip_split, $ip_fixed_octets, $ip_host_octets, $ip_placeholder) {
    $html = '<div class="input-group">';
    $html .= $ip_split ? '<span class="input-group-text font-monospace fw-semibold">' . escapeHtml($ip_fixed_octets) . '</span>' : '<span class="input-group-text"><i class="fa fa-fw fa-map-pin"></i></span>';
    $html .= '<input type="text" class="form-control font-monospace" name="' . $name . '" id="' . $id . '" value="' . escapeHtml($value) . '" placeholder="' . escapeHtml($ip_placeholder) . '"'
        . ($ip_split && $ip_host_octets === 1 ? ' inputmode="numeric"' : '') . ' maxlength="45"' . ($required ? ' required' : '') . '>';
    return $html . '</div>';
};

?>

<div class="mb-3">
    <label class="form-label">IP Address <strong class="text-danger">*</strong></label>
    <?= $ip_input('ip_address', 'networkIpAddress', $ip_split ? $ip_host_value : $ip_value, true) ?>
    <div class="small mt-1" id="networkIpFeedback"></div>
    <div class="small text-body-secondary d-flex flex-wrap column-gap-3">
        <span><?= networkTile(networkType($network), 'xs') ?> <?= escapeHtml($network['network_name']) ?> - <span class="font-monospace"><?= escapeHtml($network['network']) ?></span></span>
        <?php if ($pool) { ?><span title="Addresses DHCP hands out"><i class="fas fa-fw fa-random me-1"></i>DHCP <span class="font-monospace"><?= escapeHtml(networkRangeText(intToIpv4($pool[0]), intToIpv4($pool[1]))) ?></span></span><?php } ?>
    </div>
    <?php if ($is_new && $ip_split) { ?>
        <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" id="networkIpRangeToggle" <?= !empty($form['ip_range_end']) ? 'checked' : '' ?>>
            <label class="form-check-label small" for="networkIpRangeToggle">A run of addresses</label>
        </div>
        <div class="mt-2" id="networkIpRange" <?= !empty($form['ip_range_end']) ? '' : 'hidden' ?>>
            <label class="form-label small mb-1">To</label>
            <?= $ip_input('ip_range_end', 'networkIpRangeEnd', '', false) ?>
            <div class="form-text">Up to 256 at a time - the ones already documented are skipped.</div>
        </div>
    <?php } ?>
</div>

<div class="mb-3">
    <label class="form-label d-block">What it is</label>
    <div class="btn-group w-100 flex-wrap itflow-net-ip-types" role="group">
        <?php foreach (NETWORK_IP_TYPES as $type => [$icon, $tone, $hint, $label]) { ?>
            <input type="radio" class="btn-check" name="type" id="networkIpType<?= $type ?>" value="<?= $type ?>" <?= $form_type === $type ? 'checked' : '' ?> autocomplete="off">
            <label class="btn btn-outline-secondary text-start" for="networkIpType<?= $type ?>" title="<?= escapeHtml($hint) ?>">
                <i class="fas fa-fw <?= $icon ?> me-1"></i><?= $label ?>
            </label>
        <?php } ?>
    </div>
    <div class="form-text" id="networkIpTypeHint"><?= escapeHtml(NETWORK_IP_TYPES[$form_type][2]) ?></div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Hostname</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
            <input type="text" class="form-control" name="hostname" placeholder="dc01, printer-hr, ap-lobby" maxlength="200" value="<?= escapeHtml($form['ip_hostname'] ?? '') ?>">
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label">MAC address <span class="text-danger" id="networkIpMacRequired" <?= $form_type === 'Reservation' ? '' : 'hidden' ?>>*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
            <input type="text" class="form-control font-monospace" name="mac" placeholder="00:1A:2B:3C:4D:5E" maxlength="200" value="<?= escapeHtml($form['ip_mac'] ?? '') ?>">
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Description</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-align-left"></i></span>
            <input type="text" class="form-control" name="description" placeholder="Domain controller, HR floor printer" maxlength="200" value="<?= escapeHtml($form['ip_description'] ?? '') ?>">
        </div>
    </div>
</div>

<script>
    (function () {
        // A reservation is for a MAC; the range switch shows the second address
        var hints = <?= json_encode(array_map(function ($style) { return $style[2]; }, NETWORK_IP_TYPES)) ?>;
        document.querySelectorAll('.itflow-net-ip-types .btn-check').forEach(function (radio) {
            radio.addEventListener('change', function () {
                document.getElementById('networkIpTypeHint').textContent = hints[radio.value] || '';
                document.getElementById('networkIpMacRequired').hidden = radio.value !== 'Reservation';
            });
        });
        var toggle = document.getElementById('networkIpRangeToggle');
        if (toggle) {
            toggle.addEventListener('change', function () {
                var range = document.getElementById('networkIpRange');
                range.hidden = !toggle.checked;
                if (!toggle.checked) {
                    document.getElementById('networkIpRangeEnd').value = '';
                }
            });
        }
    })();
</script>
