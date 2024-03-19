<?php

require_once "inc_all_client.php";


if (isset($_GET['contact_id'])) {
    $contact_id = intval($_GET['contact_id']);

    $sql = mysqli_query($mysqli, "SELECT * FROM contacts 
        LEFT JOIN locations ON location_id = contact_location_id
        WHERE contact_id = $contact_id
    ");

    $row = mysqli_fetch_array($sql);
    $contact_name = nullable_htmlentities($row['contact_name']);
    $contact_title = nullable_htmlentities($row['contact_title']);
    $contact_department =nullable_htmlentities($row['contact_department']);
    $contact_phone = formatPhoneNumber($row['contact_phone']);
    $contact_extension = nullable_htmlentities($row['contact_extension']);
    $contact_mobile = formatPhoneNumber($row['contact_mobile']);
    $contact_email = nullable_htmlentities($row['contact_email']);
    $contact_photo = nullable_htmlentities($row['contact_photo']);
    $contact_pin = nullable_htmlentities($row['contact_pin']);
    $contact_initials = initials($contact_name);
    $contact_notes = nullable_htmlentities($row['contact_notes']);
    $contact_primary = intval($row['contact_primary']);
    $contact_important = intval($row['contact_important']);
    $contact_billing = intval($row['contact_billing']);
    $contact_technical = intval($row['contact_technical']);
    $contact_created_at = nullable_htmlentities($row['contact_created_at']);
    $contact_location_id = intval($row['contact_location_id']);
    $location_name = nullable_htmlentities($row['location_name']);
    $auth_method = nullable_htmlentities($row['contact_auth_method']);

    // Related Assets Query
    $sql_related_assets = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_contact_id = $contact_id ORDER BY asset_name DESC");
    $asset_count = mysqli_num_rows($sql_related_assets);

    // Related Logins Query
    $sql_related_logins = mysqli_query($mysqli, "SELECT * FROM logins WHERE login_contact_id = $contact_id ORDER BY login_name DESC");
    $login_count = mysqli_num_rows($sql_related_logins);

    // Related Software Query
    //$sql_related_software = mysqli_query($mysqli, "SELECT * FROM software, software_contacts WHERE software.software_id = software_contacts.software_id AND software_contacts.contact_id = $contact_id ORDER BY software.software_id DESC");
    $sql_related_software = mysqli_query(
        $mysqli,
        "SELECT * FROM software_contacts 
        LEFT JOIN software ON software_contacts.software_id = software.software_id 
        WHERE software_contacts.contact_id = $contact_id 
        ORDER BY software.software_id DESC"
    );

    $software_count = mysqli_num_rows($sql_related_software);

    // Related Tickets Query
    $sql_related_tickets = mysqli_query($mysqli, "SELECT * FROM tickets LEFT JOIN users on ticket_assigned_to = user_id WHERE ticket_contact_id = $contact_id ORDER BY ticket_id DESC");
    $ticket_count = mysqli_num_rows($sql_related_tickets);

    ?>

    <div class="row">

        <div class="col-md-3">

            <div class="card card-dark">
                <div class="card-body">
                    <button type="button" class="btn btn-default float-right" data-toggle="modal" data-target="#editContactModal<?php echo $contact_id; ?>">
                        <i class="fas fa-fw fa-user-edit"></i>
                    </button>
                    <h3 class="text-bold"><?php echo $contact_name; ?></h3>
                    <?php if ($contact_title) { ?>
                        <div class="text-secondary"><?php echo $contact_title; ?></div>
                    <?php } ?>

                    <div class="text-center">
                        <?php if ($contact_photo) { ?>
                            <img class="img-fluid img-circle p-3" alt="contact_photo" src="<?php echo "uploads/clients/$client_id/$contact_photo"; ?>">
                        <?php } else { ?>
                            <span class="fa-stack fa-4x">
                                <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                                <span class="fa fa-stack-1x text-white"><?php echo $contact_initials; ?></span>
                            </span>
                        <?php } ?>
                    </div>
                    <hr>
                    <?php if ($location_name) { ?>
                        <div><i class="fa fa-fw fa-map-marker-alt text-secondary mr-2"></i><?php echo $location_name; ?></div>
                    <?php }
                    if ($contact_email) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-envelope text-secondary mr-2"></i><a href='mailto:<?php echo $contact_email; ?>'><?php echo $contact_email; ?></a><button class='btn btn-sm clipboardjs' data-clipboard-text='<?php echo $contact_email; ?>'><i class='far fa-copy text-secondary'></i></button></div>
                    <?php }
                    if ($contact_phone) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-phone text-secondary mr-2"></i><a href="tel:<?php echo "$contact_phone"?>"><?php echo "$contact_phone $contact_extension"; ?></a></div>
                    <?php }
                    if ($contact_mobile) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-mobile-alt text-secondary mr-2"></i><a href="tel:<?php echo $contact_mobile; ?>"><?php echo $contact_mobile; ?></a></div>
                    <?php }
                    if ($contact_pin) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-key text-secondary mr-2"></i><?php echo $contact_pin; ?></div>
                    <?php }
                    if ($contact_primary) { ?>
                        <div class="mt-2 text-success"><i class="fa fa-fw fa-check mr-2"></i>Primary Contact</div>
                    <?php }
                    if ($contact_important) { ?>
                        <div class="mt-2 text-dark text-bold"><i class="fa fa-fw fa-check mr-2"></i>Important</div>
                    <?php }
                    if ($contact_technical) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-check text-secondary mr-2"></i>Technical</div>
                    <?php }
                    if ($contact_billing) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-check text-secondary mr-2"></i>Billing</div>
                    <?php } ?>
                    <div class="mt-2"><i class="fa fa-fw fa-clock text-secondary mr-2"></i><?php echo date('Y-m-d', strtotime($contact_created_at)); ?></div>

                    <?php require_once "client_contact_edit_modal.php";
 ?>

                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title">Notes</h5>
                </div>
                <textarea class="form-control" rows=6 id="contactNotes" placeholder="Notes, eg Personal tidbits to spark convo, temperment, etc" onblur="updateContactNotes(<?php echo $contact_id ?>)"><?php echo $contact_notes ?></textarea>
            </div>

        </div>

        <div class="col-md-9">


            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>
                </li>
                <li class="breadcrumb-item">
                    <a href="client_contacts.php?client_id=<?php echo $client_id; ?>">Contacts</a>
                </li>
                <li class="breadcrumb-item active"><?php echo "$contact_name"; ?></li>
            </ol>

            <div class="card card-dark <?php if ($asset_count == 0) { echo "d-none"; } ?>">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-fw fa-desktop mr-2"></i>Related Assets</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped table-borderless table-hover">
                            <thead>
                            <tr>
                                <th>Name/Description</th>
                                <th>Type</th>
                                <th>Make/Model</th>
                                <th>Serial Number</th>
                                <th>Install Date</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_related_assets)) {
                                $asset_id = intval($row['asset_id']);
                                $asset_type = nullable_htmlentities($row['asset_type']);
                                $asset_name = nullable_htmlentities($row['asset_name']);
                                $asset_description = nullable_htmlentities($row['asset_description']);
                                $asset_make = nullable_htmlentities($row['asset_make']);
                                $asset_model = nullable_htmlentities($row['asset_model']);
                                $asset_serial = nullable_htmlentities($row['asset_serial']);
                                if (empty($asset_serial)) {
                                    $asset_serial_display = "-";
                                } else {
                                    $asset_serial_display = $asset_serial;
                                }
                                $asset_os = nullable_htmlentities($row['asset_os']);
                                if (empty($asset_os)) {
                                    $asset_os_display = "-";
                                } else {
                                    $asset_os_display = $asset_os;
                                }
                                $asset_ip = nullable_htmlentities($row['asset_ip']);
                                if (empty($asset_ip)) {
                                    $asset_ip_display = "-";
                                } else {
                                    $asset_ip_display = "$asset_ip<button class='btn btn-sm' data-clipboard-text='$asset_ip'><i class='far fa-copy text-secondary'></i></button>";
                                }
                                $asset_mac = nullable_htmlentities($row['asset_mac']);
                                $asset_status = nullable_htmlentities($row['asset_status']);
                                $asset_purchase_date = nullable_htmlentities($row['asset_purchase_date']);
                                $asset_warranty_expire = nullable_htmlentities($row['asset_warranty_expire']);
                                $asset_install_date = nullable_htmlentities($row['asset_install_date']);
                                if (empty($asset_install_date)) {
                                    $asset_install_date_display = "-";
                                } else {
                                    $asset_install_date_display = $asset_install_date;
                                }
                                $asset_notes = nullable_htmlentities($row['asset_notes']);
                                $asset_created_at = nullable_htmlentities($row['asset_created_at']);
                                $asset_vendor_id = intval($row['asset_vendor_id']);
                                $asset_location_id = intval($row['asset_location_id']);
                                $asset_network_id = intval($row['asset_network_id']);
                                $asset_contact_id = intval($row['asset_contact_id']);

                                $login_id = $row['login_id'];
                                $login_username = nullable_htmlentities(decryptLoginEntry($row['login_username']));
                                $login_password = nullable_htmlentities(decryptLoginEntry($row['login_password']));

                                $device_icon = getAssetIcon($asset_type);

                                ?>
                                <tr>
                                    <th>
                                        <i class="fa fa-fw text-secondary fa-<?php echo $device_icon; ?> mr-2"></i>
                                        <a class="text-secondary" href="#" data-toggle="modal" data-target="#editAssetModal<?php echo $asset_id; ?>"><?php echo $asset_name; ?></a>
                                        <div class="mt-0">
                                            <small class="text-muted"><?php echo $asset_description; ?></small>
                                        </div>
                                    </th>
                                    <td><?php echo $asset_type; ?></td>
                                    <td>
                                        <?php echo $asset_make; ?>
                                        <div class="mt-0">
                                            <small class="text-muted"><?php echo $asset_model; ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo $asset_serial_display; ?></td>

                                    <td><?php echo $asset_install_date_display; ?></td>
                                    <td><?php echo $asset_status; ?></td>
                                    <td>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addAssetInterfaceModal<?php echo $asset_id; ?>">Interfaces</a>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editAssetModal<?php echo $asset_id; ?>">
                                                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                </a>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#copyAssetModal<?php echo $asset_id; ?>">
                                                    <i class="fas fa-fw fa-copy mr-2"></i>Copy
                                                </a>
                                                <?php if ($session_user_role == 3) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger" href="post.php?archive_asset=<?php echo $asset_id; ?>">
                                                        <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                                    </a>
                                                    <a class="dropdown-item text-danger text-bold" href="post.php?delete_asset=<?php echo $asset_id; ?>">
                                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                    </a>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <?php

                                require "client_asset_edit_modal.php";

                                require "client_asset_copy_modal.php";

                                require "client_asset_interface_add_modal.php";


                            }

                            ?>

                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

            <div class="card card-dark <?php if ($login_count == 0) { echo "d-none"; } ?>">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-fw fa-key mr-2"></i>Related Logins</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm-sm">
                        <table class="table table-striped table-borderless table-hover">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Username</th>
                                <th>Password</th>
                                <th>OTP</th>
                                <th>URI</th>
                                <th class="text-center">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_related_logins)) {
                                $login_id = intval($row['login_id']);
                                $login_name = nullable_htmlentities($row['login_name']);
                                $login_description = nullable_htmlentities($row['login_description']);
                                $login_uri = nullable_htmlentities($row['login_uri']);
                                if (empty($login_uri)) {
                                    $login_uri_display = "-";
                                } else {
                                    $login_uri_display = "$login_uri<button class='btn btn-sm clipboardjs' data-clipboard-text='$login_uri'><i class='far fa-copy text-secondary'></i></button><a href='https://$login_uri' target='_blank'><i class='fa fa-external-link-alt text-secondary'></i></a>";
                                }
                                $login_username = nullable_htmlentities(decryptLoginEntry($row['login_username']));
                                if (empty($login_username)) {
                                    $login_username_display = "-";
                                } else {
                                    $login_username_display = "$login_username<button class='btn btn-sm clipboardjs' data-clipboard-text='$login_username'><i class='far fa-copy text-secondary'></i></button>";
                                }
                                $login_password = nullable_htmlentities(decryptLoginEntry($row['login_password']));
                                $login_otp_secret = nullable_htmlentities($row['login_otp_secret']);
                                $login_id_with_secret = '"' . $row['login_id'] . '","' . $row['login_otp_secret'] . '"';
                                if (empty($login_otp_secret)) {
                                    $otp_display = "-";
                                } else {
                                    $otp_display = "<span onmouseenter='showOTP($login_id_with_secret)'><i class='far fa-clock'></i> <span id='otp_$login_id'><i>Hover..</i></span></span>";
                                }
                                $login_note = nullable_htmlentities($row['login_note']);
                                $login_important = intval($row['login_important']);
                                $login_contact_id = intval($row['login_contact_id']);
                                $login_vendor_id = intval($row['login_vendor_id']);
                                $login_asset_id = intval($row['login_asset_id']);
                                $login_software_id = intval($row['login_software_id']);

                                ?>
                                <tr>
                                    <td>
                                        <i class="fa fa-fw fa-key text-secondary"></i>
                                        <a class="text-dark" href="#" data-toggle="modal" data-target="#editLoginModal<?php echo $login_id; ?>">
                                            <?php echo $login_name; ?>
                                        </a>
                                    </td>
                                    <td><?php echo $login_description; ?></td>
                                    <td><?php echo $login_username_display; ?></td>
                                    <td>
                                        <a tabindex="0" href="#" data-toggle="popover" data-trigger="focus" data-placement="top" data-content="<?php echo $login_password; ?>"><i class="fas fa-2x fa-ellipsis-h text-secondary"></i><i class="fas fa-2x fa-ellipsis-h text-secondary"></i></a><button class="btn btn-sm clipboardjs" data-clipboard-text="<?php echo $login_password; ?>"><i class="far fa-copy text-secondary"></i></button>
                                    </td>
                                    <td><?php echo $otp_display; ?></td>
                                    <td><?php echo $login_uri_display; ?></td>
                                    <td>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editLoginModal<?php echo $login_id; ?>">
                                                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                </a>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#shareModal" onclick="populateShareModal(<?php echo "$client_id, 'Login', $login_id"; ?>)">
                                                    <i class="fas fa-fw fa-share-alt mr-2"></i>Share
                                                </a>
                                                <?php if ($session_user_role == 3) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger text-bold" href="post.php?delete_login=<?php echo $login_id; ?>">
                                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                    </a>
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

                </div>
            </div>

            <div class="card card-dark <?php if ($software_count == 0) { echo "d-none"; } ?>">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-fw fa-cube mr-2"></i>Related Licenses</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped table-borderless table-hover">
                            <thead class="text-dark">
                            <tr>
                                <th>Software</th>
                                <th>Type</th>
                                <th>License Type</th>
                                <th>Seats</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_related_software)) {
                                $software_id = intval($row['software_id']);
                                $software_name = nullable_htmlentities($row['software_name']);
                                $software_version = nullable_htmlentities($row['software_version']);
                                $software_type = nullable_htmlentities($row['software_type']);
                                $software_license_type = nullable_htmlentities($row['software_license_type']);
                                $software_key = nullable_htmlentities($row['software_key']);
                                $software_seats = nullable_htmlentities($row['software_seats']);
                                $software_purchase = nullable_htmlentities($row['software_purchase']);
                                $software_expire = nullable_htmlentities($row['software_expire']);
                                $software_notes = nullable_htmlentities($row['software_notes']);

                                $seat_count = 0;

                                // Get Login
                                $login_id = intval($row['login_id']);
                                $login_username = nullable_htmlentities(decryptLoginEntry($row['login_username']));
                                $login_password = nullable_htmlentities(decryptLoginEntry($row['login_password']));

                                // Asset Licenses
                                $asset_licenses_sql = mysqli_query($mysqli, "SELECT asset_id FROM software_assets WHERE software_id = $software_id");
                                $asset_licenses_array = array();
                                while ($row = mysqli_fetch_array($asset_licenses_sql)) {
                                    $asset_licenses_array[] = intval($row['asset_id']);
                                    $seat_count = $seat_count + 1;
                                }
                                $asset_licenses = implode(',', $asset_licenses_array);

                                // Contact Licenses
                                $contact_licenses_sql = mysqli_query($mysqli, "SELECT contact_id FROM software_contacts WHERE software_id = $software_id");
                                $contact_licenses_array = array();
                                while ($row = mysqli_fetch_array($contact_licenses_sql)) {
                                    $contact_licenses_array[] = intval($row['contact_id']);
                                    $seat_count = $seat_count + 1;
                                }
                                $contact_licenses = implode(',', $contact_licenses_array);

                                ?>
                                <tr>
                                    <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editSoftwareModal<?php echo $software_id; ?>"><?php echo "$software_name<br><span class='text-secondary'>$software_version</span>"; ?></a></td>
                                    <td><?php echo $software_type; ?></td>
                                    <td><?php echo $software_license_type; ?></td>
                                    <td><?php echo "$seat_count / $software_seats"; ?></td>
                                </tr>

                                <?php

                            }

                            ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card card-dark <?php if ($ticket_count == 0) { echo "d-none"; } ?>">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-fw fa-life-ring mr-2"></i>Related Tickets</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped table-borderless table-hover">
                            <thead class="text-dark">
                            <tr>
                                <th>Number</th>
                                <th>Subject</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Assigned</th>
                                <th>Last Response</th>
                                <th>Created</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_related_tickets)) {
                                $ticket_id = intval($row['ticket_id']);
                                $ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
                                $ticket_number = intval($row['ticket_number']);
                                $ticket_subject = nullable_htmlentities($row['ticket_subject']);
                                $ticket_priority = nullable_htmlentities($row['ticket_priority']);
                                $ticket_status = nullable_htmlentities($row['ticket_status']);
                                $ticket_created_at = nullable_htmlentities($row['ticket_created_at']);
                                $ticket_updated_at = nullable_htmlentities($row['ticket_updated_at']);
                                if (empty($ticket_updated_at)) {
                                    if ($ticket_status == "Closed") {
                                        $ticket_updated_at_display = "<p>Never</p>";
                                    } else {
                                        $ticket_updated_at_display = "<p class='text-danger'>Never</p>";
                                    }
                                } else {
                                    $ticket_updated_at_display = $ticket_updated_at;
                                }
                                $ticket_closed_at = nullable_htmlentities($row['ticket_closed_at']);

                                if ($ticket_status == "Open") {
                                    $ticket_status_display = "<span class='p-2 badge badge-primary'>$ticket_status</span>";
                                } elseif ($ticket_status == "Working") {
                                    $ticket_status_display = "<span class='p-2 badge badge-success'>$ticket_status</span>";
                                } else {
                                    $ticket_status_display = "<span class='p-2 badge badge-secondary'>$ticket_status</span>";
                                }

                                if ($ticket_priority == "High") {
                                    $ticket_priority_display = "<span class='p-2 badge badge-danger'>$ticket_priority</span>";
                                } elseif ($ticket_priority == "Medium") {
                                    $ticket_priority_display = "<span class='p-2 badge badge-warning'>$ticket_priority</span>";
                                } elseif ($ticket_priority == "Low") {
                                    $ticket_priority_display = "<span class='p-2 badge badge-info'>$ticket_priority</span>";
                                } else {
                                    $ticket_priority_display = "-";
                                }
                                $ticket_assigned_to = intval($row['ticket_assigned_to']);
                                if (empty($ticket_assigned_to)) {
                                    if ($ticket_status == "Closed") {
                                        $ticket_assigned_to_display = "<p>Not Assigned</p>";
                                    } else {
                                        $ticket_assigned_to_display = "<p class='text-danger'>Not Assigned</p>";
                                    }
                                } else {
                                    $ticket_assigned_to_display = nullable_htmlentities($row['user_name']);
                                }

                                ?>

                                <tr>
                                    <td><a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>"><span class="badge badge-pill badge-secondary p-3"><?php echo "$ticket_prefix$ticket_number"; ?></span></a></td>
                                    <td><a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>"><?php echo $ticket_subject; ?></a></td>
                                    <td><?php echo $ticket_priority_display; ?></td>
                                    <td><?php echo $ticket_status_display; ?></td>
                                    <td><?php echo $ticket_assigned_to_display; ?></td>
                                    <td><?php echo $ticket_updated_at_display; ?></td>
                                    <td><?php echo $ticket_created_at; ?></td>
                                </tr>

                                <?php

                            }

                            ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <?php

    require_once "share_modal.php";


    ?>

<?php } ?>

    <script>
        function updateContactNotes(contact_id) {
            var notes = document.getElementById("contactNotes").value;

            // Send a POST request to ajax.php as ajax.php with data contact_set_notes=true, contact_id=NUM, notes=NOTES
            jQuery.post(
                "ajax.php",
                {
                    contact_set_notes: 'TRUE',
                    contact_id: contact_id,
                    notes: notes
                }
            )
        }
    </script>

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

<?php
require_once "footer.php";

