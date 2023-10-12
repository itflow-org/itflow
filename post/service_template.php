<?php

/*
 * ITFlow - GET/POST request handler for client service_template info
 */

if (isset($_POST['add_service_template'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $service_template_name = sanitizeInput($_POST['name']);
    $service_template_description = sanitizeInput($_POST['description']);
    $service_template_category = sanitizeInput($_POST['category']); //TODO: Needs integration with company categories
    $service_template_importance = sanitizeInput($_POST['importance']);
    $service_template_backup = sanitizeInput($_POST['backup']);
    $service_template_notes = sanitizeInput($_POST['note']);

    // Create Service
    $service_template_sql = mysqli_query($mysqli, "INSERT INTO service_templates SET service_template_name = '$service_template_name', service_template_description = '$service_template_description', service_template_category = '$service_template_category', service_template_importance = '$service_template_importance', service_template_backup = '$service_template_backup', service_template_notes = '$service_template_notes', service_template_client_id = $client_id");

    // Create links to assets
    if ($service_template_sql) {
        $service_template_id = $mysqli->insert_id;

        if (!empty($_POST['contacts'])) {
            $service_template_contact_ids = $_POST['contacts'];
            foreach($service_template_contact_ids as $contact_id) {
                $contact_id = intval($contact_id);
                if ($contact_id > 0) {
                    mysqli_query($mysqli, "INSERT INTO service_template_contacts SET service_template_id = $service_template_id, contact_id = $contact_id");
                }
            }
        }

        if (!empty($_POST['vendors'])) {
            $service_template_vendor_ids = $_POST['vendors'];
            foreach($service_template_vendor_ids as $vendor_id) {
                $vendor_id = intval($vendor_id);
                if ($vendor_id > 0) {
                    mysqli_query($mysqli, "INSERT INTO service_template_vendors SET service_template_id = $service_template_id, vendor_id = $vendor_id");
                }
            }
        }

        if (!empty($_POST['documents'])) {
            $service_template_document_ids = $_POST['documents'];
            foreach($service_template_document_ids as $document_id) {
                $document_id = intval($document_id);
                if ($document_id > 0) {
                    mysqli_query($mysqli, "INSERT INTO service_template_documents SET service_template_id = $service_template_id, document_id = $document_id");
                }
            }
        }

        if (!empty($_POST['assets'])) {
            $service_template_asset_ids = $_POST['assets'];
            foreach($service_template_asset_ids as $asset_id) {
                $asset_id = intval($asset_id);
                if ($asset_id > 0) {
                    mysqli_query($mysqli, "INSERT INTO service_template_assets SET service_template_id = $service_template_id, asset_id = $asset_id");
                }
            }
        }

        if (!empty($_POST['logins'])) {
            $service_template_login_ids = $_POST['logins'];
            foreach($service_template_login_ids as $login_id) {
                $login_id = intval($login_id);
                if ($login_id > 0) {
                    mysqli_query($mysqli, "INSERT INTO service_template_logins SET service_template_id = $service_template_id, login_id = $login_id");
                }
            }
        }

        if (!empty($_POST['domains'])) {
            $service_template_domain_ids = $_POST['domains'];
            foreach($service_template_domain_ids as $domain_id) {
                $domain_id = intval($domain_id);
                if ($domain_id > 0) {
                    mysqli_query($mysqli, "INSERT INTO service_template_domains SET service_template_id = $service_template_id, domain_id = $domain_id");
                }
            }
        }

        if (!empty($_POST['certificates'])) {
            $service_template_cert_ids = $_POST['certificates'];
            foreach($service_template_cert_ids as $cert_id) {
                $cert_id = intval($cert_id);
                if ($cert_id > 0) {
                    mysqli_query($mysqli, "INSERT INTO service_template_certificates SET service_template_id = $service_template_id, certificate_id = $cert_id");
                }
            }
        }

        if (!empty($_POST['currency_code'])) {
            $service_template_currency_code = sanitizeInput($_POST['currency_code']);
            mysqli_query($mysqli, "UPDATE service_templates SET service_template_currency_code = '$service_template_currency_code' WHERE service_template_id = $service_template_id");
        }

        if (!empty($_POST['price'])) {
            $service_template_price = sanitizeInput($_POST['price']);
            mysqli_query($mysqli, "UPDATE service_templates SET service_template_price = '$service_template_price' WHERE service_template_id = $service_template_id");
            if($service_template_price > 0){
                mysqli_query($mysqli, "UPDATE service_templates SET service_template_billable = 1 WHERE service_template_id = $service_template_id");
            }
        }

        if (!empty($_POST['cost'])) {
            $service_template_cost = sanitizeInput($_POST['cost']);
            mysqli_query($mysqli, "UPDATE service_templates SET service_template_cost = '$service_template_cost' WHERE service_template_id = $service_template_id");
            if($service_template_cost > 0){
                mysqli_query($mysqli, "UPDATE service_templates SET service_template_billable = 1 WHERE service_template_id = $service_template_id");
            }
        }

        if (!empty($_POST['seats'])) {
            $service_template_seats = sanitizeInput($_POST['seats']);
            mysqli_query($mysqli, "UPDATE service_templates SET service_template_seats = '$service_template_seats' WHERE service_template_id = $service_template_id");
        }

        

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Service', log_action = 'Create', log_description = '$session_name created service_template $service_template_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Service added";
        header("Location: " . $_SERVER["HTTP_REFERER"]);

    }
    else{
        $_SESSION['alert_message'] = "Something went wrong (SQL)";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if (isset($_POST['edit_service_template'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $service_template_id = intval($_POST['service_template_id']);
    $service_template_name = sanitizeInput($_POST['name']);
    $service_template_description = sanitizeInput($_POST['description']);
    $service_template_category = sanitizeInput($_POST['category']); //TODO: Needs integration with company categories
    $service_template_importance = sanitizeInput($_POST['importance']);
    $service_template_backup = sanitizeInput($_POST['backup']);
    $service_template_notes = sanitizeInput($_POST['note']);

    // Update main service_template details
    mysqli_query($mysqli, "UPDATE service_templates SET service_template_name = '$service_template_name', service_template_description = '$service_template_description', service_template_category = '$service_template_category', service_template_importance = '$service_template_importance', service_template_backup = '$service_template_backup', service_template_notes = '$service_template_notes' WHERE service_template_id = $service_template_id");

    // Unlink existing relations/assets
    mysqli_query($mysqli, "DELETE FROM service_template_contacts WHERE service_template_id = $service_template_id");
    mysqli_query($mysqli, "DELETE FROM service_template_vendors WHERE service_template_id = $service_template_id");
    mysqli_query($mysqli, "DELETE FROM service_template_documents WHERE service_template_id = $service_template_id");
    mysqli_query($mysqli, "DELETE FROM service_template_assets WHERE service_template_id = $service_template_id");
    mysqli_query($mysqli, "DELETE FROM service_template_logins WHERE service_template_id = $service_template_id");
    mysqli_query($mysqli, "DELETE FROM service_template_domains WHERE service_template_id = $service_template_id");
    mysqli_query($mysqli, "DELETE FROM service_template_certificates WHERE service_template_id = $service_template_id");

    // Relink
    if (!empty($_POST['contacts'])) {
        $service_template_contact_ids = $_POST['contacts'];
        foreach($service_template_contact_ids as $contact_id) {
            $contact_id = intval($contact_id);
            if ($contact_id > 0) {
                mysqli_query($mysqli, "INSERT INTO service_template_contacts SET service_template_id = $service_template_id, contact_id = $contact_id");
            }
        }
    }

    if (!empty($_POST['vendors'])) {
        $service_template_vendor_ids = $_POST['vendors'];
        foreach($service_template_vendor_ids as $vendor_id) {
            $vendor_id = intval($vendor_id);
            if ($vendor_id > 0) {
                mysqli_query($mysqli, "INSERT INTO service_template_vendors SET service_template_id = $service_template_id, vendor_id = $vendor_id");
            }
        }
    }

    if (!empty($_POST['documents'])) {
        $service_template_document_ids = $_POST['documents'];
        foreach($service_template_document_ids as $document_id) {
            $document_id = intval($document_id);
            if ($document_id > 0) {
                mysqli_query($mysqli, "INSERT INTO service_template_documents SET service_template_id = $service_template_id, document_id = $document_id");
            }
        }
    }

    if (!empty($_POST['assets'])) {
        $service_template_asset_ids = $_POST['assets'];
        foreach($service_template_asset_ids as $asset_id) {
            $asset_id = intval($asset_id);
            if ($asset_id > 0) {
                mysqli_query($mysqli, "INSERT INTO service_template_assets SET service_template_id = $service_template_id, asset_id = $asset_id");
            }
        }
    }

    if (!empty($_POST['logins'])) {
        $service_template_login_ids = $_POST['logins'];
        foreach($service_template_login_ids as $login_id) {
            $login_id = intval($login_id);
            if ($login_id > 0) {
                mysqli_query($mysqli, "INSERT INTO service_template_logins SET service_template_id = $service_template_id, login_id = $login_id");
            }
        }
    }

    if (!empty($_POST['domains'])) {
        $service_template_domain_ids = $_POST['domains'];
        foreach($service_template_domain_ids as $domain_id) {
            $domain_id = intval($domain_id);
            if ($domain_id > 0) {
                mysqli_query($mysqli, "INSERT INTO service_template_domains SET service_template_id = $service_template_id, domain_id = $domain_id");
            }
        }
    }

    if (!empty($_POST['certificates'])) {
        $service_template_cert_ids = $_POST['certificates'];
        foreach($service_template_cert_ids as $cert_id) {
            $cert_id = intval($cert_id);
            if ($cert_id > 0) {
                mysqli_query($mysqli, "INSERT INTO service_template_certificates SET service_template_id = $service_template_id, certificate_id = $cert_id");
            }
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Service', log_action = 'Modify', log_description = '$session_name modified service_template $service_template_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Service updated";
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_service_template'])) {

    validateAdminRole();

    $service_template_id = intval($_GET['delete_service_template']);

    // Delete service_template
    $delete_sql = mysqli_query($mysqli, "DELETE FROM service_templates WHERE service_template_id = $service_template_id");

    // Delete relations
    // TODO: Convert this to a join delete
    if ($delete_sql) {
        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Service', log_action = 'Delete', log_description = '$session_name deleted service_template $service_template_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Service deleted";
        header("Location: " . $_SERVER["HTTP_REFERER"]);

    } else {
        $_SESSION['alert_message'] = "Something went wrong (SQL)";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}
