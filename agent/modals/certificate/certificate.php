<?php

/*
 * Certificate quick look - opened from the certificates list, domains,
 * services, the client overview and search. What the last check of the host
 * found and when, when it expires and whether it renews itself, who issued
 * it, the names it covers, its fingerprint and the certificate itself (to
 * copy or download), the domain it belongs to, the services that use it,
 * notes and its history. Changing it takes edit rights.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support');

$certificate_id = intval($_GET['id'] ?? 0);
$certificate = certificateRow($certificate_id);
if (!$certificate) {
    echo json_encode(['error' => 'That certificate no longer exists.']);
    exit;
}
$client_id = intval($certificate['certificate_client_id']);
enforceClientAccess();

// When it was last looked at - kept off the updated date
mysqli_query($mysqli, "UPDATE certificates SET certificate_accessed_at = NOW(), certificate_updated_at = certificate_updated_at WHERE certificate_id = $certificate_id");

$certificate_name = escapeHtml($certificate['certificate_name']);
$certificate_archived = !empty($certificate['certificate_archived_at']);
$can_edit = certificateCan(2) && !$certificate_archived;
$host = certificateHost($certificate['certificate_domain']);
$expiry = certificateExpiry($certificate);
$parsed = certificateParse($certificate['certificate_public_key']);
$names = certificateNames($certificate);
$services = servicesFor('certificate', $certificate_id);
$history = certificateHistory($certificate_id, 15);
$pem = $parsed ? $parsed['pem'] : '';
$fingerprint = trim((string) $certificate['certificate_fingerprint']);

// What the last check found, said in full when it is a problem - with what to do about it
$status_problem = null;
$status = (string) $certificate['certificate_check_status'];
if ($status !== 'ok' && isset(CERTIFICATE_CHECK_STATES[$status])) {
    $status_problem = CERTIFICATE_CHECK_STATES[$status];
    $status_problem['hint'] = [
        'mismatch' => 'install the certificate for this name, or check the host is right',
        'untrusted' => !$parsed ? 'replace a self-signed certificate with one from a CA (or your own CA, for internal hosts), or install the intermediate certificates with it'
            : ($parsed['self_signed'] ? 'replace the self-signed certificate with one from a CA (or your own CA, for internal hosts)' : 'install the intermediate certificates with it'),
        'expired' => 'renew it, and install the new one',
        'unreachable' => 'check the host and port, or that ITFlow can reach it',
    ][$status] ?? '';
}

// Covered names - the host's own first, then the rest as the certificate lists them
if ($host && $names) {
    usort($names, function ($a, $b) use ($host) {
        return intval(certificateCovers($host['host'], [$b])) - intval(certificateCovers($host['host'], [$a]));
    });
}

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <?= certificateTile() ?>
        <div class="min-w-0">
            <h5 class="modal-title d-flex flex-wrap align-items-center column-gap-2 row-gap-1">
                <span class="fw-semibold text-break"><?= $certificate_name ?></span>
                <?php if (intval($certificate['certificate_favorite'])) { ?><i class="fas fa-star text-warning small" title="Starred"></i><?php } ?>
                <?php if ($certificate_archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
            </h5>
            <div class="small text-body-secondary d-flex flex-wrap column-gap-3 mt-1">
                <span><?= escapeHtml($certificate['client_name']) ?></span>
                <?php if ($certificate['certificate_description']) { ?><span><?= escapeHtml($certificate['certificate_description']) ?></span><?php } ?>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 small text-body-secondary">
        <?= certificateStatusPill($certificate) ?>
        <?= certificateExpiryPill($expiry, true) ?>
        <?php if ($host) { ?>
            <a href="https://<?= escapeHtml($host['display']) ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt me-1"></i>Open the site</a>
        <?php } ?>
        <?php if ($host) { ?>
            <span title="<?= escapeHtml($certificate['certificate_checked_at'] ?? '') ?>"><i class="fas fa-search me-1"></i><?= $certificate['certificate_checked_at'] ? 'Checked ' . timeAgo($certificate['certificate_checked_at']) : 'Not checked yet' ?></span>
        <?php } ?>
    </div>

    <?php if ($status_problem) { ?>
        <div class="alert alert-<?= $status_problem['tone'] ?> d-flex align-items-start gap-2 py-2 small" role="status">
            <i class="fas <?= $status_problem['icon'] ?> mt-1"></i>
            <div><?= escapeHtml($status_problem['text']) ?><?= $status_problem['hint'] !== '' ? ' - ' . escapeHtml($status_problem['hint']) : '' ?>.</div>
        </div>
    <?php } ?>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="small text-body-secondary fw-semibold text-uppercase mb-2">Certificate</div>
            <dl class="itflow-asset-dl itflow-domain-dl mb-3">
                <dt>Issuer</dt>
                <dd class="text-break"><?= $certificate['certificate_issued_by'] !== '' ? escapeHtml($certificate['certificate_issued_by']) : '<span class="text-body-secondary">Unknown</span>' ?><?= $parsed && $parsed['self_signed'] ? ' <span class="small text-body-secondary">(self-signed)</span>' : '' ?></dd>
                <?php if (!empty($certificate['certificate_issued_at'])) { ?>
                    <dt>Issued</dt>
                    <dd><?= escapeHtml($certificate['certificate_issued_at']) ?> <span class="small text-body-secondary">(<?= timeAgo($certificate['certificate_issued_at']) ?>)</span></dd>
                <?php } ?>
                <dt>Expires</dt>
                <dd><?= $expiry ? escapeHtml($expiry['date']) . ' <span class="small text-body-secondary">(' . timeAgo($expiry['date']) . ')</span>' : '<span class="text-body-secondary">Unknown</span>' ?></dd>
                <?php if ($parsed) { ?>
                    <dt>Valid for</dt>
                    <dd><?= $parsed['days'] ?> days<?= certificateShortLived($certificate) ? ' <span class="small text-body-secondary">- short-lived, renews itself</span>' : '' ?></dd>
                <?php } ?>
                <?php if ($fingerprint !== '') { ?>
                    <dt>SHA-256</dt>
                    <dd class="min-w-0">
                        <div class="d-flex align-items-center min-w-0">
                            <span class="font-monospace small itflow-certificate-fingerprint"><?= str_replace(':', ':<wbr>', escapeHtml($fingerprint)) ?></span>
                            <button type="button" class="itflow-copy clipboardjs" title="Copy" data-clipboard-text="<?= escapeHtml($fingerprint) ?>"><i class="far fa-copy"></i></button>
                        </div>
                    </dd>
                <?php } ?>
            </dl>
        </div>

        <div class="col-md-6">
            <div class="small text-body-secondary fw-semibold text-uppercase mb-2">Where</div>
            <dl class="itflow-asset-dl itflow-domain-dl mb-3">
                <dt>Host</dt>
                <dd class="min-w-0">
                    <?php if ($host) { ?>
                        <div class="d-flex align-items-center min-w-0">
                            <span class="font-monospace small text-truncate"><?= escapeHtml($host['display']) ?></span>
                            <button type="button" class="itflow-copy clipboardjs" title="Copy" data-clipboard-text="<?= escapeHtml($host['display']) ?>"><i class="far fa-copy"></i></button>
                        </div>
                    <?php } else { ?>
                        <span class="text-body-secondary">None<?= $can_edit ? ' - Edit to add the host, so it can be checked' : '' ?></span>
                    <?php } ?>
                </dd>
                <dt>Domain</dt>
                <dd class="min-w-0"><?= intval($certificate['certificate_domain_id']) && $certificate['domain_name'] !== null
                    ? domainLink($certificate['certificate_domain_id'], $certificate['domain_name'], ['archived' => !empty($certificate['domain_archived_at'])])
                    : '<span class="text-body-secondary">None</span>' ?></dd>
                <dt>Covers</dt>
                <dd class="min-w-0">
                    <?php if ($names) { ?>
                        <ul class="list-unstyled mb-0 small">
                            <?php foreach (array_slice($names, 0, 8) as $covered) { ?>
                                <li class="font-monospace text-break"><?= escapeHtml($covered) ?><?= $host && certificateCovers($host['host'], [$covered]) ? ' <i class="fas fa-check text-success ms-1" title="Covers the host"></i>' : '' ?></li>
                            <?php } ?>
                        </ul>
                        <?php if (count($names) > 8) { ?>
                            <details class="small">
                                <summary class="text-body-secondary"><?= count($names) - 8 ?> more</summary>
                                <ul class="list-unstyled mb-0">
                                    <?php foreach (array_slice($names, 8) as $covered) { ?>
                                        <li class="font-monospace text-break"><?= escapeHtml($covered) ?></li>
                                    <?php } ?>
                                </ul>
                            </details>
                        <?php } ?>
                    <?php } else { ?>
                        <span class="text-body-secondary">Unknown</span>
                    <?php } ?>
                </dd>
            </dl>
        </div>
    </div>

    <?php if ($services || $can_edit) { ?>
        <div class="d-flex align-items-center mt-1 mb-1">
            <span class="small text-body-secondary fw-semibold text-uppercase me-auto">Services that use it</span>
            <?php if ($can_edit) { ?>
                <a class="small ajax-modal" href="#" data-modal-url="modals/link/link.php?from=certificate&id=<?= $certificate_id ?>&to=service"><i class="fas fa-link me-1"></i>Link a service</a>
            <?php } ?>
        </div>
        <?php if ($services) { ?>
            <?= serviceRowsHtml($services, $can_edit ? function ($row) use ($certificate_id) {
                return "post.php?unlink_item&from=certificate&id=$certificate_id&to=service&other=" . intval($row['service_id']) . "&csrf_token=" . $_SESSION['csrf_token'];
            } : null) ?>
        <?php } else { ?>
            <p class="small text-body-secondary mb-0">No service is linked to it.</p>
        <?php } ?>
    <?php } ?>

    <?php if (trim((string) $certificate['certificate_notes']) !== '') { ?>
        <div class="small text-body-secondary fw-semibold text-uppercase mt-3 mb-1">Notes</div>
        <div class="itflow-credential-note small"><?= nl2br(escapeHtml($certificate['certificate_notes'])) ?></div>
    <?php } ?>

    <?php if ($pem !== '') {
        $file_name = preg_replace('/[^a-z0-9.\-]+/i', '-', $host ? $host['host'] : $certificate['certificate_name']) . '.crt'; ?>
        <details class="mt-3">
            <summary class="small text-body-secondary fw-semibold text-uppercase">Certificate (PEM)</summary>
            <div class="d-flex flex-wrap gap-2 mt-2 mb-2">
                <button type="button" class="btn btn-sm btn-outline-secondary clipboardjs" data-clipboard-text="<?= escapeHtml($pem) ?>"><i class="far fa-copy me-1"></i>Copy</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= certificateDownloadHref($pem) ?>" download="<?= escapeHtml($file_name) ?>"><i class="fas fa-download me-1"></i>Download .crt</a>
            </div>
            <pre class="itflow-credential-note small mb-0"><?= escapeHtml($pem) ?></pre>
        </details>
    <?php } ?>

    <?php if ($history) { ?>
        <details class="mt-3">
            <summary class="small text-body-secondary fw-semibold text-uppercase">History</summary>
            <ul class="list-unstyled small mt-2 mb-0">
                <?php foreach ($history as $entry) {
                    $old = trim((string) $entry['certificate_history_old_value']);
                    $new = trim((string) $entry['certificate_history_new_value']); ?>
                    <li class="d-flex gap-2 py-1 border-bottom">
                        <span class="flex-grow-1 min-w-0 text-break">
                            <strong><?= escapeHtml($entry['label']) ?></strong>
                            <?php if (mb_strlen($old) <= 60 && mb_strlen($new) <= 60 && strpos($old . $new, "\n") === false) { ?>
                                <?= $old === '' ? 'set to' : '<span class="text-body-secondary">' . escapeHtml($old) . '</span> &rarr;' ?> <?= $new === '' ? '<span class="text-body-secondary">cleared</span>' : escapeHtml($new) ?>
                            <?php } else { ?>
                                changed
                            <?php } ?>
                        </span>
                        <span class="text-body-secondary text-nowrap" title="<?= escapeHtml($entry['certificate_history_modified_at']) ?>"><?= timeAgo($entry['certificate_history_modified_at']) ?></span>
                    </li>
                <?php } ?>
            </ul>
        </details>
    <?php } ?>

</div>

<div class="modal-footer">
    <?php if ($can_edit) { ?>
        <?php if ($host) { ?>
            <a class="btn btn-outline-secondary me-auto" href="post.php?check_certificate=<?= $certificate_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-sync-alt me-2"></i>Check now</a>
        <?php } ?>
        <button type="button" class="btn btn-primary text-bold ajax-modal" data-modal-size="lg" data-modal-url="modals/certificate/certificate_edit.php?id=<?= $certificate_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
