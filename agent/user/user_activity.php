<?php
require_once "includes/inc_all_user.php";


$sql_recent_logins = mysqli_query($mysqli, "SELECT * FROM logs
    WHERE log_type = 'Login' OR log_type = 'Login 2FA' AND log_action = 'Success' AND log_user_id = $session_user_id
    ORDER BY log_id DESC LIMIT 3"
);

$sql_recent_logs = mysqli_query($mysqli, "SELECT * FROM logs
    WHERE log_user_id = $session_user_id AND log_type NOT LIKE 'Login'
    ORDER BY log_id DESC LIMIT 5"
);

?>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-fw fa-sign-in-alt mr-2"></i>Your Recent Sign ins</h3>
    </div>
    <table class="table table-borderless">
        <tbody>
        <?php

        while ($row = mysqli_fetch_assoc($sql_recent_logins)) {
            $log_id = intval($row['log_id']);
            $log_ip = escapeHtml($row['log_ip']);
            $log_user_agent = escapeHtml($row['log_user_agent']);
            $log_user_os = getOS($log_user_agent);
            $log_user_browser = getWebBrowser($log_user_agent);
            $log_created_at = escapeHtml($row['log_created_at']);

            ?>

            <tr>
                <td><i class="fa fa-fw fa-clock text-secondary mr-2"></i><?php echo $log_created_at; ?></td>
                <td><?php echo $log_user_os; ?></td>
                <td><?php echo $log_user_browser; ?></td>
                <td><i class='fa fa-fw fa-globe text-secondary'></i> <?php echo $log_ip; ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php if (isset($session_is_admin) && $session_is_admin === true) { ?>
        <div class="card-footer">
            <a href="../../admin/audit_logs.php?q=<?php echo "$session_name successfully logged in"; ?>">See More...</a>
        </div>
    <?php } ?>
</div>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-fw fa-history mr-2"></i>Your Recent Activity</h3>
    </div>


    <table class="table">
        <tbody>
        <?php

        while ($row = mysqli_fetch_assoc($sql_recent_logs)) {
            $log_id = intval($row['log_id']);
            $log_type = escapeHtml($row['log_type']);
            $log_action = escapeHtml($row['log_action']);
            $log_description = escapeHtml($row['log_description']);
            $log_created_at = escapeHtml($row['log_created_at']);

            if ($log_action == 'Create') {
                $log_icon = "plus text-success";
            } elseif ($log_action == 'Modify') {
                $log_icon = "edit text-info";
            } elseif ($log_action == 'Delete') {
                $log_icon = "trash-alt text-danger";
            } else {
                $log_icon = "pencil";
            }

            ?>

            <tr>
                <td><i class="fa fa-fw fa-clock text-secondary mr-2"></i><?php echo $log_created_at; ?></td>
                <td><strong><i class="fa fa-fw text-secondary fa-<?php echo $log_icon; ?>"></i> <?php echo $log_type; ?></strong></td>
                <td><span class="text-secondary"><?php echo $log_description; ?></span></td>

            </tr>
            <?php
        }
        ?>
        </tbody>
    </table>
    <?php if (isset($session_is_admin) && $session_is_admin === true) { ?>
        <div class="card-footer">
            <a href="../../admin/audit_logs.php?q=<?php echo escapeHtml($session_name); ?>">See More...</a>
        </div>
    <?php } ?>
</div>

<?php
require_once "../../includes/footer.php";
