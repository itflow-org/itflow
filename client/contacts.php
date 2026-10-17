<?php
/*
 * Client Portal
 * Contacts - the people at the company, with how to reach them and their
 * roles, searchable. The primary contact also sees who can sign in to the
 * portal, and adds and manages people from here (contact.php).
 *
 * SCOPING: primary and technical contacts see the list
 * (contactCan('contacts')); keyed to $session_client_id.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

enforceContactCan('contacts');

$manage = contactCan('contacts_manage');
$q = trim((string) ($_GET['q'] ?? ''));
$where = "contact_client_id = $session_client_id AND contact_archived_at IS NULL";
if ($q !== '') {
    $q_sql = escapeSql($q);
    $q_digits = preg_replace('/\D/', '', $q);
    $where .= " AND (contact_name LIKE '%$q_sql%' OR contact_email LIKE '%$q_sql%' OR contact_title LIKE '%$q_sql%' OR contact_department LIKE '%$q_sql%'"
        . ($q_digits !== '' ? " OR contact_phone LIKE '%$q_digits%' OR contact_mobile LIKE '%$q_digits%'" : '') . ")";
}
$contacts = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_email, contact_phone, contact_phone_country_code, contact_extension,
        contact_mobile, contact_mobile_country_code, contact_photo, contact_primary, contact_billing, contact_technical, contact_department,
        user_id, user_auth_method, user_status, user_archived_at
    FROM contacts LEFT JOIN users ON user_id = contact_user_id
    WHERE $where
    ORDER BY contact_primary DESC, contact_name ASC");
?>

<div class="itflow-portal-page-head">
    <div>
        <h1>Contacts</h1>
        <p>The people at <?= escapeHtml($session_client_name) ?><?= $manage ? ', and who can sign in here' : '' ?></p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <form method="get" class="d-flex" role="search">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
                <input type="search" class="form-control" name="q" value="<?= escapeHtml($q) ?>" placeholder="Name, email, title or phone" aria-label="Search contacts" maxlength="200">
            </div>
        </form>
        <?php if ($manage) { ?>
            <a class="btn btn-primary" href="contact.php?new=1"><i class="fas fa-user-plus me-2"></i>Add a person</a>
        <?php } ?>
    </div>
</div>

<section class="itflow-portal-panel">
    <?php if (!mysqli_num_rows($contacts)) { ?>
        <?php if ($q !== '') { ?>
            <?= emptyStateHtml(['thing' => 'people', 'filtered' => true, 'match_text' => 'Nobody here matches &ldquo;' . escapeHtml($q) . '&rdquo;.', 'clear_url' => 'contacts.php']) ?>
        <?php } else { ?>
            <?= emptyStateHtml(['title' => 'No contacts yet', 'text' => '', 'icon' => 'fa-address-book', 'filtered' => false]) ?>
        <?php } ?>
    <?php } else { ?>
        <ul class="itflow-portal-list">
            <?php while ($row = mysqli_fetch_assoc($contacts)) {
                $id = intval($row['contact_id']);
                $phone = !empty($row['contact_phone']) ? formatPhoneNumber($row['contact_phone'], $row['contact_phone_country_code']) . (!empty($row['contact_extension']) ? ' x' . $row['contact_extension'] : '')
                    : (!empty($row['contact_mobile']) ? formatPhoneNumber($row['contact_mobile'], $row['contact_mobile_country_code']) : '');
                $access = portalContactAccess($row); ?>
                <li>
                    <a class="itflow-portal-ticket" href="contact.php?id=<?= $id ?>">
                        <?= portalAvatarHtml((string) $row['contact_name'], !empty($row['contact_photo']) ? "/uploads/clients/$session_client_id/" . rawurlencode(basename($row['contact_photo'])) : '') ?>
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['contact_name']) ?><?= $id === $session_contact_id ? '<span class="fw-normal text-body-secondary"> (you)</span>' : '' ?></span>
                            <span class="itflow-portal-ticket-meta">
                                <?php foreach (array_filter([$row['contact_title'], $row['contact_department']]) as $part) { ?><span><?= escapeHtml($part) ?></span><?php } ?>
                                <?php if (!empty($row['contact_email'])) { ?><span><i class="fas fa-envelope me-1"></i><?= escapeHtml($row['contact_email']) ?></span><?php } ?>
                                <?php if ($phone !== '') { ?><span><i class="fas fa-phone me-1"></i><?= escapeHtml($phone) ?></span><?php } ?>
                            </span>
                        </span>
                        <span class="itflow-portal-ticket-side">
                            <?php if ($roles = portalContactRoles($row)) { ?>
                                <span class="itflow-portal-roles"><?php foreach ($roles as $role) { ?><span><?= escapeHtml($role) ?></span><?php } ?></span>
                            <?php } ?>
                            <?php if ($manage) { ?><?= portalStateBadge($access) ?><?php } ?>
                        </span>
                    </a>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
