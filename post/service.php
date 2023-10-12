<?php

/*
 * ITFlow - GET/POST request handler for client service info
 */

if (isset($_POST['add_service'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $service_name = sanitizeInput($_POST['name']);
    $service_description = sanitizeInput($_POST['description']);
    $service_category = sanitizeInput($_POST['category']); //TODO: Needs integration with company categories
    $service_importance = sanitizeInput($_POST['importance']);
    $service_backup = sanitizeInput($_POST['backup']);
    $service_notes = sanitizeInput($_POST['note']);

    // Create Service
    $service_sql = mysqli_query($mysqli, "INSERT INTO services SET service_name = '$service_name', service_description = '$service_description', service_category = '$service_category', service_importance = '$service_importance', service_backup = '$service_backup', service_notes = '$service_notes', service_client_id = $client_id");

    // Create links to assets
    if ($service_sql) {
        $service_id = $mysqli->insert_id;

        if (!empty($_POST['contacts'])) {
            $service_contact_ids = $_POST['contacts'];
            foreach($service_contact_ids as $contact_id) {
                $contact_id = intval($contact_id);
                if ($contact_id > 0) {
                    mysqli_query($mysqli, "INSERT INTO service_contacts SET service_id = $service_id, contact_id = $contact_id");
                }
            }
        }

        if (!empty($_POST['vendors'])) {
            $service_vendor_ids = $_POST['vendors'];
            foreach($service_vendor_ids as $vendor_id) {
                $vendor_id = intval($vendor_id);
                if ($vendor_id > 0) {
                    mysqli_query($mysqli, "INSERT INTO service_vendors SET service_id = $service_id, vendor_id = $vendor_id");
                }
            }
        }

        if (!empty($_POST['documents'])) {
            $service_document_ids = $_POST['documents'];
            foreach($service_document_ids as $document_id) {
                $document_id = intval($document_id);
                if ($document_id > 0) {
                    mysqli_query($mysqli, "INSERT INTO service_documents SET service_id = $service_id, document_id = $document_id");
                }
            }
        }

        if (!empty($_POST['assets'])) {
            $service_asset_ids = $_POST['assets'];
            foreach($service_asset_ids as $asset_id) {
                $asset_id = intval($asset_id);
                if ($asset_id > 0) {
                    mysqli_query($mysqli, "INSERT INTO service_assets SET service_id = $service_id, asset_id = $asset_id");
                }
            }
        }

        if (!empty($_POST['logins'])) {
            $service_login_ids = $_POST['logins'];
            foreach($service_login_ids as $login_id) {
                $login_id = intval($login_id);
                if ($login_id > 0) {
                    mysqli_query($mysqli, "INSERT INTO service_logins SET service_id = $service_id, login_id = $login_id");
                }
            }
        }

        if (!empty($_POST['domains'])) {
            $service_domain_ids = $_POST['domains'];
            foreach($service_domain_ids as $domain_id) {
                $domain_id = intval($domain_id);
                if ($domain_id > 0) {
                    mysqli_query($mysqli, "INSERT INTO service_domains SET service_id = $service_id, domain_id = $domain_id");
                }
            }
        }

        if (!empty($_POST['certificates'])) {
            $service_cert_ids = $_POST['certificates'];
            foreach($service_cert_ids as $cert_id) {
                $cert_id = intval($cert_id);
                if ($cert_id > 0) {
                    mysqli_query($mysqli, "INSERT INTO service_certificates SET service_id = $service_id, certificate_id = $cert_id");
                }
            }
        }

        if (!empty($_POST['cost'])) {
            $service_cost = floatval($_POST['cost']);
            mysqli_query($mysqli, "UPDATE services SET service_cost = $service_cost WHERE service_id = $service_id");
            if ($service_cost > 0) {
                mysqli_query($mysqli, "UPDATE services SET service_billable = 1 WHERE service_id = $service_id");
            }
        }

        if (!empty($_POST['price'])) {
            $service_price = floatval($_POST['price']);
            mysqli_query($mysqli, "UPDATE services SET service_price = $service_price WHERE service_id = $service_id");
            if ($service_price > 0) {
                mysqli_query($mysqli, "UPDATE services SET service_billable = 1 WHERE service_id = $service_id");
            }
        }



        if (!empty($_POST['seats'])) {
            $service_seats = intval($_POST['seats']);
            mysqli_query($mysqli, "UPDATE services SET service_seats = $service_seats WHERE service_id = $service_id");
        }



        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Service', log_action = 'Create', log_description = '$session_name created service $service_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Service added";
        header("Location: " . $_SERVER["HTTP_REFERER"]);

    }
    else{
        $_SESSION['alert_message'] = "Something went wrong (SQL)";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if (isset($_POST['edit_service'])) {

    validateTechRole();

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
    if (!empty($_POST['contacts'])) {
        $service_contact_ids = $_POST['contacts'];
        foreach($service_contact_ids as $contact_id) {
            $contact_id = intval($contact_id);
            if ($contact_id > 0) {
                mysqli_query($mysqli, "INSERT INTO service_contacts SET service_id = $service_id, contact_id = $contact_id");
            }
        }
    }

    if (!empty($_POST['vendors'])) {
        $service_vendor_ids = $_POST['vendors'];
        foreach($service_vendor_ids as $vendor_id) {
            $vendor_id = intval($vendor_id);
            if ($vendor_id > 0) {
                mysqli_query($mysqli, "INSERT INTO service_vendors SET service_id = $service_id, vendor_id = $vendor_id");
            }
        }
    }

    if (!empty($_POST['documents'])) {
        $service_document_ids = $_POST['documents'];
        foreach($service_document_ids as $document_id) {
            $document_id = intval($document_id);
            if ($document_id > 0) {
                mysqli_query($mysqli, "INSERT INTO service_documents SET service_id = $service_id, document_id = $document_id");
            }
        }
    }

    if (!empty($_POST['assets'])) {
        $service_asset_ids = $_POST['assets'];
        foreach($service_asset_ids as $asset_id) {
            $asset_id = intval($asset_id);
            if ($asset_id > 0) {
                mysqli_query($mysqli, "INSERT INTO service_assets SET service_id = $service_id, asset_id = $asset_id");
            }
        }
    }

    if (!empty($_POST['logins'])) {
        $service_login_ids = $_POST['logins'];
        foreach($service_login_ids as $login_id) {
            $login_id = intval($login_id);
            if ($login_id > 0) {
                mysqli_query($mysqli, "INSERT INTO service_logins SET service_id = $service_id, login_id = $login_id");
            }
        }
    }

    if (!empty($_POST['domains'])) {
        $service_domain_ids = $_POST['domains'];
        foreach($service_domain_ids as $domain_id) {
            $domain_id = intval($domain_id);
            if ($domain_id > 0) {
                mysqli_query($mysqli, "INSERT INTO service_domains SET service_id = $service_id, domain_id = $domain_id");
            }
        }
    }

    if (!empty($_POST['certificates'])) {
        $service_cert_ids = $_POST['certificates'];
        foreach($service_cert_ids as $cert_id) {
            $cert_id = intval($cert_id);
            if ($cert_id > 0) {
                mysqli_query($mysqli, "INSERT INTO service_certificates SET service_id = $service_id, certificate_id = $cert_id");
            }
        }
    }

    if (!empty($_POST['cost'])) {
        $service_cost = floatval($_POST['cost']);
        mysqli_query($mysqli, "UPDATE services SET service_cost = $service_cost WHERE service_id = $service_id");
        if ($service_cost > 0) {
            mysqli_query($mysqli, "INSERT INTO service_billable SET service_id = $service_id, billing_type = 'Cost', billing_amount = $service_cost");
        }
    }

    if (!empty($_POST['price'])) {
        $service_price = floatval($_POST['price']);
        mysqli_query($mysqli, "UPDATE services SET service_price = $service_price WHERE service_id = $service_id");
        if ($service_price > 0) {
            mysqli_query($mysqli, "INSERT INTO service_billable SET service_id = $service_id, billing_type = 'Price', billing_amount = $service_price");
        }
    }

    if (!empty($_POST['seats'])) {
        $service_seats = intval($_POST['seats']);
        mysqli_query($mysqli, "UPDATE services SET service_seats = $service_seats WHERE service_id = $service_id");
    }


    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Service', log_action = 'Modify', log_description = '$session_name modified service $service_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Service updated";
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_service'])) {

    validateAdminRole();

    $service_id = intval($_GET['delete_service']);

    // Delete service
    $delete_sql = mysqli_query($mysqli, "DELETE FROM services WHERE service_id = $service_id");

    // Delete relations
    // TODO: Convert this to a join delete
    if ($delete_sql) {
        mysqli_query($mysqli, "DELETE FROM service_contacts WHERE service_id = $service_id");
        mysqli_query($mysqli, "DELETE FROM service_vendors WHERE service_id = $service_id");
        mysqli_query($mysqli, "DELETE FROM service_documents WHERE service_id = $service_id");
        mysqli_query($mysqli, "DELETE FROM service_assets WHERE service_id = $service_id");
        mysqli_query($mysqli, "DELETE FROM service_logins WHERE service_id = $service_id");
        mysqli_query($mysqli, "DELETE FROM service_domains WHERE service_id = $service_id");
        mysqli_query($mysqli, "DELETE FROM service_certificates WHERE service_id = $service_id");

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Service', log_action = 'Delete', log_description = '$session_name deleted service $service_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Service deleted";
        header("Location: " . $_SERVER["HTTP_REFERER"]);

    } else {
        $_SESSION['alert_message'] = "Something went wrong (SQL)";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}
