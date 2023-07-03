<?php

// Default Column Sortby Filter
$sb = "contact_name";
$o = "ASC";

require_once("inc_all_client.php");

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS * FROM contacts 
    LEFT JOIN locations ON location_id = contact_location_id
    WHERE contact_archived_at IS NULL 
    AND (contact_name LIKE '%$q%' OR contact_title LIKE '%$q%' OR location_name LIKE '%$q%'  OR contact_email LIKE '%$q%' OR contact_department LIKE '%$q%' OR contact_phone LIKE '%$phone_query%' OR contact_extension LIKE '%$q%' OR contact_mobile LIKE '%$phone_query%')
    AND contact_client_id = $client_id 
    ORDER BY contact_primary DESC, contact_important DESC, $sb $o LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-users mr-2"></i>Contacts</h3>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addContactModal">
                        <i class="fas fa-plus mr-2"></i>New Contact
                    </button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#contactInviteModal"><i class="fas fa-paper-plane mr-2"></i>Invite Contact</a>
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
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Contacts">
                            <div class="input-group-append">
                                <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="float-right">
                            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#exportContactModal"><i class="fa fa-fw fa-download mr-2"></i>Export</button>
                            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#importContactModal"><i class="fa fa-fw fa-upload mr-2"></i>Import</button>
                        </div>
                    </div>

                </div>
            </form>
            <hr>
            <div class="table-responsive-sm">
                <table class="table border">
                    <thead class="thead-light <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th class="text-center"><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=contact_name&o=<?php echo $disp; ?>">Name</a></th>
                        <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=contact_department&o=<?php echo $disp; ?>">Department</a></th>
                        <th>Contact</th>
                        <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=location_name&o=<?php echo $disp; ?>">Location</a></th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $contact_id = intval($row['contact_id']);
                        $contact_name = nullable_htmlentities($row['contact_name']);
                        $contact_title = nullable_htmlentities($row['contact_title']);
                        if (empty($contact_title)) {
                            $contact_title_display = "-";
                        } else {
                            $contact_title_display = "<small class='text-secondary'>$contact_title</small>";
                        }
                        $contact_department = nullable_htmlentities($row['contact_department']);
                        if (empty($contact_department)) {
                            $contact_department_display = "";
                        } else {
                            $contact_department_display = $contact_department;
                        }
                        $contact_phone = formatPhoneNumber($row['contact_phone']);
                        if (empty($contact_phone)) {
                            $contact_phone_display = "";
                        } else {
                            $contact_phone_display = "<div><i class='fas fa-fw fa-phone mr-2'></i>$contact_phone</div>";
                        }
                        $contact_extension = nullable_htmlentities($row['contact_extension']);
                        $contact_mobile = formatPhoneNumber($row['contact_mobile']);
                        if (empty($contact_mobile)) {
                            $contact_mobile_display = "";
                        } else {
                            $contact_mobile_display = "<div class='mt-2'><i class='fas fa-fw fa-mobile-alt mr-2'></i>$contact_mobile</div>";
                        }
                        $contact_email = nullable_htmlentities($row['contact_email']);
                        if (empty($contact_email)) {
                            $contact_email_display = "";
                        } else {
                            $contact_email_display = "<div class='mt-1'><i class='fas fa-fw fa-envelope mr-2'></i><a href='mailto:$contact_email'>$contact_email</a><button class='btn btn-sm clipboardjs' data-clipboard-text='$contact_email'><i class='far fa-copy text-secondary'></i></button></div>";
                        }
                        $contact_info_display = "$contact_phone_display $contact_mobile_display $contact_email_display";
                        if (empty($contact_info_display)) {
                            $contact_info_display = "-";
                        }
                        $contact_pin = nullable_htmlentities($row['contact_pin']);
                        $contact_photo = nullable_htmlentities($row['contact_photo']);
                        $contact_initials = initials($contact_name);
                        $contact_notes = nullable_htmlentities($row['contact_notes']);
                        $contact_important = intval($row['contact_important']);
                        $contact_billing = intval($row['contact_billing']);
                        $contact_technical = intval($row['contact_technical']);
                        $contact_created_at = nullable_htmlentities($row['contact_created_at']);
                        if ($contact_id == $primary_contact) {
                            $primary_contact_display = "<small class='text-success'>Primary Contact</small>";
                        } else {
                            $primary_contact_display = false;
                        }
                        $contact_location_id = intval($row['contact_location_id']);
                        $location_name = nullable_htmlentities($row['location_name']);
                        if (empty($location_name)) {
                            $location_name_display = "-";
                        } else {
                            $location_name_display = $location_name;
                        }
                        $auth_method = nullable_htmlentities($row['contact_auth_method']);

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

                        ?>
                        <tr>
                            <td class="text-center <?php if(!empty($contact_important)) { echo "text-bold"; }?>">
                                <a class="text-dark" href="client_contact_details.php?client_id=<?php echo $client_id; ?>&contact_id=<?php echo $contact_id; ?>">
                                    <?php if (!empty($contact_photo)) { ?>

                                        <img class="img-size-50 img-circle" src="<?php echo "uploads/clients/$client_id/$contact_photo"; ?>">

                                    <?php } else { ?>

                                        <span class="fa-stack fa-2x">
                                            <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                                            <span class="fa fa-stack-1x text-white"><?php echo $contact_initials; ?></span>
                                        </span>

                                        <br>

                                    <?php } ?>
                                    <div class="text-dark"><?php echo $contact_name; ?></div>
                                    <div><?php echo $contact_title_display; ?></div>
                                    <div><?php echo $primary_contact_display; ?></div>
                                </a>
                            </td>

                            <td><?php echo $contact_department_display; ?></td>
                            <td><?php echo $contact_info_display; ?></td>
                            <td><?php echo $location_name_display; ?></td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="client_contact_details.php?client_id=<?php echo $client_id; ?>&contact_id=<?php echo $contact_id; ?>">
                                            <i class="fas fa-fw fa-eye mr-2"></i>Details
                                        </a>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editContactModal<?php echo $contact_id; ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <?php if ($session_user_role == 3 && $contact_id !== $primary_contact) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger" href="post.php?archive_contact=<?php echo $contact_id; ?>">
                                                <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold" href="post.php?delete_contact=<?php echo $contact_id; ?>">
                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php

                        require("client_contact_edit_modal.php");

                    }

                    ?>

                    </tbody>
                </table>
            </div>
            <?php require_once("pagination.php"); ?>
        </div>
    </div>

<?php

require_once("client_contact_add_modal.php");
require_once("client_contact_invite_modal.php");
require_once("client_contact_import_modal.php");
require_once("client_contact_export_modal.php");
require_once("footer.php");
