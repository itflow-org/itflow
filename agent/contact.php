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

    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, contact_billing, contact_client_id, contact_created_at,
        contact_department, contact_email, contact_extension, contact_important,
        contact_location_id, contact_mobile, contact_mobile_country_code, contact_name,
        contact_notes, contact_phone, contact_phone_country_code, contact_photo, contact_pin,
        contact_primary, contact_technical, contact_title, location_name, user_auth_method FROM contacts
        LEFT JOIN clients ON client_id = contact_client_id
        LEFT JOIN locations ON location_id = contact_location_id
        LEFT JOIN users ON user_id = contact_user_id
        WHERE contact_id = $contact_id
        $client_query
        LIMIT 1
    ");

    if (mysqli_num_rows($sql) == 0) {
        echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='javascript:history.back()'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";
        require_once "../includes/footer.php";
        exit();
    }

    $row = mysqli_fetch_assoc($sql);
    $client_id = intval($row['client_id']);
    $client_name = escapeHtml($row['client_name']);
    $contact_name = escapeHtml($row['contact_name']);
    $contact_title = escapeHtml($row['contact_title']);
    $contact_department =escapeHtml($row['contact_department']);
    $contact_phone_country_code = escapeHtml($row['contact_phone_country_code']);
    $contact_phone = escapeHtml(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
    $contact_extension = escapeHtml($row['contact_extension']);
    $contact_mobile_country_code = escapeHtml($row['contact_mobile_country_code']);
    $contact_mobile = escapeHtml(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));
    $contact_email = escapeHtml($row['contact_email']);
    $contact_photo = escapeHtml($row['contact_photo']);
    $contact_pin = escapeHtml($row['contact_pin']);
    $contact_initials = initials($contact_name);
    $contact_notes = escapeHtml($row['contact_notes']);
    $contact_primary = intval($row['contact_primary']);
    $contact_important = intval($row['contact_important']);
    $contact_billing = intval($row['contact_billing']);
    $contact_technical = intval($row['contact_technical']);
    $contact_created_at = escapeHtml($row['contact_created_at']);
    $contact_location_id = intval($row['contact_location_id']);
    $location_name = escapeHtml($row['location_name']);
    $auth_method = escapeHtml($row['user_auth_method']);
    $contact_client_id = intval($row['contact_client_id']);

    // Override Tab Title // No Sanitizing needed as this var will opnly be used in the tab title
    $page_title = $row['contact_name'];

    // Check to see if Contact belongs to client
    if($contact_client_id !== $client_id) {
        exit();
    }

    // Related Assets Query - 1 to 1 relationship
    $sql_related_assets = mysqli_query($mysqli, "SELECT asset_created_at, asset_description, asset_favorite, asset_id, asset_install_date,
        asset_make, asset_model, asset_name, asset_notes, asset_os, asset_photo,
        asset_physical_location, asset_purchase_date, asset_serial, asset_status, asset_type,
        asset_uri, asset_uri_2, asset_warranty_expire, interface_ip, interface_ipv6, interface_mac,
        interface_nat_ip, tag_color, tag_icon, tag_id, tag_name FROM assets
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
        LEFT JOIN asset_tags ON asset_tag_asset_id = asset_id
        LEFT JOIN tags ON tag_id = asset_tag_tag_id
        WHERE asset_contact_id = $contact_id
        GROUP BY asset_id
        ORDER BY asset_name ASC"
    );
    $asset_count = mysqli_num_rows($sql_related_assets);

    // Linked Software Licenses
    $sql_linked_software = mysqli_query($mysqli, "SELECT software_expire, software_contacts.software_id, software_key, software_license_type,
        software_name, software_notes, software_purchase, software_seats, software_type,
        software_version FROM software_contacts, software
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
        ORDER BY credential_name ASC
    ");
    $credential_count = mysqli_num_rows($sql_related_credentials);

    // Related Tickets Query - 1 to 1 relationship
    $sql_related_tickets = mysqli_query($mysqli, "SELECT ticket_assigned_to, ticket_closed_at, ticket_created_at, ticket_id, ticket_number,
        ticket_prefix, ticket_priority, ticket_status, ticket_status_color, ticket_status_name,
        ticket_subject, ticket_updated_at, user_name FROM tickets
        LEFT JOIN users ON ticket_assigned_to = user_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE ticket_contact_id = $contact_id ORDER BY ticket_id DESC");
    $ticket_count = mysqli_num_rows($sql_related_tickets);

    // Related Recurring Tickets Query
    $sql_related_recurring_tickets = mysqli_query($mysqli, "SELECT recurring_ticket_frequency, recurring_ticket_id, recurring_ticket_next_run,
        recurring_ticket_priority, recurring_ticket_subject FROM recurring_tickets
        WHERE recurring_ticket_contact_id = $contact_id
        ORDER BY recurring_ticket_next_run DESC"
    );
    $recurring_ticket_count = mysqli_num_rows($sql_related_recurring_tickets);


    // Tags - many to many relationship
    $contact_tag_name_display_array = array();
    $contact_tag_id_array = array();
    $sql_contact_tags = mysqli_query($mysqli, "SELECT tag_color, tag_icon, contact_tags.tag_id, tag_name FROM contact_tags LEFT JOIN tags ON contact_tags.tag_id = tags.tag_id WHERE contact_id = $contact_id ORDER BY tag_name ASC");
    while ($row = mysqli_fetch_assoc($sql_contact_tags)) {

        $contact_tag_id = intval($row['tag_id']);
        $contact_tag_name = escapeHtml($row['tag_name']);
        $contact_tag_color = escapeHtml($row['tag_color']);
        if (empty($contact_tag_color)) {
            $contact_tag_color = "dark";
        }
        $contact_tag_icon = escapeHtml($row['tag_icon']);
        if (empty($contact_tag_icon)) {
            $contact_tag_icon = "tag";
        }

        $contact_tag_id_array[] = $contact_tag_id;
        $contact_tag_name_display_array[] = "<a href='client_contacts.php?client_id=$client_id&q=$contact_tag_name'><span class='badge text-light p-1 me-1' style='background-color: $contact_tag_color;'><i class='fa fa-fw fa-$contact_tag_icon me-2'></i>$contact_tag_name</span></a>";
    }
    $contact_tags_display = implode('', $contact_tag_name_display_array);

    // Notes - 1 to 1 relationship
    $sql_related_notes = mysqli_query($mysqli, "SELECT contact_note, contact_note_created_at, contact_note_id, contact_note_type, user_name FROM contact_notes LEFT JOIN users ON contact_note_created_by = user_id WHERE contact_note_contact_id = $contact_id AND contact_note_archived_at IS NULL ORDER BY contact_note_created_at DESC");
    $note_count = mysqli_num_rows($sql_related_notes);

    // Note type icons, read from the categories list so the seeded icons
    // stay the single source of truth
    $note_type_icons = array();
    $sql_note_type_icons = mysqli_query($mysqli, "SELECT category_name, category_icon FROM categories WHERE category_type = 'contact_note_type'");
    while ($row = mysqli_fetch_assoc($sql_note_type_icons)) {
        $note_type_icons[escapeHtml($row['category_name'])] = escapeHtml($row['category_icon']);
    }

     // Linked Services
    $sql_linked_services = mysqli_query($mysqli, "SELECT service_category, service_description, service_contacts.service_id, service_importance,
        service_name FROM service_contacts, services
        WHERE service_contacts.contact_id = $contact_id
        AND service_contacts.service_id = services.service_id
        ORDER BY service_name ASC"
    );
    $service_count = mysqli_num_rows($sql_linked_services);

    $linked_services = array();

    // Linked Documents
    $sql_linked_documents = mysqli_query($mysqli, "SELECT document_created_at, document_description, documents.document_id, document_name,
        document_updated_at, user_name FROM contact_documents, documents
        LEFT JOIN users ON document_created_by = user_id
        WHERE contact_documents.contact_id = $contact_id
        AND contact_documents.document_id = documents.document_id
        AND document_archived_at IS NULL
        ORDER BY document_name ASC"
    );
    $document_count = mysqli_num_rows($sql_linked_documents);

    $linked_documents = array();

    // Linked Files
    $sql_linked_files = mysqli_query($mysqli, "SELECT file_created_at, file_description, files.file_id, file_mime_type, file_name, file_size FROM contact_files, files
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
                    <button type="button" class="btn btn-default float-end ajax-modal"
                        data-modal-url="modals/contact/contact_edit.php?id=<?= $contact_id ?>">
                        <i class="fas fa-fw fa-user-edit"></i>
                    </button>
                    <h3 class="text-bold"><?= $contact_name ?></h3>
                    <?php if ($contact_title) { ?>
                        <div class="text-secondary"><?= $contact_title ?></div>
                    <?php } ?>

                    <div class="text-center">
                        <?php if ($contact_photo) { ?>
                            <img class="img-fluid img-circle p-3" alt="contact_photo" src="<?= "../uploads/clients/$client_id/$contact_photo" ?>">
                        <?php } else { ?>
                            <span class="fa-stack fa-4x">
                                <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                                <span class="fa fa-stack-1x text-white"><?= $contact_initials ?></span>
                            </span>
                        <?php } ?>
                    </div>
                    <?php
                    if (!empty($contact_tags_display)) { ?>
                        <div class="mt-1">
                            <?= $contact_tags_display ?>
                        </div>
                    <?php } ?>
                    <hr>
                    <?php if ($location_name) { ?>
                        <div><i class="fa fa-fw fa-map-marker-alt text-secondary me-2"></i><?= $location_name ?></div>
                    <?php }
                    if ($contact_email) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-envelope text-secondary me-2"></i><a href='mailto:<?= $contact_email ?>'><?= $contact_email ?></a><button class='btn btn-sm clipboardjs' data-clipboard-text='<?= $contact_email ?>'><i class='far fa-copy text-secondary'></i></button></div>
                    <?php }
                    if ($contact_phone) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-phone text-secondary me-2"></i><a href="tel:<?= "$contact_phone" ?>"><?= $contact_phone ?></a></div>
                    <?php }
                    if ($contact_extension) { ?>
                        <div class="ms-4">x<?= $contact_extension ?></div>
                    <?php }
                    if ($contact_mobile) { ?>
                        <div class="mt-l"><i class="fa fa-fw fa-mobile-alt text-secondary me-2"></i><a href="tel:<?= $contact_mobile ?>"><?= $contact_mobile ?></a></div>
                    <?php }
                    if ($contact_pin) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-key text-secondary me-2"></i><?= $contact_pin ?></div>
                    <?php }
                    if ($contact_primary) { ?>
                        <div class="mt-2 text-success"><i class="fa fa-fw fa-check me-2"></i>Primary Contact</div>
                    <?php }
                    if ($contact_important) { ?>
                        <div class="mt-2 text-dark text-bold"><i class="fa fa-fw fa-check me-2"></i>Important</div>
                    <?php }
                    if ($contact_technical) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-check text-secondary me-2"></i>Technical</div>
                    <?php }
                    if ($contact_billing) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-check text-secondary me-2"></i>Billing</div>
                    <?php } ?>
                    <div class="mt-2"><i class="fa fa-fw fa-clock text-secondary me-2"></i><?= date('Y-m-d', strtotime($contact_created_at)) ?></div>

                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title">Notes</h5>
                </div>
                <textarea class="form-control" rows=6 id="contactNotes" placeholder="Notes, eg Personal tidbits to spark convo, temperment, etc" onblur="updateContactNotes(<?= $contact_id ?>)"><?= $contact_notes ?></textarea>
            </div>

        </div>

        <div class="col-md-9">

            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="client_overview.php?client_id=<?= $client_id ?>"><?= $client_name ?></a>
                </li>
                <li class="breadcrumb-item">
                    <a href="contacts.php?client_id=<?= $client_id ?>">Contacts</a>
                </li>
                <li class="breadcrumb-item active"><?= $contact_name ?></li>
            </ol>

            <div class="btn-group mb-3">
                <div class="dropdown dropstart me-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="dropdown"><i class="fas fa-plus me-2"></i>New</button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="modals/ticket/ticket_add.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>" data-modal-size="lg">
                            <i class="fa fa-fw fa-life-ring me-2"></i>New Ticket
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="modals/recurring_ticket/recurring_ticket_add.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>" data-modal-size="lg">
                            <i class="fa fa-fw fa-recycle me-2"></i>New Recurring Ticket
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="modals/asset/asset_add.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>">
                            <i class="fa fa-fw fa-desktop me-2"></i>New Asset
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="modals/credential/credential_add.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>">
                            <i class="fa fa-fw fa-key me-2"></i>New Credential
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="modals/document/document_add.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>" data-modal-size="lg">
                            <i class="fa fa-fw fa-file-alt me-2"></i>New Document
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="modals/file/file_upload.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>">
                            <i class="fa fa-fw fa-upload me-2"></i>Upload file(s)
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="modals/contact/contact_note_add.php?id=<?= $contact_id ?>">
                            <i class="fas fa-fw fa-sticky-note me-2"></i>New Note
                        </a>
                    </div>
                </div>

                <div class="dropdown dropstart">
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="dropdown"><i class="fas fa-link me-2"></i>Link</button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="modals/contact/contact_link_asset.php?id=<?= $contact_id ?>">
                            <i class="fa fa-fw fa-desktop me-2"></i>Asset
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="modals/contact/contact_link_software.php?id=<?= $contact_id ?>">
                            <i class="fa fa-fw fa-cube me-2"></i>License
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="modals/contact/contact_link_credential.php?id=<?= $contact_id ?>">
                            <i class="fa fa-fw fa-key me-2"></i>Credential
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="modals/contact/contact_link_service.php?id=<?= $contact_id ?>">
                            <i class="fa fa-fw fa-stream me-2"></i>Service
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="modals/contact/contact_link_document.php?id=<?= $contact_id ?>">
                            <i class="fa fa-fw fa-folder me-2"></i>Document
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="modals/contact/contact_link_file.php?id=<?= $contact_id ?>">
                            <i class="fa fa-fw fa-paperclip me-2"></i>File
                        </a>
                    </div>
                </div>
            </div>

            <div class="card card-dark <?php if ($asset_count == 0) { echo "d-none"; } ?>">
                <div class="card-header py-2">
                    <h3 class="card-title mt-2"><i class="fa fa-fw fa-desktop me-2"></i>Related Assets</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary ajax-modal"
                            data-modal-url="modals/contact/contact_link_asset.php?id=<?= $contact_id ?>">
                            <i class="fas fa-link me-2"></i>Link Asset
                        </button>
                    </div>
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

                            while ($row = mysqli_fetch_assoc($sql_related_assets)) {
                                $asset_id = intval($row['asset_id']);
                                $asset_type = escapeHtml($row['asset_type']);
                                $asset_name = escapeHtml($row['asset_name']);
                                $asset_description = escapeHtml($row['asset_description']);
                                $asset_make = escapeHtml($row['asset_make']);
                                $asset_model = escapeHtml($row['asset_model']);
                                $asset_serial = escapeHtml($row['asset_serial']);
                                if ($asset_serial) {
                                    $asset_serial_display = $asset_serial;
                                } else {
                                     $asset_serial_display = "-";
                                }
                                $asset_os = escapeHtml($row['asset_os']);
                                if (empty($asset_os)) {
                                    $asset_os_display = "-";
                                } else {
                                    $asset_os_display = $asset_os;
                                }
                                $asset_ip = escapeHtml($row['interface_ip']);
                                if (empty($asset_ip)) {
                                    $asset_ip_display = "-";
                                } else {
                                    $asset_ip_display = "$asset_ip<button class='btn btn-sm' data-clipboard-text='$asset_ip'><i class='far fa-copy text-secondary'></i></button>";
                                }
                                $asset_nat_ip = escapeHtml($row['interface_nat_ip']);
                                $asset_ipv6 = escapeHtml($row['interface_ipv6']);
                                $asset_mac = escapeHtml($row['interface_mac']);
                                $asset_status = escapeHtml($row['asset_status']);
                                $asset_purchase_date = escapeHtml($row['asset_purchase_date']);
                                $asset_warranty_expire = escapeHtml($row['asset_warranty_expire']);
                                $asset_install_date = escapeHtml($row['asset_install_date']);
                                if (empty($asset_install_date)) {
                                    $asset_install_date_display = "-";
                                } else {
                                    $asset_install_date_display = $asset_install_date;
                                }
                                $asset_uri = escapeHtml($row['asset_uri']);
                                $asset_uri_2 = escapeHtml($row['asset_uri_2']);
                                $asset_photo = escapeHtml($row['asset_photo']);
                                $asset_physical_location = escapeHtml($row['asset_physical_location']);
                                $asset_notes = escapeHtml($row['asset_notes']);
                                $asset_favorite = intval($row['asset_favorite']);
                                $asset_created_at = escapeHtml($row['asset_created_at']);
                                $device_icon = getAssetIcon($asset_type);

                                // Tags
                                $asset_tag_name_display_array = array();
                                $asset_tag_id_array = array();
                                $sql_asset_tags = mysqli_query($mysqli, "SELECT tag_color, tag_icon, tag_id, tag_name FROM asset_tags LEFT JOIN tags ON asset_tag_tag_id = tag_id WHERE asset_tag_asset_id = $asset_id ORDER BY tag_name ASC");
                                while ($row = mysqli_fetch_assoc($sql_asset_tags)) {

                                    $asset_tag_id = intval($row['tag_id']);
                                    $asset_tag_name = escapeHtml($row['tag_name']);
                                    $asset_tag_color = escapeHtml($row['tag_color']);
                                    if (empty($asset_tag_color)) {
                                        $asset_tag_color = "dark";
                                    }
                                    $asset_tag_icon = escapeHtml($row['tag_icon']);
                                    if (empty($asset_tag_icon)) {
                                        $asset_tag_icon = "tag";
                                    }

                                    $asset_tag_id_array[] = $asset_tag_id;
                                    $asset_tag_name_display_array[] = "<a href='assets.php?$client_url tags[]=$asset_tag_id'><span class='badge text-light p-1 me-1' style='background-color: $asset_tag_color;'><i class='fa fa-fw fa-$asset_tag_icon me-2'></i>$asset_tag_name</span></a>";
                                }
                                $asset_tags_display = implode('', $asset_tag_name_display_array);

                                ?>
                                <tr>
                                    <th>
                                        <i class="fa fa-fw text-secondary fa-<?= $device_icon ?> me-1"></i>
                                        <a class="text-secondary ajax-modal" href="#"
                                            data-modal-size="lg"
                                            data-modal-url="modals/asset/asset.php?id=<?= $asset_id ?>">
                                            <?= $asset_name ?>
                                            <?php if ($asset_favorite) { echo "<i class='fas fa-fw fa-star text-warning' title='Favorite'></i>"; } ?>
                                        </a>
                                        <div class="mt-0">
                                            <small class="text-muted"><?= $asset_description ?></small>
                                        </div>
                                        <?php
                                        if ($asset_tags_display) { ?>
                                            <div class="mt-1">
                                                <?= $asset_tags_display ?>
                                            </div>
                                        <?php } ?>
                                    </th>
                                    <td><?= $asset_type ?></td>
                                    <td>
                                        <?= $asset_make ?>
                                        <div class="mt-0">
                                            <small class="text-muted"><?= $asset_model ?></small>
                                        </div>
                                    </td>
                                    <td class="font-monospace"><?= $asset_serial_display ?></td>

                                    <td><?= $asset_install_date_display ?></td>
                                    <td><?= $asset_status ?></td>
                                    <td>
                                        <div class="dropdown dropstart text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item ajax-modal" href="#"
                                                    data-modal-url="modals/asset/asset_edit.php?id=<?= $asset_id ?>">
                                                    <i class="fas fa-fw fa-edit me-2"></i>Edit
                                                </a>
                                                <a class="dropdown-item ajax-modal" href="#"
                                                    data-modal-url="modals/asset/asset_copy.php?id=<?= $asset_id ?>">
                                                    <i class="fas fa-fw fa-copy me-2"></i>Copy
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item"
                                                    href="post.php?unlink_asset_from_contact&contact_id=<?= $contact_id ?>&asset_id=<?= $asset_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                    class="btn btn-secondary btn-sm" title="Unlink">
                                                    <i class="fas fa-fw fa-unlink me-2"></i>Unlink
                                                </a>
                                                <?php if ($session_user_role == 3) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger" href="post.php?archive_asset=<?= $asset_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                                    </a>
                                                    <a class="dropdown-item text-danger text-bold" href="post.php?delete_asset=<?=  $asset_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
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

            <?php if (lookupUserPermission('module_credential')) { // Begin Credential Enforcement ?>

            <div class="card card-dark <?php if ($credential_count == 0) { echo "d-none"; } ?>">
                <div class="card-header py-2">
                    <h3 class="card-title mt-2"><i class="fa fa-fw fa-key me-2"></i>Credentials</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/contact/contact_link_credential.php?id=<?= $contact_id ?>">
                            <i class="fas fa-link me-2"></i>Link Credential
                        </button>
                    </div>
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

                            while ($row = mysqli_fetch_assoc($sql_related_credentials)) {
                                $credential_id = intval($row['credentials_credential_id']);
                                $credential_name = escapeHtml($row['credential_name']);
                                $credential_description = escapeHtml($row['credential_description']);
                                $credential_uri = escapeHtml($row['credential_uri']);
                                if (empty($credential_uri)) {
                                    $credential_uri_display = "-";
                                } else {
                                    $credential_uri_display = "$credential_uri<button class='btn btn-sm clipboardjs' data-clipboard-text='$credential_uri'><i class='far fa-copy text-secondary'></i></button><a href='$credential_uri' target='_blank'><i class='fa fa-external-link-alt text-secondary'></i></a>";
                                }
                                $credential_uri_2 = escapeHtml($row['credential_uri_2']);
                                $credential_username = escapeHtml(decryptCredentialEntry($row['credential_username']));
                                if (empty($credential_username)) {
                                    $credential_username_display = "-";
                                } else {
                                    $credential_username_display = "$credential_username<button class='btn btn-sm clipboardjs' data-clipboard-text='$credential_username'><i class='far fa-copy text-secondary'></i></button>";
                                }
                                $credential_otp_secret = escapeHtml($row['credential_otp_secret']);
                                if (empty($credential_otp_secret)) {
                                    $otp_display = "-";
                                } else {
                                    $otp_display = "<span onmouseenter='showOTPViaCredentialID($credential_id)'><i class='far fa-clock'></i> <span id='otp_$credential_id'><i>Hover..</i></span></span>";
                                }
                                $credential_note = escapeHtml($row['credential_note']);
                                $credential_favorite = intval($row['credential_favorite']);
                                $credential_contact_id = intval($row['credential_contact_id']);
                                $credential_asset_id = intval($row['credential_asset_id']);

                                // Tags
                                $credential_tag_name_display_array = array();
                                $credential_tag_id_array = array();
                                $sql_credential_tags = mysqli_query($mysqli, "SELECT tag_color, tag_icon, credential_tags.tag_id, tag_name FROM credential_tags LEFT JOIN tags ON credential_tags.tag_id = tags.tag_id WHERE credential_id = $credential_id ORDER BY tag_name ASC");
                                while ($row = mysqli_fetch_assoc($sql_credential_tags)) {

                                    $credential_tag_id = intval($row['tag_id']);
                                    $credential_tag_name = escapeHtml($row['tag_name']);
                                    $credential_tag_color = escapeHtml($row['tag_color']);
                                    if (empty($credential_tag_color)) {
                                        $credential_tag_color = "dark";
                                    }
                                    $credential_tag_icon = escapeHtml($row['tag_icon']);
                                    if (empty($credential_tag_icon)) {
                                        $credential_tag_icon = "tag";
                                    }

                                    $credential_tag_id_array[] = $credential_tag_id;
                                    $credential_tag_name_display_array[] = "<a href='credentials.php?client_id=$client_id&tags[]=$credential_tag_id'><span class='badge text-light p-1 me-1' style='background-color: $credential_tag_color;'><i class='fa fa-fw fa-$credential_tag_icon me-2'></i>$credential_tag_name</span></a>";
                                }
                                $credential_tags_display = implode('', $credential_tag_name_display_array);

                                ?>
                                <tr>
                                    <td>
                                        <i class="fa fa-fw fa-key text-secondary"></i>
                                        <a class="text-dark ajax-modal" href="#"
                                            data-modal-url="modals/credential/credential_edit.php?id=<?= $credential_id ?>">
                                            <?= $credential_name ?>
                                        </a>
                                    </td>
                                    <td><?= $credential_description ?></td>
                                    <td><?= $credential_username_display ?></td>
                                    <td>
                                        <button class="btn p-0" type="button" onclick="showPasswordViaCredentialID(this, <?= $credential_id ?>)"><i class="fas fa-2x fa-ellipsis-h text-secondary"></i><i class="fas fa-2x fa-ellipsis-h text-secondary"></i></button><button class="btn btn-sm" type="button" onclick="copyPasswordViaCredentialID(this, <?= $credential_id ?>)"><i class="far fa-copy text-secondary"></i></button>
                                    </td>
                                    <td><?= $otp_display ?></td>
                                    <td><?= $credential_uri_display ?></td>
                                    <td>
                                        <div class="dropdown dropstart text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item ajax-modal" href="#"
                                                    data-modal-url="modals/credential/credential_edit.php?id=<?= $credential_id ?>">
                                                    <i class="fas fa-fw fa-edit me-2"></i>Edit
                                                </a>
                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#shareModal" onclick="populateShareModal(<?= "$client_id, 'Credential', $credential_id" ?>)">
                                                    <i class="fas fa-fw fa-share-alt me-2"></i>Share
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item"
                                                    href="post.php?unlink_credential_from_contact&contact_id=<?= $contact_id ?>&credential_id=<?= $credential_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                    class="btn btn-secondary btn-sm" title="Unlink">
                                                    <i class="fas fa-fw fa-unlink me-2"></i>Unlink
                                                </a>
                                                <?php if ($session_user_role == 3) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger text-bold" href="post.php?delete_credential=<?= $credential_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
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

            <?php } // End Credential Enforcement ?>

            <div class="card card-dark <?php if ($software_count == 0) { echo "d-none"; } ?>">
                <div class="card-header py-2">
                    <h3 class="card-title mt-2"><i class="fa fa-fw fa-cube me-2"></i>Related Licenses</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/contact/contact_link_software.php?id=<?= $contact_id ?>">
                            <i class="fas fa-link me-2"></i>Link License
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

                            while ($row = mysqli_fetch_assoc($sql_linked_software)) {
                                $software_id = intval($row['software_id']);
                                $software_name = escapeHtml($row['software_name']);
                                $software_version = escapeHtml($row['software_version']);
                                $software_type = escapeHtml($row['software_type']);
                                $software_license_type = escapeHtml($row['software_license_type']);
                                $software_key = escapeHtml($row['software_key']);
                                $software_seats = escapeHtml($row['software_seats']);
                                $software_purchase = escapeHtml($row['software_purchase']);
                                $software_expire = escapeHtml($row['software_expire']);
                                $software_notes = escapeHtml($row['software_notes']);

                                $seat_count = 0;

                                // Asset Licenses
                                $asset_licenses_sql = mysqli_query($mysqli, "SELECT asset_id FROM software_assets WHERE software_id = $software_id");
                                $asset_licenses_array = array();
                                while ($row = mysqli_fetch_assoc($asset_licenses_sql)) {
                                    $asset_licenses_array[] = intval($row['asset_id']);
                                    $seat_count = $seat_count + 1;
                                }
                                $asset_licenses = implode(',', $asset_licenses_array);

                                // Contact Licenses
                                $contact_licenses_sql = mysqli_query($mysqli, "SELECT contact_id FROM software_contacts WHERE software_id = $software_id");
                                $contact_licenses_array = array();
                                while ($row = mysqli_fetch_assoc($contact_licenses_sql)) {
                                    $contact_licenses_array[] = intval($row['contact_id']);
                                    $seat_count = $seat_count + 1;
                                }
                                $contact_licenses = implode(',', $contact_licenses_array);

                                $linked_software[] = $software_id;

                                ?>
                                <tr>
                                    <td><?= "$software_name $software_version" ?></td>
                                    <td><?= $software_type ?></td>
                                    <td><?= $software_license_type ?></td>
                                    <td><?= "$seat_count / $software_seats" ?></td>
                                    <td class="text-center">
                                        <a href="post.php?unlink_software_from_contact&contact_id=<?= $contact_id ?>&software_id=<?= $software_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-secondary btn-sm" title="Remove License"><i class="fas fa-fw fa-unlink"></i></a>
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
                    <h3 class="card-title"><i class="fa fa-fw fa-recycle me-2"></i>Recurring Tickets</h3>
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

                            while ($row = mysqli_fetch_assoc($sql_related_recurring_tickets)) {
                                $recurring_ticket_id = intval($row['recurring_ticket_id']);
                                $recurring_ticket_subject = escapeHtml($row['recurring_ticket_subject']);
                                $recurring_ticket_priority = escapeHtml($row['recurring_ticket_priority']);
                                $recurring_ticket_frequency = escapeHtml($row['recurring_ticket_frequency']);
                                $recurring_ticket_next_run = escapeHtml($row['recurring_ticket_next_run']);
                            ?>

                                <tr>
                                    <td class="text-bold">
                                        <a class="ajax-modal" href="#"
                                            data-modal-url="modals/recurring_ticket/recurring_ticket_edit.php?id=<?= $recurring_ticket_id ?>">
                                            <?= $recurring_ticket_subject ?>
                                        </a>
                                    </td>
                                    <td><?= $recurring_ticket_priority ?></td>
                                    <td><?= $recurring_ticket_frequency ?></td>
                                    <td><?= $recurring_ticket_next_run ?></td>
                                    <td>
                                        <div class="dropdown dropstart text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item ajax-modal" href="#"
                                                    data-modal-url="modals/recurring_ticket/recurring_ticket_edit.php?id=<?= $recurring_ticket_id ?>">
                                                    <i class="fas fa-fw fa-edit me-2"></i>Edit
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="post.php?force_recurring_ticket=<?= $recurring_ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                    <i class="fa fa-fw fa-paper-plane text-secondary me-2"></i>Force Reoccur
                                                </a>
                                                <?php
                                                if ($session_user_role == 3) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_recurring_ticket=<?= $recurring_ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
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
                    <h3 class="card-title mt-2"><i class="fa fa-fw fa-life-ring me-2"></i>Related Tickets</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary ajax-modal"
                            data-modal-url="modals/ticket/ticket_add.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>"
                            data-modal-size="lg">
                            <i class="fas fa-plus me-2"></i>New Ticket
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

                            while ($row = mysqli_fetch_assoc($sql_related_tickets)) {
                                $ticket_id = intval($row['ticket_id']);
                                $ticket_prefix = escapeHtml($row['ticket_prefix']);
                                $ticket_number = intval($row['ticket_number']);
                                $ticket_subject = escapeHtml($row['ticket_subject']);
                                $ticket_priority = escapeHtml($row['ticket_priority']);
                                $ticket_status = escapeHtml($row['ticket_status']);
                                $ticket_status_name = escapeHtml($row['ticket_status_name']);
                                $ticket_status_color = escapeHtml($row['ticket_status_color']);
                                $ticket_created_at = escapeHtml($row['ticket_created_at']);
                                $ticket_updated_at = escapeHtml($row['ticket_updated_at']);
                                if (empty($ticket_updated_at)) {
                                    if ($ticket_status == "Closed") {
                                        $ticket_updated_at_display = "<p>Never</p>";
                                    } else {
                                        $ticket_updated_at_display = "<p class='text-danger'>Never</p>";
                                    }
                                } else {
                                    $ticket_updated_at_display = $ticket_updated_at;
                                }
                                $ticket_closed_at = escapeHtml($row['ticket_closed_at']);

                                if ($ticket_priority == "Urgent") {
                                    $ticket_priority_display = "<span class='p-2 badge bg-dark'>$ticket_priority</span>";
                                } elseif ($ticket_priority == "High") {
                                    $ticket_priority_display = "<span class='p-2 badge bg-danger'>$ticket_priority</span>";
                                } elseif ($ticket_priority == "Medium") {
                                    $ticket_priority_display = "<span class='p-2 badge bg-warning text-dark'>$ticket_priority</span>";
                                } elseif ($ticket_priority == "Low") {
                                    $ticket_priority_display = "<span class='p-2 badge bg-info text-dark'>$ticket_priority</span>";
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
                                    $ticket_assigned_to_display = escapeHtml($row['user_name']);
                                }

                                ?>

                                <tr>
                                    <td><a href="ticket.php?client_id=<?= $client_id ?>&ticket_id=<?= $ticket_id ?>"><span class="badge rounded-pill bg-secondary p-3"><?= "$ticket_prefix$ticket_number" ?></span></a></td>
                                    <td><a href="ticket.php?client_id=<?= $client_id ?>&ticket_id=<?= $ticket_id ?>"><?= $ticket_subject ?></a></td>
                                    <td><?= $ticket_priority_display ?></td>
                                    <td><span class='badge rounded-pill text-light p-2' style="background-color: <?= $ticket_status_color ?>"><?= $ticket_status_name ?></span></td>
                                    <td><?= $ticket_assigned_to_display ?></td>
                                    <td><?= $ticket_updated_at_display ?></td>
                                    <td><?= $ticket_created_at ?></td>
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
                    <h3 class="card-title mt-2"><i class="fa fa-fw fa-stream me-2"></i>Linked Services</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/contact/contact_link_service.php?id=<?= $contact_id ?>">
                            <i class="fas fa-link me-2"></i>Link Service
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

                            while ($row = mysqli_fetch_assoc($sql_linked_services)) {
                                $service_id = intval($row['service_id']);
                                $service_name = escapeHtml($row['service_name']);
                                $service_description = escapeHtml($row['service_description']);
                                $service_category = escapeHtml($row['service_category']);
                                $service_importance = escapeHtml($row['service_importance']);

                                $linked_services[] = $service_id;

                                ?>

                                <tr>
                                    <td>
                                        <div><?= $service_name ?></div>
                                        <div class="text-secondary"><?= $service_description ?></div>
                                    </td>
                                    <td><?= $service_category ?></td>
                                    <td><?= $service_importance ?></td>
                                    <td class="text-center">
                                        <a href="post.php?unlink_service_from_contact&contact_id=<?= $contact_id ?>&service_id=<?= $service_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-secondary btn-sm" title="Unlink"><i class="fas fa-fw fa-unlink"></i></a>
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
                    <h3 class="card-title mt-2"><i class="fa fa-fw fa-folder me-2"></i>Linked Documents</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/contact/contact_link_document.php?id=<?= $contact_id ?>">
                            <i class="fas fa-link me-2"></i>Link Document
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

                            while ($row = mysqli_fetch_assoc($sql_linked_documents)) {
                                $document_id = intval($row['document_id']);
                                $document_name = escapeHtml($row['document_name']);
                                $document_description = escapeHtml($row['document_description']);
                                $document_created_by = escapeHtml($row['user_name']);
                                $document_created_at = escapeHtml($row['document_created_at']);
                                $document_updated_at = escapeHtml($row['document_updated_at']);

                                $linked_documents[] = $document_id;

                                ?>

                                <tr>
                                    <td>
                                        <div><a href="document.php?client_id=<?= $client_id ?>&document_id=<?= $document_id ?>"><?= $document_name ?></a></div>
                                        <div class="text-secondary"><?= $document_description ?></div>
                                    </td>
                                    <td><?= $document_created_by ?></td>
                                    <td><?= $document_created_at ?></td>
                                    <td><?= $document_updated_at ?></td>
                                    <td class="text-center">
                                        <a href="#" class="btn btn-dark btn-sm ajax-modal"
                                            data-modal-size="lg"
                                            data-modal-url="modals/document/document_view.php?id=<?= $document_id ?>">
                                            <i class="fas fa-fw fa-eye"></i>
                                        </a>
                                        <a href="post.php?unlink_contact_from_document&contact_id=<?= $contact_id ?>&document_id=<?= $document_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-secondary btn-sm" title="Unlink"><i class="fas fa-fw fa-unlink"></i></a>
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
                    <h3 class="card-title mt-2"><i class="fa fa-fw fa-folder me-2"></i>Linked Files</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/contact/contact_link_file.php?id=<?= $contact_id ?>">
                            <i class="fas fa-link me-2"></i>Link File
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

                            while ($row = mysqli_fetch_assoc($sql_linked_files)) {
                                $file_id = intval($row['file_id']);
                                $file_name = escapeHtml($row['file_name']);
                                $file_description = escapeHtml($row['file_description']);
                                $file_size = escapeHtml($row['file_size']);
                                $file_size_human = formatBytes($file_size);
                                $file_mime_type = escapeHtml($row['file_mime_type']);
                                $file_created_at = escapeHtml($row['file_created_at']);

                                $linked_files[] = $file_id;

                                ?>

                                <tr>
                                    <td>
                                        <div><a href="file.php?file_id=<?= $file_id; ?>&action=view" target="_blank"><?= $file_name; ?></a></div>
                                        <div class="text-secondary"><?= $file_description ?></div>
                                    </td>
                                    <td><?= $file_mime_type ?></td>
                                    <td class="font-monospace"><?= $file_size_human ?></td>
                                    <td><?= $file_created_at ?></td>
                                    <td class="text-center">
                                        <a href="post.php?unlink_contact_from_file&contact_id=<?= $contact_id ?>&file_id=<?= $file_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-secondary btn-sm" title="Unlink"><i class="fas fa-fw fa-unlink"></i></a>
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
                    <h3 class="card-title mt-2"><i class="fa fa-fw fa-sticky-note me-2"></i>Notes</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary ajax-modal"
                            data-modal-url="modals/contact/contact_note_add.php?id=<?= $contact_id ?>">
                            <i class="fas fa-plus me-2"></i>New Note
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

                            while ($row = mysqli_fetch_assoc($sql_related_notes)) {
                                $contact_note_id = intval($row['contact_note_id']);
                                $contact_note_type = escapeHtml($row['contact_note_type']);
                                $contact_note = nl2br(escapeHtml($row['contact_note']));
                                $note_by = escapeHtml($row['user_name']);
                                $contact_note_created_at = escapeHtml($row['contact_note_created_at']);

                                // Get the corresponding icon for the note type
                                $note_type_icon = !empty($note_type_icons[$contact_note_type]) ? $note_type_icons[$contact_note_type] : 'fa-sticky-note';

                                ?>

                                <tr>
                                    <td><i class="fa fa-fw <?= $note_type_icon ?> me-2"></i><?= $contact_note_type ?></td>
                                    <td><?= $contact_note ?></td>
                                    <td><?= $note_by ?></td>
                                    <td><?= $contact_note_created_at ?></td>
                                    <td>
                                        <div class="dropdown dropstart text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item text-danger" href="post.php?archive_contact_note=<?= $contact_note_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                    <i class="fas fa-fw fa-archive me-2"></i>Archive
                                                </a>
                                                <?php if ($session_user_role == 3) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger text-bold" href="post.php?delete_contact_note=<?= $contact_note_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
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
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>',
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

    <!-- Include scripts to fetch TOTP codes and passwords via the credential ID -->
    <script src="js/credential_show_otp_via_id.js"></script>
    <script src="js/credential_show_password_via_id.js"></script>

<?php
require_once "../includes/footer.php";
