<?php

/*
 * Domain quick look - opened from the domains list, vendors, services, the
 * client overview and search. When it expires and whether it renews by
 * itself, who registers it and who hosts DNS, the website and email, its
 * records (each to copy), the email checks clients get asked about (SPF,
 * DMARC, DKIM), its certificates and services, the registrar and DNS logins,
 * notes, the raw WHOIS and its history. Changing it takes edit rights.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support');

$domain_id = intval($_GET['id'] ?? 0);
$domain = domainRow($domain_id);
if (!$domain) {
    echo json_encode(['error' => 'That domain no longer exists.']);
    exit;
}
$client_id = intval($domain['domain_client_id']);
enforceClientAccess();

// When it was last looked at - kept off the updated date, which the refresher uses
mysqli_query($mysqli, "UPDATE domains SET domain_accessed_at = NOW(), domain_updated_at = domain_updated_at WHERE domain_id = $domain_id");

$domain_name = escapeHtml($domain['domain_name']);
$domain_archived = !empty($domain['domain_archived_at']);
$can_edit = domainCan(2) && !$domain_archived;
$expiry = domainExpiry($domain['domain_expire'], $domain['domain_auto_renew']);
$facts = domainRegistrationFacts($domain['domain_raw_whois']);
$mail_checks = domainMailChecks($domain);
$certificates = domainCertificates($domain);
$services = domainServices($domain_id);
$logins = domainLogins($domain);
$history = domainHistory($domain_id, 15);
$tags = tagsForRecord('domain', $domain_id);
$txt_records = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $domain['domain_txt'])), 'strlen'));

// One record line with its copy button
$record = function ($value) {
    $value = trim((string) $value);
    return '<div class="d-flex align-items-center min-w-0"><span class="font-monospace small text-truncate" title="' . escapeHtml($value) . '">' . escapeHtml($value) . '</span>'
        . '<button type="button" class="itflow-copy clipboardjs" title="Copy" data-clipboard-text="' . escapeHtml($value) . '"><i class="far fa-copy"></i></button></div>';
};
$records = function ($text) use ($record) {
    $lines = domainLines($text);
    return $lines ? implode('', array_map($record, $lines)) : '<span class="text-body-secondary">None</span>';
};

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <?= domainTile() ?>
        <div class="min-w-0">
            <h5 class="modal-title d-flex flex-wrap align-items-center column-gap-2 row-gap-1">
                <span class="fw-semibold text-break"><?= $domain_name ?></span>
                <?php if (intval($domain['domain_favorite'])) { ?><i class="fas fa-star text-warning small" title="Starred"></i><?php } ?>
                <?php if ($domain_archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
            </h5>
            <div class="small text-body-secondary d-flex flex-wrap column-gap-3 mt-1">
                <span><?= escapeHtml($domain['client_name']) ?></span>
                <?php if ($domain['domain_description']) { ?><span><?= escapeHtml($domain['domain_description']) ?></span><?php } ?>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 small text-body-secondary">
        <?= domainExpiryPill($expiry, true) ?>
        <?php if (intval($domain['domain_auto_renew'])) { ?>
            <span class="badge rounded-pill bg-info-subtle text-info-emphasis border border-info-subtle fw-normal"><i class="fas fa-sync-alt me-1"></i>Auto-renews</span>
        <?php } ?>
        <a href="https://<?= $domain_name ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt me-1"></i>Open the website</a>
        <span title="<?= escapeHtml($domain['domain_refreshed_at'] ?? '') ?>"><i class="fas fa-search me-1"></i><?= $domain['domain_refreshed_at'] ? 'Looked up ' . timeAgo($domain['domain_refreshed_at']) : 'Not looked up yet' ?></span>
        <?php if ($tags) { ?><span><?= tagPills('domain', $domain_id, $tags, "domains.php?client_id=$client_id&", false) ?></span><?php } ?>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="small text-body-secondary fw-semibold text-uppercase mb-2">Registration</div>
            <dl class="itflow-asset-dl itflow-domain-dl mb-3">
                <dt>Expires</dt>
                <dd><?= $expiry ? escapeHtml($expiry['date']) : '<span class="text-body-secondary">Unknown</span>' ?></dd>
                <dt>Auto-renew</dt>
                <dd><?= intval($domain['domain_auto_renew']) ? 'Yes' : 'No' ?></dd>
                <?php if (!empty($facts['registered'])) { ?>
                    <dt>Registered</dt>
                    <dd><?= escapeHtml($facts['registered']) ?> <span class="small text-body-secondary">(<?= timeAgo($facts['registered']) ?>)</span></dd>
                <?php } ?>
                <?php if (!empty($facts['registrar'])) { ?>
                    <dt>Registry says</dt>
                    <dd class="text-break"><?= escapeHtml($facts['registrar']) ?></dd>
                <?php } ?>
                <?php if (!empty($facts['status'])) { ?>
                    <dt>Status</dt>
                    <dd class="small text-break"><?= escapeHtml($facts['status']) ?></dd>
                <?php } ?>
            </dl>

            <div class="small text-body-secondary fw-semibold text-uppercase mb-2">Who</div>
            <?php if (domainVendors($domain)) { ?>
                <div class="d-flex flex-column gap-2 mb-3"><?= domainVendorsHtml($domain) ?></div>
            <?php } else { ?>
                <p class="small text-body-secondary mb-3">No registrar or hosts set<?= $can_edit ? ' - Edit to add them' : '' ?>.</p>
            <?php } ?>
        </div>

        <div class="col-md-6">
            <div class="small text-body-secondary fw-semibold text-uppercase mb-2">DNS</div>
            <dl class="itflow-asset-dl itflow-domain-dl mb-3">
                <dt>A</dt>
                <dd class="min-w-0"><?= $records($domain['domain_ip']) ?></dd>
                <dt>Name servers</dt>
                <dd class="min-w-0"><?= $records($domain['domain_name_servers']) ?></dd>
                <dt>MX</dt>
                <dd class="min-w-0"><?= $records($domain['domain_mail_servers']) ?></dd>
            </dl>

            <div class="small text-body-secondary fw-semibold text-uppercase mb-2">Email</div>
            <div class="mb-2"><?= domainMailPillsHtml($mail_checks) ?></div>
            <ul class="list-unstyled small mb-0 itflow-domain-checks">
                <?php foreach ($mail_checks as $check) { ?>
                    <li class="text-break"><?= escapeHtml($check['text']) ?></li>
                <?php } ?>
            </ul>
        </div>
    </div>

    <?php if ($certificates) { ?>
        <div class="small text-body-secondary fw-semibold text-uppercase mt-3 mb-1">Certificates</div>
        <ul class="list-unstyled mb-0 itflow-links">
            <?php foreach ($certificates as $certificate) { ?>
                <li class="itflow-link-row">
                    <span class="itflow-link-icon" aria-hidden="true"><i class="fas fa-fw fa-lock"></i></span>
                    <div class="min-w-0 flex-grow-1">
                        <div class="d-flex"><a class="ajax-modal text-truncate" href="#" data-modal-url="modals/certificate/certificate_edit.php?id=<?= intval($certificate['certificate_id']) ?>"><?= escapeHtml($certificate['certificate_domain'] ?: $certificate['certificate_name']) ?></a></div>
                        <div class="small text-body-secondary text-truncate"><?= escapeHtml($certificate['certificate_issued_by'] ?: 'Issuer unknown') ?></div>
                    </div>
                    <?= softwareRenewalPill(softwareRenewal($certificate['certificate_expire']), true) ?>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>

    <?php if ($services) { ?>
        <div class="small text-body-secondary fw-semibold text-uppercase mt-3 mb-1">Services that use it</div>
        <ul class="list-unstyled mb-0 itflow-links">
            <?php foreach ($services as $service) { ?>
                <li class="itflow-link-row">
                    <span class="itflow-link-icon" aria-hidden="true"><i class="fas fa-fw fa-stream"></i></span>
                    <div class="min-w-0 flex-grow-1">
                        <div class="d-flex"><a class="ajax-modal text-truncate" href="#" data-modal-size="lg" data-modal-url="modals/service/service.php?id=<?= intval($service['service_id']) ?>"><?= escapeHtml($service['service_name']) ?></a></div>
                        <div class="small text-body-secondary"><?= escapeHtml($service['service_importance']) ?> importance</div>
                    </div>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>

    <?php if ($logins) { ?>
        <div class="small text-body-secondary fw-semibold text-uppercase mt-3 mb-1">Logins for the registrar and hosts</div>
        <?= credentialListHtml($logins) ?>
    <?php } ?>

    <?php if (trim((string) $domain['domain_notes']) !== '') { ?>
        <div class="small text-body-secondary fw-semibold text-uppercase mt-3 mb-1">Notes</div>
        <div class="itflow-credential-note small"><?= nl2br(escapeHtml($domain['domain_notes'])) ?></div>
    <?php } ?>

    <?php if ($txt_records) { ?>
        <details class="mt-3">
            <summary class="small text-body-secondary fw-semibold text-uppercase">TXT records (<?= count($txt_records) ?>)</summary>
            <div class="mt-2"><?= implode('', array_map($record, $txt_records)) ?></div>
            <?php if (trim((string) $domain['domain_dmarc']) !== '') { ?>
                <div class="small text-body-secondary mt-2">_dmarc</div>
                <?= $record($domain['domain_dmarc']) ?>
            <?php } ?>
        </details>
    <?php } ?>

    <?php if (trim((string) $domain['domain_raw_whois']) !== '') { ?>
        <details class="mt-3">
            <summary class="small text-body-secondary fw-semibold text-uppercase">WHOIS</summary>
            <pre class="itflow-credential-note small mt-2 mb-0"><?= escapeHtml($domain['domain_raw_whois']) ?></pre>
        </details>
    <?php } ?>

    <?php if ($history) { ?>
        <details class="mt-3">
            <summary class="small text-body-secondary fw-semibold text-uppercase">History</summary>
            <ul class="list-unstyled small mt-2 mb-0">
                <?php foreach ($history as $entry) {
                    $old = trim((string) $entry['domain_history_old_value']);
                    $new = trim((string) $entry['domain_history_new_value']); ?>
                    <li class="d-flex gap-2 py-1 border-bottom">
                        <span class="flex-grow-1 min-w-0 text-break">
                            <strong><?= escapeHtml($entry['label']) ?></strong>
                            <?php if (mb_strlen($old) <= 60 && mb_strlen($new) <= 60 && strpos($old . $new, "\n") === false) { ?>
                                <?= $old === '' ? 'set to' : '<span class="text-body-secondary">' . escapeHtml($old) . '</span> &rarr;' ?> <?= $new === '' ? '<span class="text-body-secondary">cleared</span>' : escapeHtml($new) ?>
                            <?php } else { ?>
                                changed
                            <?php } ?>
                        </span>
                        <span class="text-body-secondary text-nowrap" title="<?= escapeHtml($entry['domain_history_modified_at']) ?>"><?= timeAgo($entry['domain_history_modified_at']) ?></span>
                    </li>
                <?php } ?>
            </ul>
        </details>
    <?php } ?>

</div>

<div class="modal-footer">
    <?php if ($can_edit) { ?>
        <a class="btn btn-outline-secondary me-auto" href="post.php?refresh_domain=<?= $domain_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-sync-alt me-2"></i>Look up now</a>
        <button type="button" class="btn btn-primary text-bold ajax-modal" data-modal-size="lg" data-modal-url="modals/domain/domain_edit.php?id=<?= $domain_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
