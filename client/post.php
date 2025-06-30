<?php
/*
 * Client Portal
 * Process GET/POST requests
 */

require_once '../config.php';
require_once '../includes/get_settings.php';
require_once '../functions.php';
require_once 'includes/check_login.php';
require_once 'functions.php';

if (isset($_POST['add_ticket'])) {

    $subject = sanitizeInput($_POST['subject']);
    $details = mysqli_real_escape_string($mysqli, ($_POST['details']));
    $category = intval($_POST['category']);

    // Get settings from get_settings.php
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

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_source = 'Portal', ticket_category = $category, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = 1, ticket_billable = $config_ticket_default_billable, ticket_created_by = $session_user_id, ticket_contact_id = $session_contact_id, ticket_url_key = '$url_key', ticket_client_id = $session_client_id");
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

    // Logging
    logAction("Ticket", "Create", "$session_contact_name created ticket $config_ticket_prefix$ticket_number - $subject from the client portal", $session_client_id, $ticket_id);

    header("Location: ticket.php?id=" . $ticket_id);

}

if (isset($_POST['add_ticket_comment'])) {

    $ticket_id = intval($_POST['ticket_id']);
    $comment = mysqli_real_escape_string($mysqli, $_POST['comment']);

    // After stripping bad HTML, check the comment isn't just empty
    if (empty($comment)) {
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit;
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
        header("Location: " . $_SERVER["HTTP_REFERER"]);

    } else {
        // The client does not have access to this ticket
        header("Location: post.php?logout");
        exit();
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
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    } else {
        // The client does not have access to this ticket
        header("Location: post.php?logout");
        exit();
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

        // Logging
        logAction("Ticket", "Edit", "$session_contact_name marked ticket $ticket_prefix$ticket_number as resolved in the client portal", $session_client_id, $ticket_id);

        // Custom action/notif handler
        customAction('ticket_resolve', $ticket_id);

        header("Location: ticket.php?id=" . $ticket_id);

    } else {
        // The client does not have access to this ticket - send them home
        header("Location: index.php");
        exit();
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

        // Logging
        logAction("Ticket", "Edit", "$session_contact_name reopend ticket $ticket_prefix$ticket_number in the client portal", $session_client_id, $ticket_id);

        // Custom action/notif handler
        customAction('ticket_update', $ticket_id);

        header("Location: ticket.php?id=" . $ticket_id);

    } else {
        // The client does not have access to this ticket - send them home
        header("Location: index.php");
        exit();
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

        // Logging
        logAction("Ticket", "Edit", "$session_contact_name closed ticket $ticket_prefix$ticket_number in the client portal", $session_client_id, $ticket_id);

        // Custom action/notif handler
        customAction('ticket_close', $ticket_id);

        header("Location: ticket.php?id=" . $ticket_id);
    } else {
        // The client does not have access to this ticket - send them home
        header("Location: index.php");
        exit();
    }
}

if (isset($_GET['logout'])) {
    setcookie("PHPSESSID", '', time() - 3600, "/");
    unset($_COOKIE['PHPSESSID']);

    session_unset();
    session_destroy();

    header('Location: login.php');
}

if (isset($_POST['edit_profile'])) {
    $new_password = $_POST['new_password'];
    if (!empty($new_password)) {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        mysqli_query($mysqli, "UPDATE users SET user_password = '$password_hash' WHERE user_id = $session_user_id");

        // Logging
        logAction("Contact", "Edit", "Client contact $session_contact_name edited their profile/password in the client portal", $session_client_id, $session_contact_id);
    }
    header('Location: index.php');
}

if (isset($_POST['add_contact'])) {

    if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
        header("Location: post.php?logout");
        exit();
    }

    $contact_name = sanitizeInput($_POST['contact_name']);
    $contact_email = sanitizeInput($_POST['contact_email']);
    $contact_technical = intval($_POST['contact_technical']);
    $contact_billing = intval($_POST['contact_billing']);
    $contact_auth_method = sanitizeInput($_POST['contact_auth_method']);

    // Check the email isn't already in use
    $sql = mysqli_query($mysqli, "SELECT user_id FROM users WHERE user_email = '$contact_email'");
    if ($sql && mysqli_num_rows($sql) > 0) {
        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = "Cannot add contact as that email address is already in use";
        header('Location: contact_add.php');
        exit();
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

    $_SESSION['alert_message'] = "Contact $contact_name created";

    header('Location: contacts.php');
}

if (isset($_POST['edit_contact'])) {

    if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
        header("Location: post.php?logout");
        exit();
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
        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = "Cannot update contact as that email address is already in use";
        header('Location: contact_edit.php?id=' . $contact_id);
        exit();
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

    // Logging
    logAction("Contact", "Edit", "Client contact $session_contact_name edited contact $contact_name in the client portal", $session_client_id, $contact_id);

    $_SESSION['alert_message'] = "Contact $contact_name updated";

    header('Location: contacts.php');

    customAction('contact_update', $contact_id);
}

if (isset($_POST['create_stripe_customer'])) {

    if ($session_contact_primary == 0 && !$session_contact_is_billing_contact) {
        header("Location: post.php?logout");
        exit();
    }

    // Get Stripe vars
    $stripe_vars = mysqli_fetch_array(mysqli_query($mysqli, "SELECT config_stripe_enable, config_stripe_publishable, config_stripe_secret FROM settings WHERE company_id = 1"));
    $config_stripe_enable = intval($stripe_vars['config_stripe_enable']);
    $config_stripe_secret = nullable_htmlentities($stripe_vars['config_stripe_secret']);

    if (!$config_stripe_enable) {
        header("Location: autopay.php");
        exit();
    }

    // Include stripe SDK
    require_once '../plugins/stripe-php/init.php';

    // Get client's StripeID from database (should be none)
    $stripe_client_details = mysqli_fetch_array(mysqli_query($mysqli, "SELECT stripe_id FROM client_stripe WHERE client_id = $session_client_id LIMIT 1"));
    if (!$stripe_client_details) {

        try {
            // Initiate Stripe
            $stripe = new \Stripe\StripeClient($config_stripe_secret);

            // Create customer
            $customer = $stripe->customers->create([
                'name' => $session_client_name,
                'email' => $session_contact_email,
                'metadata' => [
                    'itflow_client_id' => $session_client_id,
                    'consent' => $session_contact_name
                ]
            ]);

        } catch (Exception $e) {
            $error = $e->getMessage();
            error_log("Stripe payment error - encountered exception when creating customer record for $session_client_name: $error");
            logApp("Stripe", "error", "Exception creating customer $session_client_name: $error");
        }

        // Get & Store customer ID
        $stripe_id = sanitizeInput($customer->id);

        mysqli_query($mysqli, "INSERT INTO client_stripe SET client_id = $session_client_id, stripe_id = '$stripe_id'");

        // Logging
        logAction("Stripe", "Create", "$session_contact_name created Stripe customer for $session_client_name as $stripe_id and authorised future automatic payments", $session_client_id, $session_client_id);

        $_SESSION['alert_message'] = "Stripe customer created, thank you for your consent";

    } else {
        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = "Stripe customer already exists";
    }

    header('Location: autopay.php');
}

if (isset($_GET['create_stripe_checkout'])) {

    // This page is called by the autopay_setup_stripe.js, it returns a checkout session client secret

    if ($session_contact_primary == 0 && !$session_contact_is_billing_contact) {
        header("Location: post.php?logout");
        exit();
    }

    // Get Stripe vars
    $stripe_vars = mysqli_fetch_array(mysqli_query($mysqli, "SELECT config_stripe_enable, config_stripe_publishable, config_stripe_secret FROM settings WHERE company_id = 1"));
    $config_stripe_enable = intval($stripe_vars['config_stripe_enable']);
    $config_stripe_secret = nullable_htmlentities($stripe_vars['config_stripe_secret']);

    if (!$config_stripe_enable) {
        header("Location: autopay.php");
        exit();
    }

    // Client Currency
    $client_currency_details = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_currency_code FROM clients WHERE client_id = $session_client_id LIMIT 1"));
    $client_currency = $client_currency_details['client_currency_code'];

    // Define return URL that user is redirected to once payment method is verified by Stripe
    $return_url = "https://$config_base_url/client/post.php?stripe_save_card&session_id={CHECKOUT_SESSION_ID}";

    try {
        // Initialize stripe
        require_once '../plugins/stripe-php/init.php';
        $stripe = new \Stripe\StripeClient($config_stripe_secret);

        // Create checkout session (server side)
        $checkout_session = $stripe->checkout->sessions->create([
            'currency' => $client_currency,
            'mode' => 'setup',
            'ui_mode' => 'embedded',
            'return_url' => $return_url,
        ]);
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Stripe payment error - encountered exception when creating checkout session: $error");
        logApp("Stripe", "error", "Exception creating checkout: $error");
    }

    // Return the client secret to the js script
    echo json_encode(array('clientSecret' => $checkout_session->client_secret));

    // No redirect & no point logging this
}

if (isset($_GET['stripe_save_card'])) {

    if ($session_contact_primary == 0 && !$session_contact_is_billing_contact) {
        header("Location: post.php?logout");
        exit();
    }

    // Get Stripe vars
    $stripe_vars = mysqli_fetch_array(mysqli_query($mysqli, "SELECT config_stripe_enable, config_stripe_publishable, config_stripe_secret FROM settings WHERE company_id = 1"));
    $config_stripe_enable = intval($stripe_vars['config_stripe_enable']);
    $config_stripe_secret = nullable_htmlentities($stripe_vars['config_stripe_secret']);

    if (!$config_stripe_enable) {
        header("Location: autopay.php");
        exit();
    }

    // Get session ID from URL
    $checkout_session_id = sanitizeInput($_GET['session_id']);

    // Get client's StripeID from database
    $stripe_client_details = mysqli_fetch_array(mysqli_query($mysqli, "SELECT stripe_id FROM client_stripe WHERE client_id = $session_client_id LIMIT 1"));
    $client_stripe_id = sanitizeInput($stripe_client_details['stripe_id']);

    try {
        // Initialize stripe
        require_once '../plugins/stripe-php/init.php';
        $stripe = new \Stripe\StripeClient($config_stripe_secret);

        // Retrieve checkout session
        $checkout_session = $stripe->checkout->sessions->retrieve($checkout_session_id,[]);

        // Get setup intent
        $setup_intent_id = $checkout_session->setup_intent;

        // Retrieve the setup intent details
        $setup_intent = $stripe->setupIntents->retrieve($setup_intent_id, []);

        // Get the payment method token
        $payment_method = sanitizeInput($setup_intent->payment_method);

        // Attach the payment method to the client in Stripe
        $stripe->paymentMethods->attach($payment_method, ['customer' => $client_stripe_id]);

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Stripe payment error - encountered exception when adding payment method info: $error");
        logApp("Stripe", "error", "Exception adding payment method: $error");
    }

    // Update ITFlow
    mysqli_query($mysqli, "UPDATE client_stripe SET stripe_pm = '$payment_method' WHERE client_id = $session_client_id LIMIT 1");

    // Get some card/payment method details for the email/logging
    $payment_method_details = $stripe->paymentMethods->retrieve($payment_method);
    $card_type = sanitizeInput($payment_method_details->card->brand);
    $last4 = sanitizeInput($payment_method_details->card->last4);
    $expiry_month = sanitizeInput($payment_method_details->card->exp_month);
    $expiry_year = sanitizeInput($payment_method_details->card->exp_year);

    // Format the payment details string (Visa - 4324 | Exp 12/25)
    $stripe_pm_details = "$card_type - $last4 | Exp $expiry_month/$expiry_year";

    // Save the formatted payment details into stripe_pm_details
    $update_query = "
        UPDATE client_stripe
        SET stripe_pm_details = '$stripe_pm_details'
        WHERE client_id = $session_client_id LIMIT 1";
    mysqli_query($mysqli, $update_query);

    // Send email confirmation
    // Company Details & Settings
    $sql_settings = mysqli_query($mysqli, "SELECT * FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");
    $row = mysqli_fetch_array($sql_settings);
    $company_name = sanitizeInput($row['company_name']);
    $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));
    $config_smtp_host = $row['config_smtp_host'];
    $config_smtp_port = intval($row['config_smtp_port']);
    $config_smtp_encryption = $row['config_smtp_encryption'];
    $config_smtp_username = $row['config_smtp_username'];
    $config_smtp_password = $row['config_smtp_password'];
    $config_invoice_from_name = sanitizeInput($row['config_invoice_from_name']);
    $config_invoice_from_email = sanitizeInput($row['config_invoice_from_email']);
    $config_base_url = sanitizeInput($config_base_url);

    if (!empty($config_smtp_host)) {
        $subject = "Payment method saved";
        $body = "Hello $session_contact_name,<br><br>We're writing to confirm that your payment details have been securely stored with Stripe, our trusted payment processor.<br><br>By agreeing to save your payment information, you have authorized us to automatically bill your card ($stripe_pm_details) for any future invoices. The payment details you've provided are securely stored with Stripe and will be used solely for invoices. We do not have access to your full card details.<br><br>You may update or remove your payment information at any time using the portal.<br><br>Thank you for your business!<br><br>--<br>$company_name - Billing Department<br>$config_invoice_from_email<br>$company_phone";

        $data = [
            [
                'from' => $config_invoice_from_email,
                'from_name' => $config_invoice_from_name,
                'recipient' => $session_contact_email,
                'recipient_name' => $session_contact_name,
                'subject' => $subject,
                'body' => $body,
            ]
        ];

        $mail = addToMailQueue($data);

    }

    // Logging
    logAction("Stripe", "Update", "$session_contact_name saved payment method ($stripe_pm_details) for future automatic payments (PM: $payment_method)", $session_client_id, $session_client_id);

    // Redirect
    $_SESSION['alert_message'] = "Payment method saved - thank you";
    header('Location: autopay.php');
}

if (isset($_GET['stripe_remove_pm'])) {

    if ($session_contact_primary == 0 && !$session_contact_is_billing_contact) {
        header("Location: post.php?logout");
        exit();
    }

    // Get Stripe vars
    $stripe_vars = mysqli_fetch_array(mysqli_query($mysqli, "SELECT config_stripe_enable, config_stripe_publishable, config_stripe_secret FROM settings WHERE company_id = 1"));
    $config_stripe_enable = intval($stripe_vars['config_stripe_enable']);
    $config_stripe_secret = nullable_htmlentities($stripe_vars['config_stripe_secret']);

    if (!$config_stripe_enable) {
        header("Location: autopay.php");
        exit();
    }

    $payment_method = sanitizeInput($_GET['pm']);

    try {
        // Initialize stripe
        require_once '../plugins/stripe-php/init.php';
        $stripe = new \Stripe\StripeClient($config_stripe_secret);

        // Detach PM
        $stripe->paymentMethods->detach($payment_method, []);

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Stripe payment error - encountered exception when removing payment method info for $payment_method: $error");
        logApp("Stripe", "error", "Exception removing payment method for $payment_method: $error");
    }

    // Remove payment method from ITFlow
    mysqli_query($mysqli, "UPDATE client_stripe SET stripe_pm = NULL, stripe_pm_details = NULL WHERE client_id = $session_client_id LIMIT 1");

    // Remove Auto Pay on recurring invoices that are stripe
    $sql_recurring_invoices = mysqli_query($mysqli, "SELECT recurring_invoice_id FROM recurring_invoices WHERE recurring_invoice_client_id = $session_client_id");

    while ($row = mysqli_fetch_array($sql_recurring_invoices)) {
        $recurring_invoice_id = intval($row['recurring_invoice_id']);
        mysqli_query($mysqli, "DELETE FROM recurring_payments WHERE recurring_payment_method = 'Stripe' AND recurring_payment_recurring_invoice_id = $recurring_invoice_id");
    }

    // Logging & Redirect
    logAction("Stripe", "Update", "$session_contact_name deleted saved Stripe payment method (PM: $payment_method)", $session_client_id, $session_client_id);

    $_SESSION['alert_message'] = "Payment method removed";
    header('Location: autopay.php');
}

if (isset($_POST['add_recurring_payment'])) {

    $recurring_invoice_id = intval($_POST['recurring_invoice_id']);

    // Get Recurring Info for logging and alerting
    $sql = mysqli_query($mysqli, "SELECT * FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");
    $row = mysqli_fetch_array($sql);
    $recurring_invoice_prefix = sanitizeInput($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);
    $recurring_invoice_amount = floatval($row['recurring_invoice_amount']);
    $recurring_invoice_currency_code = sanitizeInput($row['recurring_invoice_currency_code']);

    mysqli_query($mysqli,"INSERT INTO recurring_payments SET recurring_payment_currency_code = '$recurring_invoice_currency_code', recurring_payment_account_id = $config_stripe_account, recurring_payment_method = 'Stripe', recurring_payment_recurring_invoice_id = $recurring_invoice_id");

    // Get Payment ID for reference
    $recurring_payment_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Recurring Invoice", "Auto Payment", "$session_name created Auto Pay for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number in the amount of " . numfmt_format_currency($currency_format, $recurring_invoice_amount, $recurring_invoice_currency_code), $session_client_id, $recurring_invoice_id);


    $_SESSION['alert_message'] = "Automatic Payment enabled for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['delete_recurring_payment'])) {
    $recurring_invoice_id = intval($_POST['recurring_invoice_id']);

    // Get the invoice total and details
    $sql = mysqli_query($mysqli,"SELECT * FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");
    $row = mysqli_fetch_array($sql);
    $recurring_invoice_prefix = sanitizeInput($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);

    mysqli_query($mysqli,"DELETE FROM recurring_payments WHERE recurring_payment_recurring_invoice_id = $recurring_invoice_id");

    // Logging
    logAction("Recurring Invoice", "Auto Payment", "$session_name removed auto Pay from Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number", $session_client_id, $recurring_invoice_id);

    $_SESSION['alert_message'] = "Automatic Payment disabled for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['client_add_document'])) {

    // Permission check - only primary or technical contacts can create documents
    if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
        header("Location: post.php?logout");
        exit();
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

    // Logging
    logAction("Document", "Create", "Client contact $session_contact_name created document $document_name", $session_client_id, $document_id);

    $_SESSION['alert_message'] = "Document <strong>$document_name</strong> created successfully";

    header('Location: documents.php');
}

if (isset($_POST['client_upload_document'])) {

    // Permission check - only primary or technical contacts can upload documents
    if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
        header("Location: post.php?logout");
        exit();
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

                // Logging
                logAction("Document", "Upload", "Client contact $session_contact_name uploaded document $document_name with file $file_name", $session_client_id, $document_id);

                $_SESSION['alert_message'] = "Document <strong>$document_name</strong> uploaded successfully";

            } else {
                $_SESSION['alert_type'] = 'error';
                $_SESSION['alert_message'] = 'Error uploading file. Please try again.';
            }

        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'Invalid file type. Please upload PDF, Word documents, or text files only.';
        }

    } else {
        $_SESSION['alert_type'] = 'error';
        $_SESSION['alert_message'] = 'Please select a file to upload.';
    }

    header('Location: documents.php');
}

?>
