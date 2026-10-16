<?php

/*
 * Your account > Activity - your latest sign ins (and wrong MFA codes), and
 * the latest things you did. Yours only.
 */

require_once "includes/inc_all_user.php";

$logins = userRecentLogins($session_user_id, 20);

$activity = [];
$sql = mysqli_query($mysqli, "SELECT log_id, log_type, log_action, log_description, log_created_at, log_client_id, client_name FROM logs
    LEFT JOIN clients ON client_id = log_client_id AND log_client_id > 0
    WHERE log_user_id = $session_user_id AND log_type NOT IN ('Login', 'Login 2FA', 'Logout')
    ORDER BY log_id DESC LIMIT 25");
while ($row = mysqli_fetch_assoc($sql)) {
    $activity[] = $row;
}

$action_icons = [
    'Create' => 'fa-plus text-success',
    'Edit' => 'fa-pen text-info',
    'Modify' => 'fa-pen text-info',
    'Delete' => 'fa-trash-alt text-danger',
    'Archive' => 'fa-archive text-secondary',
    'Restore' => 'fa-redo text-secondary',
    'Export' => 'fa-download text-secondary',
    'Upload' => 'fa-upload text-secondary',
    'View' => 'fa-eye text-secondary',
    'Reply' => 'fa-reply text-primary',
];

?>

<div class="row g-3">

    <div class="col-xl-5">
        <div class="card h-100 mb-0">
            <div class="card-header bg-dark">
                <h3 class="card-title"><i class="fas fa-fw fa-sign-in-alt me-2"></i>Your sign ins</h3>
            </div>
            <?php if ($logins) { ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($logins as $log) { ?>
                        <li class="list-group-item"><?= userLoginLineHtml($log) ?></li>
                    <?php } ?>
                </ul>
                <div class="card-footer small text-body-secondary">Not you? Change your password, and tell an administrator.</div>
            <?php } else { ?>
                <div class="card-body small text-body-secondary">None yet.</div>
            <?php } ?>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card h-100 mb-0">
            <div class="card-header bg-dark">
                <h3 class="card-title"><i class="fas fa-fw fa-history me-2"></i>What you did</h3>
                <?php if ($session_is_admin) { ?>
                    <div class="card-tools">
                        <a class="btn btn-sm btn-outline-light" href="/admin/audit_logs.php?user=<?= intval($session_user_id) ?>">Audit log<i class="fas fa-arrow-right ms-2"></i></a>
                    </div>
                <?php } ?>
            </div>
            <?php if ($activity) { ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($activity as $log) { ?>
                        <li class="list-group-item d-flex gap-2 align-items-start">
                            <i class="fas fa-fw <?= $action_icons[$log['log_action']] ?? 'fa-circle text-secondary small' ?> mt-1" title="<?= escapeHtml($log['log_action']) ?>"></i>
                            <div class="min-w-0 flex-grow-1">
                                <div class="text-break"><?= escapeHtml($log['log_description']) ?></div>
                                <div class="text-body-secondary d-flex flex-wrap column-gap-2">
                                    <span title="<?= escapeHtml($log['log_created_at']) ?>"><?= escapeHtml(timeAgo($log['log_created_at'])) ?></span>
                                    <span><?= escapeHtml($log['log_type']) ?> · <?= escapeHtml($log['log_action']) ?></span>
                                    <?php if ($log['client_name']) { ?>
                                        <a href="/agent/client_overview.php?client_id=<?= intval($log['log_client_id']) ?>"><?= escapeHtml($log['client_name']) ?></a>
                                    <?php } ?>
                                </div>
                            </div>
                        </li>
                    <?php } ?>
                </ul>
            <?php } else { ?>
                <div class="card-body small text-body-secondary">Nothing yet.</div>
            <?php } ?>
        </div>
    </div>

</div>

<?php
require_once "../../includes/footer.php";
