<?php

/*
 * ITFlow - GET/POST request handler for client service info
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_service'])) {

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id']);
    $service_name = sanitizeInput($_POST['name']);
    $service_description = sanitizeInput($_POST['description']);
    $service_category = sanitizeInput($_POST['category']); //TODO: Needs integration with company categories
    $service_importance = sanitizeInput($_POST['importance']);
    $service_backup = sanitizeInput($_POST['backup']);
    $service_notes = sanitizeInput($_POST['note']);

    // Create Service
    mysqli_query($mysqli, "INSERT INTO services SET service_name = '$service_name', service_description = '$service_description', service_category = '$service_category', service_importance = '$service_importance', service_backup = '$service_backup', service_notes = '$service_notes', service_client_id = $client_id");

    // Create links to assets
   
    $service_id = mysqli_insert_id($mysqli);

    if (isset($_POST['contacts'])) {
        foreach($_POST['contacts'] as $contact_id) {
            $contact_id = intval($contact_id); 
            mysqli_query($mysqli, "INSERT INTO service_contacts SET service_id = $service_id, contact_id = $contact_id");
        }
    }

    if (isset($_POST['vendors'])) {
        foreach($_POST['vendors'] as $vendor_id) {
            $vendor_id = intval($vendor_id);
            mysqli_query($mysqli, "INSERT INTO service_vendors SET service_id = $service_id, vendor_id = $vendor_id");
        }
    }

    if (isset($_POST['documents'])) {
        foreach($_POST['documents'] as $document_id) {
            $document_id = intval($document_id);
            mysqli_query($mysqli, "INSERT INTO service_documents SET service_id = $service_id, document_id = $document_id");
        }
    }

    if (isset($_POST['assets'])) {
        foreach($_POST['assets'] as $asset_id) {
            $asset_id = intval($asset_id);
            mysqli_query($mysqli, "INSERT INTO service_assets SET service_id = $service_id, asset_id = $asset_id");
        }
    }

    if (isset($_POST['credentials'])) {
        foreach($_POST['credentials'] as $credential_id) {
            $credential_id = intval($credential_id);
            mysqli_query($mysqli, "INSERT INTO service_credentials SET service_id = $service_id, credential_id = $credential_id");
        }
    }

    if (isset($_POST['domains'])) {
        foreach($_POST['domains'] as $domain_id) {
            $domain_id = intval($domain_id);
            mysqli_query($mysqli, "INSERT INTO service_domains SET service_id = $service_id, domain_id = $domain_id");
        }
    }

    if (isset($_POST['certificates'])) {
        foreach($_POST['certificates'] as $cert_id) {
            $cert_id = intval($cert_id);
            mysqli_query($mysqli, "INSERT INTO service_certificates SET service_id = $service_id, certificate_id = $cert_id");
        }
    }

    logAction("Service", "Create", "$session_name created service $service_name", $client_id, $service_id);

    flash_alert("Service <strong>$service_name</strong> created");
    
    redirect();

}

if (isset($_POST['edit_service'])) {

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id']);
    $service_id = intval($_POST['service_id']);
    $service_name = sanitizeInput($_POST['name']);
    $service_description = sanitizeInput($_POST['description']);
    $service_category = sanitizeInput($_POST['category']); //TODO: Needs integration with company categories
    $service_importance = sanitizeInput($_POST['importance']);
    $service_backup = sanitizeInput($_POST['backup']);
    $service_notes = sanitizeInput($_POST['note']);

    // Update main service details
    mysqli_query($mysqli, "UPDATE services SET service_name = '$service_name', service_description = '$service_description', service_category = '$service_category', service_importance = '$service_importance', service_backup = '$service_backup', service_notes = '$service_notes' WHERE service_id = $service_id");

    // Unlink existing relations/assets
    mysqli_query($mysqli, "DELETE FROM service_contacts WHERE service_id = $service_id");
    mysqli_query($mysqli, "DELETE FROM service_vendors WHERE service_id = $service_id");
    mysqli_query($mysqli, "DELETE FROM service_documents WHERE service_id = $service_id");
    mysqli_query($mysqli, "DELETE FROM service_assets WHERE service_id = $service_id");
    mysqli_query($mysqli, "DELETE FROM service_credentials WHERE service_id = $service_id");
    mysqli_query($mysqli, "DELETE FROM service_domains WHERE service_id = $service_id");
    mysqli_query($mysqli, "DELETE FROM service_certificates WHERE service_id = $service_id");

    // Relink
    if (isset($_POST['contacts'])) {
        foreach($_POST['contacts'] as $contact_id) {
            $contact_id = intval($contact_id);
            mysqli_query($mysqli, "INSERT INTO service_contacts SET service_id = $service_id, contact_id = $contact_id");
        }
    }

    if (isset($_POST['vendors'])) {
        foreach($_POST['vendors'] as $vendor_id) {
            $vendor_id = intval($vendor_id);
            mysqli_query($mysqli, "INSERT INTO service_vendors SET service_id = $service_id, vendor_id = $vendor_id");
        }
    }

    if (isset($_POST['documents'])) {
        foreach($_POST['documents'] as $document_id) {
            $document_id = intval($document_id);
            mysqli_query($mysqli, "INSERT INTO service_documents SET service_id = $service_id, document_id = $document_id");
        }
    }

    if (isset($_POST['assets'])) {
        foreach($_POST['assets'] as $asset_id) {
            $asset_id = intval($asset_id);
            mysqli_query($mysqli, "INSERT INTO service_assets SET service_id = $service_id, asset_id = $asset_id");
        }
    }

    if (isset($_POST['credentials'])) {
        foreach($_POST['credentials'] as $credential_id) {
            $credential_id = intval($credential_id);
            mysqli_query($mysqli, "INSERT INTO service_credentials SET service_id = $service_id, credential_id = $credential_id");
        }
    }

    if (isset($_POST['domains'])) {
        foreach($_POST['domains'] as $domain_id) {
            $domain_id = intval($domain_id);
            mysqli_query($mysqli, "INSERT INTO service_domains SET service_id = $service_id, domain_id = $domain_id");
        }
    }

    if (isset($_POST['certificates'])) {
        foreach($_POST['certificates'] as $cert_id) {
        $cert_id = intval($cert_id);
            mysqli_query($mysqli, "INSERT INTO service_certificates SET service_id = $service_id, certificate_id = $cert_id");
        }
    }

    logAction("Service", "Edit", "$session_name edited service $service_name", $client_id, $service_id);

    flash_alert("Service <strong>$service_name</strong> edited");
    
    redirect();

}

if (isset($_GET['delete_service'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 3);
    
    $service_id = intval($_GET['delete_service']);

    // Get Service Details
    $sql = mysqli_query($mysqli,"SELECT service_name, service_client_id FROM services WHERE service_id = $service_id");
    $row = mysqli_fetch_array($sql);
    $service_name = sanitizeInput($row['service_name']);
    $client_id = intval($row['service_client_id']);

    // Delete service
    mysqli_query($mysqli, "DELETE FROM services WHERE service_id = $service_id");

    logAction("Service", "Delete", "$session_name deleted service $service_name", $client_id);
    
    flash_alert("Service <strong>$service_name</strong> deleted", 'error');
    
    redirect();

}
