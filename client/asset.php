<?php
/*
 * Client Portal
 * A device - what it is, who uses it and where, its warranty, the licenses
 * on it and its open tickets, with Report a problem. A primary or technical
 * contact opens any of their company's devices; anyone else the ones they use.
 *
 * SCOPING: portalAssetScopeSql().
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

$can_it = $config_module_enable_itdoc && contactCan('itdoc');
$back = $can_it ? ['assets.php', 'Devices'] : ['index.php', 'Home'];

$asset_id = intval($_GET['id'] ?? 0);
$asset = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_description, asset_type, asset_make, asset_model, asset_serial, asset_os,
        asset_status, asset_purchase_date, asset_warranty_expire, asset_install_date, asset_uri_client, asset_contact_id, contact_name, location_name
    FROM assets
    LEFT JOIN contacts ON contact_id = asset_contact_id AND contact_archived_at IS NULL
    LEFT JOIN locations ON location_id = asset_location_id AND location_archived_at IS NULL
    WHERE asset_id = $asset_id AND " . portalAssetScopeSql() . " LIMIT 1"));

if (!$asset) { ?>
    <a class="itflow-portal-back" href="<?= $back[0] ?>"><i class="fas fa-chevron-left"></i><?= $back[1] ?></a>
    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => "That device isn't here", 'text' => 'It may have been retired, or it is not one you can see.', 'icon' => 'fa-desktop', 'filtered' => false,
            'actions' => [['label' => $back[1], 'href' => $back[0], 'icon' => 'fa-arrow-left']]]) ?>
    </section>
    <?php
    require_once "includes/footer.php";
    exit;
}

$warranty = assetWarranty($asset['asset_warranty_expire']);
$what = trim($asset['asset_make'] . ' ' . $asset['asset_model']);
$link = escapeUrl($asset['asset_uri_client']);
$licenses = $can_it ? softwareHeldBy('asset', $asset_id) : [];

// Its open tickets - the ones this contact may see
$tickets = mysqli_query($mysqli, "SELECT " . portalTicketColumnsSql() . " FROM tickets
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    WHERE " . portalTicketScopeSql() . " AND tickets.ticket_archived_at IS NULL AND " . TICKET_OPEN_SQL . "
        AND (tickets.ticket_asset_id = $asset_id OR EXISTS (SELECT 1 FROM ticket_assets WHERE ticket_assets.ticket_id = tickets.ticket_id AND ticket_assets.asset_id = $asset_id))
    ORDER BY COALESCE(tickets.ticket_updated_at, tickets.ticket_created_at) DESC LIMIT 10");

$facts = array_filter([
    'Kind' => escapeHtml($asset['asset_type']),
    'Make and model' => escapeHtml($what),
    'Serial number' => $asset['asset_serial'] !== '' && $asset['asset_serial'] !== null ? '<span class="font-monospace">' . escapeHtml($asset['asset_serial']) . '</span>' : '',
    'Operating system' => escapeHtml($asset['asset_os']),
    'Used by' => !empty($asset['contact_name']) ? escapeHtml($asset['contact_name']) . (intval($asset['asset_contact_id']) === $session_contact_id ? ' (you)' : '') : '',
    'Location' => escapeHtml($asset['location_name'] ?? ''),
    'Purchased' => !empty($asset['asset_purchase_date']) ? escapeHtml(date('M j, Y', strtotime($asset['asset_purchase_date']))) . (assetAge($asset['asset_purchase_date']) !== '' ? ' <span class="text-body-secondary">(' . escapeHtml(assetAge($asset['asset_purchase_date'])) . ')</span>' : '') : '',
    'Warranty' => $warranty ? portalStateBadge(['label' => $warranty['text'], 'icon' => 'fa-shield-alt', 'class' => ['danger' => 'itflow-portal-state-late', 'warning' => 'itflow-portal-state-you', 'success' => 'itflow-portal-state-resolved'][$warranty['tone']]]) : '',
    'Status' => escapeHtml($asset['asset_status']),
], function ($value) {
    return trim((string) $value) !== '';
});
?>

<a class="itflow-portal-back" href="<?= $back[0] ?>"><i class="fas fa-chevron-left"></i><?= $back[1] ?></a>

<div class="itflow-portal-page-head">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <?= portalTile('fa-' . getAssetIcon($asset['asset_type']), 'lg') ?>
        <div class="min-w-0">
            <h1 class="text-truncate"><?= escapeHtml($asset['asset_name']) ?></h1>
            <p><?= escapeHtml(implode(' · ', array_filter([$asset['asset_type'], $what]))) ?></p>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($link) { ?>
            <a class="btn btn-outline-secondary" href="<?= $link ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt me-2"></i>Open</a>
        <?php } ?>
        <a class="btn btn-primary" href="ticket_add.php?asset=<?= $asset_id ?>"><i class="fas fa-life-ring me-2"></i>Report a problem</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <section class="itflow-portal-panel">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-info-circle"></i>Details</h2>
            </div>
            <div class="itflow-portal-panel-body">
                <?php if (trim((string) $asset['asset_description']) !== '') { ?>
                    <p class="mb-3"><?= escapeHtml($asset['asset_description']) ?></p>
                <?php } ?>
                <dl class="itflow-portal-facts">
                    <?php foreach ($facts as $label => $value) { ?>
                        <div><dt><?= $label ?></dt><dd><?= $value ?></dd></div>
                    <?php } ?>
                </dl>
            </div>
        </section>
    </div>

    <div class="col-lg-5">
        <section class="itflow-portal-panel mb-4">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-life-ring"></i>Open tickets about it</h2>
            </div>
            <?php if (!mysqli_num_rows($tickets)) { ?>
                <?= emptyStateHtml(['title' => 'Nothing open', 'text' => 'Something wrong with it? Report a problem and we will pick it up.', 'icon' => 'fa-check', 'filtered' => false]) ?>
            <?php } else { ?>
                <ul class="itflow-portal-list">
                    <?php while ($row = mysqli_fetch_assoc($tickets)) { ?>
                        <li>
                            <a class="itflow-portal-ticket" href="ticket.php?id=<?= intval($row['ticket_id']) ?>">
                                <span class="itflow-portal-ticket-main">
                                    <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['ticket_subject']) ?></span>
                                    <span class="itflow-portal-ticket-meta"><span><?= escapeHtml($row['ticket_prefix'] . $row['ticket_number']) ?></span></span>
                                </span>
                                <span class="itflow-portal-ticket-side"><?= portalTicketStateHtml($row) ?></span>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </section>

        <?php if ($licenses) { ?>
            <section class="itflow-portal-panel">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-cube"></i>Licenses on it</h2>
                    <a href="licenses.php">All licenses</a>
                </div>
                <ul class="itflow-portal-list">
                    <?php foreach ($licenses as $license) {
                        $renewal = portalRenewalState(softwareRenewal($license['software_expire']), 'Active'); ?>
                        <li class="itflow-portal-todo">
                            <span class="itflow-portal-todo-main">
                                <div><?= escapeHtml(trim($license['software_name'] . ' ' . $license['software_version'])) ?></div>
                                <div><?= escapeHtml(implode(' · ', array_filter([$license['software_type'], $license['software_license_type'], $license['vendor_name']]))) ?></div>
                            </span>
                            <?= $renewal ? portalStateBadge($renewal) : '' ?>
                        </li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>
    </div>
</div>

<?php
require_once "includes/footer.php";
