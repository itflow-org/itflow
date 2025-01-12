<?php

require_once "includes/inc_all_client.php";


if (isset($_GET['asset_id'])) {
    $asset_id = intval($_GET['asset_id']);

    $sql = mysqli_query($mysqli, "SELECT * FROM assets
        LEFT JOIN contacts ON asset_contact_id = contact_id 
        LEFT JOIN locations ON asset_location_id = location_id
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
        WHERE asset_id = $asset_id
        AND asset_client_id = $client_id
    ");

    $row = mysqli_fetch_array($sql);
    $asset_id = intval($row['asset_id']);
    $asset_type = nullable_htmlentities($row['asset_type']);
    $asset_name = nullable_htmlentities($row['asset_name']);
    $asset_description = nullable_htmlentities($row['asset_description']);
    $asset_make = nullable_htmlentities($row['asset_make']);
    $asset_model = nullable_htmlentities($row['asset_model']);
    $asset_serial = nullable_htmlentities($row['asset_serial']);
    $asset_os = nullable_htmlentities($row['asset_os']);
    $asset_uri = nullable_htmlentities($row['asset_uri']);
    $asset_uri_2 = nullable_htmlentities($row['asset_uri_2']);
    $asset_status = nullable_htmlentities($row['asset_status']);
    $asset_purchase_date = nullable_htmlentities($row['asset_purchase_date']);
    $asset_warranty_expire = nullable_htmlentities($row['asset_warranty_expire']);
    $asset_install_date = nullable_htmlentities($row['asset_install_date']);
    $asset_photo = nullable_htmlentities($row['asset_photo']);
    $asset_physical_location = nullable_htmlentities($row['asset_physical_location']);
    $asset_notes = nullable_htmlentities($row['asset_notes']);
    $asset_created_at = nullable_htmlentities($row['asset_created_at']);
    $asset_vendor_id = intval($row['asset_vendor_id']);
    $asset_location_id = intval($row['asset_location_id']);
    $asset_contact_id = intval($row['asset_contact_id']);

    $asset_ip = nullable_htmlentities($row['interface_ip']);
    $asset_ipv6 = nullable_htmlentities($row['interface_ipv6']);
    $asset_nat_ip = nullable_htmlentities($row['interface_nat_ip']);
    $asset_mac = nullable_htmlentities($row['interface_mac']);
    $asset_network_id = intval($row['interface_network_id']);

    $device_icon = getAssetIcon($asset_type);

    $contact_name = nullable_htmlentities($row['contact_name']);
    $contact_email = nullable_htmlentities($row['contact_email']);
    $contact_phone = nullable_htmlentities($row['contact_phone']);
    $contact_mobile = nullable_htmlentities($row['contact_mobile']);
    $contact_archived_at = nullable_htmlentities($row['contact_archived_at']);
    if ($contact_archived_at) {
        $contact_name_display = "<span class='text-danger' title='Archived'><s>$contact_name</s></span>";
    } else {
        $contact_name_display = $contact_name;
    }
    $location_name = nullable_htmlentities($row['location_name']);
    if (empty($location_name)) {
        $location_name = "-";
    }
    $location_archived_at = nullable_htmlentities($row['location_archived_at']);
    if ($location_archived_at) {
        $location_name_display = "<span class='text-danger' title='Archived'><s>$location_name</s></span>";
    } else {
        $location_name_display = $location_name;
    }

    // Related Tickets Query
    $sql_related_tickets = mysqli_query($mysqli, "SELECT * FROM tickets 
        LEFT JOIN users on ticket_assigned_to = user_id
        LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
        WHERE ticket_asset_id = $asset_id
        ORDER BY ticket_number DESC"
    );
    $ticket_count = mysqli_num_rows($sql_related_tickets);

    // Related Recurring Tickets Query
    $sql_related_recurring_tickets = mysqli_query($mysqli, "SELECT * FROM scheduled_tickets 
        WHERE scheduled_ticket_asset_id = $asset_id
        ORDER BY scheduled_ticket_next_run DESC"
    );
    $recurring_ticket_count = mysqli_num_rows($sql_related_recurring_tickets);

    // Related Documents
    $sql_related_documents = mysqli_query($mysqli, "SELECT * FROM asset_documents 
        LEFT JOIN documents ON asset_documents.document_id = documents.document_id
        WHERE asset_documents.asset_id = $asset_id 
        AND document_archived_at IS NULL 
        ORDER BY document_name DESC"
    );
    $document_count = mysqli_num_rows($sql_related_documents);

    // Network Interfaces
    $sql_related_interfaces = mysqli_query($mysqli, "SELECT * FROM asset_interfaces 
        LEFT JOIN assets ON asset_id = interface_asset_id
        LEFT JOIN networks ON network_id = interface_network_id
        WHERE asset_id = $asset_id
        AND interface_archived_at IS NULL 
        ORDER BY interface_name DESC"
    );
    $interface_count = mysqli_num_rows($sql_related_interfaces);

    // Related Files
    $sql_related_files = mysqli_query($mysqli, "SELECT * FROM asset_files 
        LEFT JOIN files ON asset_files.file_id = files.file_id
        WHERE asset_files.asset_id = $asset_id
        AND file_archived_at IS NULL
        ORDER BY file_name DESC"
    );
    $files_count = mysqli_num_rows($sql_related_files);
    // View Mode -- 0 List, 1 Thumbnail
    if (!empty($_GET['view'])) {
        $view = intval($_GET['view']);
    } else {
        $view = 0;
    }
    if ($view == 1) {
        $query_images = "AND (file_ext LIKE 'JPG' OR file_ext LIKE 'jpg' OR file_ext LIKE 'JPEG' OR file_ext LIKE 'jpeg' OR file_ext LIKE 'png' OR file_ext LIKE 'PNG' OR file_ext LIKE 'webp' OR file_ext LIKE 'WEBP')";
    } else {
        $query_images = '';
    }


    // Related Logins Query
    $sql_related_logins = mysqli_query($mysqli, "SELECT * FROM logins
         LEFT JOIN login_tags ON login_tags.login_id = logins.login_id
        LEFT JOIN tags ON tags.tag_id = login_tags.tag_id
        WHERE login_asset_id = $asset_id
        AND login_archived_at IS NULL
        GROUP BY logins.login_id
        ORDER BY login_name DESC"
    );
    $login_count = mysqli_num_rows($sql_related_logins);

    // Related Software Query
    $sql_related_software = mysqli_query(
        $mysqli,
        "SELECT * FROM software_assets 
        LEFT JOIN software ON software_assets.software_id = software.software_id 
        WHERE software_assets.asset_id = $asset_id
        AND software_archived_at IS NULL
        ORDER BY software_name DESC"
    );

    $software_count = mysqli_num_rows($sql_related_software);



    ?>

    <div class="row">

        <div class="col-md-3">

            <div class="card">
                <div class="card-header">
                    <button type="button" class="btn btn-light float-right" data-toggle="modal" data-target="#editAssetModal<?php echo $asset_id; ?>">
                        <i class="fas fa-fw fa-edit"></i>
                    </button>
                    <h3 class="text-bold"><i class="fa fa-fw text-secondary fa-<?php echo $device_icon; ?> mr-3"></i><?php echo $asset_name; ?></h3>
                    <?php if ($asset_photo) { ?>
                        <img class="img-fluid img-circle p-3" alt="asset_photo" src="<?php echo "uploads/clients/$client_id/$asset_photo"; ?>">
                    <?php } ?>
                    <?php if ($asset_description) { ?>
                        <div class="text-secondary"><?php echo $asset_description; ?></div>
                    <?php } ?>
                </div>
                <div class="card-body">
                    <?php if ($asset_type) { ?>
                        <div><i class="fa fa-fw fa-tag text-secondary mr-3"></i><?php echo $asset_type; ?></div>
                    <?php }
                    if ($asset_make) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-circle text-secondary mr-3"></i><?php echo "$asset_make $asset_model"; ?></div>
                    <?php }
                    if ($asset_os) { ?>
                        <div class="mt-2"><i class="fab fa-fw fa-windows text-secondary mr-3"></i><?php echo "$asset_os"; ?></div>
                    <?php }
                    if ($asset_serial) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-barcode text-secondary mr-3"></i><?php echo $asset_serial; ?></div>
                    <?php }
                    if ($asset_purchase_date) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-shopping-cart text-secondary mr-3"></i><?php echo date('Y-m-d', strtotime($asset_purchase_date)); ?></div>
                    <?php }
                    if ($asset_install_date) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-calendar-check text-secondary mr-3"></i><?php echo date('Y-m-d', strtotime($asset_install_date)); ?></div>
                    <?php }
                    if ($asset_warranty_expire) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-exclamation-triangle text-secondary mr-3"></i><?php echo date('Y-m-d', strtotime($asset_warranty_expire)); ?></div>
                    <?php } ?>
                </div>
            </div>

            <div class="card card-dark">
                <div class="card-header">
                    <h5 class="card-title">Network</h5>
                </div>
                <div class="card-body">
                    <?php if ($asset_ip) { ?>
                        <div><i class="fa fa-fw fa-globe text-secondary mr-3"></i><?php echo $asset_ip; ?></div>
                    <?php } ?>
                    <?php if ($asset_nat_ip) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-random text-secondary mr-3"></i><?php echo $asset_nat_ip; ?></div>
                    <?php }
                    if ($asset_mac) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-ethernet text-secondary mr-3"></i><?php echo $asset_mac; ?></div>
                    <?php }
                    if ($asset_uri) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-link text-secondary mr-3"></i><a href="<?php echo $asset_uri; ?>" target="_blank">Link</a></div>
                    <?php }
                    if ($asset_uri_2) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-link text-secondary mr-3"></i><a href="<?php echo $asset_uri_2; ?>" target="_blank">Link 2</a></div>
                    <?php } ?>
                </div>
            </div>


            <div class="card card-dark">
                <div class="card-header">
                    <h5 class="card-title">Assignment</h5>
                </div>
                <div class="card-body">
                    <?php if ($location_name) { ?>
                        <div><i class="fa fa-fw fa-map-marker-alt text-secondary mr-3"></i><?php echo $location_name_display; ?></div>
                    <?php }
                    if ($contact_name) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-user text-secondary mr-3"></i><?php echo $contact_name_display; ?></div>
                    <?php }
                    if ($contact_email) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-envelope text-secondary mr-3"></i><a href='mailto:<?php echo $contact_email; ?>'><?php echo $contact_email; ?></a><button class='btn btn-sm clipboardjs' data-clipboard-text='<?php echo $contact_email; ?>'><i class='far fa-copy text-secondary'></i></button></div>
                    <?php }
                    if ($contact_phone) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-phone text-secondary mr-3"></i><?php echo formatPhoneNumber($contact_phone); echo " $contact_extension"; ?></div>
                    <?php }
                    if ($contact_mobile) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-mobile-alt text-secondary mr-3"></i><?php echo formatPhoneNumber($contact_mobile); ?></div>
                    <?php } ?>
                
                </div>
            </div>

            <div class="card card-dark mb-3">
                <div class="card-header">
                    <h5 class="card-title">Notes</h5>
                </div>
                <textarea class="form-control" rows=6 id="assetNotes" placeholder="Enter quick notes here" onblur="updateAssetNotes(<?php echo $asset_id ?>)"><?php echo $asset_notes ?></textarea>    
            </div>

            <?php require_once "modals/client_asset_edit_modal.php"; ?>

        </div>

        <div class="col-md-9">

            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="clients.php">Clients</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>
                </li>
                <li class="breadcrumb-item">
                    <a href="client_assets.php?client_id=<?php echo $client_id; ?>">Assets</a>
                </li>
                <li class="breadcrumb-item active"><?php echo $asset_name; ?></li>
            </ol>

            <div class="btn-group mb-3">
                <div class="dropdown dropleft mr-2">
                    <button type="button" class="btn btn-primary" data-toggle="dropdown"><i class="fas fa-plus mr-2"></i>New</button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#addTicketModal">
                            <i class="fa fa-fw fa-life-ring mr-2"></i>New Ticket
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#addRecurringTicketModal">
                            <i class="fa fa-fw fa-recycle mr-2"></i>New Recurring Ticket
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#createContactNoteModal<?php echo $contact_id; ?>">
                            <i class="fa fa-fw fa-sticky-note mr-2"></i>New Note (WIP)
                        </a>
                    </div>
                </div>

                <div class="dropdown dropleft">
                    <button type="button" class="btn btn-outline-primary" data-toggle="dropdown"><i class="fas fa-link mr-2"></i>Link</button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkAssetModal">
                            <i class="fa fa-fw fa-desktop mr-2"></i>Asset (WIP)
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkSoftwareModal">
                            <i class="fa fa-fw fa-cube mr-2"></i>License (WIP)
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkCredentialModal">
                            <i class="fa fa-fw fa-key mr-2"></i>Credential (WIP)
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkServiceModal">
                            <i class="fa fa-fw fa-stream mr-2"></i>Service (WIP)
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkDocumentModal">
                            <i class="fa fa-fw fa-folder mr-2"></i>Document (WIP)
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkFileModal">
                            <i class="fa fa-fw fa-paperclip mr-2"></i>File (WIP)
                        </a>
                        
                        
                    </div>
                </div>
            </div>

            <div class="card card-dark">
                <div class="card-header py-2">
                    <h3 class="card-title mt-2"><i class="fa fa-fw fa-ethernet mr-2"></i>Network Interfaces</h3>
                    <div class="card-tools">      
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addAssetInterfaceModal"><i class="fas fa-plus mr-2"></i>New Interface</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped table-borderless table-hover">
                            <thead class="<?php if ($interface_count == 0) { echo "d-none"; } ?>">
                            <tr>
                                <th>Name</th>
                                <th>MAC</th>
                                <th>IP</th>
                                <th>Port</th>
                                <th>Connected To</th>
                                <th class="text-center">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_related_interfaces)) {
                                $interface_id = intval($row['interface_id']);
                                $interface_name = nullable_htmlentities($row['interface_name']);
                                $interface_mac = nullable_htmlentities($row['interface_mac']);
                                if ($interface_mac) {
                                    $interface_mac_display = "$interface_mac";
                                } else {
                                    $interface_mac_display = "-";
                                }
                                $interface_ip = nullable_htmlentities($row['interface_ip']);
                                if ($interface_ip) {
                                    $interface_ip_display = "$interface_ip";
                                } else {
                                    $interface_ip_display = "-";
                                }
                                $interface_ipv6 = nullable_htmlentities($row['interface_ipv6']);
                                $interface_port = nullable_htmlentities($row['interface_port']);
                                if ($interface_port) {
                                    $interface_port_display = "$interface_port";
                                } else {
                                    $interface_port_display = "-";
                                }
                                $interface_primary = intval($row['interface_primary']);
                                $network_id = intval($row['network_id']);
                                $network_name = nullable_htmlentities($row['network_name']);
                                if ($network_name) {
                                    $network_name_display = "<i class='fas fa-fw fa-network-wired mr-2'></i>$network_name";
                                } else {
                                    $network_name_display = "-";
                                }
                                $interface_notes = nullable_htmlentities($row['interface_notes']);
                    

                                ?>
                                <tr>
                                    <td>
                                        <i class="fa fa-fw fa-ethernet text-secondary mr-2"></i>
                                        <a class="text-dark" href="#" data-toggle="modal" data-target="#editAssetInterfaceModal<?php echo $interface_id; ?>">
                                            <?php echo $interface_name; ?>
                                        </a>
                                    </td>
                                    <td><?php echo $interface_mac_display; ?></td>
                                    <td><?php echo $interface_ip_display; ?></td>
                                    <td><?php echo $interface_port_display; ?></td>
                                    <td><?php echo $network_name_display; ?></td>
                                    <td>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editAssetInterfaceModal<?php echo $interface_id; ?>">
                                                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                </a>
                                                <?php if ($session_user_role == 3 && $interface_primary == 0) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger text-bold" href="post.php?delete_asset_interface=<?php echo $interface_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                    </a>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <?php

                                require "modals/client_asset_interface_edit_modal.php";

                            }

                            ?>

                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

            <div class="card card-dark <?php if ($login_count == 0) { echo "d-none"; } ?>">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-fw fa-key mr-2"></i>Passwords</h3>
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
                                    $login_tag_name_display_array[] = "<a href='client_logins.php?client_id=$client_id&tags[]=$login_tag_id'><span class='badge text-light p-1 mr-1' style='background-color: $login_tag_color;'><i class='fa fa-fw fa-$login_tag_icon mr-2'></i>$login_tag_name</span></a>";
                                }
                                $login_tags_display = implode('', $login_tag_name_display_array);

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

                                require "modals/client_login_edit_modal.php";

                            }

                            ?>

                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
            
            <div class="card card-dark <?php if ($software_count == 0) { echo "d-none"; } ?>">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-fw fa-cube mr-2"></i>Licenses</h3>
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

            <div class="card card-dark <?php if ($files_count == 0) { echo "d-none"; } ?>">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-fw fa-cube mr-2"></i>Files</h3>
                    <div class="btn-group float-right">
                        <?php
                            if ($view == 0) {
                        ?>
                        <a href="?client_id=<?=$client_id?>&asset_id=<?=$asset_id?>&view=0" class="btn btn-primary"><i class="fas fa-list-ul"></i></a>
                        <a href="?client_id=<?=$client_id?>&asset_id=<?=$asset_id?>&view=1" class="btn btn-outline-secondary"><i class="fas fa-th-large"></i></a>
                        <?php
                            } else {
                        ?>
                        <a href="?client_id=<?=$client_id?>&asset_id=<?=$asset_id?>&view=0" class="btn btn-outline-secondary"><i class="fas fa-list-ul"></i></a>
                        <a href="?client_id=<?=$client_id?>&asset_id=<?=$asset_id?>&view=1" class="btn btn-primary"><i class="fas fa-th-large"></i></a>
                        <?php
                            }
                        ?>

                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped table-borderless table-hover">
                            <thead class="text-dark">
                            <tr>
                                <th>Name</th>
                                <th>Uploaded</th>
                                
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_related_files)) {
                                $file_id = intval($row['file_id']);
                                $file_name = nullable_htmlentities($row['file_name']);
                                $file_description = nullable_htmlentities($row['file_description']);
                                $file_reference_name = nullable_htmlentities($row['file_reference_name']);
                                $file_ext = nullable_htmlentities($row['file_ext']);
                                if ($file_ext == 'pdf') {
                                    $file_icon = "file-pdf";
                                } elseif ($file_ext == 'gz' || $file_ext == 'tar' || $file_ext == 'zip' || $file_ext == '7z' || $file_ext == 'rar') {
                                    $file_icon = "file-archive";
                                } elseif ($file_ext == 'txt' || $file_ext == 'md') {
                                    $file_icon = "file-alt";
                                } elseif ($file_ext == 'msg') {
                                    $file_icon = "envelope";
                                } elseif ($file_ext == 'doc' || $file_ext == 'docx' || $file_ext == 'odt') {
                                    $file_icon = "file-word";
                                } elseif ($file_ext == 'xls' || $file_ext == 'xlsx' || $file_ext == 'ods') {
                                    $file_icon = "file-excel";
                                } elseif ($file_ext == 'pptx' || $file_ext == 'odp') {
                                    $file_icon = "file-powerpoint";
                                } elseif ($file_ext == 'mp3' || $file_ext == 'wav' || $file_ext == 'ogg') {
                                    $file_icon = "file-audio";
                                } elseif ($file_ext == 'mov' || $file_ext == 'mp4' || $file_ext == 'av1') {
                                    $file_icon = "file-video";
                                } elseif ($file_ext == 'jpg' || $file_ext == 'jpeg' || $file_ext == 'png' || $file_ext == 'gif' || $file_ext == 'webp' || $file_ext == 'bmp' || $file_ext == 'tif') {
                                    $file_icon = "file-image";
                                } else {
                                    $file_icon = "file";
                                }
                                $file_created_at = nullable_htmlentities($row['file_created_at']);
                                ?>
                                <tr>
                                    <td><a class="text-dark" href="<?php echo "uploads/clients/$client_id/$file_reference_name"; ?>" target="_blank" ><?php echo "$file_name<br><span class='text-secondary'>$file_description</span>"; ?></a></td>
                                    <td><?php echo $file_created_at; ?></td>
                                </tr>

                                <?php

                            }

                            ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card card-dark <?php if ($recurring_ticket_count == 0) { echo "d-none"; } ?>">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-fw fa-recycle mr-2"></i>Recurring Tickets</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped table-borderless table-hover">
                            <thead class="text-dark">
                            <tr>
                                <th>Subject</th>
                                <th>Priority</th>
                                <th>Frequency</th>
                                <th>Next Run</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_related_recurring_tickets)) {
                                $scheduled_ticket_id = intval($row['scheduled_ticket_id']);
                                $scheduled_ticket_subject = nullable_htmlentities($row['scheduled_ticket_subject']);
                                $scheduled_ticket_priority = nullable_htmlentities($row['scheduled_ticket_priority']);
                                $scheduled_ticket_frequency = nullable_htmlentities($row['scheduled_ticket_frequency']);
                                $scheduled_ticket_next_run = nullable_htmlentities($row['scheduled_ticket_next_run']);
                            ?>

                                <tr>
                                    <td class="text-bold"><a href="#" data-toggle="modal" data-target="#editRecurringTicketModal" onclick="populateRecurringTicketEditModal(<?php echo $client_id, ',', $scheduled_ticket_id ?>)"> <?php echo $scheduled_ticket_subject ?></a></td>

                                    <td><?php echo $scheduled_ticket_priority ?></td>

                                    <td><?php echo $scheduled_ticket_frequency ?></td>

                                    <td><?php echo $scheduled_ticket_next_run ?></td>

                                    <td>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#" data-toggle="modal"
                                                   data-target="#editRecurringTicketModal" onclick="populateRecurringTicketEditModal(<?php echo $client_id, ',', $scheduled_ticket_id ?>)">
                                                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                </a>
                                                <?php
                                                if ($session_user_role == 3) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_recurring_ticket=<?php echo $scheduled_ticket_id; ?>">
                                                    <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                </a>
                                            </div>
                                            <?php } ?>
                                        </div>
                                    </td>
                                </tr>

                            <?php } ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card card-dark <?php if ($ticket_count == 0) { echo "d-none"; } ?>">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-fw fa-life-ring mr-2"></i>Tickets</h3>
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
                                $ticket_status_name = nullable_htmlentities($row['ticket_status_name']);
                                $ticket_status_color = nullable_htmlentities($row['ticket_status_color']);
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
                                    if ($ticket_status == 5) {
                                        $ticket_assigned_to_display = "<p>Not Assigned</p>";
                                    } else {
                                        $ticket_assigned_to_display = "<p class='text-danger'>Not Assigned</p>";
                                    }
                                } else {
                                    $ticket_assigned_to_display = nullable_htmlentities($row['user_name']);
                                }

                                ?>

                                <tr>
                                    <td><a href="ticket.php?client_id=<?php echo $client_id; ?>&ticket_id=<?php echo $ticket_id; ?>"><span class="badge badge-pill badge-secondary p-3"><?php echo "$ticket_prefix$ticket_number"; ?></span></a></td>
                                    <td><a href="ticket.php?client_id=<?php echo $client_id; ?>&ticket_id=<?php echo $ticket_id; ?>"><?php echo $ticket_subject; ?></a></td>
                                    <td><?php echo $ticket_priority_display; ?></td>
                                    <td>
                                        <span class='badge badge-pill text-light p-2' style="background-color: <?php echo $ticket_status_color; ?>"><?php echo $ticket_status_name; ?></span>
                                    </td>
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

    require_once "modals/share_modal.php";


    ?>

<?php } ?>

<script>
    function updateAssetNotes(asset_id) {
        var notes = document.getElementById("assetNotes").value;

        // Send a POST request to ajax.php as ajax.php with data contact_set_notes=true, contact_id=NUM, notes=NOTES
        jQuery.post(
            "ajax.php",
            {
                asset_set_notes: 'TRUE',
                asset_id: asset_id,
                notes: notes
            }
        )
    }
</script>

<!-- JavaScript to Show/Hide Password Form Group -->
<script>
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

<script src="js/recurring_tickets_edit_modal.js"></script>

<?php

require_once "modals/client_asset_interface_add_modal.php";

require_once "modals/ticket_add_modal.php";

require_once "modals/recurring_ticket_add_modal.php";

require_once "modals/recurring_ticket_edit_modal.php";

require_once "includes/footer.php";

