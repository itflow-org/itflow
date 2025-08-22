<?php

require_once '../../includes/modal_header.php';

$service_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM services WHERE service_id = $service_id LIMIT 1");
$row = mysqli_fetch_array($sql);
$service_name = nullable_htmlentities($row['service_name']);
$service_description = nullable_htmlentities($row['service_description']);
$service_category = nullable_htmlentities($row['service_category']);
$service_importance = nullable_htmlentities($row['service_importance']);
$service_backup = nullable_htmlentities($row['service_backup']);
$service_notes = nullable_htmlentities($row['service_notes']);
$service_created_at = nullable_htmlentities($row['service_created_at']);
$service_updated_at = nullable_htmlentities($row['service_updated_at']);
$service_review_due = nullable_htmlentities($row['service_review_due']);
$client_id = intval($row['service_client_id']);

// Service Importance
if ($service_importance == "High") {
    $service_importance_display = "<span class='p-2 badge badge-danger'>$service_importance</span>";
} elseif ($service_importance == "Medium") {
    $service_importance_display = "<span class='p-2 badge badge-warning'>$service_importance</span>";
} elseif ($service_importance == "Low") {
    $service_importance_display = "<span class='p-2 badge badge-info'>$service_importance</span>";
} else {
    $service_importance_display = "-";
}

// Associated Assets (and their credentials/networks/locations)
$sql_assets = mysqli_query(
    $mysqli,
    "SELECT * FROM service_assets
    LEFT JOIN assets ON service_assets.asset_id = assets.asset_id
    LEFT JOIN asset_interfaces ON interface_asset_id = assets.asset_id AND interface_primary = 1
    LEFT JOIN credentials ON service_assets.asset_id = credentials.credential_asset_id
    LEFT JOIN networks ON interface_network_id = networks.network_id
    LEFT JOIN locations ON assets.asset_location_id = locations.location_id
    WHERE service_id = $service_id"
);

// Associated credentials
$sql_credentials = mysqli_query(
    $mysqli,
    "SELECT * FROM service_credentials
    LEFT JOIN credentials ON service_credentials.credential_id = credentials.credential_id
    WHERE service_id = $service_id"
);

// Associated Domains
$sql_domains = mysqli_query(
    $mysqli,
    "SELECT * FROM service_domains
    LEFT JOIN domains ON service_domains.domain_id = domains.domain_id
    WHERE service_id = $service_id"
);

// Associated Certificates
$sql_certificates = mysqli_query(
    $mysqli,
    "SELECT * FROM service_certificates
    LEFT JOIN certificates ON service_certificates.certificate_id = certificates.certificate_id
    WHERE service_id = $service_id"
);

// Associated Vendors
$sql_vendors = mysqli_query(
    $mysqli,
    "SELECT * FROM service_vendors
    LEFT JOIN vendors ON service_vendors.vendor_id = vendors.vendor_id
    WHERE service_id = $service_id"
);

// Associated Contacts
$sql_contacts = mysqli_query(
    $mysqli,
    "SELECT * FROM service_contacts
    LEFT JOIN contacts ON service_contacts.contact_id = contacts.contact_id
    WHERE service_id = $service_id"
);

// Associated Documents
$sql_docs = mysqli_query(
    $mysqli,
    "SELECT * FROM service_documents
    LEFT JOIN documents ON service_documents.document_id = documents.document_id
    WHERE service_id = $service_id"
);

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-white"><i class="fa fa-fw fa-stream mr-2"></i><?php echo $service_name; ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<div class="modal-body">
    <div class="row">

        <!-- Main/Left side -->
        <div class="col-8 border-right">
            <div class="col-12">
                <h4>Service Overview: <?php echo "$service_name $service_importance_display"; ?></h4>
                <b>Description:</b> <?php echo $service_description; ?> <br>
                <b>Backup Info:</b> <?php echo $service_backup; ?> <br><br>

                <h5><i class="fas fa-fw fa-sticky-note mr-2"></i>Notes</h5>
                <div style="white-space: pre-line"><?php echo $service_notes; ?></div>
                <hr>

                <!-- Assets -->
                <?php
                if (mysqli_num_rows($sql_assets) > 0) {
                    echo "<h5><i class='fas fa-fw fa-desktop mr-2'></i>Assets</h5><ul>";
                    mysqli_data_seek($sql_assets, 0);
                    while ($row = mysqli_fetch_array($sql_assets)) {
                        $asset_id = intval($row['asset_id']);
                        $asset_name = nullable_htmlentities($row['asset_name']);
                        $ip = !empty($row['interface_ip']) ? '(' . nullable_htmlentities($row['interface_ip']) . ')' : '';
                        echo "<li><a href='#' data-toggle='ajax-modal' data-modal-size='lg' data-ajax-url='ajax/ajax_asset_details.php' data-ajax-id='$asset_id'>$asset_name</a>$ip</li>";
                    }
                    echo "</ul>";
                }
                ?>

                <!-- Networks -->
                <?php
                $networks = [];
                if ($sql_assets) {
                    mysqli_data_seek($sql_assets, 0);
                    while ($row = mysqli_fetch_array($sql_assets)) {
                        if (!empty($row['network_name'])) {
                            $network_data = nullable_htmlentities($row["network_name"]) . ':' . nullable_htmlentities($row["network_vlan"]);
                            $networks[] = $network_data;
                        }
                    }
                    $networks = array_unique($networks);
                    if (!empty($networks)) {
                        echo "<h5><i class='fas fa-fw fa-network-wired mr-2'></i>Networks</h5><ul>";
                        foreach ($networks as $network) {
                            $network_parts = explode(":", $network);
                            $network_name = $network_parts[0];
                            $network_vlan = $network_parts[1] ?? '';
                            echo "<li><a href='networks.php?client_id=$client_id&q=$network_name'>$network_name</a> (VLAN $network_vlan)</li>";
                        }
                        echo "</ul>";
                    }
                }
                ?>

                <!-- Locations -->
                <?php
                $location_names = [];
                if ($sql_assets) {
                    mysqli_data_seek($sql_assets, 0);
                    while ($row = mysqli_fetch_array($sql_assets)) {
                        if (!empty($row['location_name'])) {
                            $location_names[] = nullable_htmlentities($row['location_name']);
                        }
                    }
                    $location_names = array_unique($location_names);
                    if (!empty($location_names)) {
                        echo "<h5><i class='fas fa-fw fa-map-marker-alt mr-2'></i>Locations</h5><ul>";
                        foreach ($location_names as $location) {
                            echo "<li><a href='locations.php?client_id=$client_id&q=$location'>$location</a></li>";
                        }
                        echo "</ul>";
                    }
                }
                ?>

                <!-- Domains -->
                <?php
                if (mysqli_num_rows($sql_domains) > 0) {
                    echo "<h5><i class='fas fa-fw fa-globe mr-2'></i>Domains</h5><ul>";
                    mysqli_data_seek($sql_domains, 0);
                    while ($row = mysqli_fetch_array($sql_domains)) {
                        if (!empty($row['domain_name'])) {
                            $domain_name = nullable_htmlentities($row['domain_name']);
                            echo "<li><a href='domains.php?client_id=$client_id&q=$domain_name'>$domain_name</a></li>";
                        }
                    }
                    echo "</ul>";
                }
                ?>

                <!-- Certificates -->
                <?php
                if (mysqli_num_rows($sql_certificates) > 0) {
                    echo "<h5><i class='fas fa-fw fa-lock mr-2'></i>Certificates</h5><ul>";
                    mysqli_data_seek($sql_certificates, 0);
                    while ($row = mysqli_fetch_array($sql_certificates)) {
                        if (!empty($row['certificate_name'])) {
                            $certificate_name = nullable_htmlentities($row['certificate_name']);
                            $certificate_domain = nullable_htmlentities($row['certificate_domain']);
                            echo "<li><a href='certificates.php?client_id=$client_id&q=$certificate_name'>$certificate_name ($certificate_domain)</a></li>";
                        }
                    }
                    echo "</ul>";
                }
                ?>

            </div>
        </div>

        <!-- Right side -->
        <div class="col-4">
            <div class="col-12">
                <h4>Additional Related Items</h4>
                <br>

                <!-- Vendors -->
                <?php
                if (mysqli_num_rows($sql_vendors) > 0) {
                    echo "<h5><i class='fas fa-fw fa-building mr-2'></i>Vendors</h5><ul>";
                    mysqli_data_seek($sql_vendors, 0);
                    while ($row = mysqli_fetch_array($sql_vendors)) {
                        $vendor_id = intval($row['vendor_id']);
                        $vendor_name = nullable_htmlentities($row['vendor_name']);
                        echo "<li><a class='ajax-modal' href='#' data-modal-size='lg' data-modal-url='modals/vendor/vendor_details.php?id=$vendor_id'>$vendor_name</a></li>";
                    }
                    echo "</ul>";
                }
                ?>

                <!-- Contacts -->
                <?php
                if (mysqli_num_rows($sql_contacts) > 0) {
                    echo "<h5><i class='fas fa-fw fa-users mr-2'></i>Contacts</h5><ul>";
                    mysqli_data_seek($sql_contacts, 0);
                    while ($row = mysqli_fetch_array($sql_contacts)) {
                        $contact_id = intval($row['contact_id']);
                        $contact_name = nullable_htmlentities($row['contact_name']);
                        echo "<li><a href='#' data-toggle='ajax-modal' data-modal-size='lg' data-ajax-url='ajax/ajax_contact_details.php' data-ajax-id='$contact_id'>$contact_name</a></li>";
                    }
                    echo "</ul>";
                }
                ?>

                <!-- Credentials -->
                <?php
                if (mysqli_num_rows($sql_assets) > 0 || mysqli_num_rows($sql_credentials) > 0) {
                    echo "<h5><i class='fas fa-fw fa-key mr-2'></i>Credentials</h5><ul>";
                    // Credentials linked to assets
                    mysqli_data_seek($sql_assets, 0);
                    while ($row = mysqli_fetch_array($sql_assets)) {
                        $credential_name = nullable_htmlentities($row['credential_name']);
                        if (!empty($credential_name)) {
                            echo "<li><a href='credentials.php?client_id=$client_id&q=$credential_name'>$credential_name</a></li>";
                        }
                    }
                    // Explicitly linked credentials
                    mysqli_data_seek($sql_credentials, 0);
                    while ($row = mysqli_fetch_array($sql_credentials)) {
                        $credential_name = nullable_htmlentities($row['credential_name']);
                        if (!empty($credential_name)) {
                            echo "<li><a href='credentials.php?client_id=$client_id&q=$credential_name'>$credential_name</a></li>";
                        }
                    }
                    echo "</ul>";
                }
                ?>

                <!-- URLs -->
                <?php
                $urls = [];
                mysqli_data_seek($sql_credentials, 0);
                while ($row = mysqli_fetch_array($sql_credentials)) {
                    if (!empty($row['credential_uri'])) {
                        $urls[] = sanitize_url($row['credential_uri']);
                    }
                }
                mysqli_data_seek($sql_assets, 0);
                while ($row = mysqli_fetch_array($sql_assets)) {
                    if (!empty($row['asset_uri'])) {
                        $urls[] = sanitize_url($row['asset_uri']);
                    }
                }
                $urls = array_unique($urls);
                if (!empty($urls)) {
                    echo "<h5><i class='fas fa-fw fa-link mr-2'></i>URLs</h5><ul>";
                    foreach ($urls as $url) {
                        $label = htmlspecialchars(parse_url($url, PHP_URL_HOST) ?: $url);
                        echo "<li><a href='$url' target='_blank'>$label</a></li>";
                    }
                    echo "</ul>";
                }
                ?>

                <!-- Documents -->
                <?php
                if (mysqli_num_rows($sql_docs) > 0) {
                    echo "<h5><i class='fas fa-fw fa-file-alt mr-2'></i>Documents</h5><ul>";
                    mysqli_data_seek($sql_docs, 0);
                    while ($row = mysqli_fetch_array($sql_docs)) {
                        $document_id = intval($row['document_id']);
                        $document_name = nullable_htmlentities($row['document_name']);
                        echo "<li><a href='#' data-toggle='ajax-modal' data-modal-size='lg' data-ajax-url='ajax/ajax_document_view.php' data-ajax-id='$document_id'>$document_name</a></li>";
                    }
                    echo "</ul>";
                }
                ?>

            </div>
        </div>
    </div>
</div>

<?php
require_once '../../includes/modal_footer.php';
?>
