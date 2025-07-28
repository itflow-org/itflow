<?php

// Default Column Sortby Filter
$sort = "role_is_admin";
$order = "DESC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM user_roles
    WHERE (role_name LIKE '%$q%' OR role_description LIKE '%$q%')
    AND role_archived_at IS NULL
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>
    <div class="alert alert-info text-center"><strong>Roles are still in development. Permissions may not be fully enforced.</strong></div>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-user-shield mr-2"></i>Roles</h3>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRoleModal">
                        <i class="fas fa-fw fa-user-plus mr-2"></i>New Role
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <div class="row">
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo stripslashes(nullable_htmlentities($q));} ?>" placeholder="Search Roles">
                            <div class="input-group-append">
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <hr>
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
                    <tr>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=role_name&order=<?php echo $disp; ?>">
                                Role <?php if ($sort == 'role_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>Members</th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=role_is_admin&order=<?php echo $disp; ?>">
                                Admin <?php if ($sort == 'role_is_admin') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $role_id = intval($row['role_id']);
                        $role_name = nullable_htmlentities($row['role_name']);
                        $role_description = nullable_htmlentities($row['role_description']);
                        $role_admin = intval($row['role_is_admin']);
                        $role_archived_at = nullable_htmlentities($row['role_archived_at']);

                        // Count number of users that have each role
                        $sql_role_user_count = mysqli_query($mysqli, "SELECT COUNT(user_id) FROM users WHERE user_role_id = $role_id AND user_archived_at IS NULL");
                        $role_user_count = mysqli_fetch_row($sql_role_user_count)[0];

                        $sql_users = mysqli_query($mysqli, "SELECT * FROM users WHERE user_role_id = $role_id AND user_archived_at IS NULL");
                        // Initialize an empty array to hold user names
                        $user_names = [];

                        // Fetch each row and store the user_name in the array
                        while($row = mysqli_fetch_assoc($sql_users)) {
                            $user_names[] = nullable_htmlentities($row['user_name']);
                        }

                        // Convert the array of user names to a comma-separated string
                        $user_names_string = implode(",", $user_names);

                        if (empty($user_names_string)) {
                            $user_names_string = "-";
                        }

                        ?>
                        <tr>
                            <td>
                                <a class="text-dark text-bold" href="#" data-toggle="modal" data-target="#editRoleModal<?php echo $role_id; ?>">
                                    <?php echo $role_name; ?>
                                </a>
                                <div class="text-secondary"><?php echo $role_description; ?></div>
                            </td>
                            <td><?php echo $user_names_string; ?></td>
                            <td><?php echo $role_admin ? 'Yes' : 'No' ; ?></td>
                            <td>
                                <?php if ($role_id !== 3) { ?>
                                    <div class="dropdown dropleft text-center">
                                        <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <div class="dropdown-menu">

                                            <a class="dropdown-item" href="#"
                                                data-toggle="ajax-modal"
                                                data-ajax-url="ajax/ajax_role_edit.php"
                                                data-ajax-id="<?php echo $role_id; ?>"
                                                >
                                                <i class="fas fa-fw fa-user-edit mr-2"></i>Edit
                                            </a>

                                            <?php if (empty($role_archived_at) && $role_user_count == 0) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_role=<?php echo $role_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                                    <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                                </a>
                                            <?php } ?>

                                        </div>
                                    </div>
                                <?php } ?>
                            </td>
                        </tr>

                        <?php

                    }

                    ?>

                    </tbody>
                </table>
            </div>
            <?php require_once "includes/filter_footer.php";
            ?>
        </div>
    </div>

<?php

require_once "modals/admin_role_add_modal.php";
require_once "includes/footer.php";
