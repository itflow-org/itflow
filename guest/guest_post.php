<?php

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/load_global_settings.php";

require_once __DIR__ . "/../includes/session_init.php";

require_once "../includes/inc_set_timezone.php"; // Must be included after session_start to work

// logAudit() reads these globals - without them guest audit rows have no IP
$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT']);

if (isset($_GET['accept_quote'], $_GET['url_key']) || isset($_GET['decline_quote'], $_GET['url_key'])) {

    // The answer and what follows it - functions/billing.php, shared with the client portal
    $accept = isset($_GET['accept_quote']);
    $quote_id = intval($accept ? $_GET['accept_quote'] : $_GET['decline_quote']);
    $url_key = escapeSql($_GET['url_key']);

    $sql = mysqli_query($mysqli, "SELECT quote_id FROM quotes WHERE quote_id = $quote_id AND quote_url_key = '$url_key'");

    if (mysqli_num_rows($sql) == 1) {

        $answer = billingQuoteAnswer($quote_id, $accept);

        if (!$answer['ok']) {
            flashAlert($answer['error'], 'error');
            redirect();
        }

        if ($accept) {
            flashAlert("Quote Accepted");
        } else {
            flashAlert("Quote Declined", 'danger');
        }

        redirect();

    } else {
        echo "Invalid!!";
    }

}

if (isset($_GET['reopen_ticket'], $_GET['url_key'])) {

    $ticket_id = intval($_GET['ticket_id']);
    $url_key = escapeSql($_GET['url_key']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key' AND ticket_resolved_at IS NOT NULL AND ticket_closed_at IS NULL");

    if (mysqli_num_rows($sql) == 1) {
        // Update the ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 2, ticket_resolved_at = NULL WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key'");
        logTicketHistory($ticket_id, "The client reopened the ticket from the guest link");

        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket reopened by client (guest URL).', ticket_reply_type = 'Internal', ticket_reply_by = 0, ticket_reply_ticket_id = $ticket_id");

        triggerCustomAction('ticket_update', $ticket_id);

        flashAlert("Ticket reopened");

        redirect();

    } else {
        echo "Invalid!!";
    }

}

if (isset($_GET['close_ticket'], $_GET['url_key'])) {

    $ticket_id = intval($_GET['ticket_id']);
    $url_key = escapeSql($_GET['url_key']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key' AND ticket_resolved_at IS NOT NULL AND ticket_closed_at IS NULL");

    if (mysqli_num_rows($sql) == 1) {

        // Update the ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 5, ticket_closed_at = NOW() WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key'");
        logTicketHistory($ticket_id, "The client closed the ticket from the guest link");

        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket closed by client (guest URL).', ticket_reply_type = 'Internal', ticket_reply_by = 0, ticket_reply_ticket_id = $ticket_id");

        triggerCustomAction('ticket_close', $ticket_id);

        flashAlert("Ticket closed");

        redirect();

    } else {
        echo "Invalid!!";
    }
}

if (isset($_GET['add_ticket_feedback'], $_GET['url_key'])) {

    $ticket_id = intval($_GET['ticket_id']);
    $url_key = escapeSql($_GET['url_key']);
    $feedback = escapeSql($_GET['feedback']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key' AND ticket_closed_at IS NOT NULL");

    if (mysqli_num_rows($sql) == 1) {
        // Add feedback
        mysqli_query($mysqli, "UPDATE tickets SET ticket_feedback = '$feedback' WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key'");

        // Notify on bad feedback
        if ($feedback == "Bad") {
            $ticket_details = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number FROM tickets WHERE ticket_id = $ticket_id LIMIT 1"));
            $ticket_prefix = escapeSql($ticket_details['ticket_prefix']);
            $ticket_number = intval($ticket_details['ticket_number']);

            appNotify("Feedback", "Guest rated ticket number $ticket_prefix$ticket_number (ID: $ticket_id) as bad", "/agent/ticket.php?ticket_id=$ticket_id");
        }

        flashAlert("Feedback recorded - thank you");

        redirect();

        triggerCustomAction('ticket_feedback', $ticket_id);

    } else {
        echo "Invalid!!";
    }

}

if (isset($_GET['approve_ticket_task'])) {

    $task_id = intval($_GET['approve_ticket_task']);
    $approval_id = intval($_GET['approval_id']);
    $url_key = escapeSql($_GET['approval_url_key']);

    $approval_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT approval_created_by, approval_required_user_id, approval_scope, approval_type, task_name,
        task_ticket_id FROM task_approvals LEFT JOIN tasks on task_id = approval_task_id WHERE approval_id = $approval_id AND approval_task_id = $task_id AND approval_url_key = '$url_key' AND approval_status = 'pending'"));

    $task_name = escapeSql($approval_row['task_name']);
    $scope = escapeHtml($approval_row['approval_scope']);
    $type = escapeHtml($approval_row['approval_type']);
    $required_user = intval($approval_row['approval_required_user_id']);
    $created_by = intval($approval_row['approval_created_by']);
    $ticket_id = intval($approval_row['task_ticket_id']);

    if (!$approval_row) {
        exit("Cannot find/approve that task");
    }

    // Approve
    mysqli_query($mysqli, "UPDATE task_approvals SET approval_status = 'approved', approval_approved_by = $required_user WHERE approval_id = $approval_id AND approval_task_id = $task_id AND approval_url_key = '$url_key' AND approval_status = 'pending'");

    // Notify tech
    mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = 'Guest approved ticket task $task_name', notification_action = 'ticket.php?ticket_id=$ticket_id', notification_user_id = $created_by");

    // Logging
    logAudit("Task", "Edit", "Guest user approved task $task_name via link (approval $approval_id)", 0, $task_id);

    flashAlert("Task Approved");
    redirect();

}

if (isset($_GET['export_quote_pdf'])) {

    // The quote's own link key is the only way in - the same one its guest page checks
    $quote = billingRow('quote', intval($_GET['export_quote_pdf']), false);
    if (!$quote || (string) $quote['quote_url_key'] === '' || !hash_equals((string) $quote['quote_url_key'], (string) ($_GET['url_key'] ?? ''))) {
        http_response_code(404);
        exit('That quote is not available.');
    }

    billingPdfSend(billingPdf('quote', $quote));

}

if (isset($_GET['export_invoice_pdf'])) {

    // The invoice's own link key is the only way in - the same one its guest page checks
    $invoice = billingRow('invoice', intval($_GET['export_invoice_pdf']), false);
    if (!$invoice || (string) $invoice['invoice_url_key'] === '' || !hash_equals((string) $invoice['invoice_url_key'], (string) ($_GET['url_key'] ?? ''))) {
        http_response_code(404);
        exit('That invoice is not available.');
    }

    billingPdfSend(billingPdf('invoice', $invoice));

}

if (isset($_POST['guest_quote_upload_file'])) {

    $quote_id = intval($_POST['quote_id']);
    $url_key = escapeSql($_POST['url_key']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT quote_id FROM quotes WHERE quote_id = $quote_id AND quote_url_key = '$url_key'");

    if (mysqli_num_rows($sql) == 1) {

        // Each PDF to the client's Client Uploads and onto the quote - functions/billing.php, shared with the client portal
        if (!empty($_FILES['file'])) {
            $upload = billingQuoteAddFiles($quote_id, $_FILES['file']);
            foreach ($upload['added'] as $added) {
                flashAlert('File uploaded!');
            }
            if ($upload['failed']) {
                flashAlert('Something went wrong uploading the file - please let the support team know.', 'error');
                logApp("Guest", "error", "Error uploading file to invoice");
            }
        }

        redirect();

    } else {
        echo "Invalid!!";
    }

}

if (isset($_POST['create_stripe_customer'])) {

    $invoice_url_key = escapeSql($_POST['url_key']);
    $invoice_id      = intval($_POST['invoice_id']);
    $name = escapeSql($_POST['name']);
    $email = escapeSql($_POST['email']);

    // Query invoice details
    $sql = mysqli_query(
        $mysqli,
        "SELECT client_id, client_name, invoice_amount, invoice_currency_code, invoice_date,
            invoice_discount_amount, invoice_due, invoice_id, invoice_number, invoice_prefix,
            invoice_status FROM invoices
         LEFT JOIN clients ON invoice_client_id = client_id
         WHERE invoice_id = $invoice_id
         AND invoice_url_key = '$invoice_url_key'
         AND invoice_status NOT IN ('Draft', 'Paid', 'Cancelled')
         LIMIT 1"
    );

    // Ensure valid invoice
    if (!$sql || mysqli_num_rows($sql) !== 1) {
        echo "<br><h2>Oops, something went wrong! Please ensure you have the correct URL and have not already paid this invoice.</h2>";
        error_log("Stripe payment error - Invoice with ID $invoice_id not found or not eligible.");
        exit();
    }

    $row = mysqli_fetch_assoc($sql);
    $invoice_id            = intval($row['invoice_id']);
    $invoice_prefix        = escapeHtml($row['invoice_prefix']);
    $invoice_number        = intval($row['invoice_number']);
    $client_id             = intval($row['client_id']);
    $client_name           = escapeHtml($row['client_name']);

    // Get Stripe provider config
    $stripe_provider_result = mysqli_query($mysqli, "
        SELECT payment_provider_id, payment_provider_private_key
        FROM payment_providers
        WHERE payment_provider_name = 'Stripe'
        AND payment_provider_active = 1
        LIMIT 1
    ");

    $stripe_provider = mysqli_fetch_assoc($stripe_provider_result);
    if (!$stripe_provider) {
        flashAlert("Stripe provider is not configured in the system.", 'danger');
        redirect("saved_payment_methods.php");
    }

    $stripe_provider_id = intval($stripe_provider['payment_provider_id']);
    $stripe_secret_key = escapeHtml($stripe_provider['payment_provider_private_key']);

    if (empty($stripe_secret_key)) {
        flashAlert("Stripe credentials missing. Please contact support.", 'danger');
        redirect("saved_payment_methods.php");
    }

    // Check if client already has a Stripe customer
    $existing_customer = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT payment_provider_client
        FROM client_payment_provider
        WHERE client_id = $client_id
        AND payment_provider_id = $stripe_provider_id
        LIMIT 1
    "));

    if (!$existing_customer) {
        try {
            // Initialize Stripe
            require_once '../includes/stripe_init.php';
            $stripe = new \Stripe\StripeClient($stripe_secret_key);

            // Create new customer in Stripe
            $customer = $stripe->customers->create([
                'name' => $client_name,
                'email' => $email,
                'metadata' => [
                    'itflow_client_id' => $client_id,
                    'consent_by' => $name,
                ]
            ]);

            $stripe_customer_id = escapeSql($customer->id);

            // Insert customer into client_payment_provider
            mysqli_query($mysqli, "
                INSERT INTO client_payment_provider
                SET client_id = $client_id,
                    payment_provider_id = $stripe_provider_id,
                    payment_provider_client = '$stripe_customer_id',
                    client_payment_provider_created_at = NOW()
            ");

            logAudit("Stripe", "Create", "Guest $name created Stripe customer for $client_name as $stripe_customer_id and authorized future automatic payments", $client_id);

            flashAlert("Stripe customer created. Thank you for your consent.");

        } catch (Exception $e) {
            $error = $e->getMessage();

            error_log("Stripe error while creating customer for $client_name: $error");

            logApp("Stripe", "error", "Failed to create Stripe customer for $client_name: $error");

            flashAlert("An error occurred while creating your Stripe customer. Please try again.", 'danger');

        }

    } else {
        flashAlert("Stripe customer already exists for your account.", 'danger');
    }

    redirect('guest_view_invoice.php?invoice_id=' . $invoice_id . '&url_key=' . urlencode($invoice_url_key));
}

