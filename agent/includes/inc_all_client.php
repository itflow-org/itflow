<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/check_login.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/page_title.php';

// Perms
enforceUserPermission('module_client');

if (isset($_GET['client_id'])) {
    $client_id = intval($_GET['client_id']);

    // Client Access Check
    enforceClientAccess();

    $sql = mysqli_query($mysqli, "UPDATE clients SET client_accessed_at = NOW() WHERE client_id = $client_id");

    $sql = mysqli_query(
        $mysqli,
        "SELECT client_abbreviation, client_archived_at, client_created_at, client_currency_code,
            client_lead, client_name, client_net_terms, client_notes, client_rate, client_referral,
            client_tax_id_number, client_type, client_website, contact_email, contact_extension,
            contact_id, contact_mobile, contact_mobile_country_code, contact_name, contact_phone,
            contact_phone_country_code, contact_primary, contact_title, location_address,
            location_city, location_country, location_id, location_name, location_phone,
            location_phone_country_code, location_primary, location_state, location_zip FROM clients
        LEFT JOIN locations ON client_id = location_client_id AND location_primary = 1
        LEFT JOIN contacts ON client_id = contact_client_id AND contact_primary = 1
        WHERE client_id = $client_id"
    );

    if (mysqli_num_rows($sql) == 0) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';

        echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='javascript:history.back()'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";
        exit;
    } else {

        $row = mysqli_fetch_assoc($sql);
        $client_name = escapeHtml($row['client_name']);
        $client_name_truncated = escapeHtml(truncate($row['client_name'], 7));
        $client_is_lead = intval($row['client_lead']);
        $client_type = escapeHtml($row['client_type']);
        $client_website = escapeHtml($row['client_website']);
        $client_referral = escapeHtml($row['client_referral']);
        $client_currency_code = escapeHtml($row['client_currency_code']);
        $client_net_terms = intval($row['client_net_terms']);
        $client_tax_id_number = escapeHtml($row['client_tax_id_number']);
        $client_abbreviation = escapeHtml($row['client_abbreviation']);
        $client_rate = floatval($row['client_rate']);
        $client_notes = escapeHtml($row['client_notes']);
        $client_created_at = escapeHtml($row['client_created_at']);
        $client_archived_at = escapeHtml($row['client_archived_at']);
        $contact_id = intval($row['contact_id']);
        $contact_name = escapeHtml($row['contact_name']);
        $contact_title = escapeHtml($row['contact_title']);
        $contact_email = escapeHtml($row['contact_email']);
        $contact_phone_country_code = escapeHtml($row['contact_phone_country_code']);
        $contact_phone = escapeHtml(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
        $contact_extension = escapeHtml($row['contact_extension']);
        $contact_mobile_country_code = escapeHtml($row['contact_mobile_country_code']);
        $contact_mobile = escapeHtml(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));
        $contact_primary = intval($row['contact_primary']);
        $location_id = intval($row['location_id']);
        $location_name = escapeHtml($row['location_name']);
        $location_address = escapeHtml($row['location_address']);
        $location_city = escapeHtml($row['location_city']);
        $location_state = escapeHtml($row['location_state']);
        $location_zip = escapeHtml($row['location_zip']);
        $location_country = escapeHtml($row['location_country']);
        $location_full_address = formatAddress($location_address, $location_city, $location_state, $location_zip, $location_country, '<br>') ?: '-';
        $location_phone_country_code = escapeHtml($row['location_phone_country_code']);
        $location_phone = escapeHtml(formatPhoneNumber($row['location_phone'], $location_phone_country_code));
        $location_primary = intval($row['location_primary']);

        // Tab Title // No Sanitizing needed
        $tab_title = $row['client_name'];

        // Client Tags

        $client_tag_name_display_array = array();
        $client_tag_id_array = array();
        $sql_client_tags = mysqli_query($mysqli, "SELECT tag_color, tag_icon, client_tags.tag_id, tag_name FROM client_tags LEFT JOIN tags ON client_tags.tag_id = tags.tag_id WHERE client_id = $client_id ORDER BY tag_name ASC");
        while ($row = mysqli_fetch_assoc($sql_client_tags)) {

            $client_tag_id = intval($row['tag_id']);
            $client_tag_name = escapeHtml($row['tag_name']);
            $client_tag_color = escapeHtml($row['tag_color']);
            if (empty($client_tag_color)) {
                $client_tag_color = "dark";
            }
            $client_tag_icon = escapeHtml($row['tag_icon']);
            if (empty($client_tag_icon)) {
                $client_tag_icon = "tag";
            }

            $client_tag_id_array[] = $client_tag_id;
            $client_tag_name_display_array[] = "<span class='badge text-light p-1 me-1' style='background-color: $client_tag_color;'><i class='fa fa-fw fa-$client_tag_icon me-2'></i>$client_tag_name</span>";
        }
        $client_tags_display = implode('', $client_tag_name_display_array);

        /*
         * Client badge counts and money totals.
         *
         * These feed the client side nav badges and the client header card, so
         * every page in the client context pays for them before it renders a
         * byte. They used to be forty separate queries, and none of the
         * *_client_id columns they filter on carries an index, so each one was
         * a full table scan - invoices alone was scanned eight times per page
         * load, tickets twice, domains / certificates / software three times
         * each.
         *
         * Same numbers, one query per table. Conditions that used to sit in a
         * WHERE clause are conditional aggregates instead, so a table is read
         * once and every figure taken from it comes out of that single pass.
         * COALESCE is needed because SUM() over no rows is NULL where COUNT()
         * was 0, and these are printed straight into the nav.
         *
         * Deliberately NOT merged any further: folding the remaining
         * single-table counts into one SELECT of scalar subqueries would save
         * round trips but not scans, and the scans are what costs. Indexes on
         * (<entity>_client_id, <entity>_archived_at) are the other half of
         * this and are a schema change, not a code one.
         */

        // Invoices - money total plus every status count in one pass.
        // The money total ignores invoice_archived_at, matching the original
        // query; every count filters on it. Keeping both in one scan means the
        // archived test has to move out of the WHERE and into the aggregates.
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
            COALESCE(SUM(CASE WHEN invoice_status NOT IN ('Draft', 'Cancelled', 'Non-Billable') THEN invoice_amount END), 0) AS invoice_amounts,
            COALESCE(SUM(invoice_archived_at IS NULL), 0) AS num_invoices,
            COALESCE(SUM(invoice_archived_at IS NULL AND invoice_status = 'Draft'), 0) AS num_invoices_draft,
            COALESCE(SUM(invoice_archived_at IS NULL AND invoice_status = 'Sent'), 0) AS num_invoices_sent,
            COALESCE(SUM(invoice_archived_at IS NULL AND invoice_status = 'Viewed'), 0) AS num_invoices_viewed,
            COALESCE(SUM(invoice_archived_at IS NULL AND invoice_status = 'Partial'), 0) AS num_invoices_partial,
            COALESCE(SUM(invoice_archived_at IS NULL AND invoice_status = 'Paid'), 0) AS num_invoices_paid,
            COALESCE(SUM(invoice_archived_at IS NULL AND invoice_status IN ('Sent', 'Viewed', 'Partial')), 0) AS num_invoices_open
            FROM invoices WHERE invoice_client_id = $client_id"));

        $invoice_amounts = floatval($row['invoice_amounts']);
        $num_invoices = intval($row['num_invoices']);
        $num_invoices_draft = intval($row['num_invoices_draft']);
        $num_invoices_sent = intval($row['num_invoices_sent']);
        $num_invoices_viewed = intval($row['num_invoices_viewed']);
        $num_invoices_partial = intval($row['num_invoices_partial']);
        $num_invoices_paid = intval($row['num_invoices_paid']);
        $num_invoices_open = intval($row['num_invoices_open']);

        // Payments - amount paid and the payment half of the income count.
        // The sum ignores payment_archived_at, the count does not, same as before.
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
            COALESCE(SUM(payment_amount), 0) AS amount_paid,
            COALESCE(SUM(payment_archived_at IS NULL), 0) AS num_payments
            FROM payments, invoices
            WHERE payment_invoice_id = invoice_id AND invoice_client_id = $client_id"));

        $amount_paid = floatval($row['amount_paid']);
        $num_payments = intval($row['num_payments']);

        $balance = $invoice_amounts - $amount_paid;

        // Revenues that are not the far side of a transfer.
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(revenue_id) AS num
            FROM revenues LEFT JOIN transfers ON transfer_revenue_id = revenue_id
            WHERE transfer_id IS NULL AND revenue_archived_at IS NULL AND revenue_client_id = $client_id"));

        $num_income = $num_payments + intval($row['num']);

        // Recurring invoices - the monthly and yearly totals and the count.
        // The two totals key off recurring_invoice_status, the count off
        // recurring_invoice_archived_at, so neither test can sit in the WHERE.
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
            COALESCE(SUM(CASE WHEN recurring_invoice_status = 1 AND recurring_invoice_frequency = 'month' THEN recurring_invoice_amount END), 0) AS recurring_monthly_total,
            COALESCE(SUM(CASE WHEN recurring_invoice_status = 1 AND recurring_invoice_frequency = 'year' THEN recurring_invoice_amount END), 0) AS recurring_yearly_total,
            COALESCE(SUM(recurring_invoice_archived_at IS NULL), 0) AS num_recurring_invoices
            FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id"));

        $recurring_monthly_total = floatval($row['recurring_monthly_total']);
        $recurring_yearly_total = floatval($row['recurring_yearly_total']) / 12;
        $recurring_monthly = $recurring_monthly_total + $recurring_yearly_total;
        $num_recurring_invoices = intval($row['num_recurring_invoices']);

        // Get Credit Balance
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(SUM(credit_amount), 0) AS credit_balance
            FROM credits WHERE credit_client_id = $client_id"));

        $credit_balance = floatval($row['credit_balance']);

        // Tickets - active and closed in one pass.
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
            COALESCE(SUM(ticket_closed_at IS NULL AND ticket_status != 4), 0) AS num_active_tickets,
            COALESCE(SUM(ticket_closed_at IS NOT NULL), 0) AS num_closed_tickets
            FROM tickets WHERE ticket_archived_at IS NULL AND ticket_client_id = $client_id"));

        $num_active_tickets = intval($row['num_active_tickets']);
        $num_closed_tickets = intval($row['num_closed_tickets']);

        // Domains - total plus both expiry buckets in one pass.
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
            COUNT(domain_id) AS num_domains,
            COALESCE(SUM(domain_expire IS NOT NULL AND domain_expire < CURRENT_DATE + INTERVAL 45 DAY), 0) AS num_domains_expiring_warning,
            COALESCE(SUM(domain_expire IS NOT NULL AND (domain_expire < CURRENT_DATE OR domain_expire < CURRENT_DATE + INTERVAL 7 DAY)), 0) AS num_domains_urgent
            FROM domains WHERE domain_archived_at IS NULL AND domain_client_id = $client_id"));

        $num_domains = intval($row['num_domains']);
        $num_domains_expiring_warning = intval($row['num_domains_expiring_warning']);
        $num_domains_urgent = intval($row['num_domains_urgent']);

        // Certificates - total plus both expiry buckets in one pass.
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
            COUNT(certificate_id) AS num_certificates,
            COALESCE(SUM(certificate_expire IS NOT NULL AND certificate_expire < CURRENT_DATE + INTERVAL 7 DAY), 0) AS num_certificates_expiring,
            COALESCE(SUM(certificate_expire IS NOT NULL AND (certificate_expire < CURRENT_DATE OR certificate_expire < CURRENT_DATE + INTERVAL 1 DAY)), 0) AS num_certificates_expired
            FROM certificates WHERE certificate_archived_at IS NULL AND certificate_client_id = $client_id"));

        $num_certificates = intval($row['num_certificates']);
        $num_certificates_expiring = intval($row['num_certificates_expiring']);
        $num_certificates_expired = intval($row['num_certificates_expired']);

        // Software - total plus both expiry buckets in one pass.
        // The 45-day window is what the original query used; the comment above
        // it said 90 days and was wrong.
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
            COUNT(software_id) AS num_software,
            COALESCE(SUM(software_expire IS NOT NULL AND software_expire < CURRENT_DATE + INTERVAL 45 DAY), 0) AS num_software_expiring,
            COALESCE(SUM(software_expire IS NOT NULL AND (software_expire < CURRENT_DATE OR software_expire < CURRENT_DATE + INTERVAL 7 DAY)), 0) AS num_software_expired
            FROM software WHERE software_archived_at IS NULL AND software_client_id = $client_id"));

        $num_software = intval($row['num_software']);
        $num_software_expiring = intval($row['num_software_expiring']);
        $num_software_expired = intval($row['num_software_expired']);

        // One count each, one table each - nothing to fold together.
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(contact_id) AS num FROM contacts WHERE contact_archived_at IS NULL AND contact_client_id = $client_id"));
        $num_contacts = intval($row['num']);

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(location_id) AS num FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id"));
        $num_locations = intval($row['num']);

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(asset_id) AS num FROM assets WHERE asset_archived_at IS NULL AND asset_client_id = $client_id"));
        $num_assets = intval($row['num']);

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(recurring_ticket_id) AS num FROM recurring_tickets WHERE recurring_ticket_client_id = $client_id"));
        $num_recurring_tickets = intval($row['num']);

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(project_id) AS num FROM projects WHERE project_archived_at IS NULL AND project_completed_at IS NULL AND project_client_id = $client_id"));
        $num_active_projects = intval($row['num']);

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(service_id) AS num FROM services WHERE service_client_id = $client_id"));
        $num_services = intval($row['num']);

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(vendor_id) AS num FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id"));
        $num_vendors = intval($row['num']);

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(credential_id) AS num FROM credentials WHERE credential_archived_at IS NULL AND credential_client_id = $client_id"));
        $num_credentials = intval($row['num']);

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(network_id) AS num FROM networks WHERE network_archived_at IS NULL AND network_client_id = $client_id"));
        $num_networks = intval($row['num']);

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(rack_id) AS num FROM racks WHERE rack_archived_at IS NULL AND rack_client_id = $client_id"));
        $num_racks = intval($row['num']);

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(quote_id) AS num FROM quotes WHERE quote_archived_at IS NULL AND quote_client_id = $client_id"));
        $num_quotes = intval($row['num']);

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(file_id) AS num FROM files WHERE file_archived_at IS NULL AND file_client_id = $client_id"));
        $num_files = intval($row['num']);

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(document_id) AS num FROM documents WHERE document_archived_at IS NULL AND document_client_id = $client_id"));
        $num_documents = intval($row['num']);

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(event_id) AS num FROM calendar_events WHERE event_client_id = $client_id"));
        $num_calendar_events = intval($row['num']);

        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(trip_id) AS num FROM trips WHERE trip_archived_at IS NULL AND trip_client_id = $client_id"));
        $num_trips = intval($row['num']);

    }
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/top_nav.php';
require_once 'includes/client_side_nav.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/inc_wrapper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/inc_alert_feedback.php';
require_once 'includes/inc_client_top_head.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/filter_header.php';
