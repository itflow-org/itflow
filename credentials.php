<?php

// Default Column Sortby Filter
$sort = "credential_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND credential_client_id = $client_id";
    $client_url = "client_id=$client_id&";
    // Overide Filter Header Archived
    if (isset($_GET['archived']) && $_GET['archived'] == 1) {
        $archived = 1;
        $archive_query = "credential_archived_at IS NOT NULL";
    } else {
        $archived = 0;
        $archive_query = "credential_archived_at IS NULL";
    }

    // Log when users load the Credentials page
    logAction("Credential", "View", "$session_name viewed the Credentials page for client", $client_id);

} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
    // Overide Filter Header Archived
    if (isset($_GET['archived']) && $_GET['archived'] == 1) {
        $archived = 1;
        $archive_query = "(client_archived_at IS NOT NULL OR credential_archived_at IS NOT NULL)";
    } else {
        $archived = 0;
        $archive_query = "(client_archived_at IS NULL AND credential_archived_at IS NULL)";
    }
    // Log when users load the Credentials page
    logAction("Credential", "View", "$session_name viewed the All Credentials page");
}

// Perms
enforceUserPermission('module_credential');

// Tags Filter
if (isset($_GET['tags']) && is_array($_GET['tags']) && !empty($_GET['tags'])) {
    // Sanitize each element of the tags array
    $sanitizedTags = array_map('intval', $_GET['tags']);
    // Convert the sanitized tags into a comma-separated string
    $tag_filter = implode(",", $sanitizedTags);
    $tag_query = "AND tags.tag_id IN ($tag_filter)";
} else {
    $tag_filter = 0;
    $tag_query = '';
}

if (!$client_url) {
    // Client Filter
    if (isset($_GET['client']) & !empty($_GET['client'])) {
        $client_query = 'AND (credential_client_id = ' . intval($_GET['client']) . ')';
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
    $location_query_innerjoin = 'INNER JOIN assets a on a.asset_id = c.credential_asset_id ';
    $location_filter = intval($_GET['location']);
} else {
    // Default - any
    $location_query_innerjoin = '';
    $location_query = '';
    $location_filter = '';
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS c.credential_id AS c_credential_id, c.*, credential_tags.*, tags.*, clients.*, contacts.*, assets.*
    FROM credentials c
    LEFT JOIN credential_tags ON credential_tags.credential_id = c.credential_id
    LEFT JOIN tags ON tags.tag_id = credential_tags.tag_id
    LEFT JOIN clients ON client_id = credential_client_id
    LEFT JOIN contacts ON contact_id = credential_contact_id
    LEFT JOIN assets ON asset_id = credential_asset_id
    $location_query_innerjoin
    WHERE $archive_query
    $tag_query
    AND (c.credential_name LIKE '%$q%' OR c.credential_description LIKE '%$q%' OR c.credential_uri LIKE '%$q%' OR tag_name LIKE '%$q%' OR client_name LIKE '%$q%')
    $location_query
    $access_permission_query
    $client_query
    GROUP BY c.credential_id
    ORDER BY c.credential_important DESC, $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-key mr-2"></i>Credentials</h3>
        <div class="card-tools">
            <?php if (lookupUserPermission("module_credential") >= 2) { ?>
                <div class="btn-group">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCredentialModal" <?php if (!isset($_COOKIE['user_encryption_session_key'])) { echo "disabled"; } ?>>
                    <i class="fas fa-plus mr-2"></i>New Credential
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                <div class="dropdown-menu">
                    <?php if ($client_url) { ?>
                    <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#importCredentialModal">
                        <i class="fa fa-fw fa-upload mr-2"></i>Import
                    </a>
                    <div class="dropdown-divider"></div>
                    <?php } ?>
                    <?php if ($num_rows[0] > 0) { ?>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportCredentialModal">
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
            <input type="hidden" name="archived" value="<?php echo $archived; ?>">
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Credentials">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="input-group mb-3 mb-md-0">
                        <select onchange="this.form.submit()" class="form-control select2" name="tags[]" data-placeholder="- Select Tags -" multiple>

                            <?php
                            $sql_tags_filter = mysqli_query($mysqli, "
                                SELECT tags.tag_id, tags.tag_name
                                FROM tags 
                                LEFT JOIN credential_tags ON credential_tags.tag_id = tags.tag_id
                                LEFT JOIN credentials ON credential_tags.credential_id = credentials.credential_id
                                WHERE tag_type = 4
                                $client_query OR tags.tag_id IN ($tag_filter)
                                GROUP BY tags.tag_id
                                HAVING COUNT(credential_tags.credential_id) > 0 OR tags.tag_id IN ($tag_filter)
                            ");
                            while ($row = mysqli_fetch_array($sql_tags_filter)) {
                                $tag_id = intval($row['tag_id']);
                                $tag_name = nullable_htmlentities($row['tag_name']); ?>

                                <option value="<?php echo $tag_id ?>" <?php if (isset($_GET['tags']) && is_array($_GET['tags']) && in_array($tag_id, $_GET['tags'])) { echo 'selected'; } ?>> <?php echo $tag_name ?> </option>

                            <?php } ?>
                        </select>
                    </div>
                </div>
                
                <?php if ($client_url) { ?>
                <div class="col-md-2">
                    <div class="input-group mb-3 mb-md-0">
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
                    <div class="input-group mb-3 mb-md-0">
                        <select class="form-control select2" name="client" onchange="this.form.submit()">
                            <option value="" <?php if ($client == "") { echo "selected"; } ?>>- All Clients -</option>

                            <?php
                            $sql_clients_filter = mysqli_query($mysqli, "
                                SELECT DISTINCT client_id, client_name 
                                FROM clients
                                JOIN credentials ON credential_client_id = client_id
                                WHERE $archive_query
                                $access_permission_query
                                ORDER BY client_name ASC
                            ");
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
                                    type="submit" form="bulkActions" name="bulk_unarchive_credentials">
                                    <i class="fas fa-fw fa-redo mr-2"></i>Unarchive
                                </button>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item text-danger text-bold"
                                    type="submit" form="bulkActions" name="bulk_delete_credentials">
                                    <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                </button>
                                <?php } else { ?>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkAssignTagsModal">
                                    <i class="fas fa-fw fa-tags mr-2"></i>Assign Tags
                                </a>
                                    <div class="dropdown-divider"></div>
                                <button class="dropdown-item text-danger confirm-link"
                                    type="submit" form="bulkActions" name="bulk_archive_credentials">
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
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
                        <tr>
                            <td class="pr-0">
                                <div class="form-check">
                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                </div>
                            </td>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=credential_name&order=<?php echo $disp; ?>">
                                    Name <?php if ($sort == 'credential_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>Username / ID</th>
                            <th>Password / Key</th>
                            <th>OTP</th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=credential_uri&order=<?php echo $disp; ?>">
                                    URI <?php if ($sort == 'credential_uri') { echo $order_icon; } ?>
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
                            $credential_id = intval($row['c_credential_id']);
                            $credential_name = nullable_htmlentities($row['credential_name']);
                            $credential_description = nullable_htmlentities($row['credential_description']);
                            $credential_uri = sanitize_url($row['credential_uri']);
                            if (empty($credential_uri)) {
                                $credential_uri_display = "-";
                            } else {
                                $credential_uri_display = "<a href='$credential_uri'>" . truncate($credential_uri,40) . "</a><button class='btn btn-sm clipboardjs' type='button' title='$credential_uri' data-clipboard-text='$credential_uri'><i class='far fa-copy text-secondary'></i></button>";
                            }
                            $credential_uri_2 = sanitize_url($row['credential_uri_2']);
                            $credential_username = nullable_htmlentities(decryptCredentialEntry($row['credential_username']));
                            if (empty($credential_username)) {
                                $credential_username_display = "-";
                            } else {
                                $credential_username_display = "$credential_username<button class='btn btn-sm clipboardjs' type='button' data-clipboard-text='$credential_username'><i class='far fa-copy text-secondary'></i></button>";
                            }
                            $credential_password = nullable_htmlentities(decryptCredentialEntry($row['credential_password']));
                            $credential_otp_secret = nullable_htmlentities($row['credential_otp_secret']);
                            $credential_id_with_secret = '"' . $row['credential_id'] . '","' . $row['credential_otp_secret'] . '"';
                            if (empty($credential_otp_secret)) {
                                $otp_display = "-";
                            } else {
                                $otp_display = "<span onmouseenter='showOTPViaCredentialID($credential_id)'><i class='far fa-clock'></i> <span id='otp_$credential_id'><i>Hover..</i></span></span>";
                            }
                            $credential_note = nullable_htmlentities($row['credential_note']);
                            $credential_created_at = nullable_htmlentities($row['credential_created_at']);
                            $credential_archived_at = nullable_htmlentities($row['credential_archived_at']);
                            $credential_important = intval($row['credential_important']);
                            $credential_contact_id = intval($row['credential_contact_id']);
                            $contact_name = nullable_htmlentities($row['contact_name']);
                            $credential_asset_id = intval($row['credential_asset_id']);
                            $asset_name = nullable_htmlentities($row['asset_name']);

                            // Tags
                            $credential_tag_name_display_array = array();
                            $credential_tag_id_array = array();
                            $sql_credential_tags = mysqli_query($mysqli, "SELECT * FROM credential_tags LEFT JOIN tags ON credential_tags.tag_id = tags.tag_id WHERE credential_id = $credential_id ORDER BY tag_name ASC");
                            while ($row = mysqli_fetch_array($sql_credential_tags)) {

                                $credential_tag_id = intval($row['tag_id']);
                                $credential_tag_name = nullable_htmlentities($row['tag_name']);
                                $credential_tag_color = nullable_htmlentities($row['tag_color']);
                                if (empty($credential_tag_color)) {
                                    $credential_tag_color = "dark";
                                }
                                $credential_tag_icon = nullable_htmlentities($row['tag_icon']);
                                if (empty($credential_tag_icon)) {
                                    $credential_tag_icon = "tag";
                                }

                                $credential_tag_id_array[] = $credential_tag_id;
                                $credential_tag_name_display_array[] = "<a href='credentials.php?$client_url tags[]=$credential_tag_id'><span class='badge text-light p-1 mr-1' style='background-color: $credential_tag_color;'><i class='fa fa-fw fa-$credential_tag_icon mr-2'></i>$credential_tag_name</span></a>";
                            }
                            $credential_tags_display = implode('', $credential_tag_name_display_array);

                            if ($credential_contact_id) { 
                                $credential_contact_display = "<a href='#' class='mr-2 mb-1 badge badge-pill badge-dark p-2' title='$contact_name'
                                    data-toggle='ajax-modal'
                                    data-modal-size='lg'
                                    data-ajax-url='ajax/ajax_contact_details.php'
                                    data-ajax-id='$credential_contact_id'>
                                    <i class='fas fa-fw fa-user'></i></a>";
                            } else {
                                $credential_contact_display = '';
                            }

                            if ($credential_asset_id) { 
                                $credential_asset_display = "<a href='#' class='mr-2 mb-1 badge badge-pill badge-secondary p-2' title='$asset_name' data-toggle='ajax-modal'
                                    data-modal-size='lg'
                                    data-ajax-url='ajax/ajax_asset_details.php'
                                    data-ajax-id='$credential_asset_id'>
                                    <i class='fas fa-fw fa-desktop'></i></a>";
                            } else {
                                $credential_asset_display = '';
                            }

                            // Check if shared
                            $sql_shared = mysqli_query(
                                $mysqli,
                                "SELECT * FROM shared_items
                                WHERE item_client_id = $client_id
                                AND item_active = 1
                                AND item_views != item_view_limit
                                AND item_expire_at > NOW()
                                AND item_type = 'Credential'
                                AND item_related_id = $credential_id
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
                            <tr class="<?php if (!empty($credential_important)) { echo "text-bold"; } ?>">
                                <td class="pr-0">
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="credential_ids[]" value="<?php echo $credential_id ?>">
                                    </div>
                                </td>
                                <td>
                                    <a class="text-dark" href="#"
                                        data-toggle="ajax-modal"
                                        data-ajax-url="ajax/ajax_credential_edit.php"
                                        data-ajax-id="<?php echo $credential_id; ?>"
                                        >
                                        <div class="media">
                                            <i class="fa fa-fw fa-2x fa-key mr-3"></i>
                                            <div class="media-body">
                                                <div><?php echo $credential_name; ?></div>
                                                <div><small class="text-secondary"><?php echo $credential_description; ?></small></div>
                                                <?php
                                                if (!empty($credential_tags_display)) { ?>
                                                    <div class="mt-1">
                                                        <?php echo $credential_tags_display; ?>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </a>
                                </td>
                                <td class="text-nowrap"><?php echo $credential_username_display; ?></td>
                                <td class="text-nowrap">
                                    <button class="btn p-0" type="button" data-toggle="popover" data-trigger="focus" data-placement="top" data-content="<?php echo $credential_password; ?>"><i class="fas fa-2x fa-ellipsis-h text-secondary"></i><i class="fas fa-2x fa-ellipsis-h text-secondary"></i></button><button class="btn btn-sm clipboardjs" type="button" data-clipboard-text="<?php echo $credential_password; ?>"><i class="far fa-copy text-secondary"></i></button>
                                </td>
                                <td class="text-nowrap"><?php echo $otp_display; ?></td>
                                <td><?php echo $credential_uri_display; ?></td>
                                <td>
                                    <?php echo "$credential_contact_display$credential_asset_display"; ?>
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
                                        <?php if ( !empty($credential_uri) || !empty($credential_uri_2) ) { ?>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-default btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fa fa-fw fa-external-link-alt"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <?php if ($credential_uri) { ?>
                                                <a href="<?php echo $credential_uri; ?>" alt="<?php echo $credential_uri; ?>" target="_blank" class="dropdown-item" >
                                                    <i class="fa fa-fw fa-external-link-alt"></i> <?php echo truncate($credential_uri,40); ?>
                                                </a>
                                                <?php } ?>
                                                <?php if ($credential_uri_2) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a href="<?php echo $credential_uri_2; ?>" target="_blank" class="dropdown-item" >
                                                    <i class="fa fa-fw fa-external-link-alt"></i> <?php echo truncate($credential_uri_2,40); ?>
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
                                                    data-ajax-id="<?php echo $credential_id; ?>"
                                                    >
                                                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#shareModal" onclick="populateShareModal(<?php echo "$client_id, 'Credential', $credential_id"; ?>)">
                                                    <i class="fas fa-fw fa-share mr-2"></i>Share
                                                </a>
                                                <?php  if (lookupUserPermission("module_credential") >= 2) { ?>
                                                    <?php if ($credential_archived_at) { ?>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-info confirm-link" href="post.php?unarchive_credential=<?php echo $credential_id; ?>">
                                                            <i class="fas fa-fw fa-redo mr-2"></i>Unarchive
                                                        </a>
                                                        <?php if (lookupUserPermission("module_credential") >= 3) { ?>
                                                            <div class="dropdown-divider"></div>
                                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_credential=<?php echo $credential_id; ?>">
                                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                        <?php } ?>
                                                        </a>
                                                    <?php } else { ?>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-danger confirm-link" href="post.php?archive_credential=<?php echo $credential_id; ?>">
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
require_once "modals/credential_export_modal.php";
if ($client_url) {
    require_once "modals/credential_import_modal.php";
}
require_once "includes/footer.php";
