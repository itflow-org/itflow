<?php

/*
 * Service quick look - opened from the services list, and from the assets,
 * contacts, vendors, domains, certificates and credentials it depends on.
 * What it is and how important, anything it depends on that needs
 * attention, what it runs on, who to call and how it comes back - and the
 * way to its page, where all of it is linked and unlinked.
 */

require_once '../../../includes/modal_header.php';

$service = serviceForModal($_GET['id'] ?? 0, 1);
$service_id = intval($service['service_id']);
$service_archived = !empty($service['service_archived_at']);
$can_edit = serviceCan(2) && !$service_archived;
$can_credentials = lookupUserPermission('module_credential') >= 1;

$counts = serviceCounts([$service_id])[$service_id];
$issues = serviceIssues([$service_id])[$service_id];
$review = serviceReview($service);
$active = function ($row) {
    return !$row['archived_at'];
};
$assets = array_values(array_filter(serviceLinked($service_id, 'asset'), $active));
$contacts = array_values(array_filter(serviceLinked($service_id, 'contact'), $active));
$vendors = array_values(array_filter(serviceLinked($service_id, 'vendor'), $active));
$urls = serviceUrls($service_id, $can_credentials);
$page_url = escapeHtml(serviceUrl($service_id, $client_id));

// The rest it depends on, counted - each opening its tab on the page
$also = [];
foreach (['credential' => ['fa-key', 'login', 'logins', 'logins'], 'domain' => ['fa-globe', 'domain', 'domains', 'web'],
    'certificate' => ['fa-lock', 'certificate', 'certificates', 'web'], 'document' => ['fa-file-alt', 'document', 'documents', 'documents']] as $type => [$icon, $one, $many, $tab]) {
    if ($counts[$type]) {
        $also[] = '<a class="itflow-contact-chip" href="' . $page_url . '#' . $tab . '"><i class="fas ' . $icon . '"></i>' . $counts[$type] . ' ' . ($counts[$type] === 1 ? $one : $many) . '</a>';
    }
}

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <?= serviceTile($service['service_category']) ?>
        <div class="min-w-0">
            <h5 class="modal-title d-flex flex-wrap align-items-center column-gap-2 row-gap-1">
                <span class="fw-semibold text-break"><?= escapeHtml($service['service_name']) ?></span>
                <?php if (intval($service['service_favorite'])) { ?><i class="fas fa-star text-warning small" title="Starred"></i><?php } ?>
                <?php if ($service_archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
            </h5>
            <div class="small text-body-secondary d-flex flex-wrap column-gap-3 mt-1">
                <span><?= escapeHtml($service['client_name']) ?></span>
                <?php if ($service['service_category']) { ?><span><?= escapeHtml($service['service_category']) ?></span><?php } ?>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <?= serviceImportancePill($service['service_importance'], true) ?>
        <?= serviceReviewPill($review, true) ?>
        <?php if ($counts['open_tickets']) { ?>
            <a class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle fw-normal text-decoration-none" href="<?= $page_url ?>#tickets"><i class="fas fa-life-ring me-1"></i><?= $counts['open_tickets'] ?> open ticket<?= $counts['open_tickets'] === 1 ? '' : 's' ?></a>
        <?php } ?>
    </div>

    <?php if ($service['service_description']) { ?>
        <p class="mb-3"><?= escapeHtml($service['service_description']) ?></p>
    <?php } ?>

    <?php if ($issues) { ?>
        <div class="alert alert-<?= in_array('danger', array_column($issues, 'tone'), true) ? 'danger' : 'warning' ?> py-2 small mb-3">
            <div class="fw-semibold mb-1">Needs attention</div>
            <?= serviceIssuesHtml($issues, 5) ?>
        </div>
    <?php } ?>

    <dl class="itflow-asset-dl mb-0">
        <dt>Runs on</dt>
        <dd class="min-w-0">
            <?php if ($assets) { ?>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach (array_slice($assets, 0, 6) as $asset) { ?>
                        <a class="itflow-contact-chip ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?id=<?= intval($asset['id']) ?>"><i class="fas fa-fw fa-<?= getAssetIcon($asset['asset_type']) ?> text-secondary"></i><?= escapeHtml($asset['name']) ?></a>
                    <?php } ?>
                    <?php if (count($assets) > 6) { ?><a class="small align-self-center" href="<?= $page_url ?>#assets">+<?= count($assets) - 6 ?> more</a><?php } ?>
                </div>
            <?php } else { ?>
                <span class="text-body-secondary">Nothing linked</span>
            <?php } ?>
        </dd>
        <?php if ($contacts) { ?>
            <dt>People</dt>
            <dd class="d-flex flex-wrap column-gap-3 row-gap-1">
                <?php foreach ($contacts as $contact) { ?>
                    <?= contactLink($contact['contact_client_id'], $contact['id'], $contact['name'], $contact['contact_photo']) ?>
                <?php } ?>
            </dd>
        <?php } ?>
        <?php if ($vendors) { ?>
            <dt>Call</dt>
            <dd>
                <?php foreach ($vendors as $vendor) {
                    $phone = contactPhoneLink($vendor['vendor_phone'], $vendor['vendor_phone_country_code'], $vendor['vendor_extension']); ?>
                    <div class="d-flex flex-wrap align-items-center column-gap-2"><?= vendorLink($vendor['id'], $vendor['name']) ?><?php if ($phone) { ?><span class="small"><?= $phone ?></span><?php } ?></div>
                <?php } ?>
            </dd>
        <?php } ?>
        <?php if ($urls) { ?>
            <dt>Opens at</dt>
            <dd class="min-w-0">
                <?php foreach (array_slice($urls, 0, 4) as $url) { ?>
                    <div class="text-truncate"><a href="<?= escapeHtml($url['url']) ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-fw <?= $url['icon'] ?> text-secondary me-1"></i><?= escapeHtml($url['label']) ?></a></div>
                <?php } ?>
            </dd>
        <?php } ?>
        <?php if (trim((string) $service['service_backup']) !== '') { ?>
            <dt>Recovery</dt>
            <dd class="itflow-service-text"><?= escapeHtml($service['service_backup']) ?></dd>
        <?php } ?>
        <?php if ($also) { ?>
            <dt>Also</dt>
            <dd class="d-flex flex-wrap gap-1"><?= implode('', $also) ?></dd>
        <?php } ?>
    </dl>

    <?php if (trim((string) $service['service_notes']) !== '') { ?>
        <div class="small text-body-secondary fw-semibold text-uppercase mt-3 mb-1">Notes</div>
        <div class="small itflow-service-text itflow-service-notes-peek"><?= escapeHtml($service['service_notes']) ?></div>
    <?php } ?>

</div>

<div class="modal-footer">
    <?php if ($can_edit) { ?>
        <a class="btn btn-outline-secondary ajax-modal me-auto" href="#" data-modal-size="lg" data-modal-url="<?= escapeHtml('modals/ticket/ticket_add.php?' . http_build_query(['client_id' => $client_id, 'service_id' => $service_id])) ?>">
            <i class="fas fa-life-ring me-2"></i>Report a problem
        </a>
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/service/service_edit.php?id=<?= $service_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
    <?php } ?>
    <a class="btn btn-primary" href="<?= $page_url ?>"><i class="fas fa-arrow-right me-2"></i>Open service</a>
</div>

<?php
require_once '../../../includes/modal_footer.php';
