<?php

/*
 * Previews of every email ITFlow sends - Admin > Mail > Previews
 * (admin/settings_mail.php shows them, admin/mail_preview.php draws one).
 *
 * Each preview is made by the code that sends the email, so it is what goes
 * out: it runs on a set of sample records (emailPreviewSample()) inside a
 * transaction that is then rolled back, while emailQueue() keeps the emails
 * instead of queuing them (emailCaptureStart(), functions/email.php). Custom
 * actions don't run meanwhile (triggerCustomAction()), and nothing that
 * writes files is called - an invoice's PDF is described, not made.
 */

/** The sample people the previews use - who they are and their addresses. */
const EMAIL_PREVIEW_PEOPLE = [
    'client' => 'Riverside Dental Practice',
    'contact' => ['Alex Morgan', 'alex.morgan@example.com', 'Practice Manager'],
    'colleague' => ['Jamie Chen', 'jamie.chen@example.com', 'IT Lead'],
    'agent' => ['Sam Taylor', 'sam.taylor@example.com'],
];

/**
 * Make the sample records - inside the preview's transaction, so they are
 * gone again after. The team's own addresses that are not set (new tickets,
 * SLA alerts, quote and payment notices) are filled in for the moment, so
 * every email has someone to go to. Their ids and the values the previews use.
 */
function emailPreviewSample() {
    global $mysqli, $session_user_id;
    $insert = function ($sql) use ($mysqli) {
        mysqli_query($mysqli, $sql);
        return intval(mysqli_insert_id($mysqli));
    };
    $e = function ($value) use ($mysqli) {
        return mysqli_real_escape_string($mysqli, (string) $value);
    };
    $people = EMAIL_PREVIEW_PEOPLE;
    $me = intval($session_user_id);

    mysqli_query($mysqli, "UPDATE settings SET
        config_ticket_new_ticket_notification_email = IF(COALESCE(config_ticket_new_ticket_notification_email, '') = '', 'tickets@example.com', config_ticket_new_ticket_notification_email),
        config_sla_notification_email = IF(COALESCE(config_sla_notification_email, '') = '', 'sla@example.com', config_sla_notification_email),
        config_quote_notification_email = IF(COALESCE(config_quote_notification_email, '') = '', 'quotes@example.com', config_quote_notification_email),
        config_invoice_paid_notification_email = IF(COALESCE(config_invoice_paid_notification_email, '') = '', 'billing@example.com', config_invoice_paid_notification_email),
        config_ticket_client_general_notifications = 1
        WHERE company_id = 1");
    $settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_ticket_prefix, config_ticket_next_number, config_invoice_prefix, config_invoice_next_number,
        config_quote_prefix, config_quote_next_number, config_sla_notification_email FROM settings WHERE company_id = 1"));
    $currency = $e((string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT company_currency FROM companies WHERE company_id = 1"))[0] ?? '') ?: 'USD');
    $sla_id = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT sla_id FROM slas WHERE sla_archived_at IS NULL ORDER BY sla_id LIMIT 1"))[0] ?? 0);
    $role_id = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT role_id FROM user_roles ORDER BY role_id LIMIT 1"))[0] ?? 0);
    $account_id = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT account_id FROM accounts ORDER BY account_id LIMIT 1"))[0] ?? 0);
    $hash = $e(password_hash(randomString(), PASSWORD_DEFAULT));

    $client_id = $insert("INSERT INTO clients SET client_name = '" . $e($people['client']) . "', client_currency_code = '$currency', client_net_terms = 30");
    $location_id = $insert("INSERT INTO locations SET location_name = 'Main office', location_address = '120 River Road', location_city = 'Springfield', location_state = 'PA', location_zip = '15001',
        location_primary = 1, location_client_id = $client_id");
    [$alex_name, $alex_email, $alex_title] = $people['contact'];
    [$jamie_name, $jamie_email, $jamie_title] = $people['colleague'];
    $alex_user = $insert("INSERT INTO users SET user_name = '" . $e($alex_name) . "', user_email = '" . $e($alex_email) . "', user_password = '$hash', user_auth_method = 'local', user_type = 2, user_status = 1");
    $jamie_user = $insert("INSERT INTO users SET user_name = '" . $e($jamie_name) . "', user_email = '" . $e($jamie_email) . "', user_password = '$hash', user_auth_method = 'local', user_type = 2, user_status = 1");
    $alex = $insert("INSERT INTO contacts SET contact_name = '" . $e($alex_name) . "', contact_title = '" . $e($alex_title) . "', contact_email = '" . $e($alex_email) . "',
        contact_primary = 1, contact_important = 1, contact_billing = 1, contact_location_id = $location_id, contact_user_id = $alex_user, contact_client_id = $client_id");
    $jamie = $insert("INSERT INTO contacts SET contact_name = '" . $e($jamie_name) . "', contact_title = '" . $e($jamie_title) . "', contact_email = '" . $e($jamie_email) . "',
        contact_technical = 1, contact_company_ticket_emails = 'new', contact_location_id = $location_id, contact_user_id = $jamie_user, contact_client_id = $client_id");
    [$agent_name, $agent_email] = $people['agent'];
    $agent = $insert("INSERT INTO users SET user_name = '" . $e($agent_name) . "', user_email = '" . $e($agent_email) . "', user_password = '$hash', user_auth_method = 'local',
        user_type = 1, user_status = 1, user_role_id = $role_id");

    // Tickets - one with a reply from us and one from them, and another for a list
    $prefix = $e($settings['config_ticket_prefix'] ?? '');
    $number = max(1, intval($settings['config_ticket_next_number'] ?? 1));
    $ticket = $insert("INSERT INTO tickets SET ticket_prefix = '$prefix', ticket_number = $number, ticket_source = 'Portal', ticket_subject = 'Front desk printer is offline',
        ticket_details = '<p>The printer at the front desk has shown <b>offline</b> since this morning. We have restarted it, but it still will not print.</p>',
        ticket_priority = 'High', ticket_status = 2, ticket_sla_id = $sla_id, ticket_billable = 0, ticket_url_key = '" . $e(randomString(32)) . "',
        ticket_response_due_at = NOW() + INTERVAL 1 HOUR, ticket_resolution_due_at = NOW() + INTERVAL 1 DAY,
        ticket_created_by = $me, ticket_assigned_to = $agent, ticket_client_id = $client_id, ticket_contact_id = $alex, ticket_location_id = $location_id");
    $ticket2 = $insert("INSERT INTO tickets SET ticket_prefix = '$prefix', ticket_number = " . ($number + 1) . ", ticket_source = 'Email', ticket_subject = 'Laptop for the new receptionist',
        ticket_details = '<p>Sara starts on Monday and will need a laptop and an email account.</p>', ticket_priority = 'Medium', ticket_status = 2, ticket_billable = 0,
        ticket_url_key = '" . $e(randomString(32)) . "', ticket_created_by = $me, ticket_assigned_to = $agent, ticket_client_id = $client_id, ticket_contact_id = $alex");
    $reply = '<p>Hi Alex,</p><p>I connected remotely and the print spooler had stopped. It is printing again now - could you send a test page to check?</p><p>Thanks,<br>' . escapeHtml(stripslashes((string) ($GLOBALS['session_name'] ?? ''))) . '</p>';
    $insert("INSERT INTO ticket_replies SET ticket_reply = '" . $e($reply) . "', ticket_reply_type = 'Public', ticket_reply_time_worked = '00:10:00', ticket_reply_by = $me, ticket_reply_ticket_id = $ticket");
    $client_reply = '<p>The test page came out fine - thank you!</p>';
    $insert("INSERT INTO ticket_replies SET ticket_reply = '" . $e($client_reply) . "', ticket_reply_type = 'Client', ticket_reply_time_worked = '00:00:00', ticket_reply_by = $alex, ticket_reply_ticket_id = $ticket");
    $insert("INSERT INTO ticket_watchers SET watcher_email = '" . $e($jamie_email) . "', watcher_ticket_id = $ticket");
    $recurring = $insert("INSERT INTO recurring_tickets SET recurring_ticket_subject = 'Monthly backup check', recurring_ticket_details = '<p>Check last month''s backups ran, and test a restore.</p>',
        recurring_ticket_priority = 'Low', recurring_ticket_frequency = 'Monthly', recurring_ticket_billable = 0, recurring_ticket_start_date = CURDATE(), recurring_ticket_next_run = CURDATE(),
        recurring_ticket_created_by = $me, recurring_ticket_assigned_to = $agent, recurring_ticket_client_id = $client_id, recurring_ticket_contact_id = $alex");
    $task = $insert("INSERT INTO tasks SET task_name = 'Replace the printer''s fuser unit', task_ticket_id = $ticket");
    $approval = $insert("INSERT INTO task_approvals SET approval_scope = 'client', approval_type = 'any', approval_status = 'pending', approval_created_by = $me,
        approval_url_key = '" . $e(randomString(32)) . "', approval_task_id = $task");

    // An invoice still owed, one paid, and a quote
    $invoice_prefix = $e($settings['config_invoice_prefix'] ?? '');
    $invoice_number = max(1, intval($settings['config_invoice_next_number'] ?? 1));
    $scope = $e('Managed IT services - ' . date('F Y'));
    $invoice = $insert("INSERT INTO invoices SET invoice_prefix = '$invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$scope', invoice_status = 'Sent',
        invoice_date = CURDATE() - INTERVAL 10 DAY, invoice_due = CURDATE() + INTERVAL 20 DAY, invoice_amount = 1540.00, invoice_currency_code = '$currency',
        invoice_url_key = '" . $e(randomString(32)) . "', invoice_category_id = 0, invoice_client_id = $client_id");
    $insert("INSERT INTO invoice_items SET item_name = 'Managed IT services', item_quantity = 1, item_price = 1250, item_subtotal = 1250, item_tax = 0, item_total = 1250, item_order = 1, item_invoice_id = $invoice");
    $insert("INSERT INTO invoice_items SET item_name = 'Microsoft 365 Business Standard', item_quantity = 10, item_price = 29, item_subtotal = 290, item_tax = 0, item_total = 290, item_order = 2, item_invoice_id = $invoice");
    $paid = $insert("INSERT INTO invoices SET invoice_prefix = '$invoice_prefix', invoice_number = " . ($invoice_number + 1) . ", invoice_scope = 'Printer repair', invoice_status = 'Paid',
        invoice_date = CURDATE() - INTERVAL 3 DAY, invoice_due = CURDATE() + INTERVAL 27 DAY, invoice_amount = 290.00, invoice_currency_code = '$currency',
        invoice_url_key = '" . $e(randomString(32)) . "', invoice_category_id = 0, invoice_client_id = $client_id");
    $insert("INSERT INTO invoice_items SET item_name = 'Printer repair', item_quantity = 2, item_price = 145, item_subtotal = 290, item_tax = 0, item_total = 290, item_order = 1, item_invoice_id = $paid");
    $insert("INSERT INTO payments SET payment_date = CURDATE(), payment_amount = 290.00, payment_currency_code = '$currency', payment_method = 'Credit Card', payment_reference = 'ch_3PxR1v2Sample',
        payment_account_id = $account_id, payment_invoice_id = $paid");
    $quote_number = max(1, intval($settings['config_quote_next_number'] ?? 1));
    $quote = $insert("INSERT INTO quotes SET quote_prefix = '" . $e($settings['config_quote_prefix'] ?? '') . "', quote_number = $quote_number, quote_scope = 'Network refresh',
        quote_status = 'Sent', quote_date = CURDATE(), quote_expire = CURDATE() + INTERVAL 30 DAY, quote_amount = 4280.00, quote_currency_code = '$currency',
        quote_url_key = '" . $e(randomString(32)) . "', quote_category_id = 0, quote_client_id = $client_id");
    foreach ([['Firewall', 1, 1890], ['24-port switch', 2, 640], ['Installation and setup', 1, 1110]] as $order => [$name, $quantity, $price]) {
        $insert("INSERT INTO quote_items SET item_name = '" . $e($name) . "', item_quantity = $quantity, item_price = $price, item_subtotal = " . ($quantity * $price) . ",
            item_tax = 0, item_total = " . ($quantity * $price) . ", item_order = " . ($order + 1) . ", item_quote_id = $quote");
    }

    // Shared links - a login sent, and one asked for
    $share = $insert("INSERT INTO shared_items SET item_active = 1, item_key = '" . $e(hash('sha256', randomString(32))) . "', item_type = 'Credential', item_related_id = 0,
        item_name = 'Microsoft 365 admin', item_note = 'Here is the admin login we talked about on the phone.', item_recipient = '" . $e($alex_email) . "', item_notify = 1,
        item_view_limit = 1, item_expire_at = NOW() + INTERVAL 1 DAY, item_created_by = $me, item_client_id = $client_id");
    $request = $insert("INSERT INTO shared_items SET item_active = 1, item_key = '" . $e(hash('sha256', randomString(32))) . "', item_type = 'Request', item_related_id = 0,
        item_name = 'Wi-Fi controller login', item_recipient = '" . $e($alex_email) . "', item_notify = 1, item_view_limit = 1, item_expire_at = NOW() + INTERVAL 3 DAY,
        item_created_by = $me, item_client_id = $client_id");

    // A calendar event with the contact invited
    $calendar = $insert("INSERT INTO calendars SET calendar_name = 'Client visits', calendar_color = '#0d6efd'");
    $start = date('Y-m-d', strtotime('next tuesday')) . ' 10:00:00';
    $event = $insert("INSERT INTO calendar_events SET event_title = 'Quarterly IT review', event_description = 'We will go over last quarter''s tickets and plan the next one.',
        event_start = '$start', event_end = '" . date('Y-m-d H:i:s', strtotime($start) + 3600) . "', event_all_day = 0, event_sequence = 0, event_created_by = $me,
        event_client_id = $client_id, event_location_id = $location_id, event_calendar_id = $calendar");
    $insert("INSERT INTO calendar_event_attendees SET attendee_contact_id = $alex, attendee_event_id = $event");

    return [
        'client_id' => $client_id, 'client' => $people['client'], 'location_id' => $location_id,
        'alex' => $alex, 'alex_user' => $alex_user, 'alex_name' => $alex_name, 'alex_email' => $alex_email,
        'jamie' => $jamie, 'jamie_user' => $jamie_user, 'jamie_name' => $jamie_name, 'jamie_email' => $jamie_email,
        'agent' => $agent, 'agent_name' => $agent_name, 'agent_email' => $agent_email,
        'ticket' => $ticket, 'ticket2' => $ticket2, 'reply' => $reply, 'client_reply' => $client_reply, 'recurring' => $recurring, 'approval' => $approval,
        'invoice' => $invoice, 'paid' => $paid, 'quote' => $quote, 'share' => $share, 'request' => $request, 'event' => $event,
        'sla_email' => (string) ($settings['config_sla_notification_email'] ?? ''),
        'by' => stripslashes((string) ($GLOBALS['session_name'] ?? '')),
    ];
}

/** Send an invoice email the way Send does (agent/post/invoice.php), without the PDF - $opts as billingInvoiceEmail() takes them. */
function emailPreviewInvoice($invoice_id, array $opts = []) {
    $invoice = billingRow('invoice', $invoice_id, false);
    [$subject, $email] = billingInvoiceEmail($invoice, $opts);
    billingEmailQueue(billingEmailRecipients($invoice['invoice_client_id']), $subject, $email);
}

/*
 * Every email, by where it goes: key => group, label, to (who gets it), when
 * (what sends it), run (sends it, given the sample) and pick (which address's
 * copy to show, when it goes to more than one).
 */
function emailPreviewCatalog() {
    $ticket_schedule = function ($s) {
        ticketSetSchedule(ticketRow($s['ticket']), date('Y-m-d', strtotime('+2 days')) . ' 10:30:00', 1);
    };
    $reference = function ($s) {
        return ticketReference(ticketRow($s['ticket']));
    };
    return [
        // ---- Tickets, to clients
        'ticket_created' => ['group' => 'Tickets - to clients', 'label' => 'Ticket opened for them', 'to' => 'The contact and the watchers', 'when' => 'An agent opens a ticket for a contact',
            'run' => function ($s) {
                ticketMailCreated($s['ticket'], 'Ticket Created -');
            }],
        'ticket_created_email' => ['group' => 'Tickets - to clients', 'label' => 'Their email became a ticket', 'to' => 'Whoever emailed', 'when' => 'The email parser makes a ticket from an email',
            'run' => function ($s) {
                ticketMailCreated($s['ticket'], 'Ticket created -', [], false, 'email', [['email' => $s['alex_email'], 'name' => $s['alex_name']]]);
            }],
        'ticket_created_scheduled' => ['group' => 'Tickets - to clients', 'label' => 'Scheduled ticket raised', 'to' => 'The contact and the watchers', 'when' => 'A recurring ticket raises its ticket',
            'pick' => 'contact', 'run' => function ($s) {
                recurringTicketRaise($s['recurring'], 'Preview');
            }],
        'ticket_reply' => ['group' => 'Tickets - to clients', 'label' => 'Reply', 'to' => 'The contact and the watchers', 'when' => 'An agent replies publicly',
            'run' => function ($s) {
                ticketMailReply($s['ticket'], $s['reply'], $s['by']);
            }],
        'ticket_resolved' => ['group' => 'Tickets - to clients', 'label' => 'Resolved', 'to' => 'The contact and the watchers', 'when' => 'An agent resolves a ticket',
            'run' => function ($s) {
                global $mysqli;
                mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 4, ticket_resolved_at = NOW() WHERE ticket_id = " . intval($s['ticket']));
                ticketMailReply($s['ticket'], $s['reply'], $s['by'], true);
            }],
        'ticket_closed' => ['group' => 'Tickets - to clients', 'label' => 'Closed', 'to' => 'The contact and the watchers', 'when' => 'A ticket is closed, by an agent or after it was resolved',
            'run' => function ($s) {
                global $mysqli;
                mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 5, ticket_resolved_at = NOW(), ticket_closed_at = NOW() WHERE ticket_id = " . intval($s['ticket']));
                ticketMailClosed($s['ticket']);
            }],
        'ticket_watcher' => ['group' => 'Tickets - to clients', 'label' => 'Made a watcher', 'to' => 'The new watcher', 'when' => 'An agent adds a watcher and asks to tell them',
            'run' => function ($s) {
                ticketMailWatchersAdded($s['ticket'], [$s['jamie_email']], $s['by']);
            }],
        'ticket_colleague_new' => ['group' => 'Tickets - to clients', 'label' => 'New ticket at their company', 'to' => 'Primary and technical contacts who asked', 'when' => 'Anyone raises a ticket for their company',
            'run' => function ($s) {
                ticketMailColleaguesNew($s['ticket']);
            }],
        'ticket_approval' => ['group' => 'Tickets - to clients', 'label' => 'Approval needed', 'to' => 'The contact, or the technical or billing contacts', 'when' => 'An agent asks for a task to be approved',
            'run' => function ($s) {
                ticketMailTaskApproval($s['approval'], $s['by']);
            }],
        'ticket_scheduled' => ['group' => 'Tickets - to clients', 'label' => 'Work booked', 'to' => 'The contact and the watchers, with a calendar file', 'when' => 'An agent schedules the work',
            'pick' => 'contact', 'run' => $ticket_schedule],
        'ticket_schedule_cancelled' => ['group' => 'Tickets - to clients', 'label' => 'Booked work cancelled', 'to' => 'The contact and the watchers, with a calendar file', 'when' => 'An agent cancels the scheduled work',
            'pick' => 'contact', 'run' => function ($s) use ($ticket_schedule) {
                $ticket_schedule($s);
                emailCaptureStart(); // only the cancellation
                ticketCancelSchedule(ticketRow($s['ticket']));
            }],
        'ticket_closed_reply' => ['group' => 'Tickets - to clients', 'label' => 'Replied to a closed ticket', 'to' => 'Whoever replied', 'when' => 'The email parser gets a reply to a closed ticket',
            'run' => function ($s) {
                ticketMailClosedReply($s['ticket'], $s['alex_email']);
            }],

        // ---- Tickets, to the team
        'agent_assigned' => ['group' => 'Tickets - to your team', 'label' => 'Assigned to you', 'to' => 'The agent', 'when' => 'Someone else assigns them a ticket',
            'run' => function ($s) {
                ticketMailAssigned($s['agent'], [$s['ticket']], $s['by']);
            }],
        'agent_assigned_many' => ['group' => 'Tickets - to your team', 'label' => 'Several assigned to you', 'to' => 'The agent', 'when' => 'Someone assigns them several at once, with Bulk Action',
            'run' => function ($s) {
                ticketMailAssigned($s['agent'], [$s['ticket'], $s['ticket2']], $s['by']);
            }],
        'agent_recurring_assigned' => ['group' => 'Tickets - to your team', 'label' => 'Recurring ticket assigned', 'to' => 'The agent', 'when' => 'Someone else assigns them a recurring ticket',
            'run' => function ($s) {
                recurringTicketMailAssigned($s['agent'], ['Monthly backup check'], $s['by']);
            }],
        'agent_new_ticket' => ['group' => 'Tickets - to your team', 'label' => 'New ticket', 'to' => 'The new-ticket address (Admin > Ticket)', 'when' => 'A ticket is raised in the portal, by email or on a schedule',
            'run' => function ($s) {
                ticketMailNewTicketAddress($s['ticket'], 'portal');
            }],
        'agent_client_replied' => ['group' => 'Tickets - to your team', 'label' => 'Client replied', 'to' => 'The agent it is assigned to', 'when' => 'The client replies in the portal or by email',
            'run' => function ($s) {
                ticketMailAssigneeReplied($s['ticket'], 'portal', $s['client_reply'], $s['alex_name']);
            }],
        'agent_sla_risk' => ['group' => 'Tickets - to your team', 'label' => 'SLA at risk', 'to' => 'The SLA alert address and the assigned agent', 'when' => 'A ticket nears its response or resolution target',
            'pick' => 'agent', 'run' => function ($s) {
                ticketSlaAlert($s['ticket'], 'response', false, date('Y-m-d H:i:s', time() + 1800), $s['sla_email']);
            }],
        'agent_sla_missed' => ['group' => 'Tickets - to your team', 'label' => 'SLA missed', 'to' => 'The SLA alert address and the assigned agent', 'when' => 'A ticket misses its response or resolution target',
            'pick' => 'agent', 'run' => function ($s) {
                ticketSlaAlert($s['ticket'], 'resolution', true, date('Y-m-d H:i:s', time() - 3600), $s['sla_email']);
            }],
        'agent_scheduled' => ['group' => 'Tickets - to your team', 'label' => 'Work booked', 'to' => 'The agent it is assigned to, with a calendar file', 'when' => 'Someone schedules the work',
            'pick' => 'agent', 'run' => $ticket_schedule],
        'agent_approval' => ['group' => 'Tickets - to your team', 'label' => 'Approval needed', 'to' => 'The agent asked', 'when' => 'Someone asks an agent to approve a task',
            'run' => function ($s) {
                global $mysqli;
                mysqli_query($mysqli, "UPDATE task_approvals SET approval_scope = 'internal', approval_type = 'specific', approval_required_user_id = " . intval($s['agent']) . " WHERE approval_id = " . intval($s['approval']));
                ticketMailTaskApproval($s['approval'], $s['by']);
            }],

        // ---- Billing
        'invoice' => ['group' => 'Billing', 'label' => 'Invoice', 'to' => 'The primary and billing contacts, with the PDF', 'when' => 'An invoice is sent, by hand or as it recurs',
            'run' => function ($s) {
                emailPreviewInvoice($s['invoice']);
            }],
        'invoice_reminder' => ['group' => 'Billing', 'label' => 'Reminder - due soon', 'to' => 'The primary and billing contacts, with the PDF', 'when' => 'Send Reminder, or the nightly reminders (Admin > Invoice)',
            'run' => function ($s) {
                global $mysqli;
                mysqli_query($mysqli, "UPDATE invoices SET invoice_due = CURDATE() + INTERVAL 3 DAY WHERE invoice_id = " . intval($s['invoice']));
                emailPreviewInvoice($s['invoice'], ['kind' => 'reminder']);
            }],
        'invoice_due_today' => ['group' => 'Billing', 'label' => 'Reminder - due today', 'to' => 'The primary and billing contacts, with the PDF', 'when' => 'Send Reminder, or the nightly reminders',
            'run' => function ($s) {
                global $mysqli;
                mysqli_query($mysqli, "UPDATE invoices SET invoice_due = CURDATE() WHERE invoice_id = " . intval($s['invoice']));
                emailPreviewInvoice($s['invoice'], ['kind' => 'reminder']);
            }],
        'invoice_overdue' => ['group' => 'Billing', 'label' => 'Reminder - overdue', 'to' => 'The primary and billing contacts, with the PDF', 'when' => 'Send Reminder, or the nightly reminders',
            'run' => function ($s) {
                global $mysqli;
                mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Overdue', invoice_due = CURDATE() - INTERVAL 11 DAY WHERE invoice_id = " . intval($s['invoice']));
                emailPreviewInvoice($s['invoice'], ['kind' => 'reminder']);
            }],
        'invoice_paid' => ['group' => 'Billing', 'label' => 'Paid invoice', 'to' => 'The primary and billing contacts, with the PDF', 'when' => 'A paid invoice is sent',
            'run' => function ($s) {
                emailPreviewInvoice($s['paid']);
            }],
        'payment_receipt' => ['group' => 'Billing', 'label' => 'Payment receipt', 'to' => 'The primary and billing contacts', 'when' => 'A payment is taken, with Email Receipt ticked',
            'run' => function ($s) {
                $invoice = billingRow('invoice', $s['paid'], false);
                [$subject, $email] = billingPaymentEmail([['invoice' => $invoice, 'amount' => 290]], ['date' => date('Y-m-d'), 'method' => 'Credit Card', 'reference' => 'ch_3PxR1v2Sample', 'currency' => $invoice['invoice_currency_code']]);
                billingEmailQueue(billingEmailRecipients($invoice['invoice_client_id']), $subject, $email);
            }],
        'quote' => ['group' => 'Billing', 'label' => 'Quote', 'to' => 'The primary contact, with the PDF', 'when' => 'A quote is sent',
            'run' => function ($s) {
                $quote = billingRow('quote', $s['quote'], false);
                [$subject, $email] = billingQuoteEmail($quote);
                billingEmailQueue(billingEmailRecipients($quote['quote_client_id'], null, 'quote'), $subject, $email, ['from' => 'quote']);
            }],
        'statement' => ['group' => 'Billing', 'label' => 'Statement', 'to' => 'The primary and billing contacts, with the PDF', 'when' => 'Send Statement on a client',
            'run' => function ($s) {
                $statement = billingStatement($s['client_id']);
                [$subject, $email] = billingStatementEmail($statement);
                billingEmailQueue(billingEmailRecipients($s['client_id']), $subject, $email);
            }],
        'card_saved' => ['group' => 'Billing', 'label' => 'Card saved', 'to' => 'The contact who saved it', 'when' => 'A contact saves a card in the client portal',
            'run' => function ($s) {
                billingMailCardSaved($s['alex_email'], $s['alex_name'], $s['client'], 'visa', '4242', 8, intval(date('Y')) + 3);
            }],
        'quote_accepted' => ['group' => 'Billing', 'label' => 'Quote accepted', 'to' => 'The quote notification address (Admin > Quote)', 'when' => 'A client accepts a quote online',
            'run' => function ($s) {
                billingQuoteAnsweredNotify($s['quote'], true, $s['alex_name'], 'Looks good - please go ahead.');
            }],
        'quote_declined' => ['group' => 'Billing', 'label' => 'Quote declined', 'to' => 'The quote notification address (Admin > Quote)', 'when' => 'A client declines a quote online',
            'run' => function ($s) {
                billingQuoteAnsweredNotify($s['quote'], false, $s['alex_name'], 'We are going to hold off until next year.');
            }],
        'payment_notify' => ['group' => 'Billing', 'label' => 'Invoice paid online', 'to' => 'The paid notification address (Admin > Invoice)', 'when' => 'A client pays online, or autopay charges a card',
            'run' => function ($s) {
                billingPaymentNotify(billingRow('invoice', $s['paid'], false), 290, 'Online card');
            }],

        // ---- Client portal
        'portal_invite' => ['group' => 'Client portal', 'label' => 'Invite', 'to' => 'The contact', 'when' => 'An agent gives a contact portal access and ticks Email them an invite',
            'run' => function ($s) {
                portalMailInvite($s['alex_user']);
            }],
        'portal_invite_colleague' => ['group' => 'Client portal', 'label' => 'Invite from a colleague', 'to' => 'The contact', 'when' => 'A primary contact gives someone access in the portal',
            'run' => function ($s) {
                portalMailInvite($s['jamie_user'], $s['alex_name']);
            }],
        'portal_reset' => ['group' => 'Client portal', 'label' => 'Forgot password', 'to' => 'The contact', 'when' => 'They ask for a link on the sign-in page',
            'run' => function ($s) {
                portalMailPasswordReset($s['alex_user']);
            }],
        'portal_password' => ['group' => 'Client portal', 'label' => 'Password changed', 'to' => 'The contact', 'when' => 'They change their password, or set it from a link',
            'run' => function ($s) {
                portalMailAccountChange($s['alex_email'], $s['alex_name'], 'password');
            }],
        'portal_mfa_on' => ['group' => 'Client portal', 'label' => 'Two-step sign-in on', 'to' => 'The contact', 'when' => 'They turn on two-step sign-in',
            'run' => function ($s) {
                portalMailAccountChange($s['alex_email'], $s['alex_name'], 'mfa_on');
            }],
        'portal_mfa_off' => ['group' => 'Client portal', 'label' => 'Two-step sign-in off', 'to' => 'The contact', 'when' => 'They turn it off, or an agent does',
            'run' => function ($s) {
                portalMailAccountChange($s['alex_email'], $s['alex_name'], 'mfa_off_by_us');
            }],

        // ---- Shared links
        'share_link' => ['group' => 'Shared links', 'label' => 'Secure link', 'to' => 'Whoever it is for', 'when' => 'An agent shares something and asks to email the link',
            'run' => function ($s) {
                global $config_base_url;
                shareEmailLink(shareRow($s['share']), "https://$config_base_url/guest/share.php?id=" . intval($s['share']) . '#sample-key', 'passphrase');
            }],
        'share_request' => ['group' => 'Shared links', 'label' => 'Login asked for', 'to' => 'Whoever it is asked of', 'when' => 'An agent asks a client for a login with a link',
            'run' => function ($s) {
                global $config_base_url;
                shareEmailLink(shareRow($s['request']), "https://$config_base_url/guest/share.php?id=" . intval($s['request']) . '#sample-key', 'none');
            }],
        'share_code' => ['group' => 'Shared links', 'label' => 'Code to open a link', 'to' => 'Whoever it is for', 'when' => 'They open a link that is protected by an emailed code',
            'run' => function ($s) {
                shareEmailCode(shareRow($s['share']), '482913');
            }],
        'share_sent_back' => ['group' => 'Shared links', 'label' => 'Login sent back', 'to' => 'The agent who asked for it', 'when' => 'A client sends a login back through a link',
            'run' => function ($s) {
                $row = shareRow($s['request']);
                shareNotifyCreator($row, 'Login sent back', 'A login was sent back for ' . shareName($row) . ' - ' . $row['client_name'], true);
            }],

        // ---- Calendar
        'event_invite' => ['group' => 'Calendar', 'label' => 'Invitation', 'to' => 'The people invited, with a calendar file', 'when' => 'An event is saved with Send invitations ticked',
            'run' => function ($s) {
                calendarSendInvites(calendarEventRow($s['event']), 'invite');
            }],
        'event_update' => ['group' => 'Calendar', 'label' => 'Event changed', 'to' => 'The people invited, with a calendar file', 'when' => 'An event they were invited to changes',
            'run' => function ($s) {
                calendarSendInvites(calendarEventRow($s['event']), 'update');
            }],
        'event_cancel' => ['group' => 'Calendar', 'label' => 'Event cancelled', 'to' => 'The people invited, with a calendar file', 'when' => 'An event is deleted, or someone is taken off it',
            'run' => function ($s) {
                calendarSendInvites(calendarEventRow($s['event']), 'cancel');
            }],

        // ---- Your team's accounts
        'user_new' => ['group' => 'Your team\'s accounts', 'label' => 'New account', 'to' => 'The new agent', 'when' => 'An admin adds an agent and ticks Email them their details',
            'run' => function ($s) {
                userMailNewAccount($s['agent_name'], $s['agent_email'], 'Sample-Passw0rd-8', true);
            }],
        'user_changed' => ['group' => 'Your team\'s accounts', 'label' => 'Account changed', 'to' => 'The agent', 'when' => 'Their password, email or MFA changes',
            'run' => function ($s) {
                userNotifyChange($s['agent_name'], $s['agent_email'], 'your password was changed');
            }],
        'user_new_device' => ['group' => 'Your team\'s accounts', 'label' => 'Sign in from a new device', 'to' => 'The agent', 'when' => 'They sign in from a new address and browser',
            'run' => function ($s) {
                userMailSignInAlert($s['agent_name'], $s['agent_email'], 'new', '203.0.113.24', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36');
            }],
        'user_mfa_failed' => ['group' => 'Your team\'s accounts', 'label' => 'Wrong MFA code', 'to' => 'The agent', 'when' => 'Someone uses their password with a wrong MFA code',
            'run' => function ($s) {
                userMailSignInAlert($s['agent_name'], $s['agent_email'], 'mfa', '198.51.100.7', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) Gecko/20100101 Firefox/129.0');
            }],

        // ---- Other
        'bulk' => ['group' => 'Other', 'label' => 'Email to many contacts', 'to' => 'The contacts picked', 'when' => 'Email on the contacts or clients list',
            'run' => function ($s) {
                emailBulkSend([['email' => $s['alex_email'], 'name' => $s['alex_name'], 'footnote' => 'You received this as a contact for ' . $s['client'] . ' with ' . (billingCompany()['company_name'] ?? '') . '.']],
                    array_key_first(emailMailboxes()) ?? 'system', '', 'Our office is closed on Friday',
                    '<p>Our office is closed this Friday for staff training. Support is still there for anything urgent - call us as usual.</p><p>Have a good weekend!</p>', true);
            }],
        'test' => ['group' => 'Other', 'label' => 'Test email', 'to' => 'Whoever it is sent to', 'when' => 'Send a test in Admin > Mail',
            'run' => function ($s) {
                emailTestSend('you@example.com', 'system');
            }],
    ];
}

/*
 * Send yourself a copy of a preview - to $to (raw), its subject marked
 * [Preview], from the mailbox it would come from, without its calendar file
 * (it would add the sample event to your calendar). True when it was queued.
 */
function emailPreviewSendCopy($key, $to, $name) {
    global $mysqli;
    $preview = emailPreview($key);
    $email = $preview['email'] ?? null;
    if (!$email || !emailReady() || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $from = $email['from'] !== '' ? ['email' => $email['from'], 'name' => $email['from_name']] : emailFrom();
    addToMailQueue([[
        'from' => mysqli_real_escape_string($mysqli, $from['email']),
        'from_name' => mysqli_real_escape_string($mysqli, $from['name']),
        'recipient' => mysqli_real_escape_string($mysqli, (string) $to),
        'recipient_name' => mysqli_real_escape_string($mysqli, (string) $name),
        'subject' => mysqli_real_escape_string($mysqli, '[Preview] ' . $email['subject']),
        'body' => mysqli_real_escape_string($mysqli, $email['html']),
    ]]);
    return true;
}

/*
 * One email from the catalog, as it would go out - ['emails' => what was
 * captured (emailCaptureStop()), 'email' => the copy to show, 'error' => why
 * it failed]. null for no such email. Nothing it does is kept.
 */
function emailPreview($key) {
    global $mysqli, $config_ticket_client_general_notifications;
    $catalog = emailPreviewCatalog();
    if (!isset($catalog[$key])) {
        return null;
    }
    $entry = $catalog[$key];
    $notifications = $config_ticket_client_general_notifications ?? 0;
    $config_ticket_client_general_notifications = 1;
    $error = '';
    $sample = [];
    mysqli_begin_transaction($mysqli);
    emailCaptureStart();
    try {
        $sample = emailPreviewSample();
        ($entry['run'])($sample);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    } finally {
        $emails = emailCaptureStop();
        // Something that ended the transaction early would have kept the sample records - say so
        if (intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT @@in_transaction"))[0] ?? 0) !== 1) {
            logApp('Mail', 'error', 'The email preview ' . escapeSql($key) . ' ended its transaction early, so its sample records may have been kept');
        }
        mysqli_rollback($mysqli);
        $config_ticket_client_general_notifications = $notifications;
    }
    $pick = ['contact' => $sample['alex_email'] ?? '', 'agent' => $sample['agent_email'] ?? ''][$entry['pick'] ?? ''] ?? '';
    $show = null;
    foreach ($emails as $email) {
        if ($pick === '' || strcasecmp($email['to'], $pick) === 0) {
            $show = $email;
            break;
        }
    }
    return ['emails' => $emails, 'email' => $show ?? ($emails[0] ?? null), 'error' => $error] + $entry;
}
