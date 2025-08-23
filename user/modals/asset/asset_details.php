<?php

require_once '../../../includes/modal_header_new.php';

$asset_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM assets
    LEFT JOIN clients ON client_id = asset_client_id 
    LEFT JOIN contacts ON asset_contact_id = contact_id 
    LEFT JOIN locations ON asset_location_id = location_id
    LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
    WHERE asset_id = $asset_id
    LIMIT 1
");

$row = mysqli_fetch_array($sql);
$client_id = intval($row['client_id']);
$client_name = nullable_htmlentities($row['client_name']);
$asset_id = intval($row['asset_id']);
$asset_type = nullable_htmlentities($row['asset_type']);
$asset_name = nullable_htmlentities($row['asset_name']);
$asset_description = nullable_htmlentities($row['asset_description']);
$asset_make = nullable_htmlentities($row['asset_make']);
$asset_model = nullable_htmlentities($row['asset_model']);
$asset_serial = nullable_htmlentities($row['asset_serial']);
$asset_os = nullable_htmlentities($row['asset_os']);
$asset_uri = sanitize_url($row['asset_uri']);
$asset_uri_2 = sanitize_url($row['asset_uri_2']);
$asset_uri_client = sanitize_url($row['asset_uri_client']);
$asset_status = nullable_htmlentities($row['asset_status']);
$asset_purchase_reference = nullable_htmlentities($row['asset_purchase_reference']);
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
$contact_extension = nullable_htmlentities($row['contact_extension']);
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

// Network Interfaces
$sql_related_interfaces = mysqli_query($mysqli, "
    SELECT 
        ai.interface_id,
        ai.interface_name,
        ai.interface_description,
        ai.interface_type,
        ai.interface_mac,
        ai.interface_ip,
        ai.interface_nat_ip,
        ai.interface_ipv6,
        ai.interface_primary,
        ai.interface_notes,
        n.network_name,
        n.network_id,
        connected_interfaces.interface_id AS connected_interface_id,
        connected_interfaces.interface_name AS connected_interface_name,
        connected_assets.asset_name AS connected_asset_name,
        connected_assets.asset_id AS connected_asset_id,
        connected_assets.asset_type AS connected_asset_type
    FROM asset_interfaces AS ai
    LEFT JOIN networks AS n
      ON n.network_id = ai.interface_network_id
    LEFT JOIN asset_interface_links AS ail
      ON (ail.interface_a_id = ai.interface_id OR ail.interface_b_id = ai.interface_id)
    LEFT JOIN asset_interfaces AS connected_interfaces
      ON (
          (ail.interface_a_id = ai.interface_id AND ail.interface_b_id = connected_interfaces.interface_id)
          OR
          (ail.interface_b_id = ai.interface_id AND ail.interface_a_id = connected_interfaces.interface_id)
      )
    LEFT JOIN assets AS connected_assets
      ON connected_assets.asset_id = connected_interfaces.interface_asset_id
    WHERE 
        ai.interface_asset_id = $asset_id
        AND ai.interface_archived_at IS NULL
    ORDER BY ai.interface_name ASC
");
$interface_count = mysqli_num_rows($sql_related_interfaces);

// Related Credentials Query
$sql_related_credentials = mysqli_query($mysqli, "
    SELECT 
        credentials.credential_id AS credential_id,
        credentials.credential_name,
        credentials.credential_description,
        credentials.credential_uri,
        credentials.credential_username,
        credentials.credential_password,
        credentials.credential_otp_secret,
        credentials.credential_note,
        credentials.credential_important,
        credentials.credential_contact_id,
        credentials.credential_asset_id
    FROM credentials
    LEFT JOIN credential_tags ON credential_tags.credential_id = credentials.credential_id
    LEFT JOIN tags ON tags.tag_id = credential_tags.tag_id
    WHERE credential_asset_id = $asset_id
      AND credential_archived_at IS NULL
    GROUP BY credentials.credential_id
    ORDER BY credential_name DESC
");
$credential_count = mysqli_num_rows($sql_related_credentials);

// Related Tickets Query
$sql_related_tickets = mysqli_query($mysqli, "
    SELECT tickets.*, users.*, ticket_statuses.*
    FROM tickets
    LEFT JOIN users ON ticket_assigned_to = user_id
    LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
    LEFT JOIN ticket_assets ON tickets.ticket_id = ticket_assets.ticket_id
    WHERE ticket_asset_id = $asset_id OR ticket_assets.asset_id = $asset_id
    GROUP BY tickets.ticket_id
    ORDER BY ticket_number DESC
");
$ticket_count = mysqli_num_rows($sql_related_tickets);

// Related Recurring Tickets Query
$sql_related_recurring_tickets = mysqli_query($mysqli, "SELECT * FROM recurring_tickets 
    LEFT JOIN recurring_ticket_assets ON recurring_tickets.recurring_ticket_id = recurring_ticket_assets.recurring_ticket_id
    WHERE recurring_ticket_asset_id = $asset_id OR recurring_ticket_assets.asset_id = $asset_id
    GROUP BY recurring_tickets.recurring_ticket_id
    ORDER BY recurring_ticket_next_run DESC"
);
$recurring_ticket_count = mysqli_num_rows($sql_related_recurring_tickets);

// Related Documents
$sql_related_documents = mysqli_query($mysqli, "SELECT * FROM asset_documents
    LEFT JOIN documents ON asset_documents.document_id = documents.document_id
    LEFT JOIN users ON user_id = document_created_by
    WHERE asset_documents.asset_id = $asset_id 
    AND document_archived_at IS NULL 
    ORDER BY document_name DESC"
);
$document_count = mysqli_num_rows($sql_related_documents);

// Related Files
$sql_related_files = mysqli_query($mysqli, "SELECT * FROM asset_files 
    LEFT JOIN files ON asset_files.file_id = files.file_id
    WHERE asset_files.asset_id = $asset_id
    AND file_archived_at IS NULL
    ORDER BY file_name DESC"
);
$file_count = mysqli_num_rows($sql_related_files);

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

if (isset($_GET['client_id'])) {
    $client_url = "client_id=$client_id&";
} else {
    $client_url = '';
}

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-<?php echo $device_icon; ?> mr-2"></i><strong><?php echo $asset_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<div class="modal-body">

    <ul class="nav nav-pills nav-justified mb-3">
        <li class="nav-item">
            <a class="nav-link active" data-toggle="pill" href="#pills-asset-details<?php echo $asset_id; ?>"><i class="fas fa-fw fa-<?php echo $device_icon; ?> fa-2x"></i><br>Details</a>
        </li>
        <?php if ($interface_count) { ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-asset-interfaces<?php echo $asset_id; ?>"><i class="fas fa-fw fa-ethernet fa-2x"></i><br>Interfaces (<?php echo $interface_count; ?>)</a>
        </li>
        <?php } ?>
        <?php if ($credential_count) { ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-asset-credentials<?php echo $asset_id; ?>"><i class="fas fa-fw fa-key fa-2x"></i><br>Credentials (<?php echo $credential_count; ?>)</a>
        </li>
        <?php } ?>
        <?php if ($ticket_count) { ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-asset-tickets<?php echo $asset_id; ?>"><i class="fas fa-fw fa-life-ring fa-2x"></i><br>Tickets (<?php echo $ticket_count; ?>)</a>
        </li>
        <?php } ?>
        <?php if ($recurring_ticket_count) { ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-asset-recurring-tickets<?php echo $asset_id; ?>"><i class="fas fa-fw fa-redo-alt fa-2x"></i><br>Recurring Tickets (<?php echo $recurring_ticket_count; ?>)</a>
        </li>
        <?php } ?>
         <?php if ($software_count) { ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-asset-licenses<?php echo $asset_id; ?>"><i class="fas fa-fw fa-cube fa-2x"></i><br>Licenses (<?php echo $software_count; ?>)</a>
        </li>
        <?php } ?>
        <?php if ($document_count) { ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-asset-documents<?php echo $asset_id; ?>"><i class="fas fa-fw fa-file-alt fa-2x"></i><br>Documents (<?php echo $document_count; ?>)</a>
        </li>
        <?php } ?>
        <?php if ($file_count) { ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-asset-files<?php echo $asset_id; ?>"><i class="fas fa-fw fa-briefcase fa-2x"></i><br>Files (<?php echo $file_count; ?>)</a>
        </li>
        <?php } ?>
    </ul>

    <hr>

    <div class="tab-content">

        <div class="tab-pane fade show active" id="pills-asset-details<?php echo $asset_id; ?>">
            <div class="card">
                <div class="card-header">
                    <h3 class="text-bold"><i class="fa fa-fw text-secondary fa-<?php echo $device_icon; ?> mr-3"></i><?php echo $asset_name; ?></h3>
                    <?php if ($asset_photo) { ?>
                        <img class="img-fluid img-circle p-3" alt="asset_photo" src="<?php echo "../uploads/clients/$client_id/$asset_photo"; ?>">
                    <?php } ?>
                    <?php if ($asset_description) { ?>
                        <div class="text-secondary"><?php echo $asset_description; ?></div>
                    <?php } ?>
                </div>
                <div class="card-body">
                    <?php if ($asset_type) { ?>
                        <div><i class="fa fa-fw fa-tag text-secondary mr-2"></i><?php echo $asset_type; ?></div>
                    <?php }
                    if ($asset_make) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-circle text-secondary mr-2"></i><?php echo "$asset_make $asset_model"; ?></div>
                    <?php }
                    if ($asset_os) { ?>
                        <div class="mt-2"><i class="fab fa-fw fa-windows text-secondary mr-2"></i><?php echo "$asset_os"; ?></div>
                    <?php }
                    if ($asset_serial) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-barcode text-secondary mr-2"></i><?php echo $asset_serial; ?></div>
                    <?php }
                    if ($asset_purchase_date) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-shopping-cart text-secondary mr-2"></i><?php echo date('Y-m-d', strtotime($asset_purchase_date)); ?></div>
                    <?php }
                    if ($asset_install_date) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-calendar-check text-secondary mr-2"></i><?php echo date('Y-m-d', strtotime($asset_install_date)); ?></div>
                    <?php }
                    if ($asset_warranty_expire) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-exclamation-triangle text-secondary mr-2"></i><?php echo date('Y-m-d', strtotime($asset_warranty_expire)); ?></div>
                    <?php } ?>
                </div>
            </div>

            <div class="card card-dark">
                <div class="card-header">
                    <h5 class="card-title">Primary Network Interface</h5>
                </div>
                <div class="card-body">
                    <?php if ($asset_ip) { ?>
                        <div><i class="fa fa-fw fa-globe text-secondary mr-2"></i><?php echo $asset_ip; ?></div>
                    <?php } ?>
                    <?php if ($asset_nat_ip) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-random text-secondary mr-2"></i><?php echo $asset_nat_ip; ?></div>
                    <?php }
                    if ($asset_mac) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-ethernet text-secondary mr-2"></i><?php echo $asset_mac; ?></div>
                    <?php }
                    if ($asset_uri) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-link text-secondary mr-2"></i><a href="<?php echo $asset_uri; ?>" target="_blank" title="<?php echo $asset_uri; ?>"><?php echo truncate($asset_uri, 20); ?></a></div>
                    <?php }
                    if ($asset_uri_2) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-link text-secondary mr-2"></i><a href="<?php echo $asset_uri_2; ?>" target="_blank" title="<?php echo $asset_uri_2; ?>"><?php echo truncate($asset_uri_2, 20); ?></a></div>
                    <?php } ?>
                    <?php
                    if ($asset_uri_client) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-link text-secondary mr-2"></i>Client URI: <a href="<?= $asset_uri_client ?>" target="_blank" title="<?= $asset_uri_client ?>"><?= truncate($asset_uri_client, 20); ?></a></div>
                    <?php } ?>
                </div>
            </div>


            <div class="card card-dark">
                <div class="card-header">
                    <h5 class="card-title">Assignment</h5>
                </div>
                <div class="card-body">
                    <?php if ($location_name) { ?>
                        <div><i class="fa fa-fw fa-map-marker-alt text-secondary mr-2"></i><?php echo $location_name_display; ?></div>
                    <?php }
                    if ($contact_name) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-user text-secondary mr-2"></i><?php echo $contact_name_display; ?></div>
                    <?php }
                    if ($contact_email) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-envelope text-secondary mr-2"></i><a href='mailto:<?php echo $contact_email; ?>'><?php echo $contact_email; ?></a><button class='btn btn-sm clipboardjs' data-clipboard-text='<?php echo $contact_email; ?>'><i class='far fa-copy text-secondary'></i></button></div>
                    <?php }
                    if ($contact_phone) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-phone text-secondary mr-2"></i><?php echo formatPhoneNumber($contact_phone); echo " $contact_extension"; ?></div>
                    <?php }
                    if ($contact_mobile) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-mobile-alt text-secondary mr-2"></i><?php echo formatPhoneNumber($contact_mobile); ?></div>
                    <?php } ?>
                
                </div>
            </div>

            <div class="card card-dark mb-3">
                <div class="card-header">
                    <h5 class="card-title">Additional Notes</h5>
                </div>
                <textarea class="form-control" rows=6 id="assetNotes" placeholder="Enter quick notes here" onblur="updateAssetNotes(<?php echo $asset_id ?>)"><?php echo $asset_notes ?></textarea>    
            </div>

        </div>

        <script>
            function updateAssetNotes(asset_id) {
                var notes = document.getElementById("assetNotes").value;

                // Send a POST request to ajax.php as ajax.php with data contact_set_notes=true, contact_id=NUM, notes=NOTES
                jQuery.post(
                    "../ajax.php",
                    {
                        asset_set_notes: 'TRUE',
                        asset_id: asset_id,
                        notes: notes
                    }
                )
            }
        </script>

        <?php if ($interface_count) { ?>
        <div class="tab-pane fade" id="pills-asset-interfaces<?php echo $asset_id; ?>">

            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover table-sm">
                    <thead class="<?php if ($interface_count == 0) { echo "d-none"; } ?>">
                        <tr>
                            <th>Name / Port</th>
                            <th>Type</th>
                            <th>MAC</th>
                            <th>IP</th>
                            <th>Network</th>
                            <th>Connected To</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = mysqli_fetch_array($sql_related_interfaces)) { ?>
                        <?php
                            $interface_id       = intval($row['interface_id']);
                            $interface_name     = nullable_htmlentities($row['interface_name']);
                            $interface_description = nullable_htmlentities($row['interface_description']);
                            $interface_type     = nullable_htmlentities($row['interface_type']);
                            $interface_mac      = nullable_htmlentities($row['interface_mac']);
                            $interface_ip       = nullable_htmlentities($row['interface_ip']);
                            $interface_nat_ip   = nullable_htmlentities($row['interface_nat_ip']);
                            $interface_ipv6     = nullable_htmlentities($row['interface_ipv6']);
                            $interface_primary  = intval($row['interface_primary']);
                            $network_id         = intval($row['network_id']);
                            $network_name       = nullable_htmlentities($row['network_name']);
                            $interface_notes    = nullable_htmlentities($row['interface_notes']);

                            // Prepare display text
                            $interface_mac_display = $interface_mac ?: '-';
                            $interface_ip_display  = $interface_ip ?: '-';
                            $interface_type_display = $interface_type ?: '-';
                            $network_name_display  = $network_name 
                                ? "<i class='fas fa-fw fa-network-wired mr-1'></i>$network_name" 
                                : '-';

                            // Connected interface details
                            $connected_asset_id = intval($row['connected_asset_id']);
                            $connected_asset_name = nullable_htmlentities($row['connected_asset_name']);
                            $connected_asset_type = nullable_htmlentities($row['connected_asset_type']);
                            $connected_asset_icon = getAssetIcon($connected_asset_type);
                            $connected_interface_name = nullable_htmlentities($row['connected_interface_name']);


                            // Show either "-" or "AssetName - Port"
                            if ($connected_asset_name) {
                                $connected_to_display = "<a class='ajax-modal' href='#' data-modal-size='lg'
                                    data-modal-url='modals/asset/asset_details.php?id=$connected_asset_id'>
                                    <strong><i class='fa fa-fw fa-$connected_asset_icon mr-1'></i>$connected_asset_name</strong> - $connected_interface_name
                                    </a>
                                ";
                            } else {
                                $connected_to_display = "-";
                            }
                        ?>
                        <tr>
                            <td>
                                <i class="fa fa-fw fa-ethernet text-secondary mr-1"></i>
                                <?php echo $interface_name; ?> <?php if($interface_primary) { echo "<small class='text-primary'>(Primary)</small>"; } ?>
                            </td>
                            <td><?php echo $interface_type_display; ?></td>
                            <td><?php echo $interface_mac_display; ?></td>
                            <td><?php echo $interface_ip_display; ?></td>
                            <td><?php echo $network_name_display; ?></td>
                            <td><?php echo $connected_to_display; ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php } ?>

        <?php if ($credential_count) { ?>
        <div class="tab-pane fade" id="pills-asset-credentials<?php echo $asset_id; ?>">
            <div class="table-responsive-sm-sm">
                <table class="table table-sm table-striped table-borderless table-hover">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Password</th>
                        <th>OTP</th>
                        <th>URI</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql_related_credentials)) {
                        $credential_id = intval($row['credential_id']);
                        $credential_name = nullable_htmlentities($row['credential_name']);
                        $credential_description = nullable_htmlentities($row['credential_description']);
                        $credential_uri = nullable_htmlentities($row['credential_uri']);
                        if (empty($credential_uri)) {
                            $credential_uri_display = "-";
                        } else {
                            $credential_uri_display = "$credential_uri";
                        }
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
                            <td>
                                <i class="fa fa-fw fa-key text-secondary"></i>
                                <?php echo $credential_name; ?>
                            </td>
                            <td><?php echo $credential_username_display; ?></td>
                            <td>
                                <button class="btn p-0" type="button" data-toggle="popover" data-trigger="focus" data-placement="top" data-content="<?php echo $credential_password; ?>"><i class="fas fa-2x fa-ellipsis-h text-secondary"></i><i class="fas fa-2x fa-ellipsis-h text-secondary"></i></button>
                                <button type='button' class='btn btn-sm clipboardjs' data-clipboard-text='<?php echo $credential_password; ?>'><i class='far fa-copy text-secondary'></i></button>
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
        <!-- Include script to get TOTP code via the credentials ID -->
        <script src="../js/credential_show_otp_via_id.js"></script>
        <?php } ?>

        <?php if ($ticket_count) { ?>
        <div class="tab-pane fade" id="pills-asset-tickets<?php echo $asset_id; ?>">
            <div class="table-responsive-sm">
                <table class="table table-sm table-striped table-borderless table-hover">
                    <thead class="text-dark">
                    <tr>
                        <th>Number</th>
                        <th>Subject</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned</th>
                        <th>Last Response</th>
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
                        $ticket_status_id = intval($row['ticket_status_id']);
                        $ticket_status_name = nullable_htmlentities($row['ticket_status_name']);
                        $ticket_status_color = nullable_htmlentities($row['ticket_status_color']);
                        $ticket_created_at = nullable_htmlentities($row['ticket_created_at']);
                        $ticket_updated_at = nullable_htmlentities($row['ticket_updated_at']);
                        if (empty($ticket_updated_at)) {
                            if ($ticket_status_name == "Closed") {
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
                            if ($ticket_status_id == 5) {
                                $ticket_assigned_to_display = "<p>Not Assigned</p>";
                            } else {
                                $ticket_assigned_to_display = "<p class='text-danger'>Not Assigned</p>";
                            }
                        } else {
                            $ticket_assigned_to_display = nullable_htmlentities($row['user_name']);
                        }

                        ?>

                        <tr>
                            <td>
                                <a href="ticket.php?client_id=<?php echo $client_id; ?>&ticket_id=<?php echo $ticket_id; ?>">
                                    <?php echo "$ticket_prefix$ticket_number"; ?>
                                </a>
                            </td>
                            <td><a href="ticket.php?client_id=<?php echo $client_id; ?>&ticket_id=<?php echo $ticket_id; ?>"><?php echo $ticket_subject; ?></a></td>
                            <td><?php echo $ticket_priority_display; ?></td>
                            <td>
                                <span class='badge badge-pill text-light p-2' style="background-color: <?php echo $ticket_status_color; ?>"><?php echo $ticket_status_name; ?></span>
                            </td>
                            <td><?php echo $ticket_assigned_to_display; ?></td>
                            <td><?php echo $ticket_updated_at_display; ?></td>
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
        <div class="tab-pane fade" id="pills-asset-recurring-tickets<?php echo $asset_id; ?>">

            <div class="table-responsive-sm">
                <table class="table table-sm table-striped table-borderless table-hover">
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
        <div class="tab-pane fade" id="pills-asset-licenses<?php echo $asset_id; ?>">
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover">
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
                            <td><?php echo "$software_name<br><span class='text-secondary'>$software_version</span>"; ?></td>
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
        <div class="tab-pane fade" id="pills-asset-documents<?php echo $asset_id; ?>">

            <div class="table-responsive-sm">
                <table class="table table-sm table-striped table-borderless table-hover">
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

                    while ($row = mysqli_fetch_array($sql_related_documents)) {
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
        <div class="tab-pane fade" id="pills-asset-files<?php echo $asset_id; ?>">
            <div class="table-responsive-sm">
                <table class="table table-sm table-striped table-borderless table-hover">
                    <thead class="text-dark">
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Uploaded</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql_related_files)) {
                        $file_id = intval($row['file_id']);
                        $file_name = nullable_htmlentities($row['file_name']);
                        $file_mime_type = nullable_htmlentities($row['file_mime_type']);
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
                            <td><a class="text-dark" href="<?php echo "../uploads/clients/$client_id/$file_reference_name"; ?>" target="_blank" ><?php echo "$file_name<br><span class='text-secondary'>$file_description</span>"; ?></a></td>
                            <td><?php echo $file_mime_type; ?></td>
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

    </div>

</div>

<div class="modal-footer">
    <a href="asset_details.php?client_id=<?php echo $client_id; ?>&asset_id=<?php echo $asset_id; ?>"
        class="btn btn-primary text-bold"><span class="text-white"><i class="fas fa-info-circle mr-2"></i>More Details</span>
    </a>
    <a href="#" class="btn btn-secondary ajax-modal" data-modal-url="modals/asset/asset_edit.php?id=<?= $asset_id ?>">
        <span class="text-white"><i class="fas fa-edit mr-2"></i>Edit</span>
    </a>
    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer_new.php';
