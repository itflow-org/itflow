<?php include("inc_all_client.php");

if (isset($_GET['contact_id'])) {
    $contact_id = intval($_GET['contact_id']);

    $sql = mysqli_query($mysqli,"SELECT * FROM contacts 
        LEFT JOIN locations ON location_id = contact_location_id
        WHERE contact_id = $contact_id
    ");

    $row = mysqli_fetch_array($sql);
    $contact_id = $row['contact_id'];
    $contact_name = htmlentities($row['contact_name']);
    $contact_title = htmlentities($row['contact_title']);
    $contact_department =htmlentities($row['contact_department']);
    $contact_phone = formatPhoneNumber($row['contact_phone']);
    $contact_extension = htmlentities($row['contact_extension']);
    $contact_mobile = formatPhoneNumber($row['contact_mobile']);
    $contact_email = htmlentities($row['contact_email']);
    $contact_photo = htmlentities($row['contact_photo']);
    $contact_initials = initials($contact_name);
    $contact_notes = htmlentities($row['contact_notes']);
    $contact_important = intval($row['contact_important']);
    $contact_billing = intval($row['contact_billing']);
    $contact_technical = intval($row['contact_technical']);
    $contact_created_at = $row['contact_created_at'];
    if ($contact_id == $primary_contact) {
        $primary_contact_display = "<small class='text-success'>Primary Contact</small>";
    } else {
        $primary_contact_display = FALSE;
    }
    $contact_location_id = $row['contact_location_id'];
    $location_name = htmlentities($row['location_name']);
    if (empty($location_name)) {
        $location_name_display = "-";
    } else {
        $location_name_display = $location_name;
    }
    $auth_method = htmlentities($row['contact_auth_method']);

    // Related Assets Query
    $sql_related_assets = mysqli_query($mysqli,"SELECT * FROM assets WHERE asset_contact_id = $contact_id AND company_id = $session_company_id ORDER BY asset_name DESC");

    $asset_count = mysqli_num_rows($sql_related_assets);

    // Related Logins Query
    $sql_related_logins = mysqli_query($mysqli,"SELECT * FROM logins WHERE login_contact_id = $contact_id AND company_id = $session_company_id ORDER BY login_name DESC");
    $login_count = mysqli_num_rows($sql_related_logins);

    // Related Software Query
    $sql_related_software = mysqli_query($mysqli,"SELECT * FROM software, software_contacts WHERE software.software_id = software_contacts.software_id AND software_contacts.contact_id = $contact_id AND software.company_id = $session_company_id ORDER BY software.software_id DESC");
    $software_count = mysqli_num_rows($sql_related_software);

    // Related Tickets Query
    $sql_related_tickets = mysqli_query($mysqli,"SELECT * FROM tickets WHERE ticket_contact_id = $contact_id AND company_id = $session_company_id ORDER BY ticket_id DESC");
    $ticket_count = mysqli_num_rows($sql_related_tickets);

    ?>

    <div class="row">

        <div class="col-md-3">

            <div class="card card-dark">
                <div class="card-body">
                    <h3 class="text-bold"><?php echo $contact_name; ?></h3>
                    <?php if (!empty($contact_title)) { ?>
                        <div class="text-secondary"><?php echo $contact_title; ?></div>
                    <?php } ?>

                    <div class="text-center">     
                        <?php if (!empty($contact_photo)) { ?>
                            <img class="img-fluid img-circle p-3" alt="contact_photo" src="<?php echo "uploads/clients/$session_company_id/$client_id/$contact_photo"; ?>">
                        <?php } else { ?>
                            <span class="fa-stack fa-4x">
                                <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                                <span class="fa fa-stack-1x text-white"><?php echo $contact_initials; ?></span>
                            </span>
                        <?php } ?>
                    </div>
                    <hr>
                    <?php if (!empty($location_name)) { ?>
                        <div class="mb-1"><i class="fa fa-fw fa-map-marker-alt text-secondary mr-3"></i><?php echo $location_name_display; ?></div>
                    <?php } ?>
                    <?php if (!empty($contact_email)) { ?>
                        <div><i class="fa fa-fw fa-envelope text-secondary mr-3"></i><a href='mailto:<?php echo $contact_email; ?>'><?php echo $contact_email; ?></a><button class='btn btn-sm clipboardjs' data-clipboard-text='<?php echo $contact_email; ?>'><i class='far fa-copy text-secondary'></i></button></div>
                    <?php } ?>
                    <?php if (!empty($contact_phone)) { ?>
                        <div class="mb-2"><i class="fa fa-fw fa-phone text-secondary mr-3"></i><?php echo "$contact_phone $contact_phone_extention"; ?></div>
                    <?php } ?>
                    <?php if (!empty($contact_mobile)) { ?>
                        <div class="mb-2"><i class="fa fa-fw fa-mobile-alt text-secondary mr-3"></i><?php echo $contact_mobile; ?></div>
                    <?php } ?>
                    <div class="mb-2"><i class="fa fa-fw fa-clock text-secondary mr-3"></i><?php echo date('Y-m-d',strtotime($contact_created_at)); ?></div>
                    <hr>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#editContactModal<?php echo $contact_id; ?>">
                        <i class="fas fa-fw fa-user-edit"></i> Edit
                    </button>

                    <?php include("client_contact_edit_modal.php"); ?>

                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title"><i class="fa fa-fw fa-edit mr-2"></i>Notes</h5>
                </div>
                <div class="card-body p-1">
                    <textarea class="form-control" rows=6 id="contactNotes" placeholder="Enter quick notes here" onblur="updateContactNotes(<?php echo $contact_id ?>)"><?php echo $contact_notes ?></textarea>
                </div>
            </div>

        </div>

        <div class="col-md-9">


            <ol class="breadcrumb d-print-none">
                <li class="breadcrumb-item">
                    <a href="invoices.php">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="clients.php">Clients</a>
                </li>
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
                    <h3 class="card-title"><i class="fa fa-fw fa-desktop"></i> Assets</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-borderless table-hover">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Make/Model</th>
                                <th>Serial Number</th>
                                <th>Operating System</th>
                                <th>Install Date</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_related_assets)) {
                                $asset_id = $row['asset_id'];
                                $asset_type = htmlentities($row['asset_type']);
                                $asset_name = htmlentities($row['asset_name']);
                                $asset_make = htmlentities($row['asset_make']);
                                $asset_model = htmlentities($row['asset_model']);
                                $asset_serial = htmlentities($row['asset_serial']);
                                if (empty($asset_serial)) {
                                    $asset_serial_display = "-";
                                } else {
                                    $asset_serial_display = $asset_serial;
                                }
                                $asset_os = htmlentities($row['asset_os']);
                                if (empty($asset_os)) {
                                    $asset_os_display = "-";
                                } else {
                                    $asset_os_display = $asset_os;
                                }
                                $asset_ip = htmlentities($row['asset_ip']);
                                if (empty($asset_ip)) {
                                    $asset_ip_display = "-";
                                } else {
                                    $asset_ip_display = "$asset_ip<button class='btn btn-sm' data-clipboard-text='$asset_ip'><i class='far fa-copy text-secondary'></i></button>";
                                }
                                $asset_mac = htmlentities($row['asset_mac']);
                                $asset_status = htmlentities($row['asset_status']);
                                $asset_purchase_date = $row['asset_purchase_date'];
                                $asset_warranty_expire = $row['asset_warranty_expire'];
                                $asset_install_date = $row['asset_install_date'];
                                if (empty($asset_install_date)) {
                                    $asset_install_date_display = "-";
                                } else {
                                    $asset_install_date_display = $asset_install_date;
                                }
                                $asset_notes = htmlentities($row['asset_notes']);
                                $asset_created_at = $row['asset_created_at'];
                                $asset_vendor_id = $row['asset_vendor_id'];
                                $asset_location_id = $row['asset_location_id'];
                                $asset_network_id = $row['asset_network_id'];
                                $asset_contact_id = $row['asset_contact_id'];
                                if ($asset_type == 'Laptop') {
                                    $device_icon = "laptop";
                                } elseif ($asset_type == 'Desktop') {
                                    $device_icon = "desktop";
                                } elseif ($asset_type == 'Server') {
                                    $device_icon = "server";
                                } elseif ($asset_type == 'Printer') {
                                    $device_icon = "print";
                                } elseif ($asset_type == 'Camera') {
                                    $device_icon = "video";
                                } elseif ($asset_type == 'Switch' || $asset_type == 'Firewall/Router') {
                                    $device_icon = "network-wired";
                                } elseif ($asset_type == 'Access Point') {
                                    $device_icon = "wifi";
                                } elseif ($asset_type == 'Phone') {
                                    $device_icon = "phone";
                                } elseif ($asset_type == 'Mobile Phone') {
                                    $device_icon = "mobile-alt";
                                } elseif ($asset_type == 'Tablet') {
                                    $device_icon = "tablet-alt";
                                } elseif ($asset_type == 'TV') {
                                    $device_icon = "tv";
                                } elseif ($asset_type == 'Virtual Machine') {
                                    $device_icon = "cloud";
                                } else {
                                    $device_icon = "tag";
                                }

                                ?>
                                <tr>
                                    <th>
                                        <i class="fa fa-fw text-secondary fa-<?php echo $device_icon; ?> mr-2"></i>
                                        <a class="text-secondary" href="#" data-toggle="modal" data-target="#editAssetModal<?php echo $asset_id; ?>"><?php echo $asset_name; ?></a>
                                    </th>
                                    <td><?php echo $asset_type; ?></td>
                                    <td><?php echo "$asset_make $asset_model"; ?></td>
                                    <td><?php echo $asset_serial_display; ?></td>
                                    <td><?php echo $asset_os_display; ?></td>
                                    <td><?php echo $asset_install_date_display; ?></td>
                                    <td><?php echo $asset_status; ?></td>
                                    <td>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addAssetInterfaceModal<?php echo $asset_id; ?>">Interfaces</a>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editAssetModal<?php echo $asset_id; ?>">Edit</a>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#copyAssetModal<?php echo $asset_id; ?>">Copy</a>
                                                <?php if ($session_user_role == 3) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger" href="post.php?archive_asset=<?php echo $asset_id; ?>">Archive</a>
                                                    <a class="dropdown-item text-danger" href="post.php?delete_asset=<?php echo $asset_id; ?>">Delete</a>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <?php

                                include("client_asset_edit_modal.php");
                                include("client_asset_copy_modal.php");
                                //include("client_asset_tickets_modal.php");
                                include("client_asset_interface_add_modal.php");

                            }

                            ?>

                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

            <div class="card card-dark <?php if ($login_count == 0) { echo "d-none"; } ?>">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-fw fa-key"></i> Passwords</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-borderless table-hover">
                            <thead>
                            <tr>
                                <th>Name</th>
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
                                $login_id = $row['login_id'];
                                $login_name = htmlentities($row['login_name']);
                                $login_uri = htmlentities($row['login_uri']);
                                if (empty($login_uri)) {
                                    $login_uri_display = "-";
                                } else {
                                    $login_uri_display = "$login_uri<button class='btn btn-sm clipboardjs' data-clipboard-text='$login_uri'><i class='far fa-copy text-secondary'></i></button><a href='https://$login_uri' target='_blank'><i class='fa fa-external-link-alt text-secondary'></i></a>";
                                }
                                $login_username = htmlentities($row['login_username']);
                                if (empty($login_username)) {
                                    $login_username_display = "-";
                                } else {
                                    $login_username_display = "$login_username<button class='btn btn-sm clipboardjs' data-clipboard-text='$login_username'><i class='far fa-copy text-secondary'></i></button>";
                                }
                                $login_password = htmlentities(decryptLoginEntry($row['login_password']));
                                $login_otp_secret = htmlentities($row['login_otp_secret']);
                                $login_id_with_secret = '"' . $row['login_id'] . '","' . $row['login_otp_secret'] . '"';
                                if (empty($login_otp_secret)) {
                                    $otp_display = "-";
                                } else {
                                    $otp_display = "<span onmouseenter='showOTP($login_id_with_secret)'><i class='far fa-clock'></i> <span id='otp_$login_id'><i>Hover..</i></span></span>";
                                }
                                $login_note = htmlentities($row['login_note']);
                                $login_contact_id = $row['login_contact_id'];
                                $login_vendor_id = $row['login_vendor_id'];
                                $login_asset_id = $row['login_asset_id'];
                                $login_software_id = $row['login_software_id'];

                                ?>
                                <tr>
                                    <td>
                                        <i class="fa fa-fw fa-key text-secondary"></i>
                                        <a class="text-dark" href="#" data-toggle="modal" data-target="#editLoginModal<?php echo $login_id; ?>">
                                            <?php echo $login_name; ?>
                                        </a>
                                    </td>
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
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editLoginModal<?php echo $login_id; ?>">Edit</a>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#shareModal" onclick="populateShareModal(<?php echo "$client_id, 'Login', $login_id"; ?>)">Share</a>
                                                <?php if ($session_user_role == 3) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger" href="post.php?delete_login=<?php echo $login_id; ?>">Delete</a>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <?php

                                include("client_login_edit_modal.php");
                            }

                            ?>

                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

            <div class="card card-dark <?php if ($software_count == 0) { echo "d-none"; } ?>">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-fw fa-cube"></i> Licenses</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-borderless table-hover">
                            <thead class="text-dark">
                                <tr>
                                <th>Software</th>
                                <th>Type</th>
                                <th>License Type</th>
                                <th>Seats</th>
                                <th class="text-center">Action</th>
                              </tr>
                            </thead>
                            <tbody>
                            <?php
                        
                            while ($row = mysqli_fetch_array($sql_related_software)) {
                                $software_id = $row['software_id'];
                                $software_name = htmlentities($row['software_name']);
                                $software_version = htmlentities($row['software_version']);
                                $software_type = htmlentities($row['software_type']);
                                $software_license_type = htmlentities($row['software_license_type']);
                                $software_key = htmlentities($row['software_key']);
                                $software_seats = htmlentities($row['software_seats']);
                                $software_purchase = $row['software_purchase'];
                                $software_expire = $row['software_expire'];
                                $software_notes = htmlentities($row['software_notes']);

                                $seat_count = 0;

                                // Asset Licenses
                                $asset_licenses_sql = mysqli_query($mysqli,"SELECT asset_id FROM software_assets WHERE software_id = $software_id");
                                $asset_licenses_array = array();
                                while ($row = mysqli_fetch_array($asset_licenses_sql)) {
                                  $asset_licenses_array[] = $row['asset_id'];
                                  $seat_count = $seat_count + 1;
                                }
                                $asset_licenses = implode(',',$asset_licenses_array);

                                // Contact Licenses
                                $contact_licenses_sql = mysqli_query($mysqli,"SELECT contact_id FROM software_contacts WHERE software_id = $software_id");
                                $contact_licenses_array = array();
                                while ($row = mysqli_fetch_array($contact_licenses_sql)) {
                                  $contact_licenses_array[] = $row['contact_id'];
                                  $seat_count = $seat_count + 1;
                                }
                                $contact_licenses = implode(',',$contact_licenses_array);

                            ?>
                                <tr>
                                    <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editSoftwareModal<?php echo $software_id; ?>"><?php echo "$software_name<br><span class='text-secondary'>$software_version</span>"; ?></a></td>
                                    <td><?php echo $software_type; ?></td>
                                    <td><?php echo $software_license_type; ?></td>
                                    <td><?php echo "$seat_count / $software_seats"; ?></td>
                                    <td>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editSoftwareModal<?php echo $software_id; ?>">Edit</a>
                                                <?php if ($session_user_role == 3) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger" href="post.php?delete_software=<?php echo $software_id; ?>">Delete</a>
                                                <?php } ?>
                                            </div>
                                        </div> 
                                    </td>
                                </tr>

                              <?php

                              include("client_software_edit_modal.php");
                              }
                              
                              ?>

                            </tbody>
                        </table>      
                    </div>
                </div>
            </div>

            <div class="card card-dark <?php if ($ticket_count == 0) { echo "d-none"; } ?>">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-fw fa-life-ring"></i> Tickets</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
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
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_related_tickets)) {
                                $ticket_id = $row['ticket_id'];
                                $ticket_prefix = htmlentities($row['ticket_prefix']);
                                $ticket_number = htmlentities($row['ticket_number']);
                                $ticket_subject = htmlentities($row['ticket_subject']);
                                $ticket_details = $row['ticket_details'];
                                $ticket_priority = htmlentities($row['ticket_priority']);
                                $ticket_status = htmlentities($row['ticket_status']);
                                $ticket_created_at = $row['ticket_created_at'];
                                $ticket_updated_at = $row['ticket_updated_at'];
                                if (empty($ticket_updated_at)) {
                                    if ($ticket_status == "Closed") {
                                        $ticket_updated_at_display = "<p>Never</p>";
                                    } else {
                                        $ticket_updated_at_display = "<p class='text-danger'>Never</p>";
                                    }
                                } else {
                                    $ticket_updated_at_display = $ticket_updated_at;
                                }
                                $ticket_closed_at = $row['ticket_closed_at'];

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
                                $ticket_assigned_to = $row['ticket_assigned_to'];
                                if (empty($ticket_assigned_to)) {
                                    if ($ticket_status == "Closed") {
                                        $ticket_assigned_to_display = "<p>Not Assigned</p>";
                                    } else {
                                        $ticket_assigned_to_display = "<p class='text-danger'>Not Assigned</p>";
                                    }
                                } else {
                                    $ticket_assigned_to_display = htmlentities($row['user_name']);
                                }
                                $contact_id = $row['contact_id'];
                                $contact_name = htmlentities($row['contact_name']);
                                if (empty($contact_name)) {
                                    $contact_display = "-";
                                } else {
                                    $contact_display = "$contact_name<br><small class='text-secondary'>$contact_email</small>";
                                }
                                $contact_title = htmlentities($row['contact_title']);
                                $contact_email = htmlentities($row['contact_email']);
                                $contact_phone = formatPhoneNumber($row['contact_phone']);
                                $contact_extension = htmlentities($row['contact_extension']);
                                $contact_mobile = formatPhoneNumber($row['contact_mobile']);

                              ?>

                                <tr>
                                    <td><a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>"><span class="badge badge-pill badge-secondary p-3"><?php echo "$ticket_prefix$ticket_number"; ?></span></a></td>
                                    <td><a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>"><?php echo $ticket_subject; ?></a></td>
                                    <td><?php echo $contact_display; ?></td>
                                    <td><?php echo $ticket_priority_display; ?></td>
                                    <td><?php echo $ticket_status_display; ?></td>
                                    <td><?php echo $ticket_assigned_to_display; ?></td>
                                    <td><?php echo $ticket_updated_at_display; ?></td>
                                    <td><?php echo $ticket_created_at; ?></td>
                                    <td>
                                        <?php if ($ticket_status !== "Closed") { ?>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTicketModal<?php echo $ticket_id; ?>">Edit</a>
                                                <?php if ($session_user_role == 3) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger" href="post.php?delete_ticket=<?php echo $ticket_id; ?>">Delete</a>
                                                <?php } ?>
                                            </div>
                                        </div>
                                        <?php } ?>
                                    </td>
                                </tr>

                            <?php

                            include("ticket_edit_modal.php");
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

    include("share_modal.php");

    ?>

<?php } ?>

<script>
function updateContactNotes(contact_id) {
    var notes = document.getElementById("contactNotes").value;

    // Send a POST request to ajax.php as ajax.php with data client_set_notes=true, client_id=NUM, notes=NOTES
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

<?php include("footer.php"); ?>
