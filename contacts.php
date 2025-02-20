<?php

// Default Column Sortby Filter
$sort = "contact_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND contact_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
}

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
        $client_query = 'AND (contact_client_id = ' . intval($_GET['client']) . ')';
        $client = intval($_GET['client']);
    } else {
        // Default - any
        $client_query = '';
        $client = '';
    }
}

if ($client_url) {
    // Location Filter
    if (isset($_GET['location']) & !empty($_GET['location'])) {
        $location_query = 'AND (contact_location_id = ' . intval($_GET['location']) . ')';
        $location_filter = intval($_GET['location']);
    } else {
        // Default - any
        $location_query = '';
        $location_filter = '';
    }
}

//Rebuild URL
//$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS contacts.*, clients.*, locations.*, users.*, GROUP_CONCAT(tags.tag_name) FROM contacts
    LEFT JOIN clients ON client_id = contact_client_id
    LEFT JOIN locations ON location_id = contact_location_id
    LEFT JOIN users ON user_id = contact_user_id
    LEFT JOIN contact_tags ON contact_tags.contact_id = contacts.contact_id
    LEFT JOIN tags ON tags.tag_id = contact_tags.tag_id
    WHERE contact_$archive_query
    $tag_query
    AND (contact_name LIKE '%$q%' OR contact_title LIKE '%$q%' OR location_name LIKE '%$q%'  OR contact_email LIKE '%$q%' OR contact_department LIKE '%$q%' OR contact_phone LIKE '%$phone_query%' OR contact_extension LIKE '%$q%' OR contact_mobile LIKE '%$phone_query%' OR tag_name LIKE '%$q%')
    $client_query
    $location_query
    GROUP BY contact_id
    ORDER BY contact_primary DESC, contact_important DESC, $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-address-book mr-2"></i>Contacts</h3>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addContactModal">
                        <i class="fas fa-plus mr-2"></i>New Contact
                    </button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#contactInviteModal"><i class="fas fa-fw fa-paper-plane mr-2"></i>Invite</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#importContactModal">
                            <i class="fa fa-fw fa-upload mr-2"></i>Import
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportContactModal">
                            <i class="fa fa-fw fa-download mr-2"></i>Export
                        </a>
                    </div>
                </div>
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
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Contacts">
                            <div class="input-group-append">
                                <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <select onchange="this.form.submit()" class="form-control select2" name="tags[]" data-placeholder="- Select Tags -" multiple>

                                <?php $sql_tags = mysqli_query($mysqli, "SELECT * FROM tags WHERE tag_type = 3");
                                while ($row = mysqli_fetch_array($sql_tags)) {
                                    $tag_id = intval($row['tag_id']);
                                    $tag_name = nullable_htmlentities($row['tag_name']); ?>

                                    <option value="<?php echo $tag_id ?>" <?php if (isset($_GET['tags']) && is_array($_GET['tags']) && in_array($tag_id, $_GET['tags'])) { echo 'selected'; } ?>> <?php echo $tag_name ?> </option>

                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <?php if ($client_url) { ?> 
                    <div class="col-md-2">
                        <div class="input-group">
                            <select class="form-control select2" name="location" onchange="this.form.submit()">
                                <option value="">- All Locations -</option>

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
                                $sql_clients_filter = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
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
                            <a href="?<?php echo $client_url; ?>archived=<?php if($archived == 1){ echo 0; } else { echo 1; } ?>" 
                                class="btn btn-<?php if($archived == 1){ echo "primary"; } else { echo "default"; } ?>">
                                <i class="fa fa-fw fa-archive mr-2"></i>Archived
                            </a>
                            <div class="dropdown ml-2" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkAssignLocationModal">
                                        <i class="fas fa-fw fa-map-marker-alt mr-2"></i>Assign Location
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditPhoneModal">
                                        <i class="fas fa-fw fa-phone-alt mr-2"></i>Set Phone Number
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditDepartmentModal">
                                        <i class="fas fa-fw fa-users mr-2"></i>Set Department
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditRoleModal">
                                        <i class="fas fa-fw fa-user-shield mr-2"></i>Set Roles
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkAssignTagsModal">
                                        <i class="fas fa-fw fa-tags mr-2"></i>Assign Tags
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkSendEmailModal">
                                        <i class="fas fa-fw fa-paper-plane mr-2"></i>Send Email
                                    </a>
                                    <?php if ($archived) { ?>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-info"
                                        type="submit" form="bulkActions" name="bulk_unarchive_contacts">
                                        <i class="fas fa-fw fa-redo mr-2"></i>Unarchive
                                    </button>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger text-bold"
                                        type="submit" form="bulkActions" name="bulk_delete_contacts">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </button>
                                    <?php } else { ?>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger confirm-link"
                                        type="submit" form="bulkActions" name="bulk_archive_contacts">
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
            <form id="bulkActions" action="post.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="table-responsive-sm">
                    <table class="table border">
                        <thead class="thead-light <?php if (!$num_rows[0]) { echo "d-none"; } ?>">
                        <tr>
                            <td class="bg-light pr-0">
                                <div class="form-check">
                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                </div>
                            </td>
                            <th>
                                <a class="text-secondary ml-3" href="?<?php echo $url_query_strings_sort; ?>&sort=contact_name&order=<?php echo $disp; ?>">
                                    Name <?php if ($sort == 'contact_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=contact_department&order=<?php echo $disp; ?>">
                                    Department <?php if ($sort == 'contact_department') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>Contact</th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=location_name&order=<?php echo $disp; ?>">
                                    Location <?php if ($sort == 'location_name') { echo $order_icon; } ?>
                                </a>
                            </th>
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
                            $contact_id = intval($row['contact_id']);
                            $contact_name = nullable_htmlentities($row['contact_name']);
                            $contact_title = nullable_htmlentities($row['contact_title']);
                            if (empty($contact_title)) {
                                $contact_title_display = "";
                            } else {
                                $contact_title_display = "<small class='text-secondary'>$contact_title</small>";
                            }
                            $contact_department = nullable_htmlentities($row['contact_department']);
                            if (empty($contact_department)) {
                                $contact_department_display = "-";
                            } else {
                                $contact_department_display = $contact_department;
                            }
                            $contact_extension = nullable_htmlentities($row['contact_extension']);
                            if (empty($contact_extension)) {
                                $contact_extension_display = "";
                            } else {
                                $contact_extension_display = "<small class='text-secondary ml-1'>x$contact_extension</small>";
                            }
                            $contact_phone = formatPhoneNumber($row['contact_phone']);
                            if (empty($contact_phone)) {
                                $contact_phone_display = "";
                            } else {
                                $contact_phone_display = "<div><i class='fas fa-fw fa-phone mr-2'></i><a href='tel:$contact_phone'>$contact_phone$contact_extension_display</a></div>";
                            }

                            $contact_mobile = formatPhoneNumber($row['contact_mobile']);
                            if (empty($contact_mobile)) {
                                $contact_mobile_display = "";
                            } else {
                                $contact_mobile_display = "<div class='mt-2'><i class='fas fa-fw fa-mobile-alt mr-2'></i><a href='tel:$contact_mobile'>$contact_mobile</a></div>";
                            }
                            $contact_email = nullable_htmlentities($row['contact_email']);
                            if (empty($contact_email)) {
                                $contact_email_display = "";
                            } else {
                                $contact_email_display = "<div class='mt-1'><i class='fas fa-fw fa-envelope mr-2'></i><a href='mailto:$contact_email'>$contact_email</a><button class='btn btn-sm clipboardjs' type='button' data-clipboard-text='$contact_email'><i class='far fa-copy text-secondary'></i></button></div>";
                            }
                            $contact_info_display = "$contact_phone_display $contact_mobile_display $contact_email_display";
                            if (empty($contact_info_display)) {
                                $contact_info_display = "-";
                            }
                            $contact_pin = nullable_htmlentities($row['contact_pin']);
                            $contact_photo = nullable_htmlentities($row['contact_photo']);
                            $contact_initials = initials($contact_name);
                            $contact_notes = nullable_htmlentities($row['contact_notes']);
                            $contact_primary = intval($row['contact_primary']);
                            $contact_important = intval($row['contact_important']);
                            $contact_billing = intval($row['contact_billing']);
                            $contact_technical = intval($row['contact_technical']);
                            $contact_created_at = nullable_htmlentities($row['contact_created_at']);
                            $contact_archived_at = nullable_htmlentities($row['contact_archived_at']);
                            if ($contact_primary == 1) {
                                $contact_primary_display = "<small class='text-success'>Primary Contact</small>";
                            } else {
                                $contact_primary_display = false;
                            }
                            $contact_location_id = intval($row['contact_location_id']);
                            $location_name = nullable_htmlentities($row['location_name']);
                            if (empty($location_name)) {
                                $location_name = "-";
                            }
                            $location_archived_at = nullable_htmlentities($row['location_archived_at']);
                            if ($location_archived_at) {
                                $location_name_display = "<div class='text-danger' title='Archived'><s>$location_name</s></div>";
                            } else {
                                $location_name_display = $location_name;
                            }
                            $auth_method = nullable_htmlentities($row['user_auth_method']);
                            $contact_user_id = intval($row['contact_user_id']);

                            // Related Assets Query
                            $sql_related_assets = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_contact_id = $contact_id ORDER BY asset_id DESC");
                            $asset_count = mysqli_num_rows($sql_related_assets);

                            // Related Logins Query
                            $sql_related_logins = mysqli_query($mysqli, "SELECT * FROM logins WHERE login_contact_id = $contact_id ORDER BY login_id DESC");
                            $login_count = mysqli_num_rows($sql_related_logins);

                            // Related Software Query
                            $sql_related_software = mysqli_query($mysqli, "SELECT * FROM software, software_contacts WHERE software.software_id = software_contacts.software_id AND software_contacts.contact_id = $contact_id ORDER BY software.software_id DESC");
                            $software_count = mysqli_num_rows($sql_related_software);

                            // Related Tickets Query
                            $sql_related_tickets = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_contact_id = $contact_id ORDER BY ticket_id DESC");
                            $ticket_count = mysqli_num_rows($sql_related_tickets);

                            // Tags
                            $contact_tag_name_display_array = array();
                            $contact_tag_id_array = array();
                            $sql_contact_tags = mysqli_query($mysqli, "SELECT * FROM contact_tags LEFT JOIN tags ON contact_tags.tag_id = tags.tag_id WHERE contact_id = $contact_id ORDER BY tag_name ASC");
                            while ($row = mysqli_fetch_array($sql_contact_tags)) {

                                $contact_tag_id = intval($row['tag_id']);
                                $contact_tag_name = nullable_htmlentities($row['tag_name']);
                                $contact_tag_color = nullable_htmlentities($row['tag_color']);
                                if (empty($contact_tag_color)) {
                                    $contact_tag_color = "dark";
                                }
                                $contact_tag_icon = nullable_htmlentities($row['tag_icon']);
                                if (empty($contact_tag_icon)) {
                                    $contact_tag_icon = "tag";
                                }

                                $contact_tag_id_array[] = $contact_tag_id;
                                $contact_tag_name_display_array[] = "<a href='contacts.php?$client_url tags[]=$contact_tag_id'><span class='badge text-light p-1 mr-1' style='background-color: $contact_tag_color;'><i class='fa fa-fw fa-$contact_tag_icon mr-2'></i>$contact_tag_name</span></a>";
                            }
                            $contact_tags_display = implode('', $contact_tag_name_display_array);

                            ?>
                            <tr>
                                <td class="pr-0 bg-light">
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="contact_ids[]" value="<?php echo $contact_id ?>">
                                    </div>
                                </td>
                                <td>
                                    <a class="text-dark" href="contact_details.php?<?php echo $client_url; ?>contact_id=<?php echo $contact_id; ?>">
                                        <div class="media">
                                            <?php if ($contact_photo) { ?>
                                                <span class="fa-stack fa-2x mr-3 text-center">
                                                    <img class="img-size-50 img-circle" src="<?php echo "uploads/clients/$client_id/$contact_photo"; ?>">
                                                </span>
                                            <?php } else { ?>
                                                <span class="fa-stack fa-2x mr-3">
                                                    <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                                                    <span class="fa fa-stack-1x text-white"><?php echo $contact_initials; ?></span>
                                                </span>
                                            <?php } ?>

                                            <div class="media-body">
                                                <div class="<?php if($contact_important) { echo "text-bold"; } ?>"><?php echo $contact_name; ?></div>
                                                <?php echo $contact_title_display; ?>
                                                <div><?php echo $contact_primary_display; ?></div>
                                                <?php
                                                if (!empty($contact_tags_display)) { ?>
                                                    <div class="mt-1">
                                                        <?php echo $contact_tags_display; ?>
                                                    </div>
                                                <?php } ?>   
                                            </div>
                                        </div>
                                    </a>
                                    
                                </td>
                                <td><?php echo $contact_department_display; ?></td>
                                <td><?php echo $contact_info_display; ?></td>
                                <td><?php echo $location_name_display; ?></td>
                                <?php if (!$client_url) { ?>
                                <td><a href="contacts.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                                <?php } ?>
                                <td>
                                    <div class="dropdown dropleft text-center">
                                        <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="contact_details.php?<?php echo $client_url; ?>contact_id=<?php echo $contact_id; ?>">
                                                <i class="fas fa-fw fa-eye mr-2"></i>Details
                                            </a>
                                            <a class="dropdown-item" href="#"
                                                data-toggle="ajax-modal"
                                                data-ajax-url="ajax/ajax_contact_note_create.php"
                                                data-ajax-id="<?php echo $contact_id; ?>">
                                                <i class="fas fa-fw fa-sticky-note mr-2"></i>Make Note
                                            </a>
                                            <a class="dropdown-item" href="#"
                                                data-toggle="ajax-modal"
                                                data-ajax-url="ajax/ajax_contact_edit.php"
                                                data-ajax-id="<?php echo $contact_id; ?>">
                                                <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                            </a>
                                            <?php if ($session_user_role == 3 && $contact_primary == 0) { ?>
                                                <?php if ($contact_archived_at) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-info confirm-link" href="post.php?unarchive_contact=<?php echo $contact_id; ?>">
                                                    <i class="fas fa-fw fa-redo mr-2"></i>Unarchive
                                                </a>
                                                <?php } else { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_contact=<?php echo $contact_id; ?>">
                                                    <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?anonymize_contact=<?php echo $contact_id; ?>">
                                                    <i class="fas fa-fw fa-user-secret mr-2"></i>Anonymize & Archive
                                                </a>
                                                <?php } ?>

                                                <?php if ($config_destructive_deletes_enable) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_contact=<?php echo $contact_id; ?>">
                                                    <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                </a>
                                                <?php } ?>
                                            <?php } ?>
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
                <?php require_once "modals/contact_bulk_assign_location_modal.php"; ?>
                <?php require_once "modals/contact_bulk_edit_phone_modal.php"; ?>
                <?php require_once "modals/contact_bulk_edit_department_modal.php"; ?>
                <?php require_once "modals/contact_bulk_edit_role_modal.php"; ?>
                <?php require_once "modals/contact_bulk_assign_tags_modal.php"; ?>
                <?php require_once "modals/contact_bulk_email_modal.php"; ?>
            </form>
            <?php require_once "includes/filter_footer.php";
?>
        </div>
    </div>

<!-- JavaScript to Show/Hide Password Form Group -->
<script>

    function generatePassword(type, id) {
        // Send a GET request to ajax.php as ajax.php?get_readable_pass=true
        jQuery.get(
            "ajax.php", {
                get_readable_pass: 'true'
            },
            function(data) {
                //If we get a response from post.php, parse it as JSON
                const password = JSON.parse(data);

                // Set the password value to the correct modal, based on the type
                if (type == "add") {
                    document.getElementById("password-add").value = password;
                } else if (type == "edit") {
                    document.getElementById("password-edit-"+id.toString()).value = password;
                }
            }
        );
    }

    $(document).ready(function() {
        $('.authMethod').on('change', function() {
            var $form = $(this).closest('.authForm');
            if ($(this).val() === 'local') {
                $form.find('.passwordGroup').show();
            } else {
                $form.find('.passwordGroup').hide();
            }
        });
        $('.authMethod').trigger('change');

    });
</script>

<script src="js/bulk_actions.js"></script>

<?php

require_once "modals/contact_add_modal.php";
require_once "modals/contact_invite_modal.php";
require_once "modals/contact_import_modal.php";
require_once "modals/contact_export_modal.php";
require_once "includes/footer.php";
