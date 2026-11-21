<?php
/*
 * API - Quotes - Send
 * POST /api/v1/quotes/send.php
 *
 * Email a quote, as Send does on its page - with a link where the client
 * views and accepts or declines it, and its PDF if you ask. A draft becomes Sent.
 *
 * Parameters (POST, JSON body):
 *   api_key      required - Your API key
 *   quote_id     required - The quote
 *   client_id    the quote's client - needed when the key's user is limited to certain clients
 *   contact_ids  optional - The client's contacts to email; left out, its primary contact
 *   note         optional - A note at the top of the email
 *   attach_pdf   optional - 1 to attach the PDF, 0 not to; left out, as your billing settings say
 *
 * Answer: emailed_to (who it was queued to), status, and url.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";

// Parse ID
$quote_id = intval($_POST['quote_id'] ?? 0);

// Default
$update_count = false;

$quote = $quote_id ? billingRow('quote', $quote_id) : null;
if ($quote && $client_id != 0 && intval($quote['quote_client_id']) !== $client_id) {
    $quote = null;
}

if ($quote) {

    $client_id = intval($quote['quote_client_id']);
    $reference = billingNumber('quote', $quote);

    // The contacts asked for - only this client's can match - or the default
    $contact_ids = $_POST['contact_ids'] ?? null;
    $recipients = is_array($contact_ids) ? billingEmailRecipients($client_id, $contact_ids) : billingEmailRecipients($client_id, null, 'quote');
    if (!$recipients) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = is_array($contact_ids) ? "None of those contacts are the client's, with an email address." : "The client has no primary contact with an email address - send contact_ids.";
        echo json_encode($return_arr);
        exit();
    }

    // The quote, with the note and the PDF (functions/billing_email.php)
    [$subject, $email] = billingQuoteEmail($quote, ['note' => trim(mb_substr((string) ($_POST['note'] ?? ''), 0, 2000))]);
    $attach_pdf = array_key_exists('attach_pdf', $_POST) ? !empty($_POST['attach_pdf']) : billingReminderRules()['attach_pdf'];
    $attach = [];
    if ($attach_pdf && ($file = billingEmailPdf(billingPdf('quote', $quote)))) {
        $attach[] = $file;
    }
    $sent = billingEmailQueue($recipients, $subject, $email, ['from' => 'quote', 'attach' => $attach]);

    $by = 'the API (' . html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8') . ')';
    billingLog('quote', $quote_id, $quote['quote_status'] === 'Draft' ? 'Sent' : $quote['quote_status'], "Quote emailed by $by to " . implode(', ', $sent) . ($attach ? ' with the PDF' : ''));

    // Sending a draft sends it - anything further along keeps its status
    if ($quote['quote_status'] === 'Draft') {
        mysqli_query($mysqli, "UPDATE quotes SET quote_status = 'Sent' WHERE quote_id = $quote_id");
    }

    $update_count = 1;
    $return_arr['emailed_to'] = $sent;
    $return_arr['status'] = (string) getFieldById('quotes', $quote_id, 'quote_status');
    $return_arr['url'] = billingGuestUrl('quote', $quote);

    $reference = escapeSql($reference);
    foreach ($recipients as $recipient) {
        logAudit("Quote", "Email", "Emailed quote $reference to " . escapeSql($recipient['email']) . " via API ($api_key_name)", $client_id, $quote_id);
    }
    logAudit("API", "Success", "Sent quote $reference via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
