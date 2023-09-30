<?php

// Default Column Sortby Filter
$sort = "user_name";
$order = "ASC";

require_once("inc_all_settings.php");

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM users, user_settings
    WHERE users.user_id = user_settings.user_id
    AND (user_name LIKE '%$q%' OR user_email LIKE '%$q%')
    AND user_archived_at IS NULL
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-users mr-2"></i>Users</h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addUserModal">
                    <i class="fas fa-fw fa-user-plus mr-2"></i>New User
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                <div class="dropdown-menu">
                    <!--<a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#userInviteModal"><i class="fas fa-paper-plane mr-2"></i>Invite User</a>-->
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form class="mb-4" autocomplete="off">
            <div class="row">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo stripslashes(nullable_htmlentities($q));} ?>" placeholder="Search Users">
                        <div class="input-group-append">
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="float-right">
                        <button type="button" class="btn btn-default" data-toggle="modal" data-target="#exportUserModal"><i class="fa fa-fw fa-download mr-2"></i>Export</button>
                    </div>
                </div>
            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th class="text-center"><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=user_name&order=<?php echo $disp; ?>">Name</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=user_email&order=<?php echo $disp; ?>">Email</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=user_role&order=<?php echo $disp; ?>">Role</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=user_status&order=<?php echo $disp; ?>">Status</a></th>
                    <th class="text-center">MFA</th>
                    <th>Last Login</th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $user_id = intval($row['user_id']);
                    $user_name = nullable_htmlentities($row['user_name']);
                    $user_email = nullable_htmlentities($row['user_email']);
                    $user_status = intval($row['user_status']);
                    if ($user_status == 2) {
                        $user_status_display = "<span class='text-info'>Invited</span>";
                    } elseif ($user_status == 1) {
                        $user_status_display = "<span class='text-success'>Active</span>";
                    } else{
                        $user_status_display = "<span class='text-danger'>Disabled</span>";
                    }
                    $user_avatar = nullable_htmlentities($row['user_avatar']);
                    $user_token = nullable_htmlentities($row['user_token']);
                    if(empty($user_token)) {
                        $mfa_status_display = "-";
                    } else {
                        $mfa_status_display = "<i class='fas fa-fw fa-check text-success'></i>";
                    }
                    $user_config_force_mfa = intval($row['user_config_force_mfa']);
                    $user_role = $row['user_role'];
                    if ($user_role == 3) {
                        $user_role_display = "Administrator";
                    } elseif ($user_role == 2) {
                        $user_role_display = "Technician";
                    } else {
                        $user_role_display = "Accountant";
                    }
                    $user_initials = nullable_htmlentities(initials($user_name));

                    $sql_last_login = mysqli_query(
                        $mysqli,
                        "SELECT * FROM logs 
                        WHERE log_user_id = $user_id AND log_type = 'Login'
                        ORDER BY log_id DESC LIMIT 1"
                    );
                    if (mysqli_num_rows($sql_last_login) == 0) {
                        $last_login = "<span class='text-bold'>Never logged in</span>";
                    } else {
                        $row = mysqli_fetch_array($sql_last_login);
                        $log_created_at = nullable_htmlentities($row['log_created_at']);
                        $log_ip = nullable_htmlentities($row['log_ip']);
                        $log_user_agent = nullable_htmlentities($row['log_user_agent']);
                        $log_user_os = getOS($log_user_agent);
                        $log_user_browser = getWebBrowser($log_user_agent);
                        $last_login = "$log_created_at<br><small class='text-secondary'>$log_user_os<br>$log_user_browser<br><i class='fa fa-fw fa-globe'></i> $log_ip</small>";
                    }

                    ?>
                    <tr>
                        <td class="text-center">
                            <a class="text-dark" href="#" data-toggle="modal" data-target="#editUserModal<?php echo $user_id; ?>">
                                <?php if (!empty($user_avatar)) { ?>
                                    <img class="img-size-50 img-circle" src="<?php echo "uploads/users/$user_id/$user_avatar"; ?>">
                                <?php } else { ?>
                                    <span class="fa-stack fa-2x">
                                        <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                                        <span class="fa fa-stack-1x text-white"><?php echo $user_initials; ?></span>
                                    </span>
                                    <br>
                                <?php } ?>

                                <div class="text-secondary"><?php echo $user_name; ?></div>
                            </a>
                        </td>
                        <td><a href="mailto:<?php echo $user_email; ?>"><?php echo $user_email; ?></a></td>
                        <td><?php echo $user_role_display; ?></td>
                        <td><?php echo $user_status_display; ?></td>
                        <td class="text-center"><?php echo $mfa_status_display; ?></td>
                        <td><?php echo $last_login; ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editUserModal<?php echo $user_id; ?>">
                                        <i class="fas fa-fw fa-user-edit mr-2"></i>Edit
                                    </a>
                                    <?php if ($user_status == 0) { ?>
                                        <a class="dropdown-item text-success" href="post.php?activate_user=<?php echo $user_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-user-check mr-2"></i>Activate
                                        </a>
                                    <?php }elseif ($user_status == 1) { ?>
                                        <a class="dropdown-item text-danger" href="post.php?disable_user=<?php echo $user_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-user-slash mr-2"></i>Disable
                                        </a>
                                    <?php } ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#archiveUserModal<?php echo $user_id; ?>">
                                        <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php

                    require("user_edit_modal.php");
                    require("user_archive_modal.php");

                }

                ?>

                </tbody>
            </table>
        </div>
        <?php require_once("pagination.php"); ?>
    </div>
</div>
<script>
    function generatePassword() {
        document.getElementById("password").value = "<?php echo randomString() ?>"
    }
</script>

<?php

require_once("user_add_modal.php");
require_once("user_invite_modal.php");
require_once("user_export_modal.php");
require_once("footer.php");
