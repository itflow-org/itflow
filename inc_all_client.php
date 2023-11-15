<?php

require_once "config.php";

require_once "functions.php";

require_once "check_login.php";

require_once "header.php";

require_once "top_nav.php";


if (isset($_GET['client_id'])) {
    $client_id = intval($_GET['client_id']);

    $sql = mysqli_query($mysqli, "UPDATE clients SET client_accessed_at = NOW() WHERE client_id = $client_id");

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM clients
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        WHERE client_id = $client_id"
    );

    if (mysqli_num_rows($sql) == 0) {
        require_once "header.php";

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
        $client_rate = floatval($row['client_rate']);
        $client_notes = nullable_htmlentities($row['client_notes']);
        $client_created_at = nullable_htmlentities($row['client_created_at']);
        $contact_id = intval($row['contact_id']);
        $contact_name = nullable_htmlentities($row['contact_name']);
        $contact_title = nullable_htmlentities($row['contact_title']);
        $contact_email = nullable_htmlentities($row['contact_email']);
        $contact_phone = formatPhoneNumber($row['contact_phone']);
        $contact_extension = nullable_htmlentities($row['contact_extension']);
        $contact_mobile = formatPhoneNumber($row['contact_mobile']);
        $contact_primary = intval($row['contact_primary']);
        $location_id = intval($row['location_id']);
        $location_name = nullable_htmlentities($row['location_name']);
        $location_address = nullable_htmlentities($row['location_address']);
        $location_city = nullable_htmlentities($row['location_city']);
        $location_state = nullable_htmlentities($row['location_state']);
        $location_zip = nullable_htmlentities($row['location_zip']);
        $location_country = nullable_htmlentities($row['location_country']);
        $location_phone = formatPhoneNumber($row['location_phone']);
        $location_primary = intval($row['location_primary']);

        // Client Tags

        $client_tag_name_display_array = array();
        $client_tag_id_array = array();
        $sql_client_tags = mysqli_query($mysqli, "SELECT * FROM client_tags LEFT JOIN tags ON client_tags.client_tag_tag_id = tags.tag_id WHERE client_tags.client_tag_client_id = $client_id ORDER BY tag_name ASC");
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
            $client_tag_name_display_array[] = "<a href='clients.php?q=$client_tag_name'><span class='badge bg-$client_tag_color'><i class='fa fa-fw fa-$client_tag_icon mr-2'></i>$client_tag_name</span></a> ";
        }
        $client_tags_display = implode('', $client_tag_name_display_array);

        //Add up all the payments for the invoice and get the total amount paid to the invoice
        $sql_invoice_amounts = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS invoice_amounts FROM invoices WHERE invoice_client_id = $client_id AND invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Cancelled'");
        $row = mysqli_fetch_array($sql_invoice_amounts);

        $invoice_amounts = floatval($row['invoice_amounts']);

        $sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_client_id = $client_id");
        $row = mysqli_fetch_array($sql_amount_paid);

        $amount_paid = floatval($row['amount_paid']);

        $balance = $invoice_amounts - $amount_paid;

        //Get Monthly Recurring Total
        $sql_recurring_monthly_total = mysqli_query($mysqli, "SELECT SUM(recurring_amount) AS recurring_monthly_total FROM recurring WHERE recurring_status = 1 AND recurring_frequency = 'month' AND recurring_client_id = $client_id");
        $row = mysqli_fetch_array($sql_recurring_monthly_total);

        $recurring_monthly_total = floatval($row['recurring_monthly_total']);

        //Get Yearly Recurring Total
        $sql_recurring_yearly_total = mysqli_query($mysqli, "SELECT SUM(recurring_amount) AS recurring_yearly_total FROM recurring WHERE recurring_status = 1 AND recurring_frequency = 'year' AND recurring_client_id = $client_id");
        $row = mysqli_fetch_array($sql_recurring_yearly_total);

        $recurring_yearly_total = floatval($row['recurring_yearly_total']) / 12;

        $recurring_monthly = $recurring_monthly_total + $recurring_yearly_total;

        //Badge Counts

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('contact_id') AS num FROM contacts WHERE contact_archived_at IS NULL AND contact_client_id = $client_id"));
        $num_contacts = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('location_id') AS num FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id"));
        $num_locations = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('asset_id') AS num FROM assets WHERE asset_archived_at IS NULL AND asset_client_id = $client_id"));
        $num_assets = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('ticket_id') AS num FROM tickets WHERE ticket_archived_at IS NULL AND ticket_status != 'Closed' AND ticket_client_id = $client_id"));
        $num_active_tickets = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('ticket_id') AS num FROM tickets WHERE ticket_archived_at IS NULL AND ticket_status = 'Closed' AND ticket_client_id = $client_id"));
        $num_closed_tickets = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('scheduled_ticket_id') AS num FROM scheduled_tickets WHERE scheduled_ticket_client_id = $client_id"));
        $num_scheduled_tickets = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('service_id') AS num FROM services WHERE service_client_id = $client_id"));
        $num_services = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id AND vendor_template = 0"));
        $num_vendors = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('login_id') AS num FROM logins WHERE login_archived_at IS NULL AND login_client_id = $client_id"));
        $num_logins = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('network_id') AS num FROM networks WHERE network_archived_at IS NULL AND network_client_id = $client_id"));
        $num_networks = $row['num'];

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

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('recurring_id') AS num FROM recurring WHERE recurring_archived_at IS NULL AND recurring_client_id = $client_id"));
        $num_recurring = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('payment_id') AS num FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_client_id = $client_id"));
        $num_payments = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('file_id') AS num FROM files WHERE file_archived_at IS NULL AND file_client_id = $client_id"));
        $num_files = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('document_id') AS num FROM documents WHERE document_archived_at IS NULL AND document_client_id = $client_id"));
        $num_documents = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('event_id') AS num FROM events WHERE event_client_id = $client_id"));
        $num_events = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('trip_id') AS num FROM trips WHERE trip_archived_at IS NULL AND trip_client_id = $client_id"));
        $num_trips = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('item_id') AS num FROM shared_items WHERE item_client_id = $client_id"));
        $num_shared_links = $row['num'];

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('log_id') AS num FROM logs WHERE log_client_id = $client_id"));
        $num_logs = $row['num'];

        // Expiring Items

        // Count Domains Expiring within 30 Days
        $row = mysqli_fetch_assoc(mysqli_query(
            $mysqli,
            "SELECT COUNT('domain_id') AS num FROM domains
            WHERE domain_client_id = $client_id
            AND domain_expire IS NOT NULL
            AND domain_expire < CURRENT_DATE + INTERVAL 30 DAY
            AND domain_archived_at IS NULL"
        ));
        $num_domains_expiring = intval($row['num']);

        // Count Certificates Expiring within 30 Days
        $row = mysqli_fetch_assoc(mysqli_query(
            $mysqli,
            "SELECT COUNT('certificate_id') AS num FROM certificates
            WHERE certificate_client_id = $client_id
            AND certificate_expire IS NOT NULL
            AND certificate_expire < CURRENT_DATE + INTERVAL 30 DAY
            AND certificate_archived_at IS NULL"
        ));
        $num_certs_expiring = intval($row['num']);

    }
}

require_once "client_side_nav.php";

require_once "inc_wrapper.php";

require_once "inc_alert_feedback.php";

require_once "inc_client_top_head.php";

require_once "pagination_head.php";

