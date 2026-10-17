<?php

/*
 * Admin > Client Portal - two-step sign-in for the portal, and the
 * announcements shown on its home page (to every client, or one) between
 * their start and end.
 */

require_once "includes/inc_all_admin.php";

$show_archived = isset($_GET['archived']);
$sql = mysqli_query($mysqli, "SELECT announcement_id, announcement_title, announcement_body, announcement_tone, announcement_client_id,
        announcement_starts_at, announcement_ends_at, announcement_created_at, announcement_archived_at, client_name, user_name
    FROM portal_announcements
    LEFT JOIN clients ON client_id = announcement_client_id
    LEFT JOIN users ON user_id = announcement_created_by
    WHERE announcement_archived_at IS " . ($show_archived ? 'NOT NULL' : 'NULL') . "
    ORDER BY COALESCE(announcement_starts_at, announcement_created_at) DESC, announcement_id DESC");
$archived_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM portal_announcements WHERE announcement_archived_at IS NOT NULL"))[0]);
$state_badges = ['showing' => 'text-bg-success', 'scheduled' => 'text-bg-info', 'ended' => 'text-bg-secondary', 'archived' => 'text-bg-secondary'];

?>

<div class="card card-dark mb-3">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-user-shield me-2"></i>Client Portal</h3>
    </div>
    <div class="card-body">
        <?php if (!$config_client_portal_enable) { ?>
            <div class="alert alert-warning">The client portal is off - turn it on under <a href="settings_module.php">Modules</a>.</div>
        <?php } ?>
        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="config_client_portal_require_mfa" value="1" id="requireMfa"<?= $config_client_portal_require_mfa ? ' checked' : '' ?>>
                    <label class="form-check-label" for="requireMfa">Require two-step sign-in</label>
                </div>
                <small class="text-secondary d-block">Contacts who sign in with a password are asked for a code from an authenticator app too. Anyone without it set up is taken to set it up before anything else. Microsoft 365 sign-ins are left to Microsoft.</small>
            </div>

            <button type="submit" name="edit_client_portal_settings" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-bullhorn me-2"></i>Announcements</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/portal_announcement/portal_announcement_add.php" data-modal-size="lg"><i class="fas fa-plus me-2"></i>New Announcement</button>
        </div>
    </div>
    <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2">
        <span class="text-secondary small me-auto">Shown at the top of the portal's home page - to every client, or one - between their start and end.</span>
        <div class="btn-group btn-group-sm">
            <a class="btn btn-outline-secondary<?= $show_archived ? '' : ' active' ?>" href="settings_client_portal.php">Current</a>
            <a class="btn btn-outline-secondary<?= $show_archived ? ' active' : '' ?>" href="settings_client_portal.php?archived=1">Archived <span class="badge text-bg-secondary ms-1"><?= $archived_count ?></span></a>
        </div>
    </div>
    <div class="table-responsive-sm">
        <table class="table table-borderless table-hover mb-0">
            <?php if (mysqli_num_rows($sql)) { ?>
                <thead class="text-dark">
                <tr>
                    <th class="ps-3">Announcement</th>
                    <th>For</th>
                    <th>When</th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
            <?php } ?>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                $announcement_id = intval($row['announcement_id']);
                $state = portalAnnouncementState($row);
                $tone_key = isset(PORTAL_ANNOUNCEMENT_TONES[$row['announcement_tone']]) ? $row['announcement_tone'] : 'info';
                $when = [];
                $when[] = $row['announcement_starts_at'] ? 'From ' . date('M j, Y g:i A', strtotime($row['announcement_starts_at'])) : 'From ' . date('M j, Y', strtotime($row['announcement_created_at']));
                $when[] = $row['announcement_ends_at'] ? 'until ' . date('M j, Y g:i A', strtotime($row['announcement_ends_at'])) : 'until archived';
                $preview = mb_strimwidth(trim(preg_replace('/\s+/', ' ', (string) $row['announcement_body'])), 0, 140, '...'); ?>
                <tr>
                    <td class="ps-3">
                        <div class="d-flex align-items-start gap-2">
                            <span class="itflow-announcement-dot is-<?= $tone_key ?> mt-2" title="<?= escapeHtml(PORTAL_ANNOUNCEMENT_TONES[$tone_key]['label']) ?>"></span>
                            <div class="min-w-0">
                                <a class="ajax-modal fw-semibold" href="#" data-modal-url="modals/portal_announcement/portal_announcement_edit.php?id=<?= $announcement_id ?>" data-modal-size="lg"><?= escapeHtml($row['announcement_title']) ?></a>
                                <span class="badge rounded-pill <?= $state_badges[$state['key']] ?> ms-1"><?= $state['label'] ?></span>
                                <?php if ($preview !== '') { ?><div class="small text-secondary"><?= escapeHtml($preview) ?></div><?php } ?>
                            </div>
                        </div>
                    </td>
                    <td><?= intval($row['announcement_client_id']) ? escapeHtml($row['client_name'] ?? 'A client no longer here') : '<span class="badge text-bg-dark">Every client</span>' ?></td>
                    <td class="small"><?= escapeHtml(implode(' ', $when)) ?><div class="text-secondary">Posted by <?= escapeHtml($row['user_name'] ?: 'someone') ?></div></td>
                    <td>
                        <div class="dropdown dropstart text-center">
                            <button class="btn btn-secondary btn-sm" title="More actions" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/portal_announcement/portal_announcement_edit.php?id=<?= $announcement_id ?>" data-modal-size="lg"><i class="fas fa-fw fa-edit me-2"></i>Edit</a>
                                <?php if ($row['announcement_archived_at']) { ?>
                                    <a class="dropdown-item" href="post.php?restore_portal_announcement=<?= $announcement_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-fw fa-redo me-2"></i>Restore</a>
                                <?php } else { ?>
                                    <a class="dropdown-item text-danger" href="post.php?archive_portal_announcement=<?= $announcement_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-fw fa-archive me-2"></i>Archive</a>
                                <?php } ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_portal_announcement=<?= $announcement_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php if (!mysqli_num_rows($sql)) { ?>
        <?= emptyStateHtml([
            'title' => $show_archived ? 'No archived announcements' : 'No announcements',
            'text' => $show_archived ? '' : 'Planned maintenance, an outage, a new service - a note at the top of every client\'s portal, or one client\'s.',
            'icon' => 'fa-bullhorn',
            'filtered' => false,
            'actions' => $show_archived ? [] : [['label' => 'New Announcement', 'modal' => 'modals/portal_announcement/portal_announcement_add.php', 'size' => 'lg']],
        ]) ?>
    <?php } ?>
</div>

<?php
require_once "../includes/footer.php";
