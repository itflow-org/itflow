<?php

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/load_global_settings.php";

require_once __DIR__ . "/../includes/session_init.php";

require_once "../includes/inc_set_timezone.php"; // Must be included after session_start to work

// logAudit() reads these globals - without them guest audit rows have no IP
$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT'] ?? '');

/*
 * A quote accepted or declined from its guest page (guest_view_quote.php) -
 * posted, never a link: mail scanners and link previews open links, and must
 * not answer a quote. Its link key is the only proof, matched exactly.
 */
if (isset($_POST['quote_id'], $_POST['url_key']) && (isset($_POST['accept_quote']) || isset($_POST['decline_quote']))) {

    // The answer and what follows it - functions/billing.php, shared with the client portal
    $accept = isset($_POST['accept_quote']);
    $quote_id = intval($_POST['quote_id']);
    $url_key = is_string($_POST['url_key']) ? $_POST['url_key'] : '';
    $quote_key = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT quote_url_key FROM quotes WHERE quote_id = $quote_id"))[0] ?? '');

    if ($quote_key !== '' && hash_equals($quote_key, $url_key)) {

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

/*
 * A contract signed from its link (guest_view_contract.php) - the round's id
 * and key are the only proof, matched exactly. Signed once, and only the
 * version on the page: a request withdrawn or replaced meanwhile isn't
 * signed. Who signed is the name given, noted as from the link.
 */
if (isset($_POST['sign_contract'])) {

    $round = contractSignaturesReady() ? contractSignatureByKey($_POST['contract_signature_id'] ?? 0, $_POST['key'] ?? '') : null;
    if (!$round) {
        http_response_code(404);
        exit("This contract link isn't working - open it again from the email.");
    }
    $back = '/guest/guest_view_contract.php?id=' . intval($round['contract_signature_id']) . '&key=' . rawurlencode((string) $round['contract_signature_url_key']);
    $state = contractSignatureState($round);
    if (!in_array($state['key'], ['sent', 'viewed'], true)) {
        flashAlert($state['key'] === 'withdrawn' ? "This request was withdrawn, so it wasn't signed." : "It has already been signed.", 'error');
        redirect($back);
    }
    if (!hash_equals((string) $round['contract_signature_document_hash'], (string) ($_POST['document_hash'] ?? ''))) {
        flashAlert("The contract changed while you had it open - read it again before signing.", 'error');
        redirect($back);
    }
    [$f, $error] = contractSignatureFromInput($_POST);
    if ($error !== '') {
        flashAlert(escapeHtml($error) . ' It was not signed yet.', 'error');
        redirect($back);
    }
    if (!contractSignatureClientSign($round, $f, 'link')) {
        flashAlert("It couldn't be signed - it may have just been withdrawn.", 'error');
        redirect($back);
    }
    flashAlert("Signed - thank you. We'll countersign it and email you a copy.");
    redirect($back);

}

if (isset($_GET['export_contract_pdf'])) {

    $round = contractSignaturesReady() ? contractSignatureByKey($_GET['id'] ?? 0, $_GET['key'] ?? '') : null;
    if (!$round) {
        http_response_code(404);
        exit("This contract link isn't working - open it again from the email.");
    }
    billingPdfSend(contractPdf(contractRow(intval($round['contract_id'])), $round), true);

}

/*
 * The guest ticket page's buttons (guest_view_ticket.php): close or reopen a
 * resolved ticket, rate a closed one. Posted, never links - mail scanners and
 * link previews open links, and must not close a ticket. The ticket's link
 * key is the only proof, matched exactly (ticketByGuestKey()). Who pressed
 * it isn't known - the link goes to watchers too - so it is noted as from
 * the guest link, never as the contact. Each happens once: a second press,
 * or a ticket an agent has moved on since, just goes back to the page.
 */
if (isset($_POST['close_ticket']) || isset($_POST['reopen_ticket']) || isset($_POST['add_ticket_feedback'])) {

    $row = ticketByGuestKey($_POST['ticket_id'] ?? 0, $_POST['url_key'] ?? '');
    if (!$row) {
        http_response_code(404);
        exit("This ticket link isn't working - open it again from the email.");
    }

    $ticket_id = intval($row['ticket_id']);
    $client_id = intval($row['ticket_client_id']);
    $reference = escapeSql(ticketReference($row));
    $back = ticketGuestPath($row);

    if (isset($_POST['close_ticket'])) {

        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 5, ticket_closed_at = NOW() WHERE ticket_id = $ticket_id AND ticket_resolved_at IS NOT NULL AND ticket_closed_at IS NULL");
        if (mysqli_affected_rows($mysqli) === 1) {
            syncTicketSlaClock($ticket_id);
            logTicketHistory($ticket_id, "The client closed the ticket from the guest link");
            mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket closed by client (guest URL).', ticket_reply_type = 'Internal', ticket_reply_by = 0, ticket_reply_ticket_id = $ticket_id");
            logAudit("Ticket", "Edit", "Ticket $reference closed from its guest link", $client_id, $ticket_id);
            triggerCustomAction('ticket_close', $ticket_id);
            flashAlert("Ticket closed - thank you");
        }

    } elseif (isset($_POST['reopen_ticket'])) {

        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 2, ticket_resolved_at = NULL WHERE ticket_id = $ticket_id AND ticket_resolved_at IS NOT NULL AND ticket_closed_at IS NULL");
        if (mysqli_affected_rows($mysqli) === 1) {
            // The resolution clock runs again, as when the portal or an emailed reply reopens it
            resetTicketResolutionSla($ticket_id);
            syncTicketSlaClock($ticket_id);
            logTicketHistory($ticket_id, "The client reopened the ticket from the guest link");
            mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket reopened by client (guest URL).', ticket_reply_type = 'Internal', ticket_reply_by = 0, ticket_reply_ticket_id = $ticket_id");
            logAudit("Ticket", "Edit", "Ticket $reference reopened from its guest link", $client_id, $ticket_id);
            // Nothing else tells you - no reply came with it
            appNotify("Ticket", "Ticket $reference was reopened from its guest link - " . escapeSql($row['ticket_subject']), "/agent/ticket.php?ticket_id=$ticket_id" . ($client_id ? "&client_id=$client_id" : ''), $client_id, $ticket_id);
            triggerCustomAction('ticket_update', $ticket_id);
            flashAlert("Ticket reopened - now tell us what is still wrong: reply to our email, or give us a call");
        }

    } else {

        // Only the two ratings the page offers - the reports count Good against Bad - and only the first one
        $feedback = $_POST['add_ticket_feedback'] === 'Bad' ? 'Bad' : 'Good';
        mysqli_query($mysqli, "UPDATE tickets SET ticket_feedback = '$feedback' WHERE ticket_id = $ticket_id AND ticket_closed_at IS NOT NULL AND (ticket_feedback IS NULL OR ticket_feedback = '')");
        if (mysqli_affected_rows($mysqli) === 1) {
            logTicketHistory($ticket_id, "The client rated the ticket " . ($feedback === 'Bad' ? 'not good' : 'good') . " from the guest link");
            if ($feedback === 'Bad') {
                appNotify("Feedback", "Guest rated ticket number $reference (ID: $ticket_id) as bad", "/agent/ticket.php?ticket_id=$ticket_id" . ($client_id ? "&client_id=$client_id" : ''), $client_id, $ticket_id);
            }
            triggerCustomAction('ticket_feedback', $ticket_id);
            flashAlert("Feedback recorded - thank you");
        }

    }

    redirect($back);

}

/*
 * Add to calendar on the guest ticket page - the calendar file the booking
 * email carried (ticketScheduleIcs()), for work booked on an open ticket.
 * The same event, so it updates one already in the calendar.
 */
if (isset($_GET['ticket_calendar'])) {

    $row = ticketByGuestKey($_GET['ticket_calendar'], $_GET['url_key'] ?? '');
    if (!$row || empty($row['ticket_schedule']) || !ticketIsOpen($row)) {
        http_response_code(404);
        exit('Nothing is booked on this ticket.');
    }

    $ics = ticketScheduleIcs(ticketScheduleRow($row['ticket_id']), $row['ticket_schedule']);
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_-]/', '', ticketReference($row)) . '.ics"');
    header('Content-Length: ' . strlen($ics));
    echo $ics;
    exit;

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

