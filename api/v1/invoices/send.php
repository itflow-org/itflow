<?php
/*
 * API - Invoices - Send
 * POST /api/v1/invoices/send.php
 *
 * Email an invoice, as Send does on its page - with a link to view and pay
 * it, and its PDF if you ask. A draft becomes Sent. With kind "reminder", a
 * reminder of what is still owed on one already sent.
 *
 * Parameters (POST, JSON body):
 *   api_key      required - Your API key
 *   invoice_id   required - The invoice
 *   client_id    the invoice's client - needed when the key's user is limited to certain clients
 *   contact_ids  optional - The client's contacts to email; left out, its primary and billing contacts
 *   kind         optional - invoice (the default) or reminder
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
$invoice_id = intval($_POST['invoice_id'] ?? 0);

// Default
$update_count = false;

$invoice = $invoice_id ? billingRow('invoice', $invoice_id) : null;
if ($invoice && $client_id != 0 && intval($invoice['invoice_client_id']) !== $client_id) {
    $invoice = null;
}

if ($invoice) {

    $client_id = intval($invoice['invoice_client_id']);
    $reference = billingNumber('invoice', $invoice);

    if (in_array($invoice['invoice_status'], ['Cancelled', 'Non-Billable'], true)) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = "$reference is " . strtolower($invoice['invoice_status']) . " - there is nothing to send.";
        echo json_encode($return_arr);
        exit();
    }

    // The contacts asked for - only this client's can match - or the defaults
    $contact_ids = $_POST['contact_ids'] ?? null;
    $recipients = billingEmailRecipients($client_id, is_array($contact_ids) ? $contact_ids : null);
    if (!$recipients) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = is_array($contact_ids) ? "None of those contacts are the client's, with an email address." : "The client has no primary or billing contact with an email address - send contact_ids.";
        echo json_encode($return_arr);
        exit();
    }

    // The invoice, or a reminder of what is still owed - with the note and the PDF
    $kind = ($_POST['kind'] ?? '') === 'reminder' ? 'reminder' : 'invoice';
    $note = trim(mb_substr((string) ($_POST['note'] ?? ''), 0, 2000));
    $attach_pdf = array_key_exists('attach_pdf', $_POST) ? !empty($_POST['attach_pdf']) : billingReminderRules()['attach_pdf'];
    [$subject, $email] = billingInvoiceEmail($invoice, ['kind' => $kind, 'note' => $note]);
    $attach = [];
    if ($attach_pdf && ($file = billingEmailPdf(billingPdf('invoice', $invoice)))) {
        $attach[] = $file;
    }
    $sent = billingEmailQueue($recipients, $subject, $email, ['attach' => $attach]);
    $reminder = $kind === 'reminder' && $email['kind'] === 'Payment reminder';
    // Texted with its link too, to those of them who agreed to texts (functions/sms.php)
    $texted = smsInvoiceNotify($invoice, $reminder ? 'reminder' : 'invoice', $recipients);

    $by = 'the API (' . html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8') . ')';
    billingLog('invoice', $invoice_id, $invoice['invoice_status'] === 'Draft' ? 'Sent' : $invoice['invoice_status'],
        ($reminder ? 'Reminder emailed' : 'Invoice emailed') . " by $by to " . implode(', ', $sent) . ($attach ? ' with the PDF' : '') . ($texted ? ' - and texted to ' . implode(', ', $texted) : ''));

    // Sending a draft sends it - anything further along keeps its status
    if ($invoice['invoice_status'] === 'Draft') {
        mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Sent' WHERE invoice_id = $invoice_id");
    }

    $update_count = 1;
    $return_arr['emailed_to'] = $sent;
    $return_arr['status'] = (string) getFieldById('invoices', $invoice_id, 'invoice_status');
    $return_arr['url'] = billingGuestUrl('invoice', $invoice);

    $reference = escapeSql($reference);
    foreach ($recipients as $recipient) {
        logAudit("Invoice", "Email", "Emailed " . escapeSql($recipient['email']) . ($reminder ? " a reminder for" : "") . " invoice $reference via API ($api_key_name)", $client_id, $invoice_id);
    }
    logAudit("API", "Success", ($reminder ? "Sent a reminder for" : "Sent") . " invoice $reference via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
