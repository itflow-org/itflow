<?php
/*
 * Client Portal
 * Devices - the company's computers, servers, network gear, phones and the
 * rest, in tabs by kind, with who uses each and whether its warranty is
 * running out. Searchable, 25 a page, each opening its own page.
 *
 * SCOPING: primary and technical contacts (portalItGate()); every query is
 * keyed to $session_client_id.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalItGate();

$per_page = 25;
$groups = portalAssetGroups();
$q = trim((string) ($_GET['q'] ?? ''));
$mine = ($_GET['scope'] ?? '') === 'mine';

$where = portalAssetScopeSql() . ($mine ? " AND asset_contact_id = $session_contact_id" : '');
if ($q !== '') {
    $q_sql = escapeSql($q);
    $where .= " AND (asset_name LIKE '%$q_sql%' OR asset_make LIKE '%$q_sql%' OR asset_model LIKE '%$q_sql%' OR asset_serial LIKE '%$q_sql%' OR contact_name LIKE '%$q_sql%')";
}
$from = "FROM assets LEFT JOIN contacts ON contact_id = asset_contact_id LEFT JOIN locations ON location_id = asset_location_id WHERE $where";

$count_sql = [];
foreach ($groups as $key => $group) {
    $count_sql[] = "COALESCE(SUM({$group['where']}), 0) AS `$key`";
}
$counts = array_map('intval', mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " $from")));

$tab = isset($groups[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'all';
$total = $counts[$tab];
$pages = max(1, (int) ceil($total / $per_page));
$page = min(max(1, intval($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $per_page;

$assets = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, asset_status, asset_warranty_expire, asset_contact_id, contact_name, location_name
    $from AND {$groups[$tab]['where']}
    ORDER BY asset_name ASC
    LIMIT $per_page OFFSET $offset");

/** This page's address with some of its settings changed. */
function assetsUrl(array $changes) {
    global $tab, $q, $mine;
    $params = array_merge(['tab' => $tab !== 'all' ? $tab : null, 'scope' => $mine ? 'mine' : null, 'q' => $q !== '' ? $q : null], $changes);
    $params = array_filter($params, function ($v) { return $v !== null && $v !== ''; });
    return 'assets.php' . ($params ? '?' . http_build_query($params) : '');
}
?>

<div class="itflow-portal-page-head">
    <div>
        <h1>Devices</h1>
        <p><?= $mine ? 'The ones you use' : 'Everything we look after at ' . escapeHtml($session_client_name) ?></p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group" role="group" aria-label="Whose devices">
            <a class="btn btn-outline-secondary<?= !$mine ? ' active' : '' ?>" href="<?= escapeHtml(assetsUrl(['scope' => null, 'page' => null])) ?>"><i class="fas fa-building me-2"></i>Everyone's</a>
            <a class="btn btn-outline-secondary<?= $mine ? ' active' : '' ?>" href="<?= escapeHtml(assetsUrl(['scope' => 'mine', 'page' => null])) ?>"><i class="fas fa-user me-2"></i>Mine</a>
        </div>
        <form method="get" class="d-flex" role="search">
            <?php if ($tab !== 'all') { ?><input type="hidden" name="tab" value="<?= escapeHtml($tab) ?>"><?php } ?>
            <?php if ($mine) { ?><input type="hidden" name="scope" value="mine"><?php } ?>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
                <input type="search" class="form-control" name="q" value="<?= escapeHtml($q) ?>" placeholder="Name, model, serial or person" aria-label="Search devices" maxlength="200">
            </div>
        </form>
    </div>
</div>

<nav class="itflow-portal-tabs" aria-label="Kinds of device">
    <?php foreach ($groups as $key => $group) {
        if ($key !== 'all' && !$counts[$key] && $key !== $tab) {
            continue;
        } ?>
        <a class="<?= $key === $tab ? 'active' : '' ?>" href="<?= escapeHtml(assetsUrl(['tab' => $key, 'page' => null])) ?>">
            <i class="fas <?= $group['icon'] ?>"></i><?= escapeHtml($group['label']) ?>
            <span class="badge rounded-pill"><?= $counts[$key] ?></span>
        </a>
    <?php } ?>
</nav>

<section class="itflow-portal-panel">
    <?php if (!$total) { ?>
        <?php if ($q !== '') { ?>
            <?= emptyStateHtml(['thing' => 'devices', 'filtered' => true, 'match_text' => 'Nothing here has &ldquo;' . escapeHtml($q) . '&rdquo; in its name, model, serial or person.', 'clear_url' => assetsUrl(['q' => null, 'page' => null])]) ?>
        <?php } elseif ($mine) { ?>
            <?= emptyStateHtml(['title' => 'No devices of yours', 'text' => 'Devices we have down as yours show here.', 'icon' => 'fa-desktop', 'filtered' => false]) ?>
        <?php } else { ?>
            <?= emptyStateHtml(['title' => 'No devices yet', 'text' => 'The devices we look after for you show here.', 'icon' => 'fa-desktop', 'filtered' => false]) ?>
        <?php } ?>
    <?php } else { ?>
        <ul class="itflow-portal-list">
            <?php while ($row = mysqli_fetch_assoc($assets)) {
                $warranty = assetWarranty($row['asset_warranty_expire']);
                $what = trim($row['asset_make'] . ' ' . $row['asset_model']); ?>
                <li>
                    <a class="itflow-portal-ticket" href="asset.php?id=<?= intval($row['asset_id']) ?>">
                        <?= portalTile('fa-' . getAssetIcon($row['asset_type'])) ?>
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['asset_name']) ?></span>
                            <span class="itflow-portal-ticket-meta">
                                <span><?= escapeHtml($what !== '' ? $what : $row['asset_type']) ?></span>
                                <?php if (!empty($row['contact_name'])) { ?><span><i class="fas fa-user me-1"></i><?= escapeHtml($row['contact_name']) ?><?= intval($row['asset_contact_id']) === $session_contact_id ? ' (you)' : '' ?></span><?php } ?>
                                <?php if (!empty($row['location_name'])) { ?><span><i class="fas fa-map-marker-alt me-1"></i><?= escapeHtml($row['location_name']) ?></span><?php } ?>
                                <?php if ((string) $row['asset_status'] !== '' && $row['asset_status'] !== 'Deployed') { ?><span><?= escapeHtml($row['asset_status']) ?></span><?php } ?>
                            </span>
                        </span>
                        <?php if ($warranty && $warranty['tone'] !== 'success') { ?>
                            <span class="itflow-portal-ticket-side">
                                <?= portalStateBadge($warranty['tone'] === 'danger'
                                    ? ['label' => 'Warranty expired', 'icon' => 'fa-shield-alt', 'class' => 'itflow-portal-state-late']
                                    : ['label' => 'Warranty ' . $warranty['short'], 'icon' => 'fa-shield-alt', 'class' => 'itflow-portal-state-you']) ?>
                            </span>
                        <?php } ?>
                    </a>
                </li>
            <?php } ?>
        </ul>
        <?php if ($pages > 1) { ?>
            <div class="itflow-portal-pager">
                <span><?= $offset + 1 ?>-<?= min($offset + $per_page, $total) ?> of <?= $total ?></span>
                <div class="btn-group">
                    <a class="btn btn-sm btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= escapeHtml(assetsUrl(['page' => $page - 1 > 1 ? $page - 1 : null])) ?>" aria-label="Previous page"><i class="fas fa-chevron-left"></i></a>
                    <a class="btn btn-sm btn-outline-secondary<?= $page >= $pages ? ' disabled' : '' ?>" href="<?= escapeHtml(assetsUrl(['page' => $page + 1])) ?>" aria-label="Next page"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
