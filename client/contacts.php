<?php
/*
 * Client Portal
 * Contacts - the people at the company, with how to reach them and their
 * roles, searchable. The primary contact also sees who can sign in to the
 * portal, and adds and manages people from here (contact.php) - or invites
 * them (invite.php), the invites waiting listed here to send again or cancel
 * (functions/portal_invite.php).
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

// The invites waiting to be accepted, and whether people here can sign up themselves - for the primary contact
$can_invite = $manage && portalInvitesReady();
$waiting = $can_invite ? portalInvitesWaiting($session_client_id) : [];
$signup_domains = [];
if ($can_invite && portalSignupOn() && intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT client_portal_signup FROM clients WHERE client_id = $session_client_id"))[0] ?? 0)) {
    $signup_domains = portalSignupDomains($session_client_id);
}
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
        <?php if ($can_invite) { ?>
            <a class="btn btn-outline-primary" href="invite.php"><i class="fas fa-paper-plane me-2"></i>Invite people</a>
        <?php } ?>
        <?php if ($manage) { ?>
            <a class="btn btn-primary" href="contact.php?new=1"><i class="fas fa-user-plus me-2"></i>Add a person</a>
        <?php } ?>
    </div>
</div>

<?php if ($waiting || $signup_domains) { ?>
    <section class="itflow-portal-panel mb-4" data-portal-invites>
        <div class="itflow-portal-panel-head">
            <h2><i class="fas fa-paper-plane"></i>Waiting to join<?= $waiting ? ' <span class="badge rounded-pill text-bg-secondary ms-1">' . count($waiting) . '</span>' : '' ?></h2>
        </div>
        <?php if ($signup_domains) { ?>
            <div class="itflow-portal-panel-body pb-0">
                <p class="small text-body-secondary mb-<?= $waiting ? '2' : '3' ?>"><i class="fas fa-door-open me-1"></i>Anyone with an address at <strong><?= escapeHtml(implode(', ', $signup_domains)) ?></strong> can also make their own account from the sign-in page - we email you when they do.</p>
            </div>
        <?php } ?>
        <?php if ($waiting) { ?>
            <ul class="itflow-portal-list">
                <?php foreach ($waiting as $invite) {
                    $invite_state = portalInviteStateLabel($invite);
                    $invite_by = portalInviteBy($invite);
                    $invite_name = trim((string) $invite['invite_name']); ?>
                    <li class="itflow-portal-ticket" data-invite="<?= intval($invite['invite_id']) ?>">
                        <?= portalAvatarHtml($invite_name !== '' ? $invite_name : (string) $invite['invite_email']) ?>
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block text-break"><?= escapeHtml($invite_name !== '' ? $invite_name : $invite['invite_email']) ?></span>
                            <span class="itflow-portal-ticket-meta">
                                <?php if ($invite_name !== '') { ?><span><i class="fas fa-envelope me-1"></i><?= escapeHtml($invite['invite_email']) ?></span><?php } ?>
                                <span>Invited <?= escapeHtml(timeAgo($invite['invite_created_at'])) ?><?= $invite_by !== '' ? ' by ' . escapeHtml($invite_by) : '' ?></span>
                            </span>
                        </span>
                        <span class="itflow-portal-ticket-side">
                            <span class="badge rounded-pill text-bg-<?= $invite_state['tone'] ?>" title="<?= escapeHtml($invite_state['title']) ?>"><?= escapeHtml($invite_state['label']) ?></span>
                            <form action="post.php" method="post" class="d-flex gap-1">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="invite_id" value="<?= intval($invite['invite_id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary" name="portal_invite_resend" value="1" title="Email it again, with a new link"><i class="fas fa-paper-plane"></i><span class="d-none d-md-inline ms-1">Send again</span></button>
                                <button type="submit" class="btn btn-sm btn-outline-danger confirm-link" name="portal_invite_cancel" value="1" title="Cancel the invite"
                                    data-confirm-title="Cancel the invite to <?= escapeHtml($invite['invite_email']) ?>?" data-confirm-text="Its link stops working. You can invite them again later." data-confirm-button="Cancel invite"><i class="fas fa-times"></i><span class="d-none d-md-inline ms-1">Cancel</span></button>
                            </form>
                        </span>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>
    </section>
<?php } ?>

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
