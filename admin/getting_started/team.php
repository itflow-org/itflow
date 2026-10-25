<?php

/*
 * Get started - Your team: who signs in now, and someone to add - a name, an
 * email and a role (getting_started_team). ITFlow makes their password: it
 * is emailed to them with their sign-in details, as Admin > Users does, or -
 * with no email set up yet - shown once for you to give them. Their own copy
 * of the vault key is made under it, so they set their own once signed in.
 */

defined('FROM_GETTING_STARTED') || die("Direct file access is not allowed");

$gs_agents = mysqli_query($mysqli, "SELECT user_id, user_name, user_email, role_name, role_is_admin FROM users LEFT JOIN user_roles ON role_id = user_role_id
    WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
$gs_roles = userRoles();
// Technician, when there is one - most people added are - or the first role that isn't an admin one
$gs_role_default = '';
foreach ($gs_roles as $gs_role_id => $gs_role) {
    if (!intval($gs_role['role_is_admin']) && ($gs_role_default === '' || strcasecmp($gs_role['role_name'], 'Technician') === 0)) {
        $gs_role_default = (string) $gs_role_id;
    }
}
$gs_role_options = '';
foreach ($gs_roles as $gs_role_id => $gs_role) {
    $gs_role_options .= '<option value="' . intval($gs_role_id) . '"' . ((string) settingsValue('team_role', $gs_role_default) === (string) $gs_role_id ? ' selected' : '') . selectOptionRole($gs_role) . '>' . escapeHtml($gs_role['role_name']) . '</option>';
}
$gs_mail_ready = emailReady();
$gs_limit = agentUserLimitReached();

?>
<ul class="list-unstyled itflow-gs-people mb-4" data-gs-agents>
    <?php while ($gs_agent = mysqli_fetch_assoc($gs_agents)) { ?>
        <li data-gs-agent="<?= intval($gs_agent['user_id']) ?>">
            <span class="itflow-gs-person-initials" aria-hidden="true"><?= escapeHtml(personInitials($gs_agent['user_name'])) ?></span>
            <div class="min-w-0 flex-grow-1">
                <div class="fw-semibold text-truncate"><?= escapeHtml($gs_agent['user_name']) ?><?= intval($gs_agent['user_id']) === intval($session_user_id) ? ' <span class="small fw-normal text-body-secondary">(you)</span>' : '' ?></div>
                <div class="small text-body-secondary text-truncate"><?= escapeHtml($gs_agent['user_email']) ?></div>
            </div>
            <span class="badge rounded-pill bg-body-tertiary text-body border fw-normal"><i class="fas <?= intval($gs_agent['role_is_admin']) ? 'fa-user-shield' : 'fa-user-tag' ?> me-1"></i><?= escapeHtml((string) $gs_agent['role_name']) ?></span>
        </li>
    <?php } ?>
</ul>

<?php if ($gs_limit) { ?>
    <div class="alert alert-info mb-0" data-gs-team-limit><?= escapeHtml(agentUserLimitMessage()) ?></div>
<?php } else { ?>
    <h2 class="h6 fw-semibold">Add someone</h2>
    <form action="post.php" method="post" autocomplete="off" data-gs-form>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <?= settingsInput('team_name', '', ['icon' => 'fa-user', 'label' => 'Name', 'required' => true, 'maxlength' => 200, 'placeholder' => 'Alex Morgan', 'autocomplete' => 'off']) ?>
            </div>
            <div class="col-sm-6">
                <?= settingsInput('team_email', '', ['icon' => 'fa-envelope', 'type' => 'email', 'label' => 'Email', 'required' => true, 'maxlength' => 200, 'placeholder' => 'alex@example.com', 'autocomplete' => 'off']) ?>
            </div>
            <div class="col-12">
                <?= settingsSelect('team_role', $gs_role_default, [], ['icon' => 'fa-id-badge', 'label' => 'Role', 'required' => true, 'options_html' => $gs_role_options,
                    'help' => 'What they can see and change - roles are in Admin &gt; Roles. A role that isn\'t an admin one sees every client, until you limit them in Admin &gt; Users.']) ?>
            </div>
        </div>
        <?php if ($gs_mail_ready) { ?>
            <p class="small text-body-secondary" data-gs-team-mail="yes"><i class="fas fa-paper-plane me-1"></i>They are emailed their sign-in details, with a password ITFlow makes for them to change.</p>
        <?php } else { ?>
            <p class="small text-body-secondary" data-gs-team-mail="no"><i class="fas fa-info-circle me-1"></i>Email isn't set up yet, so ITFlow makes their password and shows it to you once - for you to give them, and them to change.</p>
        <?php } ?>
        <button type="submit" name="getting_started_team" value="1" class="btn btn-primary" data-gs-save><i class="fas fa-user-plus me-2"></i>Add them</button>
    </form>
<?php } ?>
