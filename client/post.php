<?php
/*
 * Client Portal
 * Process GET/POST requests
 */

require_once '../config.php';
require_once '../includes/load_global_settings.php';
require_once '../functions.php';
require_once 'includes/check_login.php';
require_once 'functions.php';

if (isset($_POST['add_ticket'])) {

    $subject = sanitizeInput($_POST['subject']);
    $details = mysqli_real_escape_string($mysqli, ($_POST['details']));
    $category = intval($_POST['category']);
    $asset = intval($_POST['asset']);

    // Get settings from load_global_settings.php
    $config_ticket_prefix = sanitizeInput($config_ticket_prefix);
    $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
    $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
    $config_base_url = sanitizeInput($config_base_url);
    $config_ticket_new_ticket_notification_email = filter_var($config_ticket_new_ticket_notification_email, FILTER_VALIDATE_EMAIL);

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);

    // Ensure priority is low/med/high (as can be user defined)
    if ($_POST['priority'] !== "Low" && $_POST['priority'] !== "Medium" && $_POST['priority'] !== "High") {
        $priority = "Low";
    } else {
        $priority = sanitizeInput($_POST['priority']);
    }

    // Get the next Ticket Number and add 1 for the new ticket number
    $ticket_number = $config_ticket_next_number;
    $new_config_ticket_next_number = $config_ticket_next_number + 1;
    mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = 1");

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_source = 'Portal', ticket_category = $category, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = 1, ticket_billable = $config_ticket_default_billable, ticket_created_by = $session_user_id, ticket_contact_id = $session_contact_id, ticket_asset_id = $asset, ticket_url_key = '$url_key', ticket_client_id = $session_client_id");
    $ticket_id = mysqli_insert_id($mysqli);

    // Notify agent DL of the new ticket, if populated with a valid email
    if ($config_ticket_new_ticket_notification_email) {

        $client_name = sanitizeInput($session_client_name);
        $details = removeEmoji($details);

        $email_subject = "ITFlow - New Ticket - $client_name: $subject";
        $email_body = "Hello, <br><br>This is a notification that a new ticket has been raised in ITFlow. <br>Client: $client_name<br>Priority: $priority<br>Link: https://$config_base_url/ticket.php?ticket_id=$ticket_id <br><br><b>$subject</b><br>$details";

        // Queue Mail
        $data = [
            [
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $config_ticket_new_ticket_notification_email,
                'recipient_name' => $config_ticket_from_name,
                'subject' => $email_subject,
                'body' => $email_body,
            ]
        ];
        addToMailQueue($data);
        }

    // Custom action/notif handler
    customAction('ticket_create', $ticket_id);

    logAction("Ticket", "Create", "$session_contact_name created ticket $config_ticket_prefix$ticket_number - $subject from the client portal", $session_client_id, $ticket_id);

    redirect("ticket.php?id=" . $ticket_id);

}

if (isset($_POST['add_ticket_comment'])) {

    $ticket_id = intval($_POST['ticket_id']);
    $comment = mysqli_real_escape_string($mysqli, $_POST['comment']);

    // After stripping bad HTML, check the comment isn't just empty
    if (empty($comment)) {
        flash_alert("You must enter a comment", 'danger');
        redirect();
    }

    // Verify the contact has access to the provided ticket ID
    if (verifyContactTicketAccess($ticket_id, "Open")) {

        // Add the comment
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$comment', ticket_reply_type = 'Client', ticket_reply_by = $session_contact_id, ticket_reply_ticket_id = $ticket_id");

        $ticket_reply_id = mysqli_insert_id($mysqli);

        // Update Ticket Last Response Field & set ticket to open as client has replied
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 2 WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id LIMIT 1");


        // Get ticket details &  Notify the assigned tech (if any)
        $ticket_details = mysqli_fetch_array(mysqli_query($mysqli, "SELECT * FROM tickets LEFT JOIN clients ON ticket_client_id = client_id WHERE ticket_id = $ticket_id LIMIT 1"));

        $ticket_number = intval($ticket_details['ticket_number']);
        $ticket_assigned_to = intval($ticket_details['ticket_assigned_to']);
        $ticket_subject = sanitizeInput($ticket_details['ticket_subject']);
        $client_name = sanitizeInput($ticket_details['client_name']);

        if ($ticket_details && $ticket_assigned_to !== 0) {

            // Get tech details
            $tech_details = mysqli_fetch_array(mysqli_query($mysqli, "SELECT user_email, user_name FROM users WHERE user_id = $ticket_assigned_to LIMIT 1"));
            $tech_email = sanitizeInput($tech_details['user_email']);
            $tech_name = sanitizeInput($tech_details['user_name']);

            $subject = "$config_app_name Ticket updated - [$config_ticket_prefix$ticket_number] $ticket_subject";
            $body    = "Hello $tech_name,<br><br>A new reply has been added to the below ticket, check ITFlow for full details.<br><br>Client: $client_name<br>Ticket: $config_ticket_prefix$ticket_number<br>Subject: $ticket_subject<br><br>https://$config_base_url/ticket.php?ticket_id=$ticket_id";

            $data = [
                [
                    'from' => $config_ticket_from_email,
                    'from_name' => $config_ticket_from_name,
                    'recipient' => $tech_email,
                    'recipient_name' => $tech_name,
                    'subject' => $subject,
                    'body' => $body
                ]
            ];

            addToMailQueue($data);

        }

        // Store any attached any files
        if (!empty($_FILES)) {

            // Define & create directories, as required
            mkdirMissing('../uploads/tickets/');
            $upload_file_dir = "../uploads/tickets/" . $ticket_id . "/";
            mkdirMissing($upload_file_dir);

            for ($i = 0; $i < count($_FILES['file']['name']); $i++) {
                // Extract file details for this iteration
                $single_file = [
                    'name' => $_FILES['file']['name'][$i],
                    'type' => $_FILES['file']['type'][$i],
                    'tmp_name' => $_FILES['file']['tmp_name'][$i],
                    'error' => $_FILES['file']['error'][$i],
                    'size' => $_FILES['file']['size'][$i]
                ];

                if ($ticket_attachment_ref_name = checkFileUpload($single_file, array('jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx', 'odt', 'csv', 'xls', 'xlsx', 'ods', 'pptx', 'odp', 'zip', 'tar', 'gz', 'xml', 'msg', 'json', 'wav', 'mp3', 'ogg', 'mov', 'mp4', 'av1', 'ovpn'))) {

                    $file_tmp_path = $_FILES['file']['tmp_name'][$i];

                    $file_name = sanitizeInput($_FILES['file']['name'][$i]);
                    $extarr = explode('.', $_FILES['file']['name'][$i]);
                    $file_extension = sanitizeInput(strtolower(end($extarr)));

                    // Define destination file path
                    $dest_path = $upload_file_dir . $ticket_attachment_ref_name;

                    move_uploaded_file($file_tmp_path, $dest_path);

                    mysqli_query($mysqli, "INSERT INTO ticket_attachments SET ticket_attachment_name = '$file_name', ticket_attachment_reference_name = '$ticket_attachment_ref_name', ticket_attachment_reply_id = $ticket_reply_id, ticket_attachment_ticket_id = $ticket_id");
                }

            }
        }

        // Custom action/notif handler
        customAction('ticket_reply_client', $ticket_id);

        // Redirect back to original page
        redirect();

    } else {
        // The client does not have access to this ticket
        redirect("post.php?logout");
    }
}

if (isset($_POST['add_ticket_feedback'])) {
    
    $ticket_id = intval($_POST['ticket_id']);
    $feedback = sanitizeInput($_POST['add_ticket_feedback']);

    // Verify the contact has access to the provided ticket ID
    if (verifyContactTicketAccess($ticket_id, "Closed")) {

        // Add feedback
        mysqli_query($mysqli, "UPDATE tickets SET ticket_feedback = '$feedback' WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id LIMIT 1");

        // Notify on bad feedback
        if ($feedback == "Bad") {
            $ticket_details = mysqli_fetch_array(mysqli_query($mysqli, "SELECT ticket_number FROM tickets WHERE ticket_id = $ticket_id LIMIT 1"));
            $ticket_number = intval($ticket_details['ticket_number']);
            appNotify("Feedback", "$session_contact_name rated ticket $config_ticket_prefix$ticket_number as bad (ID: $ticket_id)", "ticket.php?ticket_id=$ticket_id", $session_client_id, $ticket_id);
        }

        // Custom action/notif handler
        customAction('ticket_feedback', $ticket_id);

        // Redirect
        redirect();
    } else {
        // The client does not have access to this ticket
        redirect("post.php?logout");
    }

}

if (isset($_GET['resolve_ticket'])) {
    
    $ticket_id = intval($_GET['resolve_ticket']);

    // Get ticket details for logging
    $row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id LIMIT 1"));

    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);

    // Verify the contact has access to the provided ticket ID
    if (verifyContactTicketAccess($ticket_id, "Open")) {

        // Resolve the ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 4, ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id");

        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket resolved by $session_contact_name.', ticket_reply_type = 'Client', ticket_reply_by = $session_contact_id, ticket_reply_ticket_id = $ticket_id");

        logAction("Ticket", "Edit", "$session_contact_name marked ticket $ticket_prefix$ticket_number as resolved in the client portal", $session_client_id, $ticket_id);

        // Custom action/notif handler
        customAction('ticket_resolve', $ticket_id);

        redirect("ticket.php?id=" . $ticket_id);

    } else {
        // The client does not have access to this ticket - send them home
        redirect("index.php");
    }

}

if (isset($_GET['reopen_ticket'])) {
    $ticket_id = intval($_GET['reopen_ticket']);

    // Get ticket details for logging
    $row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id LIMIT 1"));

    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);

    // Verify the contact has access to the provided ticket ID
    if (verifyContactTicketAccess($ticket_id, "Open")) {

        // Re-open ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 2, ticket_resolved_at = NULL WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id");

        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket reopened by $session_contact_name.', ticket_reply_type = 'Client', ticket_reply_by = $session_contact_id, ticket_reply_ticket_id = $ticket_id");

        logAction("Ticket", "Edit", "$session_contact_name reopend ticket $ticket_prefix$ticket_number in the client portal", $session_client_id, $ticket_id);

        // Custom action/notif handler
        customAction('ticket_update', $ticket_id);

        redirect("ticket.php?id=" . $ticket_id);

    } else {
        // The client does not have access to this ticket - send them home
        redirect("index.php");
    }

}

if (isset($_GET['close_ticket'])) {
    
    $ticket_id = intval($_GET['close_ticket']);

    // Get ticket details for logging
    $row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id LIMIT 1"));

    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);

    // Verify the contact has access to the provided ticket ID
    if (verifyContactTicketAccess($ticket_id, "Open")) {

        // Fully close ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 5, ticket_closed_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id");

        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket closed by $session_contact_name.', ticket_reply_type = 'Client', ticket_reply_by = $session_contact_id, ticket_reply_ticket_id = $ticket_id");

        logAction("Ticket", "Edit", "$session_contact_name closed ticket $ticket_prefix$ticket_number in the client portal", $session_client_id, $ticket_id);

        // Custom action/notif handler
        customAction('ticket_close', $ticket_id);

        redirect("ticket.php?id=" . $ticket_id);
    
    } else {
        // The client does not have access to this ticket - send them home
        redirect("index.php");
    }
}

if (isset($_GET['logout'])) {
    
    setcookie("PHPSESSID", '', time() - 3600, "/");
    unset($_COOKIE['PHPSESSID']);

    session_unset();
    session_destroy();

    redirect('login.php');

}

if (isset($_POST['edit_profile'])) {
    
    $new_password = $_POST['new_password'];
    
    if (!empty($new_password)) {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        mysqli_query($mysqli, "UPDATE users SET user_password = '$password_hash' WHERE user_id = $session_user_id");

        // Logging
        logAction("Contact", "Edit", "Client contact $session_contact_name edited their profile/password in the client portal", $session_client_id, $session_contact_id);
    }
    
    redirect('index.php');

}

if (isset($_POST['add_contact'])) {

    if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
        redirect("post.php?logout");
    }

    $contact_name = sanitizeInput($_POST['contact_name']);
    $contact_email = sanitizeInput($_POST['contact_email']);
    $contact_technical = intval($_POST['contact_technical']);
    $contact_billing = intval($_POST['contact_billing']);
    $contact_auth_method = sanitizeInput($_POST['contact_auth_method']);

    // Check the email isn't already in use
    $sql = mysqli_query($mysqli, "SELECT user_id FROM users WHERE user_email = '$contact_email'");
    if ($sql && mysqli_num_rows($sql) > 0) {
        flash_alert("Cannot add contact as that email address is already in use", 'danger');
        redirect('contact_add.php');
    }

    // Create user account with rand password for the contact
    $contact_user_id = 0;
    if ($contact_name && $contact_email && $contact_auth_method) {

        $password_hash = password_hash(randomString(), PASSWORD_DEFAULT);

        mysqli_query($mysqli, "INSERT INTO users SET user_name = '$contact_name', user_email = '$contact_email', user_password = '$password_hash', user_auth_method = '$contact_auth_method', user_type = 2");

        $contact_user_id = mysqli_insert_id($mysqli);
    
    }

    // Create contact record
    mysqli_query($mysqli, "INSERT INTO contacts SET contact_name = '$contact_name', contact_email = '$contact_email', contact_billing = $contact_billing, contact_technical = $contact_technical, contact_client_id = $session_client_id, contact_user_id = $contact_user_id");
    
    $contact_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Contact", "Create", "Client contact $session_contact_name created contact $contact_name in the client portal", $session_client_id, $contact_id);

    customAction('contact_create', $contact_id);

    flash_alert("Contact $contact_name created");

    redirect('contacts.php');

}

if (isset($_POST['edit_contact'])) {

    if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
        redirect("post.php?logout");
    }

    $contact_id = intval($_POST['contact_id']);
    $contact_name = sanitizeInput($_POST['contact_name']);
    $contact_email = sanitizeInput($_POST['contact_email']);
    $contact_technical = intval($_POST['contact_technical']);
    $contact_billing = intval($_POST['contact_billing']);
    $contact_auth_method = sanitizeInput($_POST['contact_auth_method']);

    // Get the existing contact_user_id - we look it up ourselves so the user can't just overwrite random users
    $sql = mysqli_query($mysqli,"SELECT contact_user_id FROM contacts WHERE contact_id = $contact_id AND contact_client_id = $session_client_id");
    $row = mysqli_fetch_array($sql);
    $contact_user_id = intval($row['contact_user_id']);

    // Check the email isn't already in use
    $sql = mysqli_query($mysqli, "SELECT user_id FROM users WHERE user_email = '$contact_email' AND user_id != $contact_user_id");
    if ($sql && mysqli_num_rows($sql) > 0) {
        flash_alert("Cannot update contact as that email address is already in use", 'danger');
        redirect('contact_edit.php?id=' . $contact_id);
    }

    // Update Existing User
    if ($contact_user_id > 0) {
        mysqli_query($mysqli, "UPDATE users SET user_name = '$contact_name', user_email = '$contact_email', user_auth_method = '$contact_auth_method' WHERE user_id = $contact_user_id");

    // Else, create New User
    } elseif ($contact_user_id == 0 && $contact_name && $contact_email && $contact_auth_method) {
        $password_hash = password_hash(randomString(), PASSWORD_DEFAULT);
        mysqli_query($mysqli, "INSERT INTO users SET user_name = '$contact_name', user_email = '$contact_email', user_password = '$password_hash', user_auth_method = '$contact_auth_method', user_type = 2");

        $contact_user_id = mysqli_insert_id($mysqli);
    }

    // Update contact
    mysqli_query($mysqli, "UPDATE contacts SET contact_name = '$contact_name', contact_email = '$contact_email', contact_billing = $contact_billing, contact_technical = $contact_technical, contact_user_id = $contact_user_id WHERE contact_id = $contact_id AND contact_client_id = $session_client_id AND contact_archived_at IS NULL AND contact_primary = 0");

    logAction("Contact", "Edit", "Client contact $session_contact_name edited contact $contact_name in the client portal", $session_client_id, $contact_id);

    flash_alert("Contact $contact_name updated");

    redirect('contacts.php');

    customAction('contact_update', $contact_id);

}

if (isset($_GET['add_payment_by_provider'])) {

    $invoice_id = intval($_GET['invoice_id']);
    $saved_payment_id = intval($_GET['add_payment_by_provider']);

    // Get invoice details
    $sql = mysqli_query($mysqli,"SELECT * FROM invoices
            LEFT JOIN clients ON invoice_client_id = client_id
            LEFT JOIN contacts ON client_id = contact_client_id AND contact_primary = 1
            WHERE invoice_id = $invoice_id"
    );
    $row = mysqli_fetch_array($sql);
    $invoice_number = intval($row['invoice_number']);
    $invoice_status = sanitizeInput($row['invoice_status']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_url_key = sanitizeInput($row['invoice_url_key']);
    $invoice_currency_code = sanitizeInput($row['invoice_currency_code']);
    $client_id = intval($row['client_id']);
    $client_name = sanitizeInput($row['client_name']);
    $contact_name = sanitizeInput($row['contact_name']);
    $contact_email = sanitizeInput($row['contact_email']);
    $contact_phone = sanitizeInput(formatPhoneNumber($row['contact_phone'], $row['contact_phone_country_code']));
    $contact_extension = preg_replace("/[^0-9]/", '',$row['contact_extension']);
    $contact_mobile = sanitizeInput(formatPhoneNumber($row['contact_mobile'], $row['contact_mobile_country_code']));

    // Check to make sure saved payment method belongs to logged in client
    if ($client_id !== $session_client_id) {
        flash_alert("Saved Payment method does not belong to you!", 'danger');
        redirect();
    }

    // Get ITFlow company details
    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);
    $company_name = sanitizeInput($row['company_name']);
    $company_country = sanitizeInput($row['company_country']);
    $company_address = sanitizeInput($row['company_address']);
    $company_city = sanitizeInput($row['company_city']);
    $company_state = sanitizeInput($row['company_state']);
    $company_zip = sanitizeInput($row['company_zip']);
    $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));
    $company_email = sanitizeInput($row['company_email']);
    $company_website = sanitizeInput($row['company_website']);

    // Sanitize Config vars from get_settings.php
    $config_invoice_from_name = sanitizeInput($config_invoice_from_name);
    $config_invoice_from_email = sanitizeInput($config_invoice_from_email);

    // Get Client Payment Details
    $sql = mysqli_query($mysqli, "SELECT * FROM client_saved_payment_methods LEFT JOIN payment_providers ON saved_payment_provider_id = payment_provider_id LEFT JOIN client_payment_provider ON saved_payment_client_id = client_id WHERE saved_payment_id = $saved_payment_id LIMIT 1");
    $row = mysqli_fetch_array($sql);

    $public_key = sanitizeInput($row['payment_provider_public_key']);
    $private_key = sanitizeInput($row['payment_provider_private_key']);
    $account_id = intval($row['payment_provider_account']);
    $expense_category_id = intval($row['payment_provider_expense_category']);
    $expense_vendor_id = intval($row['payment_provider_expense_vendor']);
    $expense_percentage_fee = floatval($row['payment_provider_expense_percentage_fee']);
    $expense_flat_fee = floatval($row['payment_provider_expense_flat_fee']);
    $payment_provider_client = sanitizeInput($row['payment_provider_client']);
    $saved_payment_method = sanitizeInput($row['saved_payment_provider_method']);
    $saved_payment_description = sanitizeInput($row['saved_payment_description']);

    // Sanity checks
    if (!$payment_provider_client || !$saved_payment_method) {
        flash_alert("Stripe not enabled or no client card saved", 'error');
        redirect();
    } elseif ($invoice_status !== 'Sent' && $invoice_status !== 'Viewed') {
        flash_alert("Invalid invoice state (draft/partial/paid/not billable)", 'error');
        redirect();
    } elseif ($invoice_amount == 0) {
        flash_alert("Invalid invoice amount", 'error');
        redirect();
    }

    // Initialize Stripe
    require_once __DIR__ . '/../plugins/stripe-php/init.php';
    $stripe = new \Stripe\StripeClient($private_key);

    $balance_to_pay = round($invoice_amount, 2);
    $pi_description = "ITFlow: $client_name payment of $invoice_currency_code $balance_to_pay for $invoice_prefix$invoice_number";

    // Create a payment intent
    try {
        $payment_intent = $stripe->paymentIntents->create([
            'amount' => intval($balance_to_pay * 100), // Times by 100 as Stripe expects values in cents
            'currency' => $invoice_currency_code,
            'customer' => $payment_provider_client,
            'payment_method' => $saved_payment_method,
            'off_session' => true,
            'confirm' => true,
            'description' => $pi_description,
            'metadata' => [
                'itflow_client_id' => $client_id,
                'itflow_client_name' => $client_name,
                'itflow_invoice_number' => $invoice_prefix . $invoice_number,
                'itflow_invoice_id' => $invoice_id,
            ]
        ]);

        // Get details from PI
        $pi_id = sanitizeInput($payment_intent->id);
        $pi_date = date('Y-m-d', $payment_intent->created);
        $pi_amount_paid = floatval(($payment_intent->amount_received / 100));
        $pi_currency = strtoupper(sanitizeInput($payment_intent->currency));
        $pi_livemode = $payment_intent->livemode;

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Stripe payment error - encountered exception during payment intent for invoice ID $invoice_id / $invoice_prefix$invoice_number: $error");
        logApp("Stripe", "error", "Exception during PI for invoice ID $invoice_id: $error");
    }

    if ($payment_intent->status == "succeeded" && intval($balance_to_pay) == intval($pi_amount_paid)) {

        // Update Invoice Status
        mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Paid' WHERE invoice_id = $invoice_id");

        // Add Payment to History
        mysqli_query($mysqli, "INSERT INTO payments SET payment_date = '$pi_date', payment_amount = $pi_amount_paid, payment_currency_code = '$pi_currency', payment_account_id = $account_id, payment_method = 'Stripe', payment_reference = 'Stripe - $pi_id', payment_invoice_id = $invoice_id");
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Paid', history_description = 'Online Payment added (agent)', history_invoice_id = $invoice_id");

        // Email receipt
        if (!empty($config_smtp_host)) {
            $subject = "Payment Received - Invoice $invoice_prefix$invoice_number";
            $body = "Hello $contact_name,<br><br>We have received online payment for the amount of " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . " for invoice <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount Paid: " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "<br><br>Thank you for your business!<br><br><br>--<br>$company_name - Billing Department<br>$config_invoice_from_email<br>$company_phone";

            // Queue Mail
            $data = [
                [
                    'from' => $config_invoice_from_email,
                    'from_name' => $config_invoice_from_name,
                    'recipient' => $contact_email,
                    'recipient_name' => $contact_name,
                    'subject' => $subject,
                    'body' => $body,
                ]
            ];

            // Email the internal notification address too
            if (!empty($config_invoice_paid_notification_email)) {
                $subject = "Payment Received - $client_name - Invoice $invoice_prefix$invoice_number";
                $body = "Hello, <br><br>This is a notification that an invoice has been paid in ITFlow. Below is a copy of the receipt sent to the client:-<br><br>--------<br><br>Hello $contact_name,<br><br>We have received online payment for the amount of " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . " for invoice <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount Paid: " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "<br><br>Thank you for your business!<br><br><br>--<br>$company_name - Billing Department<br>$config_invoice_from_email<br>$company_phone";

                $data[] = [
                    'from' => $config_invoice_from_email,
                    'from_name' => $config_invoice_from_name,
                    'recipient' => $config_invoice_paid_notification_email,
                    'recipient_name' => $contact_name,
                    'subject' => $subject,
                    'body' => $body,
                ];
            }

            $mail = addToMailQueue($data);

            // Email Logging
            $email_id = mysqli_insert_id($mysqli);
            mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Payment Receipt sent to mail queue ID: $email_id!', history_invoice_id = $invoice_id");
            logAction("Invoice", "Payment", "Payment receipt for invoice $invoice_prefix$invoice_number queued to $contact_email Email ID: $email_id", $client_id, $invoice_id);
        }

        // Log info
        $extended_log_desc = '';
        if (!$pi_livemode) {
            $extended_log_desc = '(DEV MODE)';
        }

        // Create Stripe payment gateway fee as an expense (if configured)
        if ($expense_vendor_id > 0 && $expense_category_id > 0) {
            $gateway_fee = round($invoice_amount * $expense_percentage_fee + $expense_flat_fee, 2);
            mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$pi_date', expense_amount = $gateway_fee, expense_currency_code = '$invoice_currency_code', expense_account_id = $account_id, expense_vendor_id = $expense_vendor_id, expense_client_id = $client_id, expense_category_id = $expense_category_id, expense_description = 'Stripe Transaction for Invoice $invoice_prefix$invoice_number In the Amount of $balance_to_pay', expense_reference = 'Stripe - $pi_id $extended_log_desc'");
        }

        // Notify/log
        appNotify("Invoice Paid", "Invoice $invoice_prefix$invoice_number automatically paid", "invoice.php?invoice_id=$invoice_id", $client_id);
        logAction("Invoice", "Payment", "$session_name initiated Stripe payment amount of " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . " added to invoice $invoice_prefix$invoice_number - $pi_id $extended_log_desc", $client_id, $invoice_id);
        customAction('invoice_pay', $invoice_id);

        flash_alert("The amount " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . " paid Invoice $invoice_prefix$invoice_number");
        
        redirect();

    } else {
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Payment failed', history_description = 'Stripe pay failed due to payment error', history_invoice_id = $invoice_id");
        
        logAction("Invoice", "Payment", "Failed online payment amount of invoice $invoice_prefix$invoice_number due to Stripe payment error", $client_id, $invoice_id);
        flash_alert("Payment failed", 'error');
        
        redirect();
    }

}

if (isset($_POST['create_stripe_customer'])) {

    if ($session_contact_primary == 0 && !$session_contact_is_billing_contact) {
        redirect("post.php?logout");
    }

    // Get Stripe provider
    $stripe_provider_result = mysqli_query($mysqli, "
        SELECT * FROM payment_providers 
        WHERE payment_provider_name = 'Stripe' 
        AND payment_provider_active = 1 
        LIMIT 1
    ");

    $stripe_provider = mysqli_fetch_array($stripe_provider_result);
    if (!$stripe_provider) {
        flash_alert("Stripe provider is not configured in the system.", 'danger');
        redirect("saved_payment_methods.php");
    }

    $stripe_provider_id = intval($stripe_provider['payment_provider_id']);
    $stripe_secret_key = nullable_htmlentities($stripe_provider['payment_provider_private_key']);

    if (empty($stripe_secret_key)) {
        flash_alert("Stripe credentials missing. Please contact support.", 'danger');
        redirect("saved_payment_methods.php");
    }

    // Check if client already has a Stripe customer
    $existing_customer = mysqli_fetch_array(mysqli_query($mysqli, "
        SELECT payment_provider_client 
        FROM client_payment_provider 
        WHERE client_id = $session_client_id 
        AND payment_provider_id = $stripe_provider_id 
        LIMIT 1
    "));

    if (!$existing_customer) {
        try {
            // Initialize Stripe
            require_once '../plugins/stripe-php/init.php';
            $stripe = new \Stripe\StripeClient($stripe_secret_key);

            // Create new customer in Stripe
            $customer = $stripe->customers->create([
                'name' => $session_client_name,
                'email' => $session_contact_email,
                'metadata' => [
                    'itflow_client_id' => $session_client_id,
                    'consent_by' => $session_contact_name
                ]
            ]);

            $stripe_customer_id = sanitizeInput($customer->id);

            // Insert customer into client_payment_provider
            mysqli_query($mysqli, "
                INSERT INTO client_payment_provider 
                SET client_id = $session_client_id, 
                    payment_provider_id = $stripe_provider_id, 
                    payment_provider_client = '$stripe_customer_id', 
                    client_payment_provider_created_at = NOW()
            ");

            logAction("Stripe", "Create", "$session_contact_name created Stripe customer for $session_client_name as $stripe_customer_id and authorized future automatic payments", $session_client_id, $session_client_id);

            flash_alert("Stripe customer created. Thank you for your consent.");

        } catch (Exception $e) {
            $error = $e->getMessage();
            
            error_log("Stripe error while creating customer for $session_client_name: $error");
            
            logApp("Stripe", "error", "Failed to create Stripe customer for $session_client_name: $error");

            flash_alert("An error occurred while creating your Stripe customer. Please try again.", 'danger');

        }

    } else {
        flash_alert("Stripe customer already exists for your account.", 'danger');
    }

    redirect('saved_payment_methods.php');
}

if (isset($_GET['create_stripe_checkout'])) {

    // This page is called by autopay_setup_stripe.js, returns a Checkout Session client_secret

    if ($session_contact_primary == 0 && !$session_contact_is_billing_contact) {
        redirect("post.php?logout");
    }

    // Fetch Stripe provider info
    $stripe_provider_result = mysqli_query($mysqli, "
        SELECT * FROM payment_providers 
        WHERE payment_provider_name = 'Stripe' 
        AND payment_provider_active = 1 
        LIMIT 1
    ");

    $stripe_provider = mysqli_fetch_array($stripe_provider_result);
    if (!$stripe_provider) {
        http_response_code(400);
        echo json_encode(['error' => 'Stripe provider not configured']);
        exit();
    }

    $stripe_provider_id = intval($stripe_provider['payment_provider_id']);
    $stripe_secret_key = nullable_htmlentities($stripe_provider['payment_provider_private_key']);

    if (empty($stripe_secret_key)) {
        http_response_code(400);
        echo json_encode(['error' => 'Stripe secret key missing']);
        exit();
    }

    // Get client currency
    $client_currency_result = mysqli_query($mysqli, "
        SELECT client_currency_code 
        FROM clients 
        WHERE client_id = $session_client_id 
        LIMIT 1
    ");
    $client_currency_row = mysqli_fetch_assoc($client_currency_result);
    $client_currency = $client_currency_row['client_currency_code'] ?? 'usd';

    // Return URL when checkout finishes
    $return_url = "https://$config_base_url/client/post.php?stripe_save_card&session_id={CHECKOUT_SESSION_ID}";

    try {
        require_once '../plugins/stripe-php/init.php';
        $stripe = new \Stripe\StripeClient($stripe_secret_key);

        // Create checkout session
        $checkout_session = $stripe->checkout->sessions->create([
            'currency' => $client_currency,
            'mode' => 'setup',
            'ui_mode' => 'embedded',
            'return_url' => $return_url,
        ]);

        echo json_encode(['clientSecret' => $checkout_session->client_secret]);

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Stripe error creating checkout session: $error");
        logApp("Stripe", "error", "Exception creating checkout session: $error");
        http_response_code(500);
        echo json_encode(['error' => 'Stripe Checkout session failed']);
    }

    exit;
}

if (isset($_GET['stripe_save_card'])) {

    if ($session_contact_primary == 0 && !$session_contact_is_billing_contact) {
        redirect("post.php?logout");
    }

    // Get Stripe provider
    $stripe_provider_result = mysqli_query($mysqli, "
        SELECT * FROM payment_providers 
        WHERE payment_provider_name = 'Stripe' 
        AND payment_provider_active = 1 
        LIMIT 1
    ");

    $stripe_provider = mysqli_fetch_array($stripe_provider_result);
    if (!$stripe_provider) {
        flash_alert("Stripe provider not configured.", 'danger');
        redirect("saved_payment_methods.php");
    }

    $stripe_provider_id = intval($stripe_provider['payment_provider_id']);
    $stripe_secret_key = nullable_htmlentities($stripe_provider['payment_provider_private_key']);

    if (empty($stripe_secret_key)) {
        flash_alert("Stripe credentials missing.", 'danger');
        redirect("saved_payment_methods.php");
    }

    // Get client's Stripe customer ID
    $client_provider_query = mysqli_query($mysqli, "
        SELECT payment_provider_client 
        FROM client_payment_provider 
        WHERE client_id = $session_client_id 
        AND payment_provider_id = $stripe_provider_id 
        LIMIT 1
    ");
    $client_provider = mysqli_fetch_array($client_provider_query);
    $stripe_customer_id = sanitizeInput($client_provider['payment_provider_client'] ?? '');

    if (empty($stripe_customer_id)) {
        flash_alert("Stripe customer ID not found for client.", 'danger');
        redirect("saved_payment_methods.php");
    }

    // Get session ID from URL
    $checkout_session_id = sanitizeInput($_GET['session_id']);

    try {
        require_once '../plugins/stripe-php/init.php';
        $stripe = new \Stripe\StripeClient($stripe_secret_key);

        // Retrieve checkout session & setup intent
        $checkout_session = $stripe->checkout->sessions->retrieve($checkout_session_id, []);
        $setup_intent_id = $checkout_session->setup_intent;
        $setup_intent = $stripe->setupIntents->retrieve($setup_intent_id, []);
        $payment_method_id = sanitizeInput($setup_intent->payment_method);

        // Attach the payment method to the Stripe customer
        $stripe->paymentMethods->attach($payment_method_id, ['customer' => $stripe_customer_id]);

        // Retrieve PM details for logging and UI
        $payment_method_details = $stripe->paymentMethods->retrieve($payment_method_id, []);
        $card_brand = sanitizeInput($payment_method_details->card->brand);
        $last4 = sanitizeInput($payment_method_details->card->last4);
        $exp_month = sanitizeInput($payment_method_details->card->exp_month);
        $exp_year = sanitizeInput($payment_method_details->card->exp_year);

        $saved_payment_description = "$card_brand - $last4 | Exp $exp_month/$exp_year";

        // Insert into client_saved_payment_methods
        mysqli_query($mysqli, "
            INSERT INTO client_saved_payment_methods 
            SET 
                saved_payment_provider_method = '$payment_method_id',
                saved_payment_description = '$saved_payment_description',
                saved_payment_client_id = $session_client_id,
                saved_payment_provider_id = $stripe_provider_id,
                saved_payment_created_at = NOW()
        ");

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Stripe error while saving payment method: $error");
        logApp("Stripe", "error", "Exception saving payment method: $error");

        flash_alert("An error occurred while saving your payment method.", 'danger');
        redirect("saved_payment_methods.php");
    }

    // Email Confirmation
    $sql_settings = mysqli_query($mysqli, "
        SELECT * FROM companies, settings 
        WHERE companies.company_id = settings.company_id 
        AND companies.company_id = 1
    ");
    $row = mysqli_fetch_array($sql_settings);

    $company_name = sanitizeInput($row['company_name']);
    $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));
    $config_invoice_from_email = sanitizeInput($row['config_invoice_from_email']);
    $config_invoice_from_name = sanitizeInput($row['config_invoice_from_name']);

    if (!empty($row['config_smtp_host'])) {
        $subject = "Payment method saved";
        $body = "Hello $session_contact_name<br><br>
        Were writing to confirm that your payment details have been securely stored with Stripe our trusted payment processor.<br><br>
        You authorized us to automatically bill your card ($saved_payment_description) for future invoices.<br><br>
        You may update or remove your payment method at any time via the client portal.<br><br>
        Thank you for your business!<br><br>
        --<br>$company_name - Billing Department<br>$config_invoice_from_email<br>$company_phone";

        $data = [[
            'from' => $config_invoice_from_email,
            'from_name' => $config_invoice_from_name,
            'recipient' => $session_contact_email,
            'recipient_name' => $session_contact_name,
            'subject' => $subject,
            'body' => $body
        ]];

        $mail = addToMailQueue($data);
    }

    logAction("Stripe", "Update", "$session_contact_name saved payment method ($saved_payment_description) (PM: $payment_method_id)", $session_client_id);

    flash_alert("Payment method saved – thank you.");
    redirect("saved_payment_methods.php");
}

if (isset($_GET['delete_saved_payment'])) {

    if ($session_contact_primary == 0 && !$session_contact_is_billing_contact) {
        redirect("post.php?logout");
    }

    $saved_payment_id = intval($_GET['delete_saved_payment']);

    // Get Stripe provider info
    $stripe_provider_result = mysqli_query($mysqli, "
        SELECT * FROM payment_providers 
        WHERE payment_provider_name = 'Stripe' 
        AND payment_provider_active = 1 
        LIMIT 1
    ");
    $stripe_provider = mysqli_fetch_array($stripe_provider_result);

    if (!$stripe_provider) {
        flash_alert("Stripe provider is not configured.", 'danger');
        redirect("saved_payment_methods.php");
    }

    $stripe_provider_id = intval($stripe_provider['payment_provider_id']);
    $stripe_secret_key = nullable_htmlentities($stripe_provider['payment_provider_private_key']);

    if (empty($stripe_secret_key)) {
        flash_alert("Stripe credentials are missing.", 'danger');
        redirect("saved_payment_methods.php");
    }

    $saved_payment_result = mysqli_query($mysqli, "
        SELECT saved_payment_id, saved_payment_description, saved_payment_provider_method 
        FROM client_saved_payment_methods 
        WHERE saved_payment_id = $saved_payment_id 
        AND saved_payment_client_id = $session_client_id 
        AND saved_payment_provider_id = $stripe_provider_id 
        LIMIT 1
    ");

    $saved_payment = mysqli_fetch_array($saved_payment_result);

    if (!$saved_payment) {
        flash_alert("Payment method not found or does not belong to you.", 'danger');
        redirect("saved_payment_methods.php");
    }

    $payment_method_id = sanitizeInput($saved_payment['saved_payment_provider_method']);

    $saved_payment_id = intval($saved_payment['saved_payment_id']);
    $saved_payment_description = nullable_htmlentities($saved_payment['saved_payment_description']);

    try {
        // Initialize Stripe
        require_once '../plugins/stripe-php/init.php';
        $stripe = new \Stripe\StripeClient($stripe_secret_key);

        // Detach the payment method from Stripe
        $stripe->paymentMethods->detach($payment_method_id, []);

    } catch (Exception $e) {
        $error = $e->getMessage();
        
        error_log("Stripe error while removing payment method $payment_method_id: $error");
        
        logApp("Stripe", "error", "Exception removing payment method $payment_method_id: $error");

        flash_alert("An error occurred while removing your payment method.", 'danger');
        
        redirect("saved_payment_methods.php");
        
    }

    // Remove saved payment method from local DB
    mysqli_query($mysqli, "
        DELETE FROM client_saved_payment_methods 
        WHERE saved_payment_id = $saved_payment_id
    ");

    // Remove any auto-pay records using this payment method
    $recurring_invoices = mysqli_query($mysqli, "
        SELECT recurring_invoice_id 
        FROM recurring_invoices 
        WHERE recurring_invoice_client_id = $session_client_id
    ");

    while ($row = mysqli_fetch_array($recurring_invoices)) {
        $recurring_invoice_id = intval($row['recurring_invoice_id']);

        mysqli_query($mysqli, "
            DELETE FROM recurring_payments 
            WHERE recurring_payment_recurring_invoice_id = $recurring_invoice_id 
            AND recurring_payment_saved_payment_id = $saved_payment_id
        ");
    }

    logAction("Stripe", "Update", "$session_contact_name deleted Stripe payment method $saved_payment_description (PM: $payment_method_id)", $session_client_id);

    flash_alert("Payment method $saved_payment_description removed.");
    
    redirect("saved_payment_methods.php");
}

if (isset($_POST['set_recurring_payment'])) {

    $recurring_invoice_id = intval($_POST['recurring_invoice_id']);
    $saved_payment_id = intval($_POST['saved_payment_id']);

    // Get Recurring Invoice Info for logging and alerting
    $sql = mysqli_query($mysqli, "SELECT * FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id AND recurring_invoice_client_id = $session_client_id");
    $row = mysqli_fetch_array($sql);
    $recurring_invoice_prefix = sanitizeInput($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);
    $recurring_invoice_currency_code = sanitizeInput($row['recurring_invoice_currency_code']);
    $recurring_invoice_amount = floatval($row['recurring_invoice_amount']);

    if ($saved_payment_id) {

        // Get Payment provider and method
        $sql = mysqli_query($mysqli, "
            SELECT * FROM payment_providers
            LEFT JOIN client_saved_payment_methods ON saved_payment_provider_id = payment_provider_id
            WHERE saved_payment_id = $saved_payment_id
            AND saved_payment_client_id = $session_client_id
            AND payment_provider_active = 1 
        ");

        $row = mysqli_fetch_array($sql);

        $provider_id = intval($row['payment_provider_id']);
        $provider_name = sanitizeInput($row['payment_provider_name']);
        $account_id = intval($row['payment_provider_account']);
        $saved_payment_description = sanitizeInput($row['saved_payment_description']);

        mysqli_query($mysqli, "DELETE FROM recurring_payments WHERE recurring_payment_recurring_invoice_id = $recurring_invoice_id");
        mysqli_query($mysqli,"INSERT INTO recurring_payments SET recurring_payment_currency_code = '$recurring_invoice_currency_code', recurring_payment_account_id = $account_id, recurring_payment_method = 'Credit Card', recurring_payment_recurring_invoice_id = $recurring_invoice_id, recurring_payment_saved_payment_id = $saved_payment_id");
        // Get Payment ID for reference
        $recurring_payment_id = mysqli_insert_id($mysqli);

        logAction("Recurring Invoice", "Auto Payment", "$session_name created Auto Pay for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number in the amount of " . numfmt_format_currency($currency_format, $recurring_invoice_amount, $recurring_invoice_currency_code), $session_client_id, $recurring_invoice_id);

        flash_alert("Automatic Payment $saved_payment_description enabled for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number");
    } else {
        // Delete
        mysqli_query($mysqli, "DELETE FROM recurring_payments WHERE recurring_payment_recurring_invoice_id = $recurring_invoice_id");

        logAction("Recurring Invoice", "Auto Payment", "$session_name removed Auto Pay for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number in the amount of " . numfmt_format_currency($currency_format, $recurring_invoice_amount, $recurring_invoice_currency_code), $session_client_id, $recurring_invoice_id);

        flash_alert("Automatic Payment Disabled for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number");
    }

    redirect();

}

if (isset($_POST['client_add_document'])) {

    // Permission check - only primary or technical contacts can create documents
    if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
        redirect("post.php?logout");
    }

    $document_name = sanitizeInput($_POST['document_name']);
    $document_description = sanitizeInput($_POST['document_description']);
    $document_content = mysqli_real_escape_string($mysqli, $_POST['document_content']);
    $document_content_raw = sanitizeInput($document_name . " " . strip_tags($_POST['document_content']));

    // Create document
    mysqli_query($mysqli, "INSERT INTO documents SET 
        document_name = '$document_name', 
        document_description = '$document_description', 
        document_content = '$document_content', 
        document_content_raw = '$document_content_raw', 
        document_client_visible = 1, 
        document_client_id = $session_client_id, 
        document_created_by = $session_contact_id");

    $document_id = mysqli_insert_id($mysqli);

    logAction("Document", "Create", "Client contact $session_contact_name created document $document_name", $session_client_id, $document_id);

    flash_alert("Document <strong>$document_name</strong> created successfully");

    redirect('documents.php');

}

if (isset($_POST['client_upload_document'])) {

    // Permission check - only primary or technical contacts can upload documents
    if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
        redirect("post.php?logout");
    }

    $document_name = sanitizeInput($_POST['document_name']);
    $document_description = sanitizeInput($_POST['document_description']);
    $client_dir = "../uploads/clients/$session_client_id";

    // Create client directory if it doesn't exist
    if (!is_dir($client_dir)) {
        mkdir($client_dir, 0755, true);
    }

    // Allowed file extensions for documents
    $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'md', 'odt', 'rtf'];

    // Check if file was uploaded
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
        
        // Validate and get a safe file reference name
        if ($file_reference_name = checkFileUpload($_FILES['document_file'], $allowedExtensions)) {

            $file_tmp_path = $_FILES['document_file']['tmp_name'];
            $file_name = sanitizeInput($_FILES['document_file']['name']);
            $extParts = explode('.', $file_name);
            $file_extension = strtolower(end($extParts));
            $file_mime_type = sanitizeInput($_FILES['document_file']['type']);
            $file_size = intval($_FILES['document_file']['size']);

            // Define destination path and move the uploaded file
            $dest_path = $client_dir . "/" . $file_reference_name;

            if (move_uploaded_file($file_tmp_path, $dest_path)) {

                // Create document entry
                $document_content = "<p>Uploaded file: <strong>$file_name</strong></p><p>$document_description</p>";
                $document_content_raw = "$document_name $file_name $document_description";

                mysqli_query($mysqli, "INSERT INTO documents SET 
                    document_name = '$document_name', 
                    document_description = '$document_description', 
                    document_content = '$document_content', 
                    document_content_raw = '$document_content_raw', 
                    document_client_visible = 1, 
                    document_client_id = $session_client_id, 
                    document_created_by = $session_contact_id");

                $document_id = mysqli_insert_id($mysqli);

                // Create file entry
                mysqli_query($mysqli, "INSERT INTO files SET 
                    file_reference_name = '$file_reference_name', 
                    file_name = '$file_name', 
                    file_description = 'Attached to document: $document_name', 
                    file_ext = '$file_extension', 
                    file_mime_type = '$file_mime_type', 
                    file_size = $file_size, 
                    file_created_by = $session_contact_id, 
                    file_client_id = $session_client_id");

                $file_id = mysqli_insert_id($mysqli);

                // Link file to document
                mysqli_query($mysqli, "INSERT INTO document_files SET document_id = $document_id, file_id = $file_id");

                logAction("Document", "Upload", "Client contact $session_contact_name uploaded document $document_name with file $file_name", $session_client_id, $document_id);

                flash_alert("Document <strong>$document_name</strong> uploaded successfully");

            } else {
                flash_alert('Error uploading file. Please try again.', 'error');
            }

        } else {
            flash_alert('Invalid file type. Please upload PDF, Word documents, or text files only.', 'error');
        }

    } else {
        flash_alert('Please select a file to upload.', 'error');
    }

    redirect('documents.php');
}
