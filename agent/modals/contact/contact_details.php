<?php

require_once '../../../includes/modal_header.php';

$contact_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM contacts 
    LEFT JOIN clients ON client_id = contact_client_id
    LEFT JOIN locations ON location_id = contact_location_id
    LEFT JOIN users ON user_id = contact_user_id
    WHERE contact_id = $contact_id
    LIMIT 1
");

$row = mysqli_fetch_array($sql);
$client_id = intval($row['client_id']);
$client_name = nullable_htmlentities($row['client_name']);
$contact_name = nullable_htmlentities($row['contact_name']);
$contact_title = nullable_htmlentities($row['contact_title']);
$contact_department =nullable_htmlentities($row['contact_department']);
$contact_phone_country_code = nullable_htmlentities($row['contact_phone_country_code']);
$contact_phone = nullable_htmlentities(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
$contact_extension = nullable_htmlentities($row['contact_extension']);
$contact_mobile_country_code = nullable_htmlentities($row['contact_mobile_country_code']);
$contact_mobile = nullable_htmlentities(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));
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
$location_country = nullable_htmlentities($row['location_country']);
$location_address = nullable_htmlentities($row['location_address']);
$location_city = nullable_htmlentities($row['location_city']);
$location_state = nullable_htmlentities($row['location_state']);
$location_zip = nullable_htmlentities($row['location_zip']);
$location_phone_country_code = nullable_htmlentities($row['location_phone_country_code']);
$location_phone = nullable_htmlentities(formatPhoneNumber($row['location_phone'], $location_phone_country_code));
if (empty($location_phone)) {
    $location_phone_display = "-";
} else {
    $location_phone_display = $location_phone;
}
$auth_method = nullable_htmlentities($row['user_auth_method']);
$contact_client_id = intval($row['contact_client_id']);

// Related Assets Query - 1 to 1 relationship
$sql_related_assets = mysqli_query($mysqli, "SELECT * FROM assets LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1 WHERE asset_contact_id = $contact_id ORDER BY asset_name DESC");
$asset_count = mysqli_num_rows($sql_related_assets);

// Linked Software Licenses
$sql_linked_software = mysqli_query($mysqli, "SELECT * FROM software_contacts, software
    WHERE software_contacts.contact_id = $contact_id 
    AND software_contacts.software_id = software.software_id
    AND software_archived_at IS NULL
    ORDER BY software_name ASC"
);
$software_count = mysqli_num_rows($sql_linked_software);

$linked_software = array();

// Related Credentials Query 1 to 1 relationship
$sql_related_credentials = mysqli_query($mysqli, "
    SELECT
        credentials.credential_id AS credentials_credential_id,   -- Alias for credentials.credential_id
        credentials.*,                              -- All other columns from credentials
        credential_tags.*,                          -- All columns from credential_tags
        tags.*                                 -- All columns from tags
    FROM credentials
    LEFT JOIN credential_tags ON credential_tags.credential_id = credentials.credential_id
    LEFT JOIN tags ON tags.tag_id = credential_tags.tag_id
    WHERE credential_contact_id = $contact_id
    GROUP BY credentials.credential_id
    ORDER BY credential_name DESC
");
$credential_count = mysqli_num_rows($sql_related_credentials);

// Related Tickets Query - 1 to 1 relationship
$sql_related_tickets = mysqli_query($mysqli, "SELECT * FROM tickets
    LEFT JOIN users ON ticket_assigned_to = user_id
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    WHERE ticket_contact_id = $contact_id ORDER BY ticket_id DESC");
$ticket_count = mysqli_num_rows($sql_related_tickets);

// Related Recurring Tickets Query
$sql_related_recurring_tickets = mysqli_query($mysqli, "SELECT * FROM recurring_tickets 
    WHERE recurring_ticket_contact_id = $contact_id
    ORDER BY recurring_ticket_next_run DESC"
);
$recurring_ticket_count = mysqli_num_rows($sql_related_recurring_tickets);


// Tags - many to many relationship
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
    $contact_tag_name_display_array[] = "<a href='client_contacts.php?client_id=$client_id&q=$contact_tag_name'><span class='badge text-light p-1 mr-1' style='background-color: $contact_tag_color;'><i class='fa fa-fw fa-$contact_tag_icon mr-2'></i>$contact_tag_name</span></a>";
}
$contact_tags_display = implode('', $contact_tag_name_display_array);

// Notes - 1 to 1 relationship
$sql_related_notes = mysqli_query($mysqli, "SELECT * FROM contact_notes LEFT JOIN users ON contact_note_created_by = user_id WHERE contact_note_contact_id = $contact_id AND contact_note_archived_at IS NULL ORDER BY contact_note_created_at DESC");
$note_count = mysqli_num_rows($sql_related_notes);

 // Linked Services
$sql_linked_services = mysqli_query($mysqli, "SELECT * FROM service_contacts, services
    WHERE service_contacts.contact_id = $contact_id 
    AND service_contacts.service_id = services.service_id
    ORDER BY service_name ASC"
);
$services_count = mysqli_num_rows($sql_linked_services);

$linked_services = array();

// Linked Documents
$sql_linked_documents = mysqli_query($mysqli, "SELECT * FROM contact_documents, documents
    LEFT JOIN users ON document_created_by = user_id
    WHERE contact_documents.contact_id = $contact_id 
    AND contact_documents.document_id = documents.document_id
    AND document_archived_at IS NULL
    ORDER BY document_name ASC"
);
$document_count = mysqli_num_rows($sql_linked_documents);

$linked_documents = array();

// Linked Files
$sql_linked_files = mysqli_query($mysqli, "SELECT * FROM contact_files, files
    WHERE contact_files.contact_id = $contact_id 
    AND contact_files.file_id = files.file_id
    AND file_archived_at IS NULL
    ORDER BY file_name ASC"
);
$file_count = mysqli_num_rows($sql_linked_files);

$linked_files = array();

if (isset($_GET['client_id'])) {
    $client_url = "client_id=$client_id&";
} else {
    $client_url = '';
}

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <div class="media">
            <?php if ($contact_photo) { ?>
                <img class="img-thumbnail img-circle img-size-50 mr-1" src="<?= "../uploads/clients/$client_id/$contact_photo" ?>">
            <?php } else { ?>
                <span class="fa-stack">
                    <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                    <span class="fa fa-stack-1x text-white"><?= $contact_initials ?></span>
                </span>
            <?php } ?>

            <div class="media-body ml-2">
                <strong><?= $contact_name ?></strong>
                <div class="text-sm"><?= $contact_title ?></div>
            </div>
        </div>
    </h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<div class="modal-body">

    <div class="row">

        <?php if ($contact_phone || $contact_mobile || $contact_extension || $contact_email) { ?>
            <div class="col-4">
                <div class="media">
                    <i class="fas fa-fw fa-user fa-2x text-secondary"></i>
                    <div class="media-body ml-2">
                        <dt>Contact Details</dt>
                        <?php if ($contact_phone) { ?>
                        <div>
                            <i class="fas fa-fw fa-phone-alt text-secondary mt-2"></i>
                            <a href="tel:<?= $contact_phone ?>"><?= $contact_phone ?></a>
                            <?php if ($contact_extension) { ?>
                            <span>ext: <?= $contact_extension ?></span>
                            <?php } ?>
                        </div>
                        <?php } ?>
        
                        <?php if ($contact_mobile) { ?>
                        <div>
                            <i class="fas fa-fw fa-mobile-alt text-secondary mt-2"></i>
                            <a href="tel:<?= $contact_mobile ?>"><?= $contact_mobile ?></a>
                        </div>
                        <?php } ?>
                        <?php if ($contact_email) { ?>
                        <div>
                            <i class="fas fa-fw fa-envelope text-secondary mt-2"></i>
                            <a href='mailto:<?= $contact_email ?>'><?= $contact_email ?></a>
                            <button type="button" class='btn btn-sm clipboardjs' data-clipboard-text='<?= $contact_email ?>'>
                            <i class='far fa-copy text-secondary'></i></button>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php if ($location_name) { ?>
            <div class="col-4">
                <div class="media">
                    <i class="fas fa-fw fa-map-marker-alt text-secondary fa-2x"></i>
                    <div class="media-body ml-2">
                        <dt><?= $location_name ?></dt>
                        <dd>   
                            <div><?= $location_address ?></div>
                            <div><?= "$location_city $location_state $location_zip" ?></div>
                            <div><?= $location_country ?></div> 
                        </dd>
                    </div>
                </div>
            </div>
        <?php } ?>

        <div class="col-4">
            <div class="media">
                <i class="fa fa-fw fa-info-circle text-secondary fa-2x"></i>
                <div class="media-body ml-2">
                    <?php if ($contact_primary) { ?>
                        <span class="text-success text-bold">Primary Contact</span><br>
                    <?php } ?>
                    <?php if ($contact_billing) { ?>
                        <span class="text-dark font-weight-bold">Billing</span><br>
                    <?php } ?>
                    <?php if ($contact_technical) { ?>
                        <span class="text-secondary">Technical</span><br>
                    <?php } ?>
                    <?php if ($contact_important) { ?>
                        <span class="text-dark font-weight-bold">Important</span>
                    <?php } ?>
                    <?php if ($contact_pin) { ?>
                        <div>
                            Pin: <?= $contact_pin ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

    </div>

    <ul class="nav nav-pills nav-justified mt-3">
        <?php if ($asset_count) { ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-contact-assets<?= $contact_id ?>"><i class="fas fa-fw fa-desktop fa-2x"></i><br>Assets (<?= $asset_count ?>)</a>
        </li>
        <?php } ?>
        <?php if ($credential_count) { ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-contact-credentials<?= $contact_id ?>"><i class="fas fa-fw fa-key fa-2x"></i><br>Credentials (<?= $credential_count ?>)</a>
        </li>
        <?php } ?>
        <?php if ($software_count) { ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-contact-licenses<?= $contact_id ?>"><i class="fas fa-fw fa-cube fa-2x"></i><br>Licenses (<?= $software_count ?>)</a>
        </li>
        <?php } ?>
        <?php if ($ticket_count) { ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-contact-tickets<?= $contact_id ?>"><i class="fas fa-fw fa-life-ring fa-2x"></i><br>Tickets (<?= $ticket_count ?>)</a>
        </li>
        <?php } ?>
        <?php if ($recurring_ticket_count) { ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-contact-recurring-tickets<?= $contact_id ?>"><i class="fas fa-fw fa-redo-alt fa-2x"></i><br>Rcr Tickets (<?= $recurring_ticket_count ?>)</a>
        </li>
        <?php } ?>
        <?php if ($document_count) { ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-contact-documents<?= $contact_id ?>"><i class="fas fa-fw fa-file-alt fa-2x"></i><br>Documents (<?= $document_count ?>)</a>
        </li>
        <?php } ?>
        <?php if ($file_count) { ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-contact-files<?= $contact_id ?>"><i class="fas fa-fw fa-briefcase fa-2x"></i><br>Files (<?= $file_count ?>)</a>
        </li>
        <?php } ?>
        <?php if ($note_count) { ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-contact-notes<?= $contact_id ?>"><i class="fas fa-fw fa-edit fa-2x"></i><br>Notes (<?= $note_count ?>)</a>
        </li>
        <?php } ?>
    </ul>

    <div class="tab-content">

        <?php if ($asset_count) { ?>
        <div class="tab-pane fade" id="pills-contact-assets<?= $contact_id ?>">

            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover table-sm">
                    <thead>
                    <tr>
                        <th>Name/Description</th>
                        <th>Type</th>
                        <th>Make/Model</th>
                        <th>Serial Number</th>
                        <th>Install Date</th>
                        <th>Status</th>
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
                        $asset_ip = nullable_htmlentities($row['interface_ip']);
                        if (empty($asset_ip)) {
                            $asset_ip_display = "-";
                        } else {
                            $asset_ip_display = "$asset_ip<button class='btn btn-sm' data-clipboard-text='$asset_ip'><i class='far fa-copy text-secondary'></i></button>";
                        }
                        $asset_nat_ip = nullable_htmlentities($row['interface_nat_ip']);
                        $asset_ipv6 = nullable_htmlentities($row['interface_ipv6']);
                        $asset_mac = nullable_htmlentities($row['interface_mac']);
                        $asset_status = nullable_htmlentities($row['asset_status']);
                        $asset_purchase_date = nullable_htmlentities($row['asset_purchase_date']);
                        $asset_warranty_expire = nullable_htmlentities($row['asset_warranty_expire']);
                        $asset_install_date = nullable_htmlentities($row['asset_install_date']);
                        if (empty($asset_install_date)) {
                            $asset_install_date_display = "-";
                        } else {
                            $asset_install_date_display = $asset_install_date;
                        }
                        $asset_uri = nullable_htmlentities($row['asset_uri']);
                        $asset_uri_2 = nullable_htmlentities($row['asset_uri_2']);
                        $asset_photo = nullable_htmlentities($row['asset_photo']);
                        $asset_physical_location = nullable_htmlentities($row['asset_physical_location']);
                        $asset_notes = nullable_htmlentities($row['asset_notes']);
                        $asset_created_at = nullable_htmlentities($row['asset_created_at']);
                        $device_icon = getAssetIcon($asset_type);

                        ?>
                        <tr>
                            <th>
                                <i class="fa fa-fw text-secondary fa-<?php echo $device_icon; ?> mr-2"></i>
                                <a class="text-secondary" href="#"
                                    data-toggle="ajax-modal"
                                    data-modal-size="lg"
                                    data-ajax-url="ajax/ajax_asset_details.php"
                                    data-ajax-id="<?php echo $asset_id; ?>">
                                    <?php echo $asset_name; ?>
                                </a>
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
                        </tr>

                        <?php

                    }

                    ?>

                    </tbody>
                </table>
            </div>
        </div>
        <?php } ?>

        <?php if ($credential_count) { ?>
        <div class="tab-pane fade" id="pills-contact-credentials<?php echo $contact_id; ?>">
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover table-sm dataTables" style="width:100%">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Username</th>
                        <th>Password</th>
                        <th>OTP</th>
                        <th>URI</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql_related_credentials)) {
                        $credential_id = intval($row['credentials_credential_id']);
                        $credential_name = nullable_htmlentities($row['credential_name']);
                        $credential_description = nullable_htmlentities($row['credential_description']);
                        $credential_uri = nullable_htmlentities($row['credential_uri']);
                        if (empty($credential_uri)) {
                            $credential_uri_display = "-";
                        } else {
                            $credential_uri_display = "$credential_uri";
                        }
                        $credential_uri_2 = nullable_htmlentities($row['credential_uri_2']);
                        $credential_username = nullable_htmlentities(decryptCredentialEntry($row['credential_username']));
                        if (empty($credential_username)) {
                            $credential_username_display = "-";
                        } else {
                            $credential_username_display = "$credential_username <button type='button' class='btn btn-sm clipboardjs' data-clipboard-text='$credential_username'><i class='far fa-copy text-secondary'></i></button>";
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
                        $credential_important = intval($row['credential_important']);
                        $credential_contact_id = intval($row['credential_contact_id']);
                        $credential_asset_id = intval($row['credential_asset_id']);

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
                            $credential_tag_name_display_array[] = "<a href='credentials.php?client_id=$client_id&tags[]=$credential_tag_id'><span class='badge text-light p-1 mr-1' style='background-color: $credential_tag_color;'><i class='fa fa-fw fa-$credential_tag_icon mr-2'></i>$credential_tag_name</span></a>";
                        }
                        $credential_tags_display = implode('', $credential_tag_name_display_array);

                        ?>
                        <tr>
                            <td><i class="fa fa-fw fa-key text-secondary mr-2"></i><?php echo $credential_name; ?></td>
                            <td><?php echo $credential_description; ?></td>
                            <td><?php echo $credential_username_display; ?></td>
                            <td>
                                <button class="btn p-0" type="button" data-toggle="popover" data-trigger="focus" data-placement="top" data-content="<?php echo $credential_password; ?>"><i class="fas fa-2x fa-ellipsis-h text-secondary"></i><i class="fas fa-2x fa-ellipsis-h text-secondary"></i></button>

                                <button type="button" class='btn btn-sm clipboardjs' data-clipboard-text='<?php echo $credential_password; ?>'><i class='far fa-copy text-secondary'></i></button>
                            </td>
                            <td><?php echo $otp_display; ?></td>
                            <td><?php echo $credential_uri_display; ?></td>
                        </tr>

                        <?php

                    }

                    ?>

                    </tbody>
                </table>
            </div>
        </div>
        <!-- Include script to get TOTP code via the credential ID -->
        <script src="js/credential_show_otp_via_id.js"></script>
        <?php } ?>

        <?php if ($ticket_count) { ?>
        <div class="tab-pane fade" id="pills-contact-tickets<?php echo $contact_id; ?>">
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover table-sm">
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
                            <td><a href="ticket.php?client_id=<?php echo $client_id; ?>&ticket_id=<?php echo $ticket_id; ?>"><span class="badge badge-pill badge-secondary p-3"><?php echo "$ticket_prefix$ticket_number"; ?></span></a></td>
                            <td><a href="ticket.php?client_id=<?php echo $client_id; ?>&ticket_id=<?php echo $ticket_id; ?>"><?php echo $ticket_subject; ?></a></td>
                            <td><?php echo $ticket_priority_display; ?></td>
                            <td><span class='badge badge-pill text-light p-2' style="background-color: <?php echo $ticket_status_color; ?>"><?php echo $ticket_status_name; ?></span></td>
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
        <?php } ?>

        <?php if ($recurring_ticket_count) { ?>
        <div class="tab-pane fade" id="pills-contact-recurring-tickets<?php echo $contact_id; ?>">

            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover table-sm">
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
                        $recurring_ticket_id = intval($row['recurring_ticket_id']);
                        $recurring_ticket_subject = nullable_htmlentities($row['recurring_ticket_subject']);
                        $recurring_ticket_priority = nullable_htmlentities($row['recurring_ticket_priority']);
                        $recurring_ticket_frequency = nullable_htmlentities($row['recurring_ticket_frequency']);
                        $recurring_ticket_next_run = nullable_htmlentities($row['recurring_ticket_next_run']);
                    ?>

                        <tr>
                            <td class="text-bold"><?php echo $recurring_ticket_subject ?></td>
                            <td><?php echo $recurring_ticket_priority ?></td>
                            <td><?php echo $recurring_ticket_frequency ?></td>
                            <td><?php echo $recurring_ticket_next_run ?></td>
                        </tr>

                    <?php } ?>

                    </tbody>
                </table>
            </div>
        </div>
        <?php } ?>

        <?php if ($software_count) { ?>
        <div class="tab-pane fade" id="pills-contact-licenses<?php echo $contact_id; ?>">
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover table-sm">
                    <thead class="text-dark">
                    <tr>
                        <th>Software</th>
                        <th>Type</th>
                        <th>Key</th>
                        <th>Seats</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql_linked_software)) {
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

                        $linked_software[] = $software_id;

                        ?>
                        <tr>
                            <td><?php echo "$software_name $software_version"; ?></td>
                            <td><?php echo $software_type; ?></td>
                            <td><?php echo $software_key; ?></td>
                            <td><?php echo "$seat_count / $software_seats"; ?></td>
                        </tr>

                        <?php

                    }

                    ?>

                    </tbody>
                </table>
            </div>
        </div>
        <?php } ?>

        <?php if ($document_count) { ?>
        <div class="tab-pane fade" id="pills-contact-documents<?= $contact_id ?>">

            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover table-sm">
                    <thead class="text-dark">
                    <tr>
                        <th>Document Title</th>
                        <th>By</th>
                        <th>Created</th>
                        <th>Updated</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql_linked_documents)) {
                        $document_id = intval($row['document_id']);
                        $document_name = nullable_htmlentities($row['document_name']);
                        $document_description = nullable_htmlentities($row['document_description']);
                        $document_created_by = nullable_htmlentities($row['user_name']);
                        $document_created_at = nullable_htmlentities($row['document_created_at']);
                        $document_updated_at = nullable_htmlentities($row['document_updated_at']);

                        $linked_documents[] = $document_id;

                        ?>

                        <tr>
                            <td>
                                <a class="ajax-modal" href="#"
                                    data-modal-size="lg"
                                    data-modal-url="modals/document/document_view.php?id=<?= $document_id ?>">
                                    <?php echo $document_name; ?>
                                </a>
                                <div class="text-secondary"><?php echo $document_description; ?></div>
                            </td>
                            <td><?php echo $document_created_by; ?></td>
                            <td><?php echo $document_created_at; ?></td>
                            <td><?php echo $document_updated_at; ?></td>
                        </tr>

                        <?php

                    }

                    ?>

                    </tbody>
                </table>
            </div>
        </div>
        <?php } ?>

        <?php if ($file_count) { ?>
        <div class="tab-pane fade" id="pills-contact-files<?php echo $contact_id; ?>">
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover table-sm">
                    <thead class="text-dark">
                    <tr>
                        <th>File Name</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Uploaded</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql_linked_files)) {
                        $file_id = intval($row['file_id']);
                        $file_name = nullable_htmlentities($row['file_name']);
                        $file_description = nullable_htmlentities($row['file_description']);
                        $file_size = nullable_htmlentities($row['file_size']);
                        $file_size_KB = round($file_size / 1024);
                        $file_reference_name = nullable_htmlentities($row['file_reference_name']);
                        $file_mime_type = nullable_htmlentities($row['file_mime_type']);
                        $file_created_at = nullable_htmlentities($row['file_created_at']);

                        $linked_files[] = $file_id;

                        ?>

                        <tr>
                            <td>
                                <div><a href="../uploads/clients/<?php echo $client_id; ?>/<?php echo $file_reference_name; ?>"><?php echo $file_name; ?></a></div>
                                <div class="text-secondary"><?php echo $file_description; ?></div>
                            </td>
                            <td><?php echo $file_mime_type; ?></td>
                            <td><?php echo $file_size_KB; ?> KB</td>
                            <td><?php echo $file_created_at; ?></td>
                        </tr>

                        <?php

                    }

                    ?>

                    </tbody>
                </table>
            </div>
        </div>
        <?php } ?>

        <?php if ($note_count) { ?>
        <div class="tab-pane fade" id="pills-contact-notes<?php echo $contact_id; ?>">
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover table-sm">
                    <thead class="text-dark">
                    <tr>
                        <th>Type</th>
                        <th>Note</th>
                        <th>By</th>
                        <th>Created</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql_related_notes)) {
                        $contact_note_id = intval($row['contact_note_id']);
                        $contact_note_type = nullable_htmlentities($row['contact_note_type']);
                        $contact_note = nullable_htmlentities($row['contact_note']);
                        $note_by = nullable_htmlentities($row['user_name']);
                        $contact_note_created_at = nullable_htmlentities($row['contact_note_created_at']);

                        // Get the corresponding icon for the note type
                        $note_type_icon = isset($note_types_array[$contact_note_type]) ? $note_types_array[$contact_note_type] : 'fa-fw fa-sticky-note'; // default icon if not found

                        ?>

                        <tr>
                            <td><i class="fa fa-fw <?php echo $note_type_icon; ?> mr-2"></i><?php echo $contact_note_type; ?></td>
                            <td><?php echo $contact_note; ?></td>
                            <td><?php echo $note_by; ?></td>
                            <td><?php echo $contact_note_created_at; ?></td>
                        </tr>

                        <?php

                    }

                    ?>

                    </tbody>
                </table>
            </div>
        </div>
        <?php } ?>           

    </div>

</div>

<div class="modal-footer">
    <a href="contact_details.php?client_id=<?= $client_id ?>&contact_id=<?= $contact_id ?>" class="btn btn-outline-primary">
        <i class="fas fa-info-circle mr-2"></i>More Details
    </a>
    <a href="#" class="btn btn-secondary ajax-modal" 
        data-modal-url="modals/contact/contact_edit.php?id=<?= $contact_id ?>">
        <i class="fas fa-edit mr-2"></i>Edit
    </a>
</div>

<?php
require_once '../../../includes/modal_footer.php';
