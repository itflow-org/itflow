<?php

// Default Column Sortby Filter
$sort = "login_name";
$order = "ASC";

require_once "inc_all_client.php";

// Log when users load the Credentials/Logins page
mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Credential', log_action = 'View', log_description = '$session_name viewed the Credentials page for client', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");


// Location Filter
if (isset($_GET['location']) & !empty($_GET['location'])) {
    $location_query = 'AND (a.asset_location_id = ' . intval($_GET['location']) . ')';
    $location_query_innerjoin = 'INNER JOIN assets a on a.asset_id = l.login_asset_id ';
    $location = intval($_GET['location']);
} else {
    // Default - any
    $location_query_innerjoin = '';
    $location_query = '';
    $location = '';
}


//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * 
    FROM logins l
    $location_query_innerjoin
    WHERE l.login_client_id = $client_id
    AND l.login_$archive_query
    AND (l.login_name LIKE '%$q%' OR l.login_description LIKE '%$q%' OR l.login_uri LIKE '%$q%')
    $location_query
    ORDER BY l.login_important DESC, $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-key mr-2"></i>Credentials</h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addLoginModal" <?php if (!isset($_COOKIE['user_encryption_session_key'])) { echo "disabled"; } ?>>
                    <i class="fas fa-plus mr-2"></i>New Credential
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                <div class="dropdown-menu">
                    <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#importLoginModal">
                        <i class="fa fa-fw fa-upload mr-2"></i>Import
                    </a>
                    <?php if ($num_rows[0] > 0) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportLoginModal">
                            <i class="fa fa-fw fa-download mr-2"></i>Export
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Credentials">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="input-group">
                        <select class="form-control select2" name="location" onchange="this.form.submit()">
                            <option value="" <?php if ($location == "") { echo "selected"; } ?>>- All Asset Locations -</option>

                            <?php
                            $sql_locations_filter = mysqli_query($mysqli, "SELECT * FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_name ASC");
                            while ($row = mysqli_fetch_array($sql_locations_filter)) {
                                $location_id = intval($row['location_id']);
                                $location_name = nullable_htmlentities($row['location_name']);
                            ?>
                                <option <?php if ($location == $location_id) { echo "selected"; } ?> value="<?php echo $location_id; ?>"><?php echo $location_name; ?></option>
                            <?php
                            }
                            ?>

                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="btn-group float-right">
                        <a href="?client_id=<?php echo $client_id; ?>&archived=<?php if($archived == 1){ echo 0; } else { echo 1; } ?>"
                            class="btn btn-<?php if($archived == 1){ echo "primary"; } else { echo "default"; } ?>">
                            <i class="fa fa-fw fa-archive mr-2"></i>Archived
                        </a>
                        <div class="dropdown ml-2" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu">
                                <?php if ($archived) { ?>
                                <button class="dropdown-item text-info"
                                    type="submit" form="bulkActions" name="bulk_unarchive_logins">
                                    <i class="fas fa-fw fa-redo mr-2"></i>Unarchive
                                </button>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item text-danger text-bold"
                                    type="submit" form="bulkActions" name="bulk_delete_logins">
                                    <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                </button>
                                <?php } else { ?>
                                <button class="dropdown-item text-danger confirm-link"
                                    type="submit" form="bulkActions" name="bulk_archive_logins">
                                    <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                </button>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
        <hr>
        <form id="bulkActions" action="post.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                        <tr>
                            <td class="pr-0">
                                <div class="form-check">
                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                </div>
                            </td>
                            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=login_name&order=<?php echo $disp; ?>">Name</a></th>
                            <th>Username / ID</th>
                            <th>Password / Key</th>
                            <th>OTP</th>
                            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=login_uri&order=<?php echo $disp; ?>">URI</a></th>
                            <th></th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $login_id = intval($row['login_id']);
                            $login_name = nullable_htmlentities($row['login_name']);
                            $login_description = nullable_htmlentities($row['login_description']);
                            $login_uri = nullable_htmlentities($row['login_uri']);
                            if (empty($login_uri)) {
                                $login_uri_display = "-";
                            } else {
                                $login_uri_display = truncate($login_uri,40) . "<button class='btn btn-sm clipboardjs' type='button' data-clipboard-text='$login_uri'><i class='far fa-copy text-secondary'></i></button>";
                            }
                            $login_uri_2 = nullable_htmlentities($row['login_uri_2']);
                            $login_username = nullable_htmlentities(decryptLoginEntry($row['login_username']));
                            if (empty($login_username)) {
                                $login_username_display = "-";
                            } else {
                                $login_username_display = "$login_username<button class='btn btn-sm clipboardjs' type='button' data-clipboard-text='$login_username'><i class='far fa-copy text-secondary'></i></button>";
                            }
                            $login_password = nullable_htmlentities(decryptLoginEntry($row['login_password']));
                            $login_otp_secret = nullable_htmlentities($row['login_otp_secret']);
                            $login_id_with_secret = '"' . $row['login_id'] . '","' . $row['login_otp_secret'] . '"';
                            if (empty($login_otp_secret)) {
                                $otp_display = "-";
                            } else {
                                $otp_display = "<span onmouseenter='showOTPViaLoginID($login_id)'><i class='far fa-clock'></i> <span id='otp_$login_id'><i>Hover..</i></span></span>";
                            }
                            $login_note = nullable_htmlentities($row['login_note']);
                            $login_created_at = nullable_htmlentities($row['login_created_at']);
                            $login_archived_at = nullable_htmlentities($row['login_archived_at']);
                            $login_important = intval($row['login_important']);
                            $login_contact_id = intval($row['login_contact_id']);
                            $login_vendor_id = intval($row['login_vendor_id']);
                            $login_asset_id = intval($row['login_asset_id']);
                            $login_software_id = intval($row['login_software_id']);

                            // Check if shared
                            $sql_shared = mysqli_query(
                                $mysqli,
                                "SELECT * FROM shared_items
                                WHERE item_client_id = $client_id
                                AND item_active = 1
                                AND item_views != item_view_limit
                                AND item_expire_at > NOW()
                                AND item_type = 'Login'
                                AND item_related_id = $login_id
                                LIMIT 1"
                            );
                            if (mysqli_num_rows($sql_shared) > 0) {
                                $row = mysqli_fetch_array($sql_shared);
                                $item_id = intval($row['item_id']);
                                $item_active = nullable_htmlentities($row['item_active']);
                                $item_key = nullable_htmlentities($row['item_key']);
                                $item_type = nullable_htmlentities($row['item_type']);
                                $item_related_id = intval($row['item_related_id']);
                                $item_note = nullable_htmlentities($row['item_note']);
                                $item_views = nullable_htmlentities($row['item_views']);
                                $item_view_limit = nullable_htmlentities($row['item_view_limit']);
                                $item_created_at = nullable_htmlentities($row['item_created_at']);
                                $item_expire_at = nullable_htmlentities($row['item_expire_at']);
                                $item_expire_at_human = timeAgo($row['item_expire_at']);
                            }


                        ?>
                            <tr class="<?php if (!empty($login_important)) { echo "text-bold"; } ?>">
                                <td class="pr-0">
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="login_ids[]" value="<?php echo $login_id ?>">
                                    </div>
                                </td>
                                <td>
                                    <a class="text-dark" href="#" data-toggle="modal" data-target="#editLoginModal<?php echo $login_id; ?>">
                                        <div class="media">
                                            <i class="fa fa-fw fa-2x fa-key mr-3"></i>
                                            <div class="media-body">
                                                <div><?php echo $login_name; ?></div>
                                                <div><small class="text-secondary"><?php echo $login_description; ?></small></div>
                                            </div>
                                        </div>
                                    </a>
                                </td>
                                <td><?php echo $login_username_display; ?></td>
                                <td>
                                    <button class="btn p-0" type="button" data-toggle="popover" data-trigger="focus" data-placement="top" data-content="<?php echo $login_password; ?>"><i class="fas fa-2x fa-ellipsis-h text-secondary"></i><i class="fas fa-2x fa-ellipsis-h text-secondary"></i></button><button class="btn btn-sm clipboardjs" type="button" data-clipboard-text="<?php echo $login_password; ?>"><i class="far fa-copy text-secondary"></i></button>
                                </td>
                                <td><?php echo $otp_display; ?></td>
                                <td><?php echo $login_uri_display; ?></td>
                                <td>
                                    <?php if (mysqli_num_rows($sql_shared) > 0) { ?>
                                        <div title="Expires <?php echo $item_expire_at_human; ?>">
                                            <i class="fas fa-fw fa-link"></i> Shared
                                        </div>
                                    <?php } ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <?php if ( !empty($login_uri) || !empty($login_uri_2) ) { ?>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-default btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fa fa-fw fa-external-link-alt"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <?php if ($login_uri) { ?>
                                                <a href="<?php echo $login_uri; ?>" alt="<?php echo $login_uri; ?>" target="_blank" class="dropdown-item" >
                                                    <i class="fa fa-fw fa-external-link-alt"></i> <?php echo truncate($login_uri,40); ?>
                                                </a>
                                                <?php } ?>
                                                <?php if ($login_uri_2) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a href="<?php echo $login_uri_2; ?>" target="_blank" class="dropdown-item" >
                                                    <i class="fa fa-fw fa-external-link-alt"></i> <?php echo truncate($login_uri_2,40); ?>
                                                </a>
                                                <?php } ?>
                                            </div>
                                        </div>
                                        <?php } ?>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editLoginModal<?php echo $login_id; ?>">
                                                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#shareModal" onclick="populateShareModal(<?php echo "$client_id, 'Login', $login_id"; ?>)">
                                                    <i class="fas fa-fw fa-share mr-2"></i>Share
                                                </a>
                                                <?php if ($session_user_role == 3) { ?>
                                                    <?php if ($login_archived_at) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-info confirm-link" href="post.php?unarchive_login=<?php echo $login_id; ?>">
                                                        <i class="fas fa-fw fa-redo mr-2"></i>Unarchive
                                                    </a>
                                                    <?php if ($config_destructive_deletes_enable) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_login=<?php echo $login_id; ?>">
                                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                    </a>
                                                    <?php } ?>
                                                    <?php } else { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger confirm-link" href="post.php?archive_login=<?php echo $login_id; ?>">
                                                        <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                                    </a>
                                                    <?php } ?>
                                                <?php } ?>
                                            </div>
                                        </div>
                                </td>
                            </tr>

                        <?php

                            require "client_login_edit_modal.php";
                        }

                        ?>

                    </tbody>
                </table>
            </div>
        </form>
        <?php require_once "pagination.php";
        ?>
    </div>
</div>

<!-- Include script to get TOTP code via the login ID -->
<script src="js/logins_show_otp_via_id.js"></script>

<!-- Include script to generate readable passwords for login entries -->
<script src="js/logins_generate_password.js"></script>

<script src="js/bulk_actions.js"></script>

<?php

require_once "client_login_add_modal.php";

require_once "share_modal.php";

require_once "client_login_import_modal.php";

require_once "client_login_export_modal.php";

require_once "footer.php";
