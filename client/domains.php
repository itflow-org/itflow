<?php
/*
 * Client Portal
 * Domains & certificates - the company's domain names with who registers
 * them and runs their DNS, website and email, and the web certificates on
 * their sites, each with when it expires or renews.
 * ?tab=certificates opens on the certificates (certificates.php sends here).
 *
 * SCOPING: primary and technical contacts (portalItGate()); keyed to
 * $session_client_id.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalItGate();

$tab = ($_GET['tab'] ?? '') === 'certificates' ? 'certificates' : 'domains';

[$columns, $joins] = domainSelectSql();
$domains = mysqli_query($mysqli, "SELECT $columns FROM domains $joins
    WHERE domain_client_id = $session_client_id AND domain_archived_at IS NULL
    ORDER BY domain_expire IS NULL, domain_expire ASC, domain_name ASC");
$certificates = mysqli_query($mysqli, "SELECT certificate_id, certificate_name, certificate_domain, certificate_issued_by, certificate_issued_at, certificate_expire
    FROM certificates WHERE certificate_client_id = $session_client_id AND certificate_archived_at IS NULL
    ORDER BY certificate_expire IS NULL, certificate_expire ASC, certificate_name ASC");
$counts = ['domains' => mysqli_num_rows($domains), 'certificates' => mysqli_num_rows($certificates)];

/** A domain's or certificate's expiry (domainExpiry(), certificateExpiry()) as a portal state - auto-renewing ones aren't a worry. */
function domainsState($expiry) {
    if (!$expiry) {
        return null;
    }
    if ($expiry['state'] === 'expired') {
        return ['label' => 'Expired', 'icon' => 'fa-calendar-times', 'class' => 'itflow-portal-state-late'];
    }
    $auto = !empty($expiry['auto']) || !empty($expiry['short_lived']);
    if ($expiry['state'] === 'soon') {
        return ['label' => $expiry['text'], 'icon' => $auto ? 'fa-sync-alt' : 'fa-hourglass-half', 'class' => $auto ? 'itflow-portal-state-new' : 'itflow-portal-state-you'];
    }
    return ['label' => $expiry['text'], 'icon' => $auto ? 'fa-sync-alt' : 'fa-calendar-check', 'class' => 'itflow-portal-state-resolved'];
}
?>

<div class="itflow-portal-page-head">
    <div>
        <h1>Domains &amp; certificates</h1>
        <p>Your domain names and the certificates that keep your sites secure</p>
    </div>
</div>

<nav class="itflow-portal-tabs" aria-label="Domains and certificates">
    <a class="<?= $tab === 'domains' ? 'active' : '' ?>" href="domains.php"><i class="fas fa-globe"></i>Domains <span class="badge rounded-pill"><?= $counts['domains'] ?></span></a>
    <a class="<?= $tab === 'certificates' ? 'active' : '' ?>" href="domains.php?tab=certificates"><i class="fas fa-lock"></i>Certificates <span class="badge rounded-pill"><?= $counts['certificates'] ?></span></a>
</nav>

<section class="itflow-portal-panel">
    <?php if ($tab === 'domains') { ?>
        <?php if (!$counts['domains']) { ?>
            <?= emptyStateHtml(['title' => 'No domains yet', 'text' => 'The domain names we look after for you show here.', 'icon' => 'fa-globe', 'filtered' => false]) ?>
        <?php } else { ?>
            <ul class="itflow-portal-list">
                <?php while ($row = mysqli_fetch_assoc($domains)) {
                    $state = domainsState(domainExpiry($row['domain_expire'], $row['domain_auto_renew'])); ?>
                    <li class="itflow-portal-ticket">
                        <?= portalTile('fa-globe') ?>
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['domain_name']) ?></span>
                            <span class="itflow-portal-ticket-meta">
                                <?php foreach (domainVendors($row) as $vendor) { ?>
                                    <span><?= escapeHtml($vendor['name']) ?> <span class="text-body-tertiary">(<?= escapeHtml(implode(', ', $vendor['roles'])) ?>)</span></span>
                                <?php } ?>
                                <?php if (trim((string) $row['domain_description']) !== '') { ?><span><?= escapeHtml($row['domain_description']) ?></span><?php } ?>
                            </span>
                        </span>
                        <span class="itflow-portal-ticket-side">
                            <?= $state ? portalStateBadge($state) : '' ?>
                            <?php if (!empty($row['domain_expire'])) { ?><small><?= escapeHtml(date('M j, Y', strtotime($row['domain_expire']))) ?></small><?php } ?>
                        </span>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>
    <?php } else { ?>
        <?php if (!$counts['certificates']) { ?>
            <?= emptyStateHtml(['title' => 'No certificates yet', 'text' => 'The web certificates we watch for you show here.', 'icon' => 'fa-lock', 'filtered' => false]) ?>
        <?php } else { ?>
            <ul class="itflow-portal-list">
                <?php while ($row = mysqli_fetch_assoc($certificates)) {
                    $state = domainsState(certificateExpiry($row)); ?>
                    <li class="itflow-portal-ticket">
                        <?= portalTile('fa-lock') ?>
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['certificate_name']) ?></span>
                            <span class="itflow-portal-ticket-meta">
                                <?php if (trim((string) $row['certificate_domain']) !== '') { ?><span><i class="fas fa-globe me-1"></i><?= escapeHtml($row['certificate_domain']) ?></span><?php } ?>
                                <?php if (trim((string) $row['certificate_issued_by']) !== '') { ?><span>Issued by <?= escapeHtml($row['certificate_issued_by']) ?></span><?php } ?>
                            </span>
                        </span>
                        <span class="itflow-portal-ticket-side">
                            <?= $state ? portalStateBadge($state) : '' ?>
                            <?php if (!empty($row['certificate_expire'])) { ?><small><?= escapeHtml(date('M j, Y', strtotime($row['certificate_expire']))) ?></small><?php } ?>
                        </span>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
