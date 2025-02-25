<?php

// Default Column Sortby Filter
$sort = "login_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND login_client_id = $client_id";
    $client_url = "client_id=$client_id&";
    // Log when users load the Credentials/Logins page
    logAction("Credential", "View", "$session_name viewed the Credentials page for client", $client_id);
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
    // Log when users load the Credentials/Logins page
    logAction("Credential", "View", "$session_name viewed the All Credentials page");
}

// Perms
enforceUserPermission('module_credential');

// Tags Filter
if (isset($_GET['tags']) && is_array($_GET['tags']) && !empty($_GET['tags'])) {
    // Sanitize each element of the status array
    $sanitizedTags = array();
    foreach ($_GET['tags'] as $tag) {
        // Escape each status to prevent SQL injection
        $sanitizedTags[] = "'" . intval($tag) . "'";
    }

    // Convert the sanitized tags into a comma-separated string
    $sanitizedTagsString = implode(",", $sanitizedTags);
    $tag_query = "AND tags.tag_id IN ($sanitizedTagsString)";
} else {
    $tag_query = '';
}

if (!$client_url) {
    // Client Filter
    if (isset($_GET['client']) & !empty($_GET['client'])) {
        $client_query = 'AND (login_client_id = ' . intval($_GET['client']) . ')';
        $client = intval($_GET['client']);
    } else {
        // Default - any
        $client_query = '';
        $client = '';
    }
}

// Location Filter
if ($client_url && isset($_GET['location']) && !empty($_GET['location'])) {
    $location_query = 'AND (a.asset_location_id = ' . intval($_GET['location']) . ')';
    $location_query_innerjoin = 'INNER JOIN assets a on a.asset_id = l.login_asset_id ';
    $location_filter = intval($_GET['location']);
} else {
    // Default - any
    $location_query_innerjoin = '';
    $location_query = '';
    $location_filter = '';
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS l.login_id AS l_login_id, l.*, login_tags.*, tags.*, clients.*
    FROM logins l
    LEFT JOIN login_tags ON login_tags.login_id = l.login_id
    LEFT JOIN tags ON tags.tag_id = login_tags.tag_id
    LEFT JOIN clients ON client_id = login_client_id
    $location_query_innerjoin
    WHERE l.login_$archive_query
    $tag_query
    AND (l.login_name LIKE '%$q%' OR l.login_description LIKE '%$q%' OR l.login_uri LIKE '%$q%' OR tag_name LIKE '%$q%' OR client_name LIKE '%$q%')
    $location_query
    $access_permission_query
    $client_query
    GROUP BY l.login_id
    ORDER BY l.login_important DESC, $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-key mr-2"></i>Credentials</h3>
        <div class="card-tools">
            <?php if (lookupUserPermission("module_credential") >= 2) { ?>
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
            <?php } ?>
        </div>
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <?php } ?>
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Credentials">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
                
                <?php if ($client_url) { ?>
                <div class="col-md-2">
                    <div class="input-group">
                        <select class="form-control select2" name="location" onchange="this.form.submit()">
                            <option value="">- All Asset Locations -</option>

                            <?php
                            $sql_locations_filter = mysqli_query($mysqli, "SELECT * FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_name ASC");
                            while ($row = mysqli_fetch_array($sql_locations_filter)) {
                                $location_id = intval($row['location_id']);
                                $location_name = nullable_htmlentities($row['location_name']);
                            ?>
                                <option <?php if ($location_filter == $location_id) { echo "selected"; } ?> value="<?php echo $location_id; ?>"><?php echo $location_name; ?></option>
                            <?php
                            }
                            ?>

                        </select>
                    </div>
                </div>
                <?php } else { ?>
                <div class="col-md-2">
                    <div class="input-group">
                        <select class="form-control select2" name="client" onchange="this.form.submit()">
                            <option value="" <?php if ($client == "") { echo "selected"; } ?>>- All Clients -</option>

                            <?php
                            $sql_clients_filter = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_archived_at IS NULL $access_permission_query ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_array($sql_clients_filter)) {
                                $client_id = intval($row['client_id']);
                                $client_name = nullable_htmlentities($row['client_name']);
                            ?>
                                <option <?php if ($client == $client_id) { echo "selected"; } ?> value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>
                            <?php
                            }
                            ?>

                        </select>
                    </div>
                </div>
                <?php } ?>

                <div class="col-md-3">
                    <div class="input-group">
                        <select onchange="this.form.submit()" class="form-control select2" name="tags[]" data-placeholder="- Select Tags -" multiple>

                            <?php $sql_tags = mysqli_query($mysqli, "SELECT * FROM tags WHERE tag_type = 4");
                            while ($row = mysqli_fetch_array($sql_tags)) {
                                $tag_id = intval($row['tag_id']);
                                $tag_name = nullable_htmlentities($row['tag_name']); ?>

                                <option value="<?php echo $tag_id ?>" <?php if (isset($_GET['tags']) && is_array($_GET['tags']) && in_array($tag_id, $_GET['tags'])) { echo 'selected'; } ?>> <?php echo $tag_name ?> </option>

                            <?php } ?>
                        </select>
                        <div class="input-group-append">
                            <button class="btn btn-secondary" type="button"
                                data-toggle="ajax-modal"
                                data-modal-size="sm"
                                data-ajax-url="ajax/ajax_tag_add.php"
                                data-ajax-id="4">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="btn-group float-right">
                        <a href="?<?php echo $client_url; ?>&archived=<?php if($archived == 1){ echo 0; } else { echo 1; } ?>"
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
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkAssignTagsModal">
                                    <i class="fas fa-fw fa-tags mr-2"></i>Assign Tags
                                </a>
                                    <div class="dropdown-divider"></div>
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
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=login_name&order=<?php echo $disp; ?>">
                                    Name <?php if ($sort == 'login_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>Username / ID</th>
                            <th>Password / Key</th>
                            <th>OTP</th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=login_uri&order=<?php echo $disp; ?>">
                                    URI <?php if ($sort == 'login_uri') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th></th>
                            <?php if (!$client_url) { ?>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                                    Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <?php } ?>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $client_id = intval($row['client_id']);
                            $client_name = nullable_htmlentities($row['client_name']);
                            $login_id = intval($row['l_login_id']);
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
                            $login_asset_id = intval($row['login_asset_id']);

                            // Tags
                            $login_tag_name_display_array = array();
                            $login_tag_id_array = array();
                            $sql_login_tags = mysqli_query($mysqli, "SELECT * FROM login_tags LEFT JOIN tags ON login_tags.tag_id = tags.tag_id WHERE login_id = $login_id ORDER BY tag_name ASC");
                            while ($row = mysqli_fetch_array($sql_login_tags)) {

                                $login_tag_id = intval($row['tag_id']);
                                $login_tag_name = nullable_htmlentities($row['tag_name']);
                                $login_tag_color = nullable_htmlentities($row['tag_color']);
                                if (empty($login_tag_color)) {
                                    $login_tag_color = "dark";
                                }
                                $login_tag_icon = nullable_htmlentities($row['tag_icon']);
                                if (empty($login_tag_icon)) {
                                    $login_tag_icon = "tag";
                                }

                                $login_tag_id_array[] = $login_tag_id;
                                $login_tag_name_display_array[] = "<a href='credentials.php?$client_url tags[]=$login_tag_id'><span class='badge text-light p-1 mr-1' style='background-color: $login_tag_color;'><i class='fa fa-fw fa-$login_tag_icon mr-2'></i>$login_tag_name</span></a>";
                            }
                            $login_tags_display = implode('', $login_tag_name_display_array);

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
                                $item_recipient = nullable_htmlentities($row['item_recipient']);
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
                                    <a class="text-dark" href="#"
                                        data-toggle="ajax-modal"
                                        data-ajax-url="ajax/ajax_credential_edit.php"
                                        data-ajax-id="<?php echo $login_id; ?>"
                                        >
                                        <div class="media">
                                            <i class="fa fa-fw fa-2x fa-key mr-3"></i>
                                            <div class="media-body">
                                                <div><?php echo $login_name; ?></div>
                                                <div><small class="text-secondary"><?php echo $login_description; ?></small></div>
                                                <?php
                                                if (!empty($login_tags_display)) { ?>
                                                    <div class="mt-1">
                                                        <?php echo $login_tags_display; ?>
                                                    </div>
                                                <?php } ?>
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
                                        <div class="media" title="Expires <?php echo $item_expire_at_human; ?>">
                                            <i class="fas fa-link mr-2 mt-1"></i>
                                            <div class="media-body">Shared
                                                <br>
                                                <small class="text-secondary"><?php echo $item_recipient; ?></small>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </td>
                                <?php if (!$client_url) { ?>
                                <td><a href="credentials.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                                <?php } ?>
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
                                                <a class="dropdown-item" href="#"
                                                    data-toggle="ajax-modal"
                                                    data-ajax-url="ajax/ajax_credential_edit.php"
                                                    data-ajax-id="<?php echo $login_id; ?>"
                                                    >
                                                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#shareModal" onclick="populateShareModal(<?php echo "$client_id, 'Login', $login_id"; ?>)">
                                                    <i class="fas fa-fw fa-share mr-2"></i>Share
                                                </a>
                                                <?php  if (lookupUserPermission("module_credential") >= 2) { ?>
                                                    <?php if ($login_archived_at) { ?>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-info confirm-link" href="post.php?unarchive_login=<?php echo $login_id; ?>">
                                                            <i class="fas fa-fw fa-redo mr-2"></i>Unarchive
                                                        </a>
                                                        <?php if (lookupUserPermission("module_credential") >= 3) { ?>
                                                            <div class="dropdown-divider"></div>
                                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_login=<?php echo $login_id; ?>">
                                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                        <?php } ?>
                                                        </a>
                                                    <?php } else { ?>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-danger confirm-link" href="post.php?archive_login=<?php echo $login_id; ?>">
                                                            <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                                        </a>
                                                    <?php } ?>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                        <?php

                        }

                        ?>

                    </tbody>
                </table>
            </div>
            <?php require_once "modals/credential_bulk_assign_tags_modal.php"; ?>
        </form>
        <?php require_once "includes/filter_footer.php";
        ?>
    </div>
</div>

<!-- Include script to get TOTP code via the login ID -->
<script src="js/credential_show_otp_via_id.js"></script>
<!-- Include script to generate readable passwords for login entries -->
<script src="js/generate_password.js"></script>
<script src="js/bulk_actions.js"></script>

<?php

require_once "modals/credential_add_modal.php";
require_once "modals/share_modal.php";
require_once "modals/credential_import_modal.php";
require_once "modals/credential_export_modal.php";
require_once "includes/footer.php";
