<?php

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/get_settings.php";

session_start();

require_once "../includes/inc_set_timezone.php"; // Must be included after session_start to work

if (isset($_GET['accept_quote'], $_GET['url_key'])) {
    $quote_id = intval($_GET['accept_quote']);
    $url_key = sanitizeInput($_GET['url_key']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT quote_prefix, quote_number, client_name, client_id FROM quotes LEFT JOIN clients ON quote_client_id = client_id WHERE quote_id = $quote_id AND quote_url_key = '$url_key'");

    if (mysqli_num_rows($sql) == 1) {
        $row = mysqli_fetch_array($sql);
        $quote_prefix = sanitizeInput($row['quote_prefix']);
        $quote_number = intval($row['quote_number']);
        $client_name = sanitizeInput($row['client_name']);
        $client_id = intval($row['client_id']);

        mysqli_query($mysqli, "UPDATE quotes SET quote_status = 'Accepted' WHERE quote_id = $quote_id");
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Accepted', history_description = 'Client accepted Quote!', history_quote_id = $quote_id");

        // Notification
        appNotify("Quote Accepted", "Quote $quote_prefix$quote_number has been accepted by $client_name", "quote.php?quote_id=$quote_id", $client_id);
        customAction('quote_accept', $quote_id);

        // Internal email notification

        $sql_company = mysqli_query($mysqli, "SELECT company_name FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql_company);
        $company_name = sanitizeInput($row['company_name']);

        $sql_settings = mysqli_query($mysqli, "SELECT * FROM settings WHERE company_id = 1");
        $row = mysqli_fetch_array($sql_settings);
        $config_smtp_host = $row['config_smtp_host'];
        $config_smtp_port = intval($row['config_smtp_port']);
        $config_smtp_encryption = $row['config_smtp_encryption'];
        $config_smtp_username = $row['config_smtp_username'];
        $config_smtp_password = $row['config_smtp_password'];
        $config_quote_from_name = sanitizeInput($row['config_quote_from_name']);
        $config_quote_from_email = sanitizeInput($row['config_quote_from_email']);
        $config_quote_notification_email = sanitizeInput($row['config_quote_notification_email']);
        $config_base_url = sanitizeInput($config_base_url);

        if (!empty($config_smtp_host) && !empty($config_quote_notification_email)) {
            $subject = "Quote Accepted - $client_name - Quote $quote_prefix$quote_number";
            $body = "Hello, <br><br>This is a notification that a quote has been accepted in ITFlow. <br><br>Client: $client_name<br>Quote: <a href=\'https://$config_base_url/quote.php?quote_id=$quote_id\'>$quote_prefix$quote_number</a><br><br>~<br>$company_name - Billing<br>$config_quote_from_email";

            $data[] = [
                'from' => $config_quote_from_email,
                'from_name' => $config_quote_from_name,
                'recipient' => $config_quote_notification_email,
                'subject' => $subject,
                'body' => $body,
            ];

            $mail = addToMailQueue($data);
        }

        $_SESSION['alert_message'] = "Quote Accepted";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    } else {
        echo "Invalid!!";
    }
}

if (isset($_GET['decline_quote'], $_GET['url_key'])) {
    $quote_id = intval($_GET['decline_quote']);
    $url_key = sanitizeInput($_GET['url_key']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT quote_prefix, quote_number, client_name, client_id FROM quotes LEFT JOIN clients ON quote_client_id = client_id WHERE quote_id = $quote_id AND quote_url_key = '$url_key'");

    if (mysqli_num_rows($sql) == 1) {
        $row = mysqli_fetch_array($sql);
        $quote_prefix = sanitizeInput($row['quote_prefix']);
        $quote_number = intval($row['quote_number']);
        $client_name = sanitizeInput($row['client_name']);
        $client_id = intval($row['client_id']);

        mysqli_query($mysqli, "UPDATE quotes SET quote_status = 'Declined' WHERE quote_id = $quote_id");
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Declined', history_description = 'Client declined Quote!', history_quote_id = $quote_id");

        // Notification
        appNotify("Quote Declined", "Quote $quote_prefix$quote_number has been declined by $client_name", "quote.php?quote_id=$quote_id", $client_id);
        customAction('quote_decline', $quote_id);

        // Internal email notification

        $sql_company = mysqli_query($mysqli, "SELECT company_name FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql_company);
        $company_name = sanitizeInput($row['company_name']);

        $sql_settings = mysqli_query($mysqli, "SELECT * FROM settings WHERE company_id = 1");
        $row = mysqli_fetch_array($sql_settings);
        $config_smtp_host = $row['config_smtp_host'];
        $config_smtp_port = intval($row['config_smtp_port']);
        $config_smtp_encryption = $row['config_smtp_encryption'];
        $config_smtp_username = $row['config_smtp_username'];
        $config_smtp_password = $row['config_smtp_password'];
        $config_quote_from_name = sanitizeInput($row['config_quote_from_name']);
        $config_quote_from_email = sanitizeInput($row['config_quote_from_email']);
        $config_quote_notification_email = sanitizeInput($row['config_quote_notification_email']);
        $config_base_url = sanitizeInput($config_base_url);

        if (!empty($config_smtp_host) && !empty($config_quote_notification_email)) {
            $subject = "Quote Declined - $client_name - Quote $quote_prefix$quote_number";
            $body = "Hello, <br><br>This is a notification that a quote has been declined in ITFlow. <br><br>Client: $client_name<br>Quote: <a href=\'https://$config_base_url/quote.php?quote_id=$quote_id\'>$quote_prefix$quote_number</a><br><br>~<br>$company_name - Billing<br>$config_quote_from_email";

            $data[] = [
                'from' => $config_quote_from_email,
                'from_name' => $config_quote_from_name,
                'recipient' => $config_quote_notification_email,
                'subject' => $subject,
                'body' => $body,
            ];

            $mail = addToMailQueue($data);
        }

        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = "Quote Declined";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    } else {
        echo "Invalid!!";
    }
}

if (isset($_GET['reopen_ticket'], $_GET['url_key'])) {
    $ticket_id = intval($_GET['ticket_id']);
    $url_key = sanitizeInput($_GET['url_key']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key' AND ticket_resolved_at IS NOT NULL AND ticket_closed_at IS NULL");

    if (mysqli_num_rows($sql) == 1) {
        // Update the ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 2, ticket_resolved_at = NULL WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key'");
        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket reopened by client (guest URL).', ticket_reply_type = 'Internal', ticket_reply_by = 0, ticket_reply_ticket_id = $ticket_id");
        // Logging
        customAction('ticket_update', $ticket_id);
        $_SESSION['alert_message'] = "Ticket reopened";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    } else {
        echo "Invalid!!";
    }
}

if (isset($_GET['close_ticket'], $_GET['url_key'])) {
    $ticket_id = intval($_GET['ticket_id']);
    $url_key = sanitizeInput($_GET['url_key']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key' AND ticket_resolved_at IS NOT NULL AND ticket_closed_at IS NULL");

    if (mysqli_num_rows($sql) == 1) {
        // Update the ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 5, ticket_closed_at = NOW() WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key'");
        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket closed by client (guest URL).', ticket_reply_type = 'Internal', ticket_reply_by = 0, ticket_reply_ticket_id = $ticket_id");
        // Logging
        customAction('ticket_close', $ticket_id);
        $_SESSION['alert_message'] = "Ticket closed";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    } else {
        echo "Invalid!!";
    }
}

if (isset($_GET['add_ticket_feedback'], $_GET['url_key'])) {
    $ticket_id = intval($_GET['ticket_id']);
    $url_key = sanitizeInput($_GET['url_key']);
    $feedback = sanitizeInput($_GET['feedback']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key' AND ticket_closed_at IS NOT NULL");

    if (mysqli_num_rows($sql) == 1) {
        // Add feedback
        mysqli_query($mysqli, "UPDATE tickets SET ticket_feedback = '$feedback' WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key'");

        // Notify on bad feedback
        if ($feedback == "Bad") {
            $ticket_details = mysqli_fetch_array(mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number FROM tickets WHERE ticket_id = $ticket_id LIMIT 1"));
            $ticket_prefix = sanitizeInput($ticket_details['ticket_prefix']);
            $ticket_number = intval($ticket_details['ticket_number']);

            appNotify("Feedback", "Guest rated ticket number $ticket_prefix$ticket_number (ID: $ticket_id) as bad", "ticket.php?ticket_id=$ticket_id");
        }

        $_SESSION['alert_message'] = "Feedback recorded - thank you";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        customAction('ticket_feedback', $ticket_id);
    } else {
        echo "Invalid!!";
    }
}

if (isset($_GET['export_quote_pdf'])) {

    $quote_id = intval($_GET['export_quote_pdf']);
    $url_key = sanitizeInput($_GET['url_key']);

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM quotes
        LEFT JOIN clients ON quote_client_id = client_id
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        WHERE quote_id = $quote_id AND quote_url_key = '$url_key'
        LIMIT 1"
    );

    if (mysqli_num_rows($sql) == 1) {

        $row = mysqli_fetch_array($sql);
        $quote_id = intval($row['quote_id']);
        $quote_prefix = nullable_htmlentities($row['quote_prefix']);
        $quote_number = intval($row['quote_number']);
        $quote_scope = nullable_htmlentities($row['quote_scope']);
        $quote_status = nullable_htmlentities($row['quote_status']);
        $quote_date = nullable_htmlentities($row['quote_date']);
        $quote_expire = nullable_htmlentities($row['quote_expire']);
        $quote_amount = floatval($row['quote_amount']);
        $quote_discount = floatval($row['quote_discount_amount']);
        $quote_currency_code = nullable_htmlentities($row['quote_currency_code']);
        $quote_note = nullable_htmlentities($row['quote_note']);
        $quote_url_key = nullable_htmlentities($row['quote_url_key']);
        $quote_created_at = nullable_htmlentities($row['quote_created_at']);
        $category_id = intval($row['quote_category_id']);
        $client_id = intval($row['client_id']);
        $client_name = nullable_htmlentities($row['client_name']);
        $location_address = nullable_htmlentities($row['location_address']);
        $location_city = nullable_htmlentities($row['location_city']);
        $location_state = nullable_htmlentities($row['location_state']);
        $location_zip = nullable_htmlentities($row['location_zip']);
        $location_country = nullable_htmlentities($row['location_country']);
        $contact_email = nullable_htmlentities($row['contact_email']);
        $contact_phone_country_code = nullable_htmlentities($row['contact_phone_country_code']);
        $contact_phone = nullable_htmlentities(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
        $contact_extension = nullable_htmlentities($row['contact_extension']);
        $contact_mobile_country_code = nullable_htmlentities($row['contact_mobile_country_code']);
        $contact_mobile = nullable_htmlentities(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));
        $client_website = nullable_htmlentities($row['client_website']);
        $client_currency_code = nullable_htmlentities($row['client_currency_code']);
        $client_net_terms = intval($row['client_net_terms']);
        if ($client_net_terms == 0) {
            $client_net_terms = $config_default_net_terms;
        }

        $sql = mysqli_query($mysqli, "SELECT * FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");
        $row = mysqli_fetch_array($sql);

        $company_id = intval($row['company_id']);
        $company_name = nullable_htmlentities($row['company_name']);
        $company_country = nullable_htmlentities($row['company_country']);
        $company_address = nullable_htmlentities($row['company_address']);
        $company_city = nullable_htmlentities($row['company_city']);
        $company_state = nullable_htmlentities($row['company_state']);
        $company_zip = nullable_htmlentities($row['company_zip']);
        $company_phone_country_code = nullable_htmlentities($row['company_phone_country_code']);
        $company_phone = nullable_htmlentities(formatPhoneNumber($row['company_phone'], $company_phone_country_code));
        $company_email = nullable_htmlentities($row['company_email']);
        $company_website = nullable_htmlentities($row['company_website']);
        $company_logo = nullable_htmlentities($row['company_logo']);
        $company_locale = nullable_htmlentities($row['company_locale']);
        //Set Currency Format
        $currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

        require_once("../plugins/TCPDF/tcpdf.php");

        // Start TCPDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        // Logo + Right Columns
        $html = '<table width="100%" cellspacing="0" cellpadding="3">
        <tr>
            <td width="40%">';
        if (!empty($company_logo) && file_exists("../uploads/settings/$company_logo")) {
            $html .= '<img src="/uploads/settings/' . $company_logo . '" width="120">';
        }
        $html .= '</td>
            <td width="60%" align="right">
                <span style="font-size:18pt; font-weight:bold;">QUOTE</span><br>
                <span style="font-size:14pt;">' . $quote_prefix . $quote_number . '</span><br>';
        if (strtolower($quote_status) === 'accepted') {
            $html .= '<span style="color:green; font-weight:bold;">ACCEPTED</span><br>';
        }
        if (strtolower($quote_status) === 'declined') {
            $html .= '<span style="color:red; font-weight:bold;">DECLINED</span><br>';
        }
        $html .= '</td>
        </tr>
        </table><br>';

        // Billing titles
        $html .= '<table width="100%" cellspacing="0" cellpadding="2">
        <tr>
            <td width="50%" style="font-size:14pt; font-weight:bold;">' . $company_name . '</td>
            <td width="50%" align="right" style="font-size:14pt; font-weight:bold;">' . $client_name . '</td>
        </tr>
        <tr>
            <td style="font-size:10pt; line-height:1.4;">' . nl2br("$company_address\n$company_city $company_state $company_zip\n$company_country\n$company_phone\n$company_website") . '</td>
            <td style="font-size:10pt; line-height:1.4;" align="right">' . nl2br("$location_address\n$location_city $location_state $location_zip\n$location_country\n$contact_email\n$contact_phone") . '</td>
        </tr>
        </table><br>';

        // Date table
        $html .= '<table border="0" cellpadding="2" cellspacing="0" width="100%">
        <tr>
            <td width="60%"></td>
            <td width="20%" style="font-size:10pt;"><strong>Date:</strong></td>
            <td width="20%" style="font-size:10pt;" align="right">' . $quote_date . '</td>
        </tr>
        <tr>
            <td></td>
            <td style="font-size:10pt;"><strong>Expires:</strong></td>
            <td style="font-size:10pt;" align="right">' . $quote_expire . '</td>
        </tr>
        </table><br><br>';

        // Items header
        $html .= '
        <table border="0" cellpadding="5" cellspacing="0" width="100%">
        <tr style="background-color:#f0f0f0;">
            <th align="left" width="40%"><strong>Item</strong></th>
            <th align="center" width="10%"><strong>Qty</strong></th>
            <th align="right" width="15%"><strong>Price</strong></th>
            <th align="right" width="15%"><strong>Tax</strong></th>
            <th align="right" width="20%"><strong>Amount</strong></th>
        </tr>';

        // Load items
        $sql_items = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE item_quote_id = $quote_id ORDER BY item_order ASC");
        while ($item = mysqli_fetch_array($sql_items)) {
            $name = $item['item_name'];
            $desc = $item['item_description'];
            $qty = $item['item_quantity'];
            $price = $item['item_price'];
            $tax = $item['item_tax'];
            $total = $item['item_total'];

            $sub_total += $price * $qty;
            $total_tax += $tax;

            $html .= '
            <tr>
                <td><strong>' . $name . '</strong>
                    <br><span style="font-style:italic; font-size:9pt;">' . nl2br($desc) . '</span>
                </td>
                <td align="center">' . number_format($qty, 2) . '</td>
                <td align="right">' . numfmt_format_currency($currency_format, $price, $quote_currency_code) . '</td>
                <td align="right">' . numfmt_format_currency($currency_format, $tax, $quote_currency_code) . '</td>
                <td align="right">' . numfmt_format_currency($currency_format, $total, $quote_currency_code) . '</td>
            </tr>';
        }

        $html .= '</table><br><hr><br><br>';

        // Totals
        $html .= '<table width="100%" cellspacing="0" cellpadding="4">
        <tr>
            <td width="70%" rowspan="6" valign="top"><i>' . nl2br($quote_note) . '</i></td>
            <td width="30%">
                <table width="100%" cellpadding="3" cellspacing="0">
                    <tr><td>Subtotal:</td><td align="right">' . numfmt_format_currency($currency_format, $sub_total, $quote_currency_code) . '</td></tr>';
        if ($quote_discount > 0) {
            $html .= '<tr><td>Discount:</td><td align="right">-' . numfmt_format_currency($currency_format, $quote_discount, $quote_currency_code) . '</td></tr>';
        }
        if ($total_tax > 0) {
            $html .= '<tr><td>Tax:</td><td align="right">' . numfmt_format_currency($currency_format, $total_tax, $quote_currency_code) . '</td></tr>';
        }
        $html .= '
        <tr><td><h3><strong>Total:</strong></h3></td><td align="right"><h3><strong>' . numfmt_format_currency($currency_format, $quote_amount, $quote_currency_code) . '</strong></h3></td></tr>
        </table>
            </td>
        </tr>
        </table><br><br>';

        // Footer
        $html .= '<div style="text-align:center; font-size:9pt; color:gray;">' . nl2br($config_quote_footer) . '</div>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', "{$quote_date}_{$company_name}_{$client_name}_Quote_{$quote_prefix}{$quote_number}");
        $pdf->Output("$filename.pdf", 'I');
    }
    exit;
}

if (isset($_GET['export_invoice_pdf'])) {

    $invoice_id = intval($_GET['export_invoice_pdf']);
    $url_key = sanitizeInput($_GET['url_key']);

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        WHERE invoice_id = $invoice_id AND invoice_url_key = '$url_key'
        LIMIT 1"
    );

    if (mysqli_num_rows($sql) == 1) {

        $row = mysqli_fetch_array($sql);
        $invoice_id = intval($row['invoice_id']);
        $invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
        $invoice_number = intval($row['invoice_number']);
        $invoice_scope = nullable_htmlentities($row['invoice_scope']);
        $invoice_status = nullable_htmlentities($row['invoice_status']);
        $invoice_date = nullable_htmlentities($row['invoice_date']);
        $invoice_due = nullable_htmlentities($row['invoice_due']);
        $invoice_amount = floatval($row['invoice_amount']);
        $invoice_discount = floatval($row['invoice_discount_amount']);
        $invoice_currency_code = nullable_htmlentities($row['invoice_currency_code']);
        $invoice_note = nullable_htmlentities($row['invoice_note']);
        $invoice_url_key = nullable_htmlentities($row['invoice_url_key']);
        $invoice_created_at = nullable_htmlentities($row['invoice_created_at']);
        $category_id = intval($row['invoice_category_id']);
        $client_id = intval($row['client_id']);
        $client_name = nullable_htmlentities($row['client_name']);
        $location_address = nullable_htmlentities($row['location_address']);
        $location_city = nullable_htmlentities($row['location_city']);
        $location_state = nullable_htmlentities($row['location_state']);
        $location_zip = nullable_htmlentities($row['location_zip']);
        $location_country = nullable_htmlentities($row['location_country']);
        $contact_email = nullable_htmlentities($row['contact_email']);
        $contact_phone_country_code = nullable_htmlentities($row['contact_phone_country_code']);
        $contact_phone = nullable_htmlentities(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
        $contact_extension = nullable_htmlentities($row['contact_extension']);
        $contact_mobile_country_code = nullable_htmlentities($row['contact_mobile_country_code']);
        $contact_mobile = nullable_htmlentities(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));
        $client_website = nullable_htmlentities($row['client_website']);
        $client_currency_code = nullable_htmlentities($row['client_currency_code']);
        $client_net_terms = intval($row['client_net_terms']);
        if ($client_net_terms == 0) {
            $client_net_terms = $config_default_net_terms;
        }

        $sql = mysqli_query($mysqli, "SELECT * FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);
        $company_id = intval($row['company_id']);
        $company_name = nullable_htmlentities($row['company_name']);
        $company_country = nullable_htmlentities($row['company_country']);
        $company_address = nullable_htmlentities($row['company_address']);
        $company_city = nullable_htmlentities($row['company_city']);
        $company_state = nullable_htmlentities($row['company_state']);
        $company_zip = nullable_htmlentities($row['company_zip']);
        $company_phone_country_code = nullable_htmlentities($row['company_phone_country_code']);
        $company_phone = nullable_htmlentities(formatPhoneNumber($row['company_phone'], $company_phone_country_code));
        $company_email = nullable_htmlentities($row['company_email']);
        $company_website = nullable_htmlentities($row['company_website']);
        $company_tax_id = nullable_htmlentities($row['company_tax_id']);
        if ($config_invoice_show_tax_id && !empty($company_tax_id)) {
            $company_tax_id_display = "Tax ID: $company_tax_id";
        } else {
            $company_tax_id_display = "";
        }
        $company_logo = nullable_htmlentities($row['company_logo']);
        $company_locale = nullable_htmlentities($row['company_locale']);
        //Set Currency Format
        $currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

        $sql_payments = mysqli_query($mysqli, "SELECT * FROM payments, accounts WHERE payment_account_id = account_id AND payment_invoice_id = $invoice_id ORDER BY payments.payment_id DESC");

        //Add up all the payments for the invoice and get the total amount paid to the invoice
        $sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
        $row = mysqli_fetch_array($sql_amount_paid);
        $amount_paid = floatval($row['amount_paid']);

        $balance = $invoice_amount - $amount_paid;

        //check to see if overdue
        if ($invoice_status !== "Paid" && $invoice_status !== "Draft" && $invoice_status !== "Cancelled" && $invoice_status !== "Non-Billable") {
            $unixtime_invoice_due = strtotime($invoice_due) + 86400;
            if ($unixtime_invoice_due < time()) {
                $invoice_overdue = "Overdue";
            }
        }

        //Set Badge color based off of invoice status
        $invoice_badge_color = getInvoiceBadgeColor($invoice_status);

        require_once("../plugins/TCPDF/tcpdf.php");

        // Start TCPDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        // Logo + Right Columns
        $html = '<table width="100%" cellspacing="0" cellpadding="3">
        <tr>
            <td width="40%">';
        if (!empty($company_logo) && file_exists("../uploads/settings/$company_logo")) {
            $html .= '<img src="/uploads/settings/' . $company_logo . '" width="120">';
        }
        $html .= '</td>
            <td width="60%" align="right">
                <span style="font-size:18pt; font-weight:bold;">Invoice</span><br>
                <span style="font-size:14pt;">' . $invoice_prefix . $invoice_number . '</span><br>';
        if (strtolower($invoice_status) === 'paid') {
            $html .= '<span style="color:green; font-weight:bold;">PAID</span><br>';
        }
        $html .= '</td>
        </tr>
        </table><br>';

        // Billing titles
        $html .= '<table width="100%" cellspacing="0" cellpadding="2">
        <tr>
            <td width="50%" style="font-size:14pt; font-weight:bold;">' . $company_name . '</td>
            <td width="50%" align="right" style="font-size:14pt; font-weight:bold;">' . $client_name . '</td>
        </tr>
        <tr>
            <td style="font-size:10pt; line-height:1.4;">' . nl2br("$company_address\n$company_city $company_state $company_zip\n$company_country\n$company_phone\n$company_website\n$company_tax_id_display") . '</td>
            <td style="font-size:10pt; line-height:1.4;" align="right">' . nl2br("$location_address\n$location_city $location_state $location_zip\n$location_country\n$contact_email\n$contact_phone") . '</td>
        </tr>
        </table><br>';

        // Date table
        $html .= '<table border="0" cellpadding="2" cellspacing="0" width="100%">
        <tr>
            <td width="60%"></td>
            <td width="20%" style="font-size:10pt;"><strong>Date:</strong></td>
            <td width="20%" style="font-size:10pt;" align="right">' . $invoice_date . '</td>
        </tr>
        <tr>
            <td></td>
            <td style="font-size:10pt;"><strong>Due:</strong></td>
            <td style="font-size:10pt;" align="right">' . $invoice_due . '</td>
        </tr>
        </table><br><br>';

        // Items header
        $html .= '
        <table border="0" cellpadding="5" cellspacing="0" width="100%">
        <tr style="background-color:#f0f0f0;">
            <th align="left" width="40%"><strong>Item</strong></th>
            <th align="center" width="10%"><strong>Qty</strong></th>
            <th align="right" width="15%"><strong>Price</strong></th>
            <th align="right" width="15%"><strong>Tax</strong></th>
            <th align="right" width="20%"><strong>Amount</strong></th>
        </tr>';

        // Load items
        $sql_items = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id ORDER BY item_order ASC");
        while ($item = mysqli_fetch_array($sql_items)) {
            $name = $item['item_name'];
            $desc = $item['item_description'];
            $qty = $item['item_quantity'];
            $price = $item['item_price'];
            $tax = $item['item_tax'];
            $total = $item['item_total'];

            $sub_total += $price * $qty;
            $total_tax += $tax;

            $html .= '
            <tr>
                <td><strong>' . $name . '</strong>
                    <br><span style="font-style:italic; font-size:9pt;">' . nl2br($desc) . '</span>
                </td>
                <td align="center">' . number_format($qty, 2) . '</td>
                <td align="right">' . numfmt_format_currency($currency_format, $price, $invoice_currency_code) . '</td>
                <td align="right">' . numfmt_format_currency($currency_format, $tax, $invoice_currency_code) . '</td>
                <td align="right">' . numfmt_format_currency($currency_format, $total, $invoice_currency_code) . '</td>
            </tr>';
        }

        $html .= '</table><br><hr><br><br>';

        // Totals
        $html .= '<table width="100%" cellspacing="0" cellpadding="4">
        <tr>
            <td width="70%" rowspan="6" valign="top"><i>' . nl2br($invoice_note) . '</i></td>
            <td width="30%">
                <table width="100%" cellpadding="3" cellspacing="0">
                    <tr><td>Subtotal:</td><td align="right">' . numfmt_format_currency($currency_format, $sub_total, $invoice_currency_code) . '</td></tr>';
        if ($invoice_discount > 0) {
            $html .= '<tr><td>Discount:</td><td align="right">-' . numfmt_format_currency($currency_format, $invoice_discount, $invoice_currency_code) . '</td></tr>';
        }
        if ($total_tax > 0) {
            $html .= '<tr><td>Tax:</td><td align="right">' . numfmt_format_currency($currency_format, $total_tax, $invoice_currency_code) . '</td></tr>';
        }
        $html .= '
        <tr><td>Total:</td><td align="right">' . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . '</td></tr>';
        if ($amount_paid > 0) {
            $html .= '<tr><td>Paid:</td><td align="right">' . numfmt_format_currency($currency_format, $amount_paid, $invoice_currency_code) . '</td></tr>';
        }
        $html .= '
        <tr><td><h3><strong>Balance:</strong></h3></td><td align="right"><h3><strong>' . numfmt_format_currency($currency_format, $balance, $invoice_currency_code) . '</strong></h3></td></tr>
        </table>
            </td>
        </tr>
        </table><br><br>';

        // Footer
        $html .= '<div style="text-align:center; font-size:9pt; color:gray;">' . nl2br($config_invoice_footer) . '</div>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', "{$invoice_date}_{$company_name}_{$client_name}_Invoice_{$invoice_prefix}{$invoice_number}");
        $pdf->Output("$filename.pdf", 'I');
    }
    
    exit;

}

if (isset($_POST['guest_quote_upload_file'])) {
    $quote_id = intval($_POST['quote_id']);
    $url_key = sanitizeInput($_POST['url_key']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT quote_prefix, quote_number, client_id FROM quotes LEFT JOIN clients ON quote_client_id = client_id WHERE quote_id = $quote_id AND quote_url_key = '$url_key'");

    if (mysqli_num_rows($sql) == 1) {
        $row = mysqli_fetch_array($sql);
        $quote_prefix = sanitizeInput($row['quote_prefix']);
        $quote_number = intval($row['quote_number']);
        $client_id = intval($row['client_id']);

        // Define & create directories, as required
        $upload_file_dir = "../uploads/clients/$client_id/";
        mkdirMissing($upload_file_dir);

        // Store attached any file
        if (!empty($_FILES)) {

            for ($i = 0; $i < count($_FILES['file']['name']); $i++) {
                // Extract file details for this iteration
                $single_file = [
                    'name' => $_FILES['file']['name'][$i],
                    'type' => $_FILES['file']['type'][$i],
                    'tmp_name' => $_FILES['file']['tmp_name'][$i],
                    'error' => $_FILES['file']['error'][$i],
                    'size' => $_FILES['file']['size'][$i]
                ];

                if ($file_reference_name = checkFileUpload($single_file, array('pdf'))) {

                    $file_tmp_path = $_FILES['file']['tmp_name'][$i];

                    $file_name = sanitizeInput($_FILES['file']['name'][$i]);
                    $extarr = explode('.', $_FILES['file']['name'][$i]);
                    $file_extension = sanitizeInput(strtolower(end($extarr)));

                    // Extract the file mime type and size
                    $file_mime_type = sanitizeInput($single_file['type']);
                    $file_size = intval($single_file['size']);

                    // Define destination file path
                    $dest_path = $upload_file_dir . $file_reference_name;

                    // Get/Create a top-level folder called Client Uploads
                    $folder_sql = mysqli_query($mysqli, "SELECT * FROM folders WHERE folder_name = 'Client Uploads' AND parent_folder = 0 AND folder_client_id = $client_id LIMIT 1");
                    if (mysqli_num_rows($folder_sql) == 1) {
                        // Get
                        $row = mysqli_fetch_array($folder_sql);
                        $folder_id = $row['folder_id'];
                    } else {
                        // Create
                        mysqli_query($mysqli,"INSERT INTO folders SET folder_name = 'Client Uploads', parent_folder = 0, folder_location = 1, folder_client_id = $client_id");
                        $folder_id = mysqli_insert_id($mysqli);
                        logAction("Folder", "Create", "Automatically created folder Client Uploads", $client_id, $folder_id);
                    }

                    // Do move/upload
                    move_uploaded_file($file_tmp_path, $dest_path);

                    // Create reference in files
                    mysqli_query($mysqli,"INSERT INTO files SET file_reference_name = '$file_reference_name', file_name = '$file_name', file_description = 'Uploaded via $quote_prefix$quote_number', file_ext = '$file_extension', file_mime_type = '$file_mime_type', file_size = $file_size, file_folder_id = $folder_id, file_client_id = $client_id");
                    $file_id = mysqli_insert_id($mysqli);

                    // Associate file with quote
                    mysqli_query($mysqli, "INSERT INTO quote_files SET quote_id = $quote_id, file_id = $file_id");

                    // Logging & feedback
                    $_SESSION['alert_message'] = 'File uploaded!';
                    appNotify("Quote File", "$file_name was uploaded to quote $quote_prefix$quote_number", "quote.php?quote_id=$quote_id", $client_id);
                    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Upload', history_description = 'Client uploaded file $file_name', history_quote_id = $quote_id");
                    logAction("File", "Upload", "Guest uploaded file $file_name to quote $quote_prefix$quote_number", $client_id);

                } else {
                    $_SESSION['alert_type'] = 'error';
                    $_SESSION['alert_message'] = 'Something went wrong uploading the file - please let the support team know.';
                    logApp("Guest", "error", "Error uploading file to invoice");
                }

            }
        }

        header("Location: " . $_SERVER["HTTP_REFERER"]);

    } else {
        echo "Invalid!!";
    }
}

?>
