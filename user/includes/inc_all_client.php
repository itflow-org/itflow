<?php

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/router.php";
require_once "../includes/check_login.php";
require_once "../includes/page_title.php";

// Perms
enforceUserPermission('module_client');

if (isset($_GET['client_id'])) {
    $client_id = intval($_GET['client_id']);

    // Client Access Check
    //  Ensure the user has permission to access this client (admins ignored)
    if (!in_array($client_id, $client_access_array) AND !empty($client_access_string) AND !$session_is_admin) {
        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Client', log_action = 'Access', log_description = '$session_name was denied permission from accessing client', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $client_id");

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Access Denied - You do not have permission to access that client!";

        echo "<script>window.history.back();</script>";
        exit();
    }

    $sql = mysqli_query($mysqli, "UPDATE clients SET client_accessed_at = NOW() WHERE client_id = $client_id");

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM clients
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        WHERE client_id = $client_id"
    );

    if (mysqli_num_rows($sql) == 0) {
        require_once "../includes/header.php";

        echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1></center>";
    } else {

        $row = mysqli_fetch_array($sql);
        $client_name = nullable_htmlentities($row['client_name']);
        $client_is_lead = intval($row['client_lead']);
        $client_type = nullable_htmlentities($row['client_type']);
        $client_website = nullable_htmlentities($row['client_website']);
        $client_referral = nullable_htmlentities($row['client_referral']);
        $client_currency_code = nullable_htmlentities($row['client_currency_code']);
        $client_net_terms = intval($row['client_net_terms']);
        if ($client_net_terms == 0) {
            $client_net_terms = $config_default_net_terms;
        }
        $client_tax_id_number = nullable_htmlentities($row['client_tax_id_number']);
        $client_abbreviation = nullable_htmlentities($row['client_abbreviation']);
        $client_rate = floatval($row['client_rate']);
        $client_notes = nullable_htmlentities($row['client_notes']);
        $client_created_at = nullable_htmlentities($row['client_created_at']);
        $client_archived_at = nullable_htmlentities($row['client_archived_at']);
        $contact_id = intval($row['contact_id']);
        $contact_name = nullable_htmlentities($row['contact_name']);
        $contact_title = nullable_htmlentities($row['contact_title']);
        $contact_email = nullable_htmlentities($row['contact_email']);
        $contact_phone_country_code = nullable_htmlentities($row['contact_phone_country_code']);
        $contact_phone = nullable_htmlentities(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
        $contact_extension = nullable_htmlentities($row['contact_extension']);
        $contact_mobile_country_code = nullable_htmlentities($row['contact_mobile_country_code']);
        $contact_mobile = nullable_htmlentities(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));
        $contact_primary = intval($row['contact_primary']);
        $location_id = intval($row['location_id']);
        $location_name = nullable_htmlentities($row['location_name']);
        $location_address = nullable_htmlentities($row['location_address']);
        $location_city = nullable_htmlentities($row['location_city']);
        $location_state = nullable_htmlentities($row['location_state']);
        $location_zip = nullable_htmlentities($row['location_zip']);
        $location_country = nullable_htmlentities($row['location_country']);
        $location_phone_country_code = nullable_htmlentities($row['location_phone_country_code']);
        $location_phone = nullable_htmlentities(formatPhoneNumber($row['location_phone'], $location_phone_country_code));
        $location_primary = intval($row['location_primary']);

        // Tab Title // No Sanitizing needed
        $tab_title = $row['client_name'];

        // Client Tags

        $client_tag_name_display_array = array();
        $client_tag_id_array = array();
        $sql_client_tags = mysqli_query($mysqli, "SELECT * FROM client_tags LEFT JOIN tags ON client_tags.tag_id = tags.tag_id WHERE client_id = $client_id ORDER BY tag_name ASC");
        while ($row = mysqli_fetch_array($sql_client_tags)) {

            $client_tag_id = intval($row['tag_id']);
            $client_tag_name = nullable_htmlentities($row['tag_name']);
            $client_tag_color = nullable_htmlentities($row['tag_color']);
            if (empty($client_tag_color)) {
                $client_tag_color = "dark";
            }
            $client_tag_icon = nullable_htmlentities($row['tag_icon']);
            if (empty($client_tag_icon)) {
                $client_tag_icon = "tag";
            }

            $client_tag_id_array[] = $client_tag_id;
            $client_tag_name_display_array[] = "<span class='badge text-light p-1 mr-1' style='background-color: $client_tag_color;'><i class='fa fa-fw fa-$client_tag_icon mr-2'></i>$client_tag_name</span>";
        }
        $client_tags_display = implode('', $client_tag_name_display_array);

        //Add up all the payments for the invoice and get the total amount paid to the invoice
        $sql_invoice_amounts = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS invoice_amounts FROM invoices WHERE invoice_client_id = $client_id AND invoice_status != 'Draft' AND invoice_status != 'Cancelled' AND invoice_status != 'Non-Billable'");
        $row = mysqli_fetch_array($sql_invoice_amounts);

        $invoice_amounts = floatval($row['invoice_amounts']);

        $sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_client_id = $client_id");
        $row = mysqli_fetch_array($sql_amount_paid);

        $amount_paid = floatval($row['amount_paid']);

        $balance = $invoice_amounts - $amount_paid;

        //Get Monthly Recurring Total
        $sql_recurring_monthly_total = mysqli_query($mysqli, "SELECT SUM(recurring_invoice_amount) AS recurring_monthly_total FROM recurring_invoices WHERE recurring_invoice_status = 1 AND recurring_invoice_frequency = 'month' AND recurring_invoice_client_id = $client_id");
        $row = mysqli_fetch_array($sql_recurring_monthly_total);

        $recurring_monthly_total = floatval($row['recurring_monthly_total']);

        //Get Yearly Recurring Total
        $sql_recurring_yearly_total = mysqli_query($mysqli, "SELECT SUM(recurring_invoice_amount) AS recurring_yearly_total FROM recurring_invoices WHERE recurring_invoice_status = 1 AND recurring_invoice_frequency = 'year' AND recurring_invoice_client_id = $client_id");
        $row = mysqli_fetch_array($sql_recurring_yearly_total);

        $recurring_yearly_total = floatval($row['recurring_yearly_total']) / 12;

        $recurring_monthly = $recurring_monthly_total + $recurring_yearly_total;

        // Get Credit Balance
        $sql_credit_balance = mysqli_query($mysqli, "SELECT SUM(credit_amount) AS credit_balance FROM credits WHERE credit_client_id = $client_id");
        $row = mysqli_fetch_array($sql_credit_balance);

        $credit_balance = floatval($row['credit_balance']);

        // Badge Counts

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('contact_id') AS num FROM contacts WHERE contact_archived_at IS NULL AND contact_client_id = $client_id"));
        $num_contacts = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('location_id') AS num FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id"));
        $num_locations = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('asset_id') AS num FROM assets WHERE asset_archived_at IS NULL AND asset_client_id = $client_id"));
        $num_assets = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('ticket_id') AS num FROM tickets WHERE ticket_archived_at IS NULL AND ticket_closed_at IS NULL AND ticket_status != 4 AND ticket_client_id = $client_id"));
        $num_active_tickets = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('ticket_id') AS num FROM tickets WHERE ticket_archived_at IS NULL AND ticket_closed_at IS NOT NULL AND ticket_client_id = $client_id"));
        $num_closed_tickets = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('recurring_ticket_id') AS num FROM recurring_tickets WHERE recurring_ticket_client_id = $client_id"));
        $num_recurring_tickets = $row['num'];

        // Active Project Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('project_id') AS num FROM projects WHERE project_archived_at IS NULL AND project_completed_at IS NULL AND project_client_id = $client_id"));
        $num_active_projects = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('service_id') AS num FROM services WHERE service_client_id = $client_id"));
        $num_services = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id"));
        $num_vendors = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('credential_id') AS num FROM credentials WHERE credential_archived_at IS NULL AND credential_client_id = $client_id"));
        $num_credentials = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('network_id') AS num FROM networks WHERE network_archived_at IS NULL AND network_client_id = $client_id"));
        $num_networks = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('rack_id') AS num FROM racks WHERE rack_archived_at IS NULL AND rack_client_id = $client_id"));
        $num_racks = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('domain_id') AS num FROM domains WHERE domain_archived_at IS NULL AND domain_client_id = $client_id"));
        $num_domains = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('certificate_id') AS num FROM certificates WHERE certificate_archived_at IS NULL AND certificate_client_id = $client_id"));
        $num_certificates = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('software_id') AS num FROM software WHERE software_archived_at IS NULL AND software_client_id = $client_id"));
        $num_software = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE (invoice_status = 'Sent' OR invoice_status = 'Viewed' OR invoice_status = 'Partial') AND invoice_archived_at IS NULL AND invoice_client_id = $client_id"));
        $num_invoices_open = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Draft' AND invoice_archived_at IS NULL AND invoice_client_id = $client_id"));
        $num_invoices_draft = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Sent' AND invoice_archived_at IS NULL AND invoice_client_id = $client_id"));
        $num_invoices_sent = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Viewed' AND invoice_archived_at IS NULL AND invoice_client_id = $client_id"));
        $num_invoices_viewed = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Partial' AND invoice_archived_at IS NULL AND invoice_client_id = $client_id"));
        $num_invoices_partial = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Paid' AND invoice_archived_at IS NULL AND invoice_client_id = $client_id"));
        $num_invoices_paid = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_archived_at IS NULL AND invoice_client_id = $client_id"));
        $num_invoices = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('quote_id') AS num FROM quotes WHERE quote_archived_at IS NULL AND quote_client_id = $client_id"));
        $num_quotes = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('recurring_invoice_id') AS num FROM recurring_invoices WHERE recurring_invoice_archived_at IS NULL AND recurring_invoice_client_id = $client_id"));
        $num_recurring_invoices = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('payment_id') AS num FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_client_id = $client_id"));
        $num_payments = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('file_id') AS num FROM files WHERE file_archived_at IS NULL AND file_client_id = $client_id"));
        $num_files = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('document_id') AS num FROM documents WHERE document_archived_at IS NULL AND document_client_id = $client_id"));
        $num_documents = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('event_id') AS num FROM calendar_events WHERE event_client_id = $client_id"));
        $num_calendar_events = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('trip_id') AS num FROM trips WHERE trip_archived_at IS NULL AND trip_client_id = $client_id"));
        $num_trips = $row['num'];

        // Expiring Items

        // Count Domains Expiring within 45 Days
        $row = mysqli_fetch_assoc(mysqli_query(
            $mysqli,
            "SELECT COUNT('domain_id') AS num FROM domains
            WHERE domain_client_id = $client_id
            AND domain_expire IS NOT NULL
            AND domain_expire < CURRENT_DATE + INTERVAL 45 DAY
            AND domain_archived_at IS NULL"
        ));
        $num_domains_expiring_warning= intval($row['num']);

        // Count Domains Expired or within 7 days
        $row = mysqli_fetch_assoc(mysqli_query(
            $mysqli,
            "SELECT COUNT('domain_id') AS num FROM domains
            WHERE domain_client_id = $client_id
            AND domain_expire IS NOT NULL
            AND (
                    domain_expire < CURRENT_DATE
                    OR domain_expire < CURRENT_DATE + INTERVAL 7 DAY
                )
            AND domain_archived_at IS NULL"
        ));
        $num_domains_urgent = intval($row['num']);

        // Count Certificates Expiring within 7 Days
        $row = mysqli_fetch_assoc(mysqli_query(
            $mysqli,
            "SELECT COUNT('certificate_id') AS num FROM certificates
            WHERE certificate_client_id = $client_id
            AND certificate_expire IS NOT NULL
            AND certificate_expire < CURRENT_DATE + INTERVAL 7 DAY
            AND certificate_archived_at IS NULL"
        ));
        $num_certificates_expiring = intval($row['num']);

        // Count Certificates Expired or within 7 days
        $row = mysqli_fetch_assoc(mysqli_query(
            $mysqli,
            "SELECT COUNT('certificate_id') AS num FROM certificates
            WHERE certificate_client_id = $client_id
            AND certificate_expire IS NOT NULL
            AND (
                    certificate_expire < CURRENT_DATE
                    OR certificate_expire < CURRENT_DATE + INTERVAL 1 DAY
                )
            AND certificate_archived_at IS NULL"
        ));
        $num_certificates_expired = intval($row['num']);

        // Count Software Expiring within 90 Days
        $row = mysqli_fetch_assoc(mysqli_query(
            $mysqli,
            "SELECT COUNT('software_id') AS num FROM software
            WHERE software_client_id = $client_id
            AND software_expire IS NOT NULL
            AND software_expire < CURRENT_DATE + INTERVAL 45 DAY
            AND software_archived_at IS NULL"
        ));
        $num_software_expiring = intval($row['num']);

        // Count Software Expired or within 14 days
        $row = mysqli_fetch_assoc(mysqli_query(
            $mysqli,
            "SELECT COUNT('software_id') AS num FROM software
            WHERE software_client_id = $client_id
            AND software_expire IS NOT NULL
            AND (
                    software_expire < CURRENT_DATE
                    OR software_expire < CURRENT_DATE + INTERVAL 7 DAY
                )
            AND software_archived_at IS NULL"
        ));
        $num_software_expired = intval($row['num']);

    }
}

require_once "../includes/header.php";
require_once "../includes/top_nav.php";
require_once "includes/client_side_nav.php";
require_once "../includes/inc_wrapper.php";
require_once "../includes/inc_alert_feedback.php";
require_once "includes/inc_client_top_head.php";
require_once "../includes/filter_header.php";
