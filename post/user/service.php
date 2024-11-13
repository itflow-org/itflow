<?php

/*
 * ITFlow - GET/POST request handler for client service info
 */

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

    if (isset($_POST['logins'])) {
        foreach($_POST['logins'] as $login_id) {
            $login_id = intval($login_id);
            mysqli_query($mysqli, "INSERT INTO service_logins SET service_id = $service_id, login_id = $login_id");
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

    // Logging
    logAction("Service", "Create", "$session_name created service $service_name", $client_id, $service_id);

    $_SESSION['alert_message'] = "Service <strong>$service_name</strong> created";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

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
    mysqli_query($mysqli, "DELETE FROM service_logins WHERE service_id = $service_id");
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

    if (isset($_POST['logins'])) {
        foreach($_POST['logins'] as $login_id) {
            $login_id = intval($login_id);
            mysqli_query($mysqli, "INSERT INTO service_logins SET service_id = $service_id, login_id = $login_id");
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

    // Logging
    logAction("Service", "Edit", "$session_name edited service $service_name", $client_id, $service_id);

    $_SESSION['alert_message'] = "Service <strong>$service_name</strong> edited";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_service'])) {

    enforceUserPermission('module_support', 3);
    validateCSRFToken($_GET['csrf_token']);

    $service_id = intval($_GET['delete_service']);

    // Get Service Details
    $sql = mysqli_query($mysqli,"SELECT service_name, service_client_id FROM services WHERE service_id = $service_id");
    $row = mysqli_fetch_array($sql);
    $service_name = sanitizeInput($row['service_name']);
    $client_id = intval($row['service_client_id']);

    // Delete service
    mysqli_query($mysqli, "DELETE FROM services WHERE service_id = $service_id");

    // Delete relations
    // TODO: Convert this to a join delete
    mysqli_query($mysqli, "DELETE FROM service_contacts WHERE service_id = $service_id");
    mysqli_query($mysqli, "DELETE FROM service_vendors WHERE service_id = $service_id");
    mysqli_query($mysqli, "DELETE FROM service_documents WHERE service_id = $service_id");
    mysqli_query($mysqli, "DELETE FROM service_assets WHERE service_id = $service_id");
    mysqli_query($mysqli, "DELETE FROM service_logins WHERE service_id = $service_id");
    mysqli_query($mysqli, "DELETE FROM service_domains WHERE service_id = $service_id");
    mysqli_query($mysqli, "DELETE FROM service_certificates WHERE service_id = $service_id");

    // Logging
    logAction("Service", "Delete", "$session_name deleted service $service_name", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Service <strong>$service_name</strong> deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
