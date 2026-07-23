<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client');

$contact_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM contacts
    LEFT JOIN clients ON client_id = contact_client_id
    LEFT JOIN locations ON location_id = contact_location_id
    LEFT JOIN users ON user_id = contact_user_id
    WHERE contact_id = $contact_id
    LIMIT 1
");

$row = mysqli_fetch_assoc($sql);

$client_id = intval($row['client_id']);
$client_name = escapeHtml($row['client_name']);
$contact_name = escapeHtml($row['contact_name']);
$contact_title = escapeHtml($row['contact_title']);
$contact_department = escapeHtml($row['contact_department']);
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
$location_country = escapeHtml($row['location_country']);
$location_address = escapeHtml($row['location_address']);
$location_city = escapeHtml($row['location_city']);
$location_state = escapeHtml($row['location_state']);
$location_zip = escapeHtml($row['location_zip']);
$location_phone_country_code = escapeHtml($row['location_phone_country_code']);
$location_phone = escapeHtml(formatPhoneNumber($row['location_phone'], $location_phone_country_code));
$location_phone_display = empty($location_phone) ? "-" : $location_phone;

$auth_method = escapeHtml($row['user_auth_method']);
$contact_client_id = intval($row['contact_client_id']);

// Related Assets Query - 1 to 1 relationship
$sql_related_assets = mysqli_query($mysqli, "SELECT * FROM assets
    LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
    LEFT JOIN asset_tags ON asset_tag_asset_id = asset_id
    LEFT JOIN tags ON tag_id = asset_tag_tag_id
    WHERE asset_contact_id = $contact_id
    GROUP BY asset_id
    ORDER BY asset_name ASC"
);
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
        credentials.credential_id AS credentials_credential_id,
        credentials.*,
        credential_tags.*,
        tags.*
    FROM credentials
    LEFT JOIN credential_tags ON credential_tags.credential_id = credentials.credential_id
    LEFT JOIN tags ON tags.tag_id = credential_tags.tag_id
    WHERE credential_contact_id = $contact_id
    GROUP BY credentials.credential_id
    ORDER BY credential_name ASC
");
$credential_count = mysqli_num_rows($sql_related_credentials);

// Related Tickets Query - 1 to 1 relationship
$sql_related_tickets = mysqli_query($mysqli, "SELECT * FROM tickets
    LEFT JOIN users ON ticket_assigned_to = user_id
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    WHERE ticket_contact_id = $contact_id
    ORDER BY ticket_id DESC
");
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
$sql_contact_tags = mysqli_query($mysqli, "SELECT * FROM contact_tags
    LEFT JOIN tags ON contact_tags.tag_id = tags.tag_id
    WHERE contact_id = $contact_id
    ORDER BY tag_name ASC
");
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
    $contact_tag_name_display_array[] = "<a href='client_contacts.php?client_id=$client_id&q=$contact_tag_name'><span class='badge text-light p-1 mr-1' style='background-color: $contact_tag_color;'><i class='fa fa-fw fa-$contact_tag_icon mr-2'></i>$contact_tag_name</span></a>";
}
$contact_tags_display = implode('', $contact_tag_name_display_array);

// Notes - 1 to 1 relationship
$sql_related_notes = mysqli_query($mysqli, "SELECT * FROM contact_notes
    LEFT JOIN users ON contact_note_created_by = user_id
    WHERE contact_note_contact_id = $contact_id
    AND contact_note_archived_at IS NULL
    ORDER BY contact_note_created_at DESC
");
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

// Pick first available tab (Details tab removed)
$first_tab = null;
if ($asset_count) { $first_tab = "assets"; }
elseif ($credential_count) { $first_tab = "credentials"; }
elseif ($software_count) { $first_tab = "licenses"; }
elseif ($ticket_count) { $first_tab = "tickets"; }
elseif ($recurring_ticket_count) { $first_tab = "recurring"; }
elseif ($document_count) { $first_tab = "documents"; }
elseif ($file_count) { $first_tab = "files"; }
elseif ($note_count) { $first_tab = "notes"; }

enforceClientAccess();

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

    <!-- Contact details always visible (top of every tab) -->
    <div class="card card-outline card-secondary mb-3">
        <div class="card-body p-3">
            <div class="row">

                <div class="col-12 col-md-5 mb-3 mb-md-0">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-fw fa-user text-secondary mr-2 mt-1"></i>
                        <div class="w-100">
                            <div class="text-muted text-sm">Contact</div>

                            <?php if ($contact_phone) { ?>
                                <div class="mt-1">
                                    <i class="fas fa-fw fa-phone-alt text-secondary mr-1"></i>
                                    <a href="tel:<?= $contact_phone ?>"><?= $contact_phone ?></a>
                                    <?php if ($contact_extension) { ?>
                                        <span class="text-muted ml-1">ext: <?= $contact_extension ?></span>
                                    <?php } ?>
                                </div>
                            <?php } ?>

                            <?php if ($contact_mobile) { ?>
                                <div class="mt-1">
                                    <i class="fas fa-fw fa-mobile-alt text-secondary mr-1"></i>
                                    <a href="tel:<?= $contact_mobile ?>"><?= $contact_mobile ?></a>
                                </div>
                            <?php } ?>

                            <?php if ($contact_email) { ?>
                                <div class="mt-1">
                                    <i class="fas fa-fw fa-envelope text-secondary mr-1"></i>
                                    <a href="mailto:<?= $contact_email ?>"><?= $contact_email ?></a>
                                    <button type="button" class="btn btn-xs btn-link p-0 ml-1 clipboardjs" data-clipboard-text="<?= $contact_email ?>">
                                        <i class="far fa-copy text-secondary"></i>
                                    </button>
                                </div>
                            <?php } ?>

                            <?php if (!$contact_phone && !$contact_mobile && !$contact_email) { ?>
                                <div class="text-muted">-</div>
                            <?php } ?>

                            <?php if ($contact_tags_display) { ?>
                                <div class="mt-2">
                                    <?= $contact_tags_display ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-4 mb-3 mb-md-0">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-fw fa-map-marker-alt text-secondary mr-2 mt-1"></i>
                        <div class="w-100">
                            <div class="text-muted text-sm">Location</div>
                            <?php if ($location_name) { ?>
                                <div class="font-weight-bold"><?= $location_name ?></div>
                                <div class="text-muted">
                                    <?= $location_address ?><br>
                                    <?= "$location_city $location_state $location_zip" ?><br>
                                    <?= $location_country ?>
                                </div>
                            <?php } else { ?>
                                <div class="text-muted">-</div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-fw fa-info-circle text-secondary mr-2 mt-1"></i>
                        <div class="w-100">
                            <div class="text-muted text-sm">Flags</div>

                            <?php if ($contact_primary) { ?>
                                <div><span class="text-success font-weight-bold">Primary</span></div>
                            <?php } ?>
                            <?php if ($contact_billing) { ?>
                                <div><span class="font-weight-bold text-dark">Billing</span></div>
                            <?php } ?>
                            <?php if ($contact_technical) { ?>
                                <div><span class="text-secondary">Technical</span></div>
                            <?php } ?>
                            <?php if ($contact_important) { ?>
                                <div><span class="font-weight-bold text-dark">Important</span></div>
                            <?php } ?>
                            <?php if ($contact_pin) { ?>
                                <div class="mt-1"><span class="text-muted">PIN:</span> <?= $contact_pin ?></div>
                            <?php } ?>

                            <?php if (!$contact_primary && !$contact_billing && !$contact_technical && !$contact_important && !$contact_pin) { ?>
                                <div class="text-muted">-</div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="row no-gutters">

        <!-- Left sticky nav -->
        <div class="col-12 col-md-3 pr-md-3 mb-3 mb-md-0">
            <div class="sticky-top">
                <div class="nav nav-pills nav-sidebar flex-column" role="tablist" aria-orientation="vertical">

                    <?php if ($asset_count) { ?>
                        <a class="nav-link <?= ($first_tab === "assets") ? "active" : "" ?>"
                           data-toggle="pill"
                           href="#pills-contact-assets<?= $contact_id ?>"
                           role="tab"
                           aria-controls="pills-contact-assets<?= $contact_id ?>"
                           aria-selected="<?= ($first_tab === "assets") ? "true" : "false" ?>">
                            <i class="fas fa-fw fa-desktop mr-2"></i>
                            <span class="d-none d-md-inline">Assets (<?= $asset_count ?>)</span>
                        </a>
                    <?php } ?>

                    <?php
                    if (lookupUserPermission('module_credential') && ($credential_count)) { ?>
                        <a class="nav-link <?= ($first_tab === "credentials") ? "active" : "" ?>"
                           data-toggle="pill"
                           href="#pills-contact-credentials<?= $contact_id ?>"
                           role="tab"
                           aria-controls="pills-contact-credentials<?= $contact_id ?>"
                           aria-selected="<?= ($first_tab === "credentials") ? "true" : "false" ?>">
                            <i class="fas fa-fw fa-key mr-2"></i>
                            <span class="d-none d-md-inline">Credentials (<?= $credential_count ?>)</span>
                        </a>
                    <?php } ?>

                    <?php if ($software_count) { ?>
                        <a class="nav-link <?= ($first_tab === "licenses") ? "active" : "" ?>"
                           data-toggle="pill"
                           href="#pills-contact-licenses<?= $contact_id ?>"
                           role="tab"
                           aria-controls="pills-contact-licenses<?= $contact_id ?>"
                           aria-selected="<?= ($first_tab === "licenses") ? "true" : "false" ?>">
                            <i class="fas fa-fw fa-cube mr-2"></i>
                            <span class="d-none d-md-inline">Licenses (<?= $software_count ?>)</span>
                        </a>
                    <?php } ?>

                    <?php if ($ticket_count) { ?>
                        <a class="nav-link <?= ($first_tab === "tickets") ? "active" : "" ?>"
                           data-toggle="pill"
                           href="#pills-contact-tickets<?= $contact_id ?>"
                           role="tab"
                           aria-controls="pills-contact-tickets<?= $contact_id ?>"
                           aria-selected="<?= ($first_tab === "tickets") ? "true" : "false" ?>">
                            <i class="fas fa-fw fa-life-ring mr-2"></i>
                            <span class="d-none d-md-inline">Tickets (<?= $ticket_count ?>)</span>
                        </a>
                    <?php } ?>

                    <?php if ($recurring_ticket_count) { ?>
                        <a class="nav-link <?= ($first_tab === "recurring") ? "active" : "" ?>"
                           data-toggle="pill"
                           href="#pills-contact-recurring-tickets<?= $contact_id ?>"
                           role="tab"
                           aria-controls="pills-contact-recurring-tickets<?= $contact_id ?>"
                           aria-selected="<?= ($first_tab === "recurring") ? "true" : "false" ?>">
                            <i class="fas fa-fw fa-redo-alt mr-2"></i>
                            <span class="d-none d-md-inline">Rcr Tickets (<?= $recurring_ticket_count ?>)</span>
                        </a>
                    <?php } ?>

                    <?php if ($document_count) { ?>
                        <a class="nav-link <?= ($first_tab === "documents") ? "active" : "" ?>"
                           data-toggle="pill"
                           href="#pills-contact-documents<?= $contact_id ?>"
                           role="tab"
                           aria-controls="pills-contact-documents<?= $contact_id ?>"
                           aria-selected="<?= ($first_tab === "documents") ? "true" : "false" ?>">
                            <i class="fas fa-fw fa-file-alt mr-2"></i>
                            <span class="d-none d-md-inline">Documents (<?= $document_count ?>)</span>
                        </a>
                    <?php } ?>

                    <?php if ($file_count) { ?>
                        <a class="nav-link <?= ($first_tab === "files") ? "active" : "" ?>"
                           data-toggle="pill"
                           href="#pills-contact-files<?= $contact_id ?>"
                           role="tab"
                           aria-controls="pills-contact-files<?= $contact_id ?>"
                           aria-selected="<?= ($first_tab === "files") ? "true" : "false" ?>">
                            <i class="fas fa-fw fa-briefcase mr-2"></i>
                            <span class="d-none d-md-inline">Files (<?= $file_count ?>)</span>
                        </a>
                    <?php } ?>

                    <?php if ($note_count) { ?>
                        <a class="nav-link <?= ($first_tab === "notes") ? "active" : "" ?>"
                           data-toggle="pill"
                           href="#pills-contact-notes<?= $contact_id ?>"
                           role="tab"
                           aria-controls="pills-contact-notes<?= $contact_id ?>"
                           aria-selected="<?= ($first_tab === "notes") ? "true" : "false" ?>">
                            <i class="fas fa-fw fa-edit mr-2"></i>
                            <span class="d-none d-md-inline">Notes (<?= $note_count ?>)</span>
                        </a>
                    <?php } ?>

                </div>
            </div>
        </div>

        <!-- Right content -->
        <div class="col-12 col-md-9">
            <div class="tab-content">

                <?php if ($asset_count) { ?>
                <div class="tab-pane fade <?= ($first_tab === "assets") ? "show active" : "" ?>" id="pills-contact-assets<?= $contact_id ?>">

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
                            while ($row = mysqli_fetch_assoc($sql_related_assets)) {
                                $asset_id = intval($row['asset_id']);
                                $asset_type = escapeHtml($row['asset_type']);
                                $asset_name = escapeHtml($row['asset_name']);
                                $asset_description = escapeHtml($row['asset_description']);
                                $asset_make = escapeHtml($row['asset_make']);
                                $asset_model = escapeHtml($row['asset_model']);
                                $asset_serial = escapeHtml($row['asset_serial']);
                                $asset_serial_display = empty($asset_serial) ? "-" : $asset_serial;

                                $asset_install_date = escapeHtml($row['asset_install_date']);
                                $asset_install_date_display = empty($asset_install_date) ? "-" : $asset_install_date;

                                $asset_status = escapeHtml($row['asset_status']);

                                $device_icon = getAssetIcon($asset_type);

                                // Tags
                                $asset_tag_name_display_array = array();
                                $sql_asset_tags = mysqli_query($mysqli, "SELECT * FROM asset_tags LEFT JOIN tags ON asset_tag_tag_id = tag_id WHERE asset_tag_asset_id = $asset_id ORDER BY tag_name ASC");
                                while ($row2 = mysqli_fetch_assoc($sql_asset_tags)) {
                                    $asset_tag_id = intval($row2['tag_id']);
                                    $asset_tag_name = escapeHtml($row2['tag_name']);
                                    $asset_tag_color = escapeHtml($row2['tag_color']);
                                    if (empty($asset_tag_color)) {
                                        $asset_tag_color = "dark";
                                    }
                                    $asset_tag_icon = escapeHtml($row2['tag_icon']);
                                    if (empty($asset_tag_icon)) {
                                        $asset_tag_icon = "tag";
                                    }

                                    $asset_tag_name_display_array[] = "<a href='assets.php?$client_url tags[]=$asset_tag_id'><span class='badge text-light p-1 mr-1' style='background-color: $asset_tag_color;'><i class='fa fa-fw fa-$asset_tag_icon mr-2'></i>$asset_tag_name</span></a>";
                                }
                                $asset_tags_display = implode('', $asset_tag_name_display_array);
                                $asset_favorite = intval($row['asset_favorite']);

                                ?>
                                <tr>
                                    <th>
                                        <a href="#" class="ajax-modal"
                                           data-modal-size="lg"
                                           data-modal-url="modals/asset/asset.php?id=<?= $asset_id ?>">
                                               <i class="fa fa-fw text-secondary fa-<?= $device_icon ?> mr-2"></i><?= $asset_name ?>
                                            <?php if ($asset_favorite) { echo "<i class='fas fa-fw fa-star text-warning' title='Favorite'></i>"; } ?>
                                        </a>
                                        <div class="mt-0">
                                            <small class="text-muted"><?= $asset_description ?></small>
                                        </div>
                                        <?php if ($asset_tags_display) { ?>
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
                                    <td><?= $asset_serial_display ?></td>
                                    <td><?= $asset_install_date_display ?></td>
                                    <td><?= $asset_status ?></td>
                                </tr>
                                <?php
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>

                </div>
                <?php } ?>

                <?php if (lookupUserPermission('module_credential') && ($credential_count)) { ?>
                <div class="tab-pane fade <?= ($first_tab === "credentials") ? "show active" : "" ?>" id="pills-contact-credentials<?= $contact_id ?>">
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
                            while ($row = mysqli_fetch_assoc($sql_related_credentials)) {
                                $credential_id = intval($row['credentials_credential_id']);
                                $credential_name = escapeHtml($row['credential_name']);
                                $credential_description = escapeHtml($row['credential_description']);

                                $credential_uri = escapeHtml($row['credential_uri']);
                                $credential_uri_display = empty($credential_uri) ? "-" : $credential_uri;

                                $credential_username = escapeHtml(decryptCredentialEntry($row['credential_username']));
                                if (empty($credential_username)) {
                                    $credential_username_display = "-";
                                } else {
                                    $credential_username_display = "$credential_username <button type='button' class='btn btn-sm clipboardjs' data-clipboard-text='$credential_username'><i class='far fa-copy text-secondary'></i></button>";
                                }

                                $credential_otp_secret = escapeHtml($row['credential_otp_secret']);
                                if (empty($credential_otp_secret)) {
                                    $otp_display = "-";
                                } else {
                                    $otp_display = "<span onmouseenter='showOTPViaCredentialID($credential_id)'><i class='far fa-clock'></i> <span id='otp_$credential_id'><i>Hover..</i></span></span>";
                                }
                                ?>
                                <tr>
                                    <td><i class="fa fa-fw fa-key text-secondary mr-2"></i><?= $credential_name ?></td>
                                    <td><?= $credential_description ?></td>
                                    <td><?= $credential_username_display ?></td>
                                    <td>
                                        <button class="btn p-0" type="button" onclick="showPasswordViaCredentialID(this, <?php echo $credential_id; ?>)">
                                            <i class="fas fa-2x fa-ellipsis-h text-secondary"></i><i class="fas fa-2x fa-ellipsis-h text-secondary"></i>
                                        </button>
                                        <button class="btn btn-sm" type="button" onclick="copyPasswordViaCredentialID(this, <?php echo $credential_id; ?>)">
                                            <i class="far fa-copy text-secondary"></i>
                                        </button>
                                    </td>
                                    <td><?= $otp_display ?></td>
                                    <td><?= $credential_uri_display ?></td>
                                </tr>
                                <?php
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Include scripts to fetch TOTP codes and passwords via the credential ID -->
                    <script src="js/credential_show_otp_via_id.js"></script>
                    <script src="js/credential_show_password_via_id.js"></script>
                </div>
                <?php } ?>

                <?php if ($ticket_count) { ?>
                <div class="tab-pane fade <?= ($first_tab === "tickets") ? "show active" : "" ?>" id="pills-contact-tickets<?= $contact_id ?>">
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
                                    $ticket_assigned_to_display = escapeHtml($row['user_name']);
                                }
                                ?>
                                <tr>
                                    <td>
                                        <a href="ticket.php?client_id=<?= $client_id ?>&ticket_id=<?= $ticket_id ?>">
                                            <span class="badge badge-pill badge-secondary p-3"><?= "$ticket_prefix$ticket_number" ?></span>
                                        </a>
                                    </td>
                                    <td><a href="ticket.php?client_id=<?= $client_id ?>&ticket_id=<?= $ticket_id ?>"><?= $ticket_subject ?></a></td>
                                    <td><?= $ticket_priority_display ?></td>
                                    <td><span class="badge badge-pill text-light p-2" style="background-color: <?= $ticket_status_color ?>"><?= $ticket_status_name ?></span></td>
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
                <?php } ?>

                <?php if ($recurring_ticket_count) { ?>
                <div class="tab-pane fade <?= ($first_tab === "recurring") ? "show active" : "" ?>" id="pills-contact-recurring-tickets<?= $contact_id ?>">
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
                            while ($row = mysqli_fetch_assoc($sql_related_recurring_tickets)) {
                                $recurring_ticket_subject = escapeHtml($row['recurring_ticket_subject']);
                                $recurring_ticket_priority = escapeHtml($row['recurring_ticket_priority']);
                                $recurring_ticket_frequency = escapeHtml($row['recurring_ticket_frequency']);
                                $recurring_ticket_next_run = escapeHtml($row['recurring_ticket_next_run']);
                                ?>
                                <tr>
                                    <td class="text-bold"><?= $recurring_ticket_subject ?></td>
                                    <td><?= $recurring_ticket_priority ?></td>
                                    <td><?= $recurring_ticket_frequency ?></td>
                                    <td><?= $recurring_ticket_next_run ?></td>
                                </tr>
                                <?php
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php } ?>

                <?php if ($software_count) { ?>
                <div class="tab-pane fade <?= ($first_tab === "licenses") ? "show active" : "" ?>" id="pills-contact-licenses<?= $contact_id ?>">
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
                            while ($row = mysqli_fetch_assoc($sql_linked_software)) {
                                $software_id = intval($row['software_id']);
                                $software_name = escapeHtml($row['software_name']);
                                $software_version = escapeHtml($row['software_version']);
                                $software_type = escapeHtml($row['software_type']);
                                $software_key = escapeHtml($row['software_key']);
                                $software_seats = escapeHtml($row['software_seats']);

                                $seat_count = 0;

                                // Asset Licenses
                                $asset_licenses_sql = mysqli_query($mysqli, "SELECT asset_id FROM software_assets WHERE software_id = $software_id");
                                while ($row2 = mysqli_fetch_assoc($asset_licenses_sql)) {
                                    $seat_count = $seat_count + 1;
                                }

                                // Contact Licenses
                                $contact_licenses_sql = mysqli_query($mysqli, "SELECT contact_id FROM software_contacts WHERE software_id = $software_id");
                                while ($row2 = mysqli_fetch_assoc($contact_licenses_sql)) {
                                    $seat_count = $seat_count + 1;
                                }

                                $linked_software[] = $software_id;
                                ?>
                                <tr>
                                    <td><?= "$software_name $software_version" ?></td>
                                    <td><?= $software_type ?></td>
                                    <td><?= $software_key ?></td>
                                    <td><?= "$seat_count / $software_seats" ?></td>
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
                <div class="tab-pane fade <?= ($first_tab === "documents") ? "show active" : "" ?>" id="pills-contact-documents<?= $contact_id ?>">
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
                                        <a class="ajax-modal" href="#"
                                           data-modal-size="lg"
                                           data-modal-url="modals/document/document_view.php?id=<?= $document_id ?>">
                                            <?= $document_name ?>
                                        </a>
                                        <div class="text-secondary"><?= $document_description ?></div>
                                    </td>
                                    <td><?= $document_created_by ?></td>
                                    <td><?= $document_created_at ?></td>
                                    <td><?= $document_updated_at ?></td>
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
                <div class="tab-pane fade <?= ($first_tab === "files") ? "show active" : "" ?>" id="pills-contact-files<?= $contact_id ?>">
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
                            while ($row = mysqli_fetch_assoc($sql_linked_files)) {
                                $file_id = intval($row['file_id']);
                                $file_name = escapeHtml($row['file_name']);
                                $file_description = escapeHtml($row['file_description']);
                                $file_size = escapeHtml($row['file_size']);
                                $file_size_human = formatBytes($file_size);
                                $file_mime_type = escapeHtml($row['file_mime_type']);
                                $file_created_at = escapeHtml($row['file_created_at']);

                                $linked_files[] = intval($row['file_id']);
                                ?>
                                <tr>
                                    <td>
                                        <div>
                                            <a href="file.php?file_id=<?= $file_id ?>&action=view" target="_blank"><?= $file_name ?></a>
                                        </div>
                                        <div class="text-secondary"><?= $file_description ?></div>
                                    </td>
                                    <td><?= $file_mime_type ?></td>
                                    <td class="text-monospace"><?= $file_size_human ?></td>
                                    <td><?= $file_created_at ?></td>
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
                <div class="tab-pane fade <?= ($first_tab === "notes") ? "show active" : "" ?>" id="pills-contact-notes<?= $contact_id ?>">
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
                            while ($row = mysqli_fetch_assoc($sql_related_notes)) {
                                $contact_note_type = escapeHtml($row['contact_note_type']);
                                $contact_note = escapeHtml($row['contact_note']);
                                $note_by = escapeHtml($row['user_name']);
                                $contact_note_created_at = escapeHtml($row['contact_note_created_at']);

                                $note_type_icon = isset($note_types_array[$contact_note_type]) ? $note_types_array[$contact_note_type] : 'fa-fw fa-sticky-note';
                                ?>
                                <tr>
                                    <td><i class="fa fa-fw <?= $note_type_icon ?> mr-2"></i><?= $contact_note_type ?></td>
                                    <td><?= $contact_note ?></td>
                                    <td><?= $note_by ?></td>
                                    <td><?= $contact_note_created_at ?></td>
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

    </div>

</div>

<div class="modal-footer">
    <a href="contact_details.php?client_id=<?= $client_id ?>&contact_id=<?= $contact_id ?>" class="btn btn-outline-primary">
        <i class="fas fa-info-circle mr-2"></i>Open Full Contact
    </a>
    <a href="#" class="btn btn-secondary ajax-modal"
       data-modal-url="modals/contact/contact_edit.php?id=<?= $contact_id ?>">
        <i class="fas fa-edit mr-2"></i>Edit
    </a>
</div>

<?php
require_once '../../../includes/modal_footer.php';
