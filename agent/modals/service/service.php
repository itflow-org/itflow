<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support');

$service_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT service_backup, service_category, service_client_id, service_created_at,
    service_description, service_importance, service_name, service_notes, service_review_due,
    service_updated_at FROM services WHERE service_id = $service_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$service_name = escapeHtml($row['service_name']);
$service_description = escapeHtml($row['service_description']);
$service_category = escapeHtml($row['service_category']);
$service_importance = escapeHtml($row['service_importance']);
$service_backup = escapeHtml($row['service_backup']);
$service_notes = escapeHtml($row['service_notes']);
$service_created_at = escapeHtml($row['service_created_at']);
$service_updated_at = escapeHtml($row['service_updated_at']);
$service_review_due = escapeHtml($row['service_review_due']);
$client_id = intval($row['service_client_id']);

enforceClientAccess();

// Service Importance
if ($service_importance == "High") {
    $service_importance_display = "<span class='p-2 badge bg-danger'>$service_importance</span>";
} elseif ($service_importance == "Medium") {
    $service_importance_display = "<span class='p-2 badge bg-warning text-dark'>$service_importance</span>";
} elseif ($service_importance == "Low") {
    $service_importance_display = "<span class='p-2 badge bg-info text-dark'>$service_importance</span>";
} else {
    $service_importance_display = "-";
}

// Associated Assets (and their networks/locations) - one row each, their credentials come below
$sql_assets = mysqli_query(
    $mysqli,
    "SELECT * FROM service_assets
    LEFT JOIN assets ON service_assets.asset_id = assets.asset_id
    LEFT JOIN asset_interfaces ON interface_asset_id = assets.asset_id AND interface_primary = 1
    LEFT JOIN networks ON interface_network_id = networks.network_id
    LEFT JOIN locations ON assets.asset_location_id = locations.location_id
    WHERE service_id = $service_id"
);

// Its credentials - the ones linked to it and the ones on its assets - for those who may see credentials
$service_credentials = lookupUserPermission('module_credential') >= 1
    ? credentialRows("credential_client_id = $client_id AND (credential_id IN (SELECT credential_id FROM service_credentials WHERE service_id = $service_id)
        OR credential_asset_id IN (SELECT asset_id FROM service_assets WHERE service_id = $service_id))")
    : [];

// Associated Domains
$sql_domains = mysqli_query(
    $mysqli,
    "SELECT domains.domain_id, domain_name, domain_expire, domain_auto_renew, domain_archived_at FROM service_domains
    JOIN domains ON service_domains.domain_id = domains.domain_id
    WHERE service_id = $service_id
    ORDER BY domain_name"
);

// Associated Certificates
$sql_certificates = mysqli_query(
    $mysqli,
    "SELECT certificates.certificate_id, certificate_domain, certificate_name, certificate_issued_at, certificate_expire, certificate_check_status,
        certificate_checked_at, certificate_archived_at FROM service_certificates
    JOIN certificates ON service_certificates.certificate_id = certificates.certificate_id
    WHERE service_id = $service_id
    ORDER BY certificate_name"
);

// Associated Vendors
$sql_vendors = mysqli_query(
    $mysqli,
    "SELECT vendors.vendor_id, vendor_name FROM service_vendors
    LEFT JOIN vendors ON service_vendors.vendor_id = vendors.vendor_id
    WHERE service_id = $service_id"
);

// Associated Contacts
$sql_contacts = mysqli_query(
    $mysqli,
    "SELECT service_contacts.contact_id, contact_client_id, contact_name, contact_photo FROM service_contacts
    LEFT JOIN contacts ON service_contacts.contact_id = contacts.contact_id
    WHERE service_id = $service_id"
);

// Associated Documents
$sql_docs = mysqli_query(
    $mysqli,
    "SELECT documents.document_id AS id, document_name AS name, document_description, document_created_at, document_updated_at
    FROM service_documents
    JOIN documents ON service_documents.document_id = documents.document_id
    WHERE service_id = $service_id AND document_archived_at IS NULL
    ORDER BY document_name ASC"
);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-white"><i class="fa fa-fw fa-stream me-2"></i><?= $service_name ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="row">

        <!-- Main/Left side -->
        <div class="col-8 border-end">
            <div class="col-12">
                <h4>Service Overview: <?= "$service_name $service_importance_display" ?></h4>
                <b>Description:</b> <?= $service_description ?> <br>
                <b>Backup Info:</b> <?= $service_backup ?> <br><br>

                <h5><i class="fas fa-fw fa-sticky-note me-2"></i>Notes</h5>
                <div style="white-space: pre-line"><?= $service_notes ?></div>
                <hr>

                <!-- Assets -->
                <?php
                if (mysqli_num_rows($sql_assets) > 0) {
                    echo "<h5><i class='fas fa-fw fa-desktop me-2'></i>Assets</h5><ul>";
                    mysqli_data_seek($sql_assets, 0);
                    while ($row = mysqli_fetch_assoc($sql_assets)) {
                        $asset_id = intval($row['asset_id']);
                        $asset_name = escapeHtml($row['asset_name']);
                        $ip = !empty($row['interface_ip']) ? '(' . escapeHtml($row['interface_ip']) . ')' : '';
                        echo "<li><a class='ajax-modal' href='#' data-modal-size='lg' data-modal-url='modals/asset/asset.php?id=$asset_id'>$asset_name</a>$ip</li>";
                    }
                    echo "</ul>";
                }
                ?>

                <!-- Networks -->
                <?php
                $networks = [];
                if ($sql_assets) {
                    mysqli_data_seek($sql_assets, 0);
                    while ($row = mysqli_fetch_assoc($sql_assets)) {
                        if (!empty($row['network_name'])) {
                            $network_data = escapeHtml($row["network_name"]) . ':' . escapeHtml($row["network_vlan"]);
                            $networks[] = $network_data;
                        }
                    }
                    $networks = array_unique($networks);
                    if (!empty($networks)) {
                        echo "<h5><i class='fas fa-fw fa-network-wired me-2'></i>Networks</h5><ul>";
                        foreach ($networks as $network) {
                            $network_parts = explode(":", $network);
                            $network_name = $network_parts[0];
                            $network_vlan = $network_parts[1] ?? '';
                            echo "<li><a href='networks.php?client_id=$client_id&q=$network_name'>$network_name</a> (VLAN <span class='font-monospace'>$network_vlan</span>)</li>";
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
                    while ($row = mysqli_fetch_assoc($sql_assets)) {
                        if (!empty($row['location_name'])) {
                            $location_names[] = escapeHtml($row['location_name']);
                        }
                    }
                    $location_names = array_unique($location_names);
                    if (!empty($location_names)) {
                        echo "<h5><i class='fas fa-fw fa-map-marker-alt me-2'></i>Locations</h5><ul>";
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
                    echo "<h5><i class='fas fa-fw fa-globe me-2'></i>Domains</h5><ul class='list-unstyled ms-1'>";
                    mysqli_data_seek($sql_domains, 0);
                    while ($row = mysqli_fetch_assoc($sql_domains)) {
                        // Each opens its quick look, with when it expires
                        echo '<li class="mb-1 d-flex align-items-center gap-2">' . domainLink($row['domain_id'], $row['domain_name'], ['archived' => !empty($row['domain_archived_at'])])
                            . domainExpiryPill(domainExpiry($row['domain_expire'], $row['domain_auto_renew']), true) . '</li>';
                    }
                    echo "</ul>";
                }
                ?>

                <!-- Certificates -->
                <?php
                if (mysqli_num_rows($sql_certificates) > 0) {
                    echo "<h5><i class='fas fa-fw fa-lock me-2'></i>Certificates</h5><ul class='list-unstyled ms-1'>";
                    mysqli_data_seek($sql_certificates, 0);
                    while ($row = mysqli_fetch_assoc($sql_certificates)) {
                        // Each opens its quick look, with when it expires and anything the last check found wrong
                        $certificate_problem = !in_array($row['certificate_check_status'], [null, '', 'ok'], true);
                        echo '<li class="mb-1 d-flex flex-wrap align-items-center gap-2">' . certificateLink($row['certificate_id'], $row['certificate_name'], ['archived' => !empty($row['certificate_archived_at'])])
                            . ($certificate_problem ? certificateStatusPill($row) : '') . certificateExpiryPill(certificateExpiry($row)) . '</li>';
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
                    echo "<h5><i class='fas fa-fw fa-building me-2'></i>Vendors</h5><ul>";
                    mysqli_data_seek($sql_vendors, 0);
                    while ($row = mysqli_fetch_assoc($sql_vendors)) {
                        $vendor_id = intval($row['vendor_id']);
                        $vendor_name = escapeHtml($row['vendor_name']);
                        echo "<li class='mb-1'>" . vendorLink($vendor_id, $row['vendor_name']) . "</li>";
                    }
                    echo "</ul>";
                }
                ?>

                <!-- Contacts -->
                <?php
                if (mysqli_num_rows($sql_contacts) > 0) {
                    // No bullets - each contact leads with their avatar instead
                    echo "<h5><i class='fas fa-fw fa-users me-2'></i>Contacts</h5><ul class='list-unstyled ms-1'>";
                    mysqli_data_seek($sql_contacts, 0);
                    while ($row = mysqli_fetch_assoc($sql_contacts)) {
                        echo '<li class="mb-1">' . contactLink($row['contact_client_id'], $row['contact_id'], $row['contact_name'], $row['contact_photo']) . '</li>';
                    }
                    echo "</ul>";
                }
                ?>

                <!-- Credentials -->
                <?php if ($service_credentials) { ?>
                    <h5><i class="fas fa-fw fa-key me-2"></i>Credentials</h5>
                    <div class="mb-3"><?= credentialListHtml($service_credentials) ?></div>
                <?php } ?>

                <!-- URLs -->
                <?php
                $urls = [];
                foreach ($service_credentials as $row) {
                    if (!empty($row['credential_uri'])) {
                        $urls[] = escapeUrl($row['credential_uri']);
                    }
                }
                mysqli_data_seek($sql_assets, 0);
                while ($row = mysqli_fetch_assoc($sql_assets)) {
                    if (!empty($row['asset_uri'])) {
                        $urls[] = escapeUrl($row['asset_uri']);
                    }
                }
                $urls = array_unique($urls);
                if (!empty($urls)) {
                    echo "<h5><i class='fas fa-fw fa-link me-2'></i>URLs</h5><ul>";
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
                    // The same rows as a document's Linked card
                    echo "<h5><i class='fas fa-fw fa-file-alt me-2'></i>Documents</h5><ul class='list-unstyled itflow-links'>";
                    mysqli_data_seek($sql_docs, 0);
                    while ($row = mysqli_fetch_assoc($sql_docs)) {
                        echo '<li class="itflow-link-row">' . linkRowHtml('document', $row, $client_id) . '</li>';
                    }
                    echo "</ul>";
                }
                ?>

            </div>
        </div>
    </div>
</div>

<?php
require_once '../../../includes/modal_footer.php';
