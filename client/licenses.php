<?php
/*
 * Client Portal
 * Licenses - the software the company is licensed for: how many seats are in
 * use of how many bought, and when each renews, with tabs for the ones
 * renewing in the next 30 days and the ones that have run out. License keys
 * stay on the agent side.
 *
 * SCOPING: primary and technical contacts (portalItGate()); keyed to
 * $session_client_id.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalItGate();

$q = trim((string) ($_GET['q'] ?? ''));
$tabs = [
    'all' => ['label' => 'All', 'icon' => 'fa-list', 'where' => '1 = 1'],
    'soon' => ['label' => 'Renewing soon', 'icon' => 'fa-hourglass-half', 'where' => 'software_expire BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY'],
    'expired' => ['label' => 'Expired', 'icon' => 'fa-calendar-times', 'where' => 'software_expire < CURDATE()'],
];
$where = "software_client_id = $session_client_id AND software_archived_at IS NULL";
if ($q !== '') {
    $q_sql = escapeSql($q);
    $where .= " AND (software_name LIKE '%$q_sql%' OR software_type LIKE '%$q_sql%' OR vendor_name LIKE '%$q_sql%')";
}
$from = "FROM software LEFT JOIN vendors ON vendor_id = software_vendor_id WHERE $where";

$count_sql = [];
foreach ($tabs as $key => $t) {
    $count_sql[] = "COALESCE(SUM({$t['where']}), 0) AS `$key`";
}
$counts = array_map('intval', mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " $from")));
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'all';

$licenses = mysqli_query($mysqli, "SELECT software_id, software_name, software_version, software_type, software_license_type, software_seats, software_expire, vendor_name,
        " . softwareSeatsUsedSql('software.software_id') . " AS seats_used
    $from AND {$tabs[$tab]['where']}
    ORDER BY software_name ASC");

/** This page's address with some of its settings changed. */
function licensesUrl(array $changes) {
    global $tab, $q;
    $params = array_merge(['tab' => $tab !== 'all' ? $tab : null, 'q' => $q !== '' ? $q : null], $changes);
    $params = array_filter($params, function ($v) { return $v !== null && $v !== ''; });
    return 'licenses.php' . ($params ? '?' . http_build_query($params) : '');
}
?>

<div class="itflow-portal-page-head">
    <div>
        <h1>Licenses</h1>
        <p>The software you are licensed for, and when it renews</p>
    </div>
    <form method="get" class="d-flex" role="search">
        <?php if ($tab !== 'all') { ?><input type="hidden" name="tab" value="<?= escapeHtml($tab) ?>"><?php } ?>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
            <input type="search" class="form-control" name="q" value="<?= escapeHtml($q) ?>" placeholder="Name, kind or vendor" aria-label="Search licenses" maxlength="200">
        </div>
    </form>
</div>

<nav class="itflow-portal-tabs" aria-label="Licenses">
    <?php foreach ($tabs as $key => $t) { ?>
        <a class="<?= $key === $tab ? 'active' : '' ?><?= $key !== 'all' && $counts[$key] ? ' itflow-portal-tab-alert' : '' ?>" href="<?= escapeHtml(licensesUrl(['tab' => $key])) ?>">
            <i class="fas <?= $t['icon'] ?>"></i><?= escapeHtml($t['label']) ?>
            <span class="badge rounded-pill"><?= $counts[$key] ?></span>
        </a>
    <?php } ?>
</nav>

<section class="itflow-portal-panel">
    <?php if (!mysqli_num_rows($licenses)) { ?>
        <?php if ($q !== '') { ?>
            <?= emptyStateHtml(['thing' => 'licenses', 'filtered' => true, 'match_text' => 'No license has &ldquo;' . escapeHtml($q) . '&rdquo; in its name, kind or vendor.', 'clear_url' => licensesUrl(['q' => null])]) ?>
        <?php } elseif ($tab === 'soon') { ?>
            <?= emptyStateHtml(['title' => 'Nothing renewing soon', 'text' => 'Licenses renewing in the next 30 days show here.', 'icon' => 'fa-check', 'filtered' => false]) ?>
        <?php } elseif ($tab === 'expired') { ?>
            <?= emptyStateHtml(['title' => 'Nothing has run out', 'text' => '', 'icon' => 'fa-check', 'filtered' => false]) ?>
        <?php } else { ?>
            <?= emptyStateHtml(['title' => 'No licenses yet', 'text' => 'The software we manage for you shows here.', 'icon' => 'fa-cube', 'filtered' => false]) ?>
        <?php } ?>
    <?php } else { ?>
        <ul class="itflow-portal-list">
            <?php while ($row = mysqli_fetch_assoc($licenses)) {
                $seats = softwareSeats($row['seats_used'], $row['software_seats']);
                $renewal = portalRenewalState(softwareRenewal($row['software_expire'])); ?>
                <li class="itflow-portal-ticket">
                    <?= portalTile(softwareTypeStyle($row['software_type'])['icon']) ?>
                    <span class="itflow-portal-ticket-main">
                        <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml(trim($row['software_name'] . ' ' . $row['software_version'])) ?></span>
                        <span class="itflow-portal-ticket-meta">
                            <?php foreach (array_filter([$row['software_type'], $row['software_license_type'], $row['vendor_name']]) as $part) { ?><span><?= escapeHtml($part) ?></span><?php } ?>
                            <span class="<?= $seats['state'] === 'over' ? 'text-danger-emphasis' : '' ?>" title="<?= escapeHtml($seats['title']) ?>"><i class="fas fa-users me-1"></i><?= $seats['state'] === 'unlimited' ? escapeHtml($seats['text']) : $seats['used'] . ' of ' . $seats['total'] . ' seats in use' ?></span>
                        </span>
                    </span>
                    <?php if ($renewal) { ?>
                        <span class="itflow-portal-ticket-side">
                            <?= portalStateBadge($renewal) ?>
                            <small><?= escapeHtml(date('M j, Y', strtotime($row['software_expire']))) ?></small>
                        </span>
                    <?php } ?>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
