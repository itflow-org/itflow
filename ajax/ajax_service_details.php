<?php

require_once '../includes/ajax_header.php';

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

// Associated URLs ---- REMOVED for now
//$sql_urls = mysqli_query($mysqli, "SELECT * FROM service_urls
//WHERE service_id = '$service_id'");

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
<div class="modal-header">
    <h5 class="modal-title text-white"><i class="fa fa-fw fa-stream mr-2"></i><?php echo $service_name; ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<div class="modal-body bg-white">
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
                if (mysqli_num_rows($sql_assets) > 0) { ?>
                    <h5><i class="fas fa-fw fa-desktop mr-2"></i>Assets</h5>
                    <ul>
                        <?php
                        // Reset the $sql_assets pointer to the start - as we've already cycled through once
                        mysqli_data_seek($sql_assets, 0);

                        while ($row = mysqli_fetch_array($sql_assets)) {
                            $asset_id = intval($row['asset_id']);
                            $asset_name = nullable_htmlentities($row['asset_name']);
                            if (!empty($row['interface_ip'])) {
                                $ip = '('.nullable_htmlentities($row["interface_ip"]).')';
                            } else {
                                $ip = '';
                            }
                            echo "<li><a href='#' data-toggle='ajax-modal'
                                data-modal-size='lg'
                                data-ajax-url='ajax/ajax_asset_details.php'
                                data-ajax-id='$asset_id'>$asset_name</a>$ip</li>";
                        }
                        ?>
                    </ul>
                    <?php
                }
                ?>

                <!-- Networks -->
                <?php
                if ($sql_assets) {

                    $networks = [];

                    // Reset the $sql_assets pointer to the start
                    mysqli_data_seek($sql_assets, 0);

                    // Get networks linked to assets - push name to array
                    while ($row = mysqli_fetch_array($sql_assets)) {
                        if (!empty($row['network_name'])) {
                            $network_data = nullable_htmlentities("$row[network_name]:$row[network_vlan]");
                            array_push($networks, $network_data);
                        }
                    }

                    // Remove duplicates
                    $networks = array_unique($networks);

                    // Display
                    if (!empty($networks)) { ?>
                        <h5><i class="fas fa-fw fa-network-wired mr-2"></i>Networks</h5>
                        <ul>
                        <?php
                    }
                    foreach($networks as $network) {
                        $network = explode(":", $network);
                        echo "<li><a href=\"networks.php?client_id=$client_id&q=$network[0]\">$network[0] </a>(VLAN $network[1])</li>";
                    }

                    ?>
                    </ul>
                    <?php
                }
                ?>

                <!-- Locations -->
                <?php
                if ($sql_assets) {

                    $location_names = [];

                    // Reset the $sql_assets pointer to the start - as we've already cycled through once
                    mysqli_data_seek($sql_assets, 0);

                    // Get locations linked to assets - push their name and vlan to arrays
                    while ($row = mysqli_fetch_array($sql_assets)) {
                        if (!empty($row['location_name'])) {
                            array_push($location_names, $row['location_name']);
                        }
                    }

                    // Remove duplicates
                    $location_names = array_unique($location_names);

                    // Display
                    if (!empty($location_names)) { ?>
                        <h5><i class="fas fa-fw fa-map-marker-alt mr-2"></i>Locations</h5>
                        <ul>
                        <?php
                    }
                    foreach($location_names as $location) {
                        echo "<li><a href=\"locations.php?client_id=$client_id&q=$location\">$location</a></li>";
                    }
                    ?>
                    </ul>
                    <?php
                }
                ?>

                <!-- Domains -->
                <?php
                if (mysqli_num_rows($sql_domains) > 0) { ?>
                    <h5><i class="fas fa-fw fa-globe mr-2"></i>Domains</h5>
                    <ul>
                        <?php
                        // Reset the $sql_domains pointer to the start
                        mysqli_data_seek($sql_domains, 0);

                        // Showing linked domains
                        while ($row = mysqli_fetch_array($sql_domains)) {
                            if (!empty($row['domain_name'])) {
                                echo "<li><a href=\"domains.php?client_id=$client_id&q=$row[domain_name]\">$row[domain_name]</a></li>";
                            }
                        }
                        ?>
                    </ul>
                    <?php
                }
                ?>

                <!-- Certificates -->
                <?php
                if (mysqli_num_rows($sql_certificates) > 0) { ?>
                    <h5><i class="fas fa-fw fa-lock mr-2"></i>Certificates</h5>
                    <ul>
                        <?php
                        // Reset the $sql_certificates pointer to the start
                        mysqli_data_seek($sql_certificates, 0);

                        // Showing linked certs
                        while ($row = mysqli_fetch_array($sql_certificates)) {
                            if (!empty($row['certificate_name'])) {
                                echo "<li><a href=\"certificates.php?client_id=$client_id&q=$row[certificate_name]\">$row[certificate_name] ($row[certificate_domain])</a></li>";
                            }
                        }
                        ?>
                    </ul>
                    <?php
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
                // Reset the $sql_vendors pointer to the start
                mysqli_data_seek($sql_vendors, 0);

                if (mysqli_num_rows($sql_vendors) > 0) { ?>
                    <h5><i class="fas fa-fw fa-building mr-2"></i>Vendors</h5>
                    <ul>
                        <?php
                        while ($row = mysqli_fetch_array($sql_vendors)) {

                            $vendor_id = intval($row['vendor_id']);
                            $vendor_name = nullable_htmlentities($row['vendor_name']);
                            echo "<li><a href='#' data-toggle='ajax-modal'
                                data-modal-size='lg'
                                data-ajax-url='ajax/ajax_vendor_details.php'
                                data-ajax-id='$vendor_id'>
                                $vendor_name
                                </a>
                            </li>";
                        }
                        ?>
                    </ul>
                    <?php
                }
                ?>

                <!-- Contacts -->
                <?php
                if (mysqli_num_rows($sql_contacts) > 0) { ?>
                    <h5><i class="fas fa-fw fa-users mr-2"></i>Contacts</h5>
                    <ul>
                        <?php
                        // Reset the $sql_contacts pointer to the start
                        mysqli_data_seek($sql_contacts, 0);

                        while ($row = mysqli_fetch_array($sql_contacts)) {
                            $contact_id = intval($row['contact_id']);
                            $contact_name = nullable_htmlentities($row['contact_name']);
                            echo "<li><a href='#' data-toggle='ajax-modal'
                                data-modal-size='lg'
                                data-ajax-url='ajax/ajax_contact_details.php'
                                data-ajax-id='$contact_id'>
                                $contact_name
                                </a>
                            </li>";
                        }
                        ?>
                    </ul>
                    <?php
                }
                ?>

                <!-- Credentials -->
                <?php
                if (mysqli_num_rows($sql_assets) > 0 || mysqli_num_rows($sql_credentials) > 0) { ?>
                    <h5><i class="fas fa-fw fa-key mr-2"></i>Credentials</h5>
                    <ul>
                        <?php
                        // Reset the $sql_assets/credentials pointer to the start
                        mysqli_data_seek($sql_assets, 0);
                        mysqli_data_seek($sql_credentials, 0);

                        // Showing credentials linked to assets
                        while ($row = mysqli_fetch_array($sql_assets)) {
                            if (!empty($row['credential_name'])) {
                                echo "<li><a href=\"credentials.php?client_id=$client_id&q=$row[credential_name]\">$row[credential_name]</a></li>";
                            }
                        }

                        // Showing explicitly linked credentials
                        while ($row = mysqli_fetch_array($sql_credentials)) {
                            if (!empty($row['credential_name'])) {
                                echo "<li><a href=\"credentials.php?client_id=$client_id&q=$row[credential_name]\">$row[credential_name]</a></li>";
                            }
                        }
                        ?>
                    </ul>
                    <?php
                }
                ?>

                <!-- URLs -->
                <?php
                if ($sql_credentials || $sql_assets) { ?>
                    <h5><i class="fas fa-fw fa-link mr-2"></i>URLs</h5>
                    <ul>
                        <?php
                        // Reset the $sql_credentials pointer to the start
                        mysqli_data_seek($sql_credentials, 0);

                        // Showing URLs linked to credentials
                        while ($row = mysqli_fetch_array($sql_credentials)) {
                            if (!empty($row['credential_uri'])) {
                                echo "<li><a href=\"https://$row[credential_uri]\">$row[credential_uri]</a></li>";
                            }
                        }

                        // Reset the $sql_assets pointer to the start
                        mysqli_data_seek($sql_assets, 0);

                        // Show URLs linked to assets, that also have credentials
                        while ($row = mysqli_fetch_array($sql_assets)) {
                            if (!empty($row['credential_uri'])) {
                                echo "<li><a href=\"https://$row[credential_uri]\">$row[credential_uri]</a></li>";
                            }
                        }
                        ?>
                    </ul>
                    <?php
                }
                ?>

                <!-- Documents -->
                <?php
                if (mysqli_num_rows($sql_docs) > 0) { ?>
                    <h5><i class="fas fa-fw fa-file-alt mr-2"></i>Documents</h5>
                    <ul>
                        <?php
                        // Reset the $sql_docs pointer to the start
                        mysqli_data_seek($sql_docs, 0);

                        while ($row = mysqli_fetch_array($sql_docs)) {
                            $document_id = intval($row['document_id']);
                            $document_name = nullable_htmlentities($row['document_name']);
                            echo "<li><a href='#' data-toggle='ajax-modal'
                                data-modal-size='lg'
                                data-ajax-url='ajax/ajax_document_view.php'
                                data-ajax-id='$document_id'>
                                $document_name
                                </a>
                            </li>";
                        }
                        ?>
                    </ul>
                    <?php
                }
                ?>

                <!--                            <h5><i class="nav-icon fas fa-file-alt"></i> Services</h5>-->
                <!--                            <ul>-->
                <!--                                <li>Related Service - Coming soon!</li>-->
                <!--                            </ul>-->

            </div>
        </div>
    </div>
</div>

<?php
require_once "../includes/ajax_footer.php";
