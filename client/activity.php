<?php
/*
 * Client Portal
 * Activity - everything this contact did in the portal, and every sign-in,
 * newest first: ?tab=signins for signing in (and password links, wrong
 * codes), ?tab=changes for the rest. 25 to a page.
 *
 * No capability gate: this is the contact's own record. Scoped on
 * log_user_id - the portal user this contact signs in as - so an agent
 * working this client never appears here, and on log_client_id as a second
 * fence.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

$per_page = 25;
$tabs = [
    'all' => ['label' => 'All', 'icon' => 'fa-list', 'where' => '1 = 1'],
    'signins' => ['label' => 'Signing in', 'icon' => 'fa-sign-in-alt', 'where' => "log_type = 'Client Login'"],
    'changes' => ['label' => 'Changes', 'icon' => 'fa-pen', 'where' => "log_type <> 'Client Login'"],
];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'all';
$scope = "log_user_id = $session_user_id AND log_client_id = $session_client_id AND $session_user_id > 0";

$counts = [];
foreach ($tabs as $key => $info) {
    $counts[$key] = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM logs WHERE $scope AND {$info['where']}"))[0]);
}
$total = $counts[$tab];
$pages = max(1, (int) ceil($total / $per_page));
$page = min(max(1, intval($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $per_page;

$sql = mysqli_query($mysqli, "SELECT log_type, log_action, log_description, log_ip, log_user_agent, log_created_at FROM logs
    WHERE $scope AND {$tabs[$tab]['where']}
    ORDER BY log_id DESC LIMIT $offset, $per_page");

/** What a row was, in words, with its icon. */
function activityLabel(array $row) {
    $sign_in = [
        'Success' => ['Signed in', 'fa-sign-in-alt'],
        'MFA Failed' => ['Wrong two-step code', 'fa-exclamation-triangle'],
        'Reset Requested' => ['Asked for a password link', 'fa-envelope'],
        'Reset Sent' => ['Password link emailed', 'fa-envelope'],
        'Password Reset' => ['Set a password from a link', 'fa-key'],
        'Failed' => ['Sign-in refused', 'fa-ban'],
    ];
    if ($row['log_type'] === 'Client Login') {
        return $sign_in[$row['log_action']] ?? ['Signing in', 'fa-sign-in-alt'];
    }
    return [trim($row['log_type'] . ' ' . strtolower((string) $row['log_action'])), 'fa-pen'];
}

function activityUrl(array $changes) {
    global $tab;
    $params = array_filter(array_merge(['tab' => $tab !== 'all' ? $tab : null], $changes), function ($v) { return $v !== null && $v !== ''; });
    return 'activity.php' . ($params ? '?' . http_build_query($params) : '');
}
?>

<a class="itflow-portal-back" href="profile.php"><i class="fas fa-chevron-left"></i>Your account</a>

<div class="itflow-portal-page-head">
    <div>
        <h1>Your activity</h1>
        <p>Every time you signed in, and what you did here</p>
    </div>
</div>

<nav class="itflow-portal-tabs" aria-label="Kinds of activity">
    <?php foreach ($tabs as $key => $info) { ?>
        <a class="<?= $tab === $key ? 'active' : '' ?>" href="<?= escapeHtml(activityUrl(['tab' => $key !== 'all' ? $key : null])) ?>"><i class="fas <?= $info['icon'] ?>"></i><?= $info['label'] ?> <span class="badge rounded-pill"><?= $counts[$key] ?></span></a>
    <?php } ?>
</nav>

<section class="itflow-portal-panel">
    <?php if (!$total) { ?>
        <?= emptyStateHtml(['title' => $tab === 'all' ? 'Nothing yet' : 'Nothing here yet', 'text' => 'What you do in the portal shows here.', 'icon' => 'fa-history', 'filtered' => false]) ?>
    <?php } else { ?>
        <ul class="itflow-portal-list">
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                [$label, $icon] = activityLabel($row); ?>
                <li class="itflow-portal-ticket">
                    <?= portalTile($icon) ?>
                    <span class="itflow-portal-ticket-main">
                        <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml(ucfirst($label)) ?></span>
                        <span class="d-block small text-break"><?= escapeHtml($row['log_description']) ?></span>
                        <span class="itflow-portal-ticket-meta"><span><?= portalDateTime($row['log_created_at']) ?></span><span><?= userDeviceHtml($row['log_user_agent']) ?></span><span class="font-monospace"><?= escapeHtml($row['log_ip']) ?></span></span>
                    </span>
                </li>
            <?php } ?>
        </ul>
        <?php if ($pages > 1) { ?>
            <div class="itflow-portal-pager">
                <span><?= $offset + 1 ?>-<?= min($offset + $per_page, $total) ?> of <?= $total ?></span>
                <div class="btn-group">
                    <a class="btn btn-sm btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= escapeHtml(activityUrl(['page' => $page - 1 > 1 ? $page - 1 : null])) ?>" aria-label="Newer"><i class="fas fa-chevron-left"></i></a>
                    <a class="btn btn-sm btn-outline-secondary<?= $page >= $pages ? ' disabled' : '' ?>" href="<?= escapeHtml(activityUrl(['page' => $page + 1])) ?>" aria-label="Older"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
