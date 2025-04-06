<?php

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

if (isset($_GET['contact_id'])) {
    $contact_id = intval($_GET['contact_id']);

    $sql = mysqli_query($mysqli, "SELECT * FROM contacts 
        LEFT JOIN clients ON client_id = contact_client_id
        LEFT JOIN locations ON location_id = contact_location_id
        LEFT JOIN users ON user_id = contact_user_id
        WHERE contact_id = $contact_id
        $client_query
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
    $auth_method = nullable_htmlentities($row['user_auth_method']);
    $contact_client_id = intval($row['contact_client_id']);

    // Override Tab Title // No Sanitizing needed as this var will opnly be used in the tab title
    $page_title = $row['contact_name'];

    // Check to see if Contact belongs to client
    if($contact_client_id !== $client_id) {
        exit();
    }

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
    $service_count = mysqli_num_rows($sql_linked_services);

    $linked_services = array();

    // Linked Documents
    $sql_linked_documents = mysqli_query($mysqli, "SELECT * FROM contact_documents, documents
        LEFT JOIN users ON document_created_by = user_id
        WHERE contact_documents.contact_id = $contact_id 
        AND contact_documents.document_id = documents.document_id
        AND document_template = 0
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

    ?>

    <div class="row">

        <div class="col-md-3">

            <div class="card card-dark">
                <div class="card-body">
                    <button type="button" class="btn btn-default float-right"
                        data-toggle="ajax-modal"
                        data-ajax-url="ajax/ajax_contact_edit.php"
                        data-ajax-id="<?php echo $contact_id; ?>">
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
                    <?php
                    if (!empty($contact_tags_display)) { ?>
                        <div class="mt-1">
                            <?php echo $contact_tags_display; ?>
                        </div>
                    <?php } ?>
                    <hr>
                    <?php if ($location_name) { ?>
                        <div><i class="fa fa-fw fa-map-marker-alt text-secondary mr-2"></i><?php echo $location_name; ?></div>
                    <?php }
                    if ($contact_email) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-envelope text-secondary mr-2"></i><a href='mailto:<?php echo $contact_email; ?>'><?php echo $contact_email; ?></a><button class='btn btn-sm clipboardjs' data-clipboard-text='<?php echo $contact_email; ?>'><i class='far fa-copy text-secondary'></i></button></div>
                    <?php }
                    if ($contact_phone) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-phone text-secondary mr-2"></i><a href="tel:<?php echo "$contact_phone"?>"><?php echo $contact_phone; ?></a></div>
                    <?php }
                    if ($contact_extension) { ?>
                        <div class="ml-4">x<?php echo $contact_extension; ?></div>
                    <?php }
                    if ($contact_mobile) { ?>
                        <div class="mt-l"><i class="fa fa-fw fa-mobile-alt text-secondary mr-2"></i><a href="tel:<?php echo $contact_mobile; ?>"><?php echo $contact_mobile; ?></a></div>
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
                    <a href="contacts.php?client_id=<?php echo $client_id; ?>">Contacts</a>
                </li>
                <li class="breadcrumb-item active"><?php echo $contact_name; ?></li>
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
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#addAssetModal">
                            <i class="fa fa-fw fa-desktop mr-2"></i>New Asset
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#addCredentialModal">
                            <i class="fa fa-fw fa-key mr-2"></i>New Credential
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#addDocumentModal">
                            <i class="fa fa-fw fa-file-alt mr-2"></i>New Document
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#uploadFilesModal">
                            <i class="fa fa-fw fa-upload mr-2"></i>Upload file(s)
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#"
                            data-toggle="ajax-modal"
                            data-ajax-url="ajax/ajax_contact_note_create.php"
                            data-ajax-id="<?php echo $contact_id; ?>">
                            <i class="fas fa-fw fa-sticky-note mr-2"></i>New Note
                        </a>
                    </div>
                </div>

                <div class="dropdown dropleft">
                    <button type="button" class="btn btn-outline-primary" data-toggle="dropdown"><i class="fas fa-link mr-2"></i>Link</button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkAssetModal">
                            <i class="fa fa-fw fa-desktop mr-2"></i>Asset
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkSoftwareModal">
                            <i class="fa fa-fw fa-cube mr-2"></i>License
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkCredentialModal">
                            <i class="fa fa-fw fa-key mr-2"></i>Credential
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkServiceModal">
                            <i class="fa fa-fw fa-stream mr-2"></i>Service
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkDocumentModal">
                            <i class="fa fa-fw fa-folder mr-2"></i>Document
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkFileModal">
                            <i class="fa fa-fw fa-paperclip mr-2"></i>File
                        </a>
                        
                        
                    </div>
                </div>
            </div>

            <div class="card card-dark <?php if ($asset_count == 0) { echo "d-none"; } ?>">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-fw fa-desktop mr-2"></i>Related Assets</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped table-borderless table-hover dataTables" style="width:100%">
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
                                    <td>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#"
                                                    data-toggle="ajax-modal"
                                                    data-ajax-url="ajax/ajax_asset_edit.php"
                                                    data-ajax-id="<?php echo $asset_id; ?>">
                                                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                </a>
                                                <a class="dropdown-item" href="#"
                                                    data-toggle="ajax-modal"
                                                    data-ajax-url="ajax/ajax_asset_copy.php"
                                                    data-ajax-id="<?php echo $asset_id; ?>">
                                                    <i class="fas fa-fw fa-copy mr-2"></i>Copy
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" 
                                                    href="post.php?unlink_asset_from_contact&contact_id=<?php echo $contact_id; ?>&asset_id=<?php echo $asset_id; ?>" 
                                                    class="btn btn-secondary btn-sm" title="Unlink">
                                                    <i class="fas fa-fw fa-unlink mr-2"></i>Unlink
                                                </a>
                                                <?php if ($session_user_role == 3) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger" href="post.php?archive_asset=<?php echo $asset_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                                    </a>
                                                    <a class="dropdown-item text-danger text-bold" href="post.php?delete_asset=<?php echo $asset_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                    </a>
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

                </div>
            </div>

            <div class="card card-dark <?php if ($credential_count == 0) { echo "d-none"; } ?>">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-fw fa-key mr-2"></i>Credentials</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped table-borderless table-hover dataTables" style="width:100%">
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

                            while ($row = mysqli_fetch_array($sql_related_credentials)) {
                                $credential_id = intval($row['credentials_credential_id']);
                                $credential_name = nullable_htmlentities($row['credential_name']);
                                $credential_description = nullable_htmlentities($row['credential_description']);
                                $credential_uri = nullable_htmlentities($row['credential_uri']);
                                if (empty($credential_uri)) {
                                    $credential_uri_display = "-";
                                } else {
                                    $credential_uri_display = "$credential_uri<button class='btn btn-sm clipboardjs' data-clipboard-text='$credential_uri'><i class='far fa-copy text-secondary'></i></button><a href='$credential_uri' target='_blank'><i class='fa fa-external-link-alt text-secondary'></i></a>";
                                }
                                $credential_uri_2 = nullable_htmlentities($row['credential_uri_2']);
                                $credential_username = nullable_htmlentities(decryptCredentialEntry($row['credential_username']));
                                if (empty($credential_username)) {
                                    $credential_username_display = "-";
                                } else {
                                    $credential_username_display = "$credential_username<button class='btn btn-sm clipboardjs' data-clipboard-text='$credential_username'><i class='far fa-copy text-secondary'></i></button>";
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
                                    <td>
                                        <i class="fa fa-fw fa-key text-secondary"></i>
                                        <a class="text-dark" href="#"
                                            data-toggle="ajax-modal"
                                            data-ajax-url="ajax/ajax_credential_edit.php"
                                            data-ajax-id="<?php echo $credential_id; ?>"
                                            >
                                            <?php echo $credential_name; ?>
                                        </a>
                                    </td>
                                    <td><?php echo $credential_description; ?></td>
                                    <td><?php echo $credential_username_display; ?></td>
                                    <td>
                                        <button class="btn p-0" type="button" data-toggle="popover" data-trigger="focus" data-placement="top" data-content="<?php echo $credential_password; ?>"><i class="fas fa-2x fa-ellipsis-h text-secondary"></i><i class="fas fa-2x fa-ellipsis-h text-secondary"></i></button><button class="btn btn-sm clipboardjs" data-clipboard-text="<?php echo $credential_password; ?>"><i class="far fa-copy text-secondary"></i></button>
                                    </td>
                                    <td><?php echo $otp_display; ?></td>
                                    <td><?php echo $credential_uri_display; ?></td>
                                    <td>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#"
                                                    data-toggle="ajax-modal"
                                                    data-ajax-url="ajax/ajax_credential_edit.php"
                                                    data-ajax-id="<?php echo $credential_id; ?>">
                                                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                </a>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#shareModal" onclick="populateShareModal(<?php echo "$client_id, 'Credential', $credential_id"; ?>)">
                                                    <i class="fas fa-fw fa-share-alt mr-2"></i>Share
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" 
                                                    href="post.php?unlink_credential_from_contact&contact_id=<?php echo $contact_id; ?>&credential_id=<?php echo $credential_id; ?>" 
                                                    class="btn btn-secondary btn-sm" title="Unlink">
                                                    <i class="fas fa-fw fa-unlink mr-2"></i>Unlink
                                                </a>
                                                <?php if ($session_user_role == 3) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger text-bold" href="post.php?delete_credential=<?php echo $credential_id; ?>">
                                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                    </a>
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

                </div>
            </div>

            <div class="card card-dark <?php if ($software_count == 0) { echo "d-none"; } ?>">
                <div class="card-header py-2">
                    <h3 class="card-title mt-2"><i class="fa fa-fw fa-cube mr-2"></i>Related Licenses</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#linkSoftwareModal">
                            <i class="fas fa-link mr-2"></i>Link License
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped table-borderless table-hover dataTables" style="width:100%">
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
                                    <td><?php echo $software_license_type; ?></td>
                                    <td><?php echo "$seat_count / $software_seats"; ?></td>
                                    <td class="text-center">
                                        <a href="post.php?unlink_software_from_contact&contact_id=<?php echo $contact_id; ?>&software_id=<?php echo $software_id; ?>" class="btn btn-secondary btn-sm" title="Remove License"><i class="fas fa-fw fa-unlink"></i></a>
                                    </td>
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
                                $recurring_ticket_id = intval($row['recurring_ticket_id']);
                                $recurring_ticket_subject = nullable_htmlentities($row['recurring_ticket_subject']);
                                $recurring_ticket_priority = nullable_htmlentities($row['recurring_ticket_priority']);
                                $recurring_ticket_frequency = nullable_htmlentities($row['recurring_ticket_frequency']);
                                $recurring_ticket_next_run = nullable_htmlentities($row['recurring_ticket_next_run']);
                            ?>

                                <tr>
                                    <td class="text-bold">
                                        <a href="#"
                                            data-toggle="ajax-modal"
                                            data-ajax-url="ajax/ajax_recurring_ticket_edit.php"
                                            data-ajax-id="<?php echo $recurring_ticket_id; ?>"
                                            >
                                            <?php echo $recurring_ticket_subject ?>
                                        </a>
                                    </td>
                                    <td><?php echo $recurring_ticket_priority ?></td>
                                    <td><?php echo $recurring_ticket_frequency ?></td>
                                    <td><?php echo $recurring_ticket_next_run ?></td>
                                    <td>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#"
                                                    data-toggle="ajax-modal"
                                                    data-ajax-url="ajax/ajax_recurring_ticket_edit.php"
                                                    data-ajax-id="<?php echo $recurring_ticket_id; ?>"
                                                    >
                                                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="post.php?force_recurring_ticket=<?php echo $recurring_ticket_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                                    <i class="fa fa-fw fa-paper-plane text-secondary mr-2"></i>Force Reoccur
                                                </a>
                                                <?php
                                                if ($session_user_role == 3) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_recurring_ticket=<?php echo $recurring_ticket_id; ?>">
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
                <div class="card-header py-2">
                    <h3 class="card-title mt-2"><i class="fa fa-fw fa-life-ring mr-2"></i>Related Tickets</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTicketModal">
                            <i class="fas fa-plus mr-2"></i>New Ticket
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped table-borderless table-hover dataTables" style="width:100%">
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
            </div>

            <div class="card card-dark <?php if ($service_count == 0) { echo "d-none"; } ?>">
                <div class="card-header py-2">
                    <h3 class="card-title mt-2"><i class="fa fa-fw fa-stream mr-2"></i>Linked Services</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#linkServiceModal">
                            <i class="fas fa-link mr-2"></i>Link Service
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped table-borderless table-hover dataTables" style="width:100%">
                            <thead class="text-dark">
                            <tr>
                                <th>Service</th>
                                <th>Category</th>
                                <th>Importance</th>
                                <th class="text-center">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_linked_services)) {
                                $service_id = intval($row['service_id']);
                                $service_name = nullable_htmlentities($row['service_name']);
                                $service_description = nullable_htmlentities($row['service_description']);
                                $service_category = nullable_htmlentities($row['service_category']);
                                $service_importance = nullable_htmlentities($row['service_importance']);

                                $linked_services[] = $service_id;

                                ?>

                                <tr>
                                    <td>
                                        <div><?php echo $service_name; ?></div>
                                        <div class="text-secondary"><?php echo $service_description; ?></div>
                                    </td>
                                    <td><?php echo $service_category; ?></td>
                                    <td><?php echo $service_importance; ?></td>
                                    <td class="text-center">
                                        <a href="post.php?unlink_service_from_contact&contact_id=<?php echo $contact_id; ?>&service_id=<?php echo $service_id; ?>" class="btn btn-secondary btn-sm" title="Unlink"><i class="fas fa-fw fa-unlink"></i></a>
                                    </td>
                                </tr>

                                <?php

                            }

                            ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card card-dark <?php if ($document_count == 0) { echo "d-none"; } ?>">
                <div class="card-header py-2">
                    <h3 class="card-title mt-2"><i class="fa fa-fw fa-folder mr-2"></i>Linked Documents</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#linkDocumentModal">
                            <i class="fas fa-link mr-2"></i>Link Document
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped table-borderless table-hover dataTables" style="width:100%">
                            <thead class="text-dark">
                            <tr>
                                <th>Document Title</th>
                                <th>By</th>
                                <th>Created</th>
                                <th>Updated</th>
                                <th class="text-center">Action</th>
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
                                        <div><a href="client_document_details.php?client_id=<?php echo $client_id; ?>&document_id=<?php echo $document_id; ?>"><?php echo $document_name; ?></a></div>
                                        <div class="text-secondary"><?php echo $document_description; ?></div>
                                    </td>
                                    <td><?php echo $document_created_by; ?></td>
                                    <td><?php echo $document_created_at; ?></td>
                                    <td><?php echo $document_updated_at; ?></td>
                                    <td class="text-center">
                                        <a href="#" class="btn btn-dark btn-sm"
                                            data-toggle="ajax-modal"
                                            data-modal-size="lg"
                                            data-ajax-url="ajax/ajax_document_view.php"
                                            data-ajax-id="<?php echo $document_id; ?>"
                                            >
                                            <i class="fas fa-fw fa-eye"></i>
                                        </a>
                                        <a href="post.php?unlink_contact_from_document&contact_id=<?php echo $contact_id; ?>&document_id=<?php echo $document_id; ?>" class="btn btn-secondary btn-sm" title="Unlink"><i class="fas fa-fw fa-unlink"></i></a>
                                    </td>
                                </tr>

                                <?php

                            }

                            ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card card-dark <?php if ($file_count == 0) { echo "d-none"; } ?>">
                <div class="card-header py-2">
                    <h3 class="card-title mt-2"><i class="fa fa-fw fa-folder mr-2"></i>Linked Files</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#linkFileModal">
                            <i class="fas fa-link mr-2"></i>Link File
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped table-borderless table-hover dataTables" style="width:100%">
                            <thead class="text-dark">
                            <tr>
                                <th>File Name</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Uploaded</th>
                                <th class="text-center">Action</th>
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
                                        <div><a href="uploads/clients/<?php echo $client_id; ?>/<?php echo $file_reference_name; ?>"><?php echo $file_name; ?></a></div>
                                        <div class="text-secondary"><?php echo $file_description; ?></div>
                                    </td>
                                    <td><?php echo $file_mime_type; ?></td>
                                    <td><?php echo $file_size_KB; ?> KB</td>
                                    <td><?php echo $file_created_at; ?></td>
                                    <td class="text-center">
                                        <a href="post.php?unlink_contact_from_file&contact_id=<?php echo $contact_id; ?>&file_id=<?php echo $file_id; ?>" class="btn btn-secondary btn-sm" title="Unlink"><i class="fas fa-fw fa-unlink"></i></a>
                                    </td>
                                </tr>

                                <?php

                            }

                            ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
                
            <div class="card card-dark <?php if ($note_count == 0) { echo "d-none"; } ?>">
                <div class="card-header py-2">
                    <h3 class="card-title mt-2"><i class="fa fa-fw fa-sticky-note mr-2"></i>Notes</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary"
                            data-toggle="ajax-modal"
                            data-ajax-url="ajax/ajax_contact_note_create.php"
                            data-ajax-id="<?php echo $contact_id; ?>">
                            <i class="fas fa-plus mr-2"></i>New Note
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped table-borderless table-hover dataTables" style="width:100%">
                            <thead class="text-dark">
                            <tr>
                                <th>Type</th>
                                <th>Note</th>
                                <th>By</th>
                                <th>Created</th>
                                <th class="text-center">Action</th>
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
                                    <td>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item text-danger" href="post.php?archive_contact_note=<?php echo $contact_note_id; ?>">
                                                    <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                                </a>
                                                <?php if ($session_user_role == 3) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger text-bold" href="post.php?delete_contact_note=<?php echo $contact_note_id; ?>">
                                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                    </a>
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
                </div>
            </div>

        </div>

    </div>

    <?php

    require_once "modals/share_modal.php";

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

    <!-- Include script to get TOTP code via the Credential ID -->
    <script src="js/credential_show_otp_via_id.js"></script>

<?php

require_once "modals/ticket_add_modal.php";
require_once "modals/recurring_ticket_add_modal.php";
require_once "modals/asset_add_modal.php";
require_once "modals/credential_add_modal.php";
require_once "modals/client_document_add_modal.php";
require_once "modals/client_file_upload_modal.php";

require_once "modals/contact_link_asset_modal.php";
require_once "modals/contact_link_software_modal.php";
require_once "modals/contact_link_credential_modal.php";
require_once "modals/contact_link_service_modal.php";
require_once "modals/contact_link_document_modal.php";
require_once "modals/contact_link_file_modal.php";

require_once "includes/footer.php";
