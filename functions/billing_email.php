<?php

/*
 * Billing emails - one look for every email a client gets about money: an
 * invoice, a receipt for one paid, a quote, a payment reminder, a payment
 * received and a statement.
 *
 * Each is drawn by emailHtml() (functions/email.php), as every email is: the
 * company's logo (or name), what it is about, a summary box with what it
 * comes to, any note from the agent, a button to open it online - to pay an
 * invoice when online payments are on, or answer a quote - and the company's
 * details at the foot. The PDF can go with it.
 *
 * Reminders follow a schedule from the invoice settings: days before and
 * after the due date, then every so many days while it is owed. Statements
 * can go out each month to every client with a balance, and a client can be
 * left out of both.
 */

/* ------------------------------------------------------------ The rules */

/**
 * Reminders and statements, from the invoice settings - with the old
 * behaviour until 2.9.8 is applied:
 *   enabled            send reminders at all
 *   days               days from the due date to send on, negative before it, sorted
 *   repeat             then every so many days after the last of them, 0 for no more
 *   statement_monthly  email a statement each month to clients with a balance
 *   statement_day      on this day of the month (1 to 28)
 *   attach_pdf         put the PDF on invoice, quote, reminder and statement emails
 */
function billingReminderRules($fresh = false) {
    global $mysqli;
    static $rules = null;
    if ($rules !== null && !$fresh) {
        return $rules;
    }
    $rules = ['enabled' => false, 'days' => [1, 30], 'repeat' => 30, 'statement_monthly' => false, 'statement_day' => 1, 'attach_pdf' => true];
    try {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_send_invoice_reminders, config_invoice_reminder_days, config_invoice_reminder_repeat,
            config_statement_monthly, config_statement_day, config_billing_email_attach_pdf FROM settings WHERE company_id = 1"));
    } catch (mysqli_sql_exception $e) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_send_invoice_reminders FROM settings WHERE company_id = 1"));
    }
    if ($row) {
        $rules['enabled'] = intval($row['config_send_invoice_reminders'] ?? 0) === 1;
        if (array_key_exists('config_invoice_reminder_days', $row)) {
            $rules['days'] = billingReminderDaysParse($row['config_invoice_reminder_days']);
            $rules['repeat'] = max(0, min(365, intval($row['config_invoice_reminder_repeat'])));
            $rules['statement_monthly'] = intval($row['config_statement_monthly']) === 1;
            $rules['statement_day'] = max(1, min(28, intval($row['config_statement_day'])));
            $rules['attach_pdf'] = intval($row['config_billing_email_attach_pdf']) === 1;
        }
    }
    return $rules;
}

/** Days from a list - "-3, 0, 7 14" - as whole days from 60 before to a year after, sorted, each once. */
function billingReminderDaysParse($text) {
    $days = [];
    foreach (preg_split('/[\s,;]+/', trim((string) $text)) as $part) {
        if (preg_match('/^[+-]?\d{1,3}$/', $part)) {
            $day = intval($part);
            if ($day >= -60 && $day <= 365) {
                $days[$day] = $day;
            }
        }
    }
    sort($days);
    return array_values($days);
}

/** Is a reminder due for an invoice $days after its due date (negative before it)? */
function billingReminderOnDay($days, ?array $rules = null) {
    $rules = $rules ?? billingReminderRules();
    $days = intval($days);
    if (in_array($days, $rules['days'], true)) {
        return true;
    }
    $last = $rules['days'] ? max(max($rules['days']), 0) : 0;
    return $rules['repeat'] > 0 && $days > $last && ($days - $last) % $rules['repeat'] === 0;
}

/** The schedule in words - "3 days before it is due, on the day, 7 and 30 days late, then every 30 days". */
function billingReminderScheduleText(?array $rules = null) {
    $rules = $rules ?? billingReminderRules();
    $parts = [];
    $before = array_reverse(array_map('abs', array_filter($rules['days'], function ($day) {
        return $day < 0;
    })));
    $after = array_values(array_filter($rules['days'], function ($day) {
        return $day > 0;
    }));
    $list = function ($days) {
        return count($days) > 1 ? implode(', ', array_slice($days, 0, -1)) . ' and ' . end($days) : (string) reset($days);
    };
    if ($before) {
        $parts[] = $list($before) . ' day' . (end($before) === 1 && count($before) === 1 ? '' : 's') . ' before it is due';
    }
    if (in_array(0, $rules['days'], true)) {
        $parts[] = 'on the due date';
    }
    if ($after) {
        $parts[] = $list($after) . ' day' . (end($after) === 1 && count($after) === 1 ? '' : 's') . ' late';
    }
    if ($rules['repeat'] > 0) {
        $parts[] = ($parts ? 'then ' : '') . 'every ' . $rules['repeat'] . ' day' . ($rules['repeat'] === 1 ? '' : 's') . ($after || in_array(0, $rules['days'], true) ? '' : ' after it is due') . ' until it is paid';
    }
    return $parts ? implode(', ', $parts) : 'never';
}

/**
 * When an invoice's next reminder goes, from tomorrow on: ['date' => Y-m-d,
 * 'days' => days from its due date], or null - none on the schedule, or
 * reminders are off. With $late_only, none before it is late - autopay
 * charges it on the day.
 */
function billingReminderNext(array $invoice, ?array $rules = null, $late_only = false) {
    $rules = $rules ?? billingReminderRules();
    $due = substr((string) ($invoice['invoice_due'] ?? ''), 0, 10);
    if (!$rules['enabled'] || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
        return null;
    }
    $today = new DateTime('today');
    $due_date = new DateTime($due);
    $from = (int) $due_date->diff($today)->format('%r%a') + 1;
    if ($late_only) {
        $from = max($from, 1);
    }
    for ($days = $from; $days <= $from + 400; $days++) {
        if (billingReminderOnDay($days, $rules)) {
            $date = (clone $due_date)->modify(($days >= 0 ? '+' : '') . $days . ' days');
            return ['date' => $date->format('Y-m-d'), 'days' => $days];
        }
    }
    return null;
}

/** Will a recurring autopay charge this invoice on its due date? Then it isn't reminded before. */
function billingReminderAutopay(array $invoice) {
    global $mysqli;
    $recurring_id = intval($invoice['invoice_recurring_invoice_id'] ?? 0);
    return $recurring_id > 0 && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM recurring_payments WHERE recurring_payment_recurring_invoice_id = $recurring_id LIMIT 1")) > 0;
}

/** How late an invoice is, in words - "due in 3 days", "due today", "7 days late". */
function billingReminderWhen($days) {
    $days = intval($days);
    if ($days < 0) {
        return 'due in ' . (-$days) . ' day' . ($days === -1 ? '' : 's');
    }
    return $days === 0 ? 'due today' : $days . ' day' . ($days === 1 ? '' : 's') . ' late';
}

/* ------------------------------------------------------------ The email */

/** A date for an email - "Oct 20, 2026". */
function billingEmailDate($date) {
    $time = strtotime((string) $date);
    return $time ? date('M j, Y', $time) : (string) $date;
}

/**
 * One billing email as HTML - emailHtml() (functions/email.php), greeting
 * nobody when it has no name, as an internal one has none.
 */
function billingEmailHtml(array $email) {
    if (empty($email['name'])) {
        $email['name'] = null;
    }
    return emailHtml($email);
}

/** Who billing emails come from - invoices and quotes each have their own address. */
function billingEmailFrom($which = 'invoice') {
    global $mysqli;
    static $from = null;
    if ($from === null) {
        $from = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_invoice_from_email, config_invoice_from_name, config_quote_from_email, config_quote_from_name,
            config_invoice_paid_notification_email, config_smtp_provider FROM settings WHERE company_id = 1")) ?: [];
    }
    if ($which === 'quote' && !empty($from['config_quote_from_email'])) {
        return ['email' => (string) $from['config_quote_from_email'], 'name' => (string) $from['config_quote_from_name']];
    }
    if ($which === 'notify') {
        return ['email' => (string) ($from['config_invoice_paid_notification_email'] ?? ''), 'name' => ''];
    }
    if ($which === 'ready') {
        return ['email' => (string) ($from['config_smtp_provider'] ?? ''), 'name' => ''];
    }
    return ['email' => (string) ($from['config_invoice_from_email'] ?? ''), 'name' => (string) ($from['config_invoice_from_name'] ?? '')];
}

/** Can billing emails go out at all - mail is set up? */
function billingEmailReady() {
    return billingEmailFrom('ready')['email'] !== '' && billingEmailFrom()['email'] !== '';
}

/**
 * A client's contacts to email: the ones picked (any of the client's with an
 * email address), or with none picked the defaults for $type - the primary
 * and billing contacts, or for a quote the primary contact.
 * [['id', 'name', 'email'], ...], each address once.
 */
function billingEmailRecipients($client_id, ?array $contact_ids = null, $type = 'invoice') {
    global $mysqli;
    $client_id = intval($client_id);
    if ($contact_ids !== null) {
        $contact_ids = array_filter(array_map('intval', $contact_ids));
        if (!$contact_ids) {
            return [];
        }
        $filter = 'AND contact_id IN (' . implode(', ', $contact_ids) . ')';
    } else {
        $filter = documentDefaultContactFilterSql($type === 'quote' ? 'quote' : 'invoice');
    }
    $recipients = [];
    $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email FROM contacts
        WHERE contact_client_id = $client_id AND contact_archived_at IS NULL AND contact_email IS NOT NULL AND contact_email != '' $filter
        ORDER BY contact_primary DESC, contact_billing DESC, contact_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $key = strtolower(trim($row['contact_email']));
        if (!filter_var(trim($row['contact_email']), FILTER_VALIDATE_EMAIL) || isset($recipients[$key])) {
            continue;
        }
        $recipients[$key] = ['id' => intval($row['contact_id']), 'name' => (string) $row['contact_name'], 'email' => trim($row['contact_email'])];
    }
    return array_values($recipients);
}

/**
 * Queue a billing email to each recipient, greeting each by name. $opts:
 * from ('invoice' or 'quote' - whose address it comes from) and attach (a
 * list of billingEmailPdf() files). Who it was queued to, "Name (email)".
 */
function billingEmailQueue(array $recipients, $subject, array $email, array $opts = []) {
    global $mysqli;
    $from = billingEmailFrom($opts['from'] ?? 'invoice');
    $attachments = array_values(array_filter((array) ($opts['attach'] ?? [])));
    $data = [];
    $sent = [];
    foreach ($recipients as $recipient) {
        $address = trim((string) ($recipient['email'] ?? ''));
        if (!filter_var($address, FILTER_VALIDATE_EMAIL) || isset($sent[strtolower($address)])) {
            continue;
        }
        $name = (string) ($recipient['name'] ?? '');
        $html = billingEmailHtml(['name' => array_key_exists('greet', $recipient) ? $recipient['greet'] : $name] + $email);
        $data[] = [
            'from' => mysqli_real_escape_string($mysqli, $from['email']),
            'from_name' => mysqli_real_escape_string($mysqli, $from['name']),
            'recipient' => mysqli_real_escape_string($mysqli, $address),
            'recipient_name' => mysqli_real_escape_string($mysqli, $name),
            'subject' => mysqli_real_escape_string($mysqli, $subject),
            'body' => mysqli_real_escape_string($mysqli, $html),
            'attachments' => $attachments,
        ];
        $sent[strtolower($address)] = $name !== '' ? "$name ($address)" : $address;
    }
    if ($data) {
        addToMailQueue($data);
    }
    return array_values($sent);
}

/**
 * A document's PDF, saved where the mail queue can attach it - ['path' =>
 * app-relative path, 'name' => file name], or null when it can't be made.
 * They go in uploads/tmp/billing_mail, which the web can't reach, and the
 * nightly run clears them out after a week. $pdf is a billingPdf() or
 * billingStatementPdf() result.
 */
function billingEmailPdf(array $pdf) {
    $dir = 'uploads/tmp/billing_mail/' . bin2hex(random_bytes(12));
    $full = dirname(__DIR__) . '/' . $dir;
    if (!is_dir($full) && !@mkdir($full, 0775, true)) {
        return null;
    }
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $pdf['name']);
    if (@file_put_contents("$full/$name", $pdf['data']) === false) {
        return null;
    }
    return ['path' => "$dir/$name", 'name' => $name];
}

/** Clear PDFs made for emails more than $days days ago. How many went. */
function billingEmailPdfCleanup($days = 7) {
    $base = dirname(__DIR__) . '/uploads/tmp/billing_mail';
    if (!is_dir($base)) {
        return 0;
    }
    $cutoff = time() - max(1, intval($days)) * 86400;
    $removed = 0;
    foreach (glob("$base/*", GLOB_ONLYDIR) ?: [] as $dir) {
        if (filemtime($dir) >= $cutoff) {
            continue;
        }
        foreach (glob("$dir/*") ?: [] as $file) {
            @unlink($file);
        }
        if (@rmdir($dir)) {
            $removed++;
        }
    }
    return $removed;
}

/* ----------------------------------------------- What each one says */

/** The footnote for a client's billing contact. */
function billingEmailFootnote($client_name) {
    $company = billingCompany();
    return "You received this email as a billing contact for $client_name with " . ($company['company_name'] ?? 'us') . '.';
}

/**
 * An invoice email - [subject, email]. $opts:
 *   kind  invoice (the default), or reminder
 *   note  the agent's own words
 * Paid, it is a receipt. A reminder says when it is or was due and what is
 * still owed - firmer once it is late.
 */
function billingInvoiceEmail(array $invoice, array $opts = []) {
    $number = billingNumber('invoice', $invoice);
    $currency = (string) $invoice['invoice_currency_code'];
    $paid = invoicePaid($invoice['invoice_id']);
    $state = invoiceState($invoice, $paid);
    $company = billingCompany();
    $company_name = (string) ($company['company_name'] ?? '');
    $scope = trim((string) $invoice['invoice_scope']);
    $url = billingGuestUrl('invoice', $invoice);
    $owed = $state['balance'] > 0 && !in_array($invoice['invoice_status'], ['Paid', 'Cancelled', 'Non-Billable'], true);
    $pay = $owed && billingOnlinePayments();
    $kind = ($opts['kind'] ?? 'invoice') === 'reminder' && $owed ? 'reminder' : 'invoice';
    $contact_line = 'Questions? Reply to this email' . (!empty($company['phone_display']) ? ' or call us on ' . $company['phone_display'] : '') . '.';

    $summary = ['Invoice' => $number];
    if ($scope !== '') {
        $summary['For'] = $scope;
    }
    $summary['Issued'] = billingEmailDate($invoice['invoice_date']);
    if ($owed) {
        $summary['Due'] = billingEmailDate($invoice['invoice_due']);
    }
    $summary['Total'] = billingMoney($invoice['invoice_amount'], $currency);
    if ($paid > 0) {
        $summary['Paid'] = billingMoney($paid, $currency);
    }
    $email = [
        'note' => $opts['note'] ?? '',
        'button' => ['label' => $pay ? 'View and pay invoice' : 'View invoice', 'url' => $url],
        'footnote' => billingEmailFootnote($invoice['client_name']),
    ];

    if (!$owed && $invoice['invoice_status'] === 'Paid') {
        return ["Receipt for invoice $number from $company_name", $email + [
            'kind' => 'Receipt', 'tone' => 'success', 'title' => "Invoice $number is paid",
            'intro' => ['Thank you - invoice ' . $number . ($scope !== '' ? " for \"$scope\"" : '') . ' is paid in full.'],
            'summary' => $summary, 'strong' => 'Paid',
            'after' => ['Please keep this email for your records. You can download the invoice as a PDF from its page.'],
        ]];
    }

    if ($kind === 'reminder') {
        $days = $state['days'] !== null ? -intval($state['days']) : 0;
        $balance = billingMoney($state['balance'], $currency);
        $summary['Balance due'] = $balance;
        if ($days < 0) {
            $subject = "Reminder: invoice $number is due " . billingEmailDate($invoice['invoice_due']);
            $intro = ["A friendly reminder that invoice $number" . ($scope !== '' ? " for \"$scope\"" : '') . " is due on " . billingEmailDate($invoice['invoice_due']) . ", with $balance to pay."];
            $tone = 'primary';
            $title = 'Due ' . billingEmailDate($invoice['invoice_due']);
        } elseif ($days === 0) {
            $subject = "Reminder: invoice $number is due today";
            $intro = ["A friendly reminder that invoice $number" . ($scope !== '' ? " for \"$scope\"" : '') . " is due today, with $balance to pay."];
            $tone = 'warning';
            $title = 'Due today';
        } else {
            $subject = "Overdue: invoice $number is $days day" . ($days === 1 ? '' : 's') . ' past due';
            $intro = ["Invoice $number" . ($scope !== '' ? " for \"$scope\"" : '') . ' was due on ' . billingEmailDate($invoice['invoice_due']) . " and $balance is still outstanding. Please arrange payment as soon as you can."];
            $tone = 'danger';
            $title = "$days day" . ($days === 1 ? '' : 's') . ' past due';
        }
        return [$subject, $email + [
            'kind' => 'Payment reminder', 'tone' => $tone, 'title' => "Invoice $number - $title",
            'intro' => $intro, 'summary' => $summary, 'strong' => 'Balance due',
            'after' => ["If you have already paid, thank you, and please disregard this reminder. $contact_line"],
        ]];
    }

    $summary[$owed ? 'Balance due' : 'Balance'] = billingMoney($state['balance'], $currency);
    return ["Invoice $number from $company_name", $email + [
        'kind' => 'Invoice', 'tone' => 'primary', 'title' => "Invoice $number",
        'intro' => ['Here is your invoice' . ($scope !== '' ? " for \"$scope\"" : '') . '.' . ($owed ? ' Payment is due by ' . billingEmailDate($invoice['invoice_due']) . '.' : '')],
        'summary' => $summary, 'strong' => $owed ? 'Balance due' : 'Balance',
        'after' => [($pay ? 'You can pay online by card from the invoice page, and download it as a PDF. ' : 'You can download it as a PDF from the invoice page. ') . $contact_line],
    ]];
}

/** A quote email - [subject, email]. $opts: note. */
function billingQuoteEmail(array $quote, array $opts = []) {
    $number = billingNumber('quote', $quote);
    $currency = (string) $quote['quote_currency_code'];
    $company = billingCompany();
    $scope = trim((string) $quote['quote_scope']);
    $groups = quoteGroups($quote);
    $summary = ['Quote' => $number];
    if ($scope !== '') {
        $summary['For'] = $scope;
    }
    $summary['Date'] = billingEmailDate($quote['quote_date']);
    $summary['Valid until'] = billingEmailDate($quote['quote_expire']);
    $strong = 'Total';
    if (quoteHasRecurring($groups)) {
        foreach ($groups as $group) {
            if ($group['items'] || $group['frequency'] === '') {
                $label = $group['frequency'] === '' ? 'Up front' : $group['label'];
                $summary[$label] = billingMoney($group['total'], $currency);
                if ($group['frequency'] === '') {
                    $strong = $label;
                }
            }
        }
    } else {
        $summary['Total'] = billingMoney($quote['quote_amount'], $currency);
    }
    return ["Quote $number from " . ($company['company_name'] ?? '') . ($scope !== '' ? " - $scope" : ''), [
        'kind' => 'Quote', 'tone' => 'primary', 'title' => "Quote $number",
        'intro' => ['Thank you for the opportunity. Here is our quote' . ($scope !== '' ? " for \"$scope\"" : '') . ', valid until ' . billingEmailDate($quote['quote_expire']) . '.'],
        'note' => $opts['note'] ?? '',
        'summary' => $summary, 'strong' => $strong,
        'button' => ['label' => 'View and accept quote', 'url' => billingGuestUrl('quote', $quote)],
        'after' => ['You can accept or decline it online, and download it as a PDF. Questions? Reply to this email.'],
        'footnote' => billingEmailFootnote($quote['client_name']),
    ]];
}

/**
 * A payment received - [subject, email]. $applied: what went on each
 * invoice, [['invoice' => billingRow(), 'amount' => ...], ...]. $payment:
 * date, method, reference, currency.
 */
function billingPaymentEmail(array $applied, array $payment) {
    $currency = (string) ($payment['currency'] ?? ($applied[0]['invoice']['invoice_currency_code'] ?? ''));
    $total = array_sum(array_column($applied, 'amount'));
    $first = $applied[0]['invoice'];
    $summary = ['Amount paid' => billingMoney($total, $currency), 'Date' => billingEmailDate($payment['date'] ?? date('Y-m-d'))];
    if (!empty($payment['method'])) {
        $summary['Method'] = $payment['method'];
    }
    if (!empty($payment['reference'])) {
        $summary['Reference'] = $payment['reference'];
    }
    $email = ['kind' => 'Payment received', 'tone' => 'success', 'footnote' => billingEmailFootnote($first['client_name'])];
    if (count($applied) === 1) {
        $number = billingNumber('invoice', $first);
        $balance = invoiceState($first, invoicePaid($first['invoice_id']))['balance'];
        $summary['Invoice'] = $number;
        $summary[$balance > 0 ? 'Still to pay' : 'Invoice balance'] = $balance > 0 ? billingMoney($balance, $currency) : 'Paid in full';
        return ["Payment received for invoice $number", $email + [
            'title' => 'Thank you for your payment',
            'intro' => ['We have received your payment of ' . billingMoney($total, $currency) . " for invoice $number."],
            'summary' => $summary, 'strong' => 'Amount paid',
            'button' => ['label' => 'View invoice', 'url' => billingGuestUrl('invoice', $first)],
            'after' => ['Please keep this email as your receipt.'],
        ]];
    }
    $rows = [];
    foreach ($applied as $entry) {
        $balance = invoiceState($entry['invoice'], invoicePaid($entry['invoice']['invoice_id']))['balance'];
        $rows[] = [['text' => billingNumber('invoice', $entry['invoice']), 'url' => billingGuestUrl('invoice', $entry['invoice'])],
            billingMoney($entry['amount'], $currency), $balance > 0 ? billingMoney($balance, $currency) : 'Paid'];
    }
    return ['Payment received - ' . count($applied) . ' invoices', $email + [
        'title' => 'Thank you for your payment',
        'intro' => ['We have received your payment of ' . billingMoney($total, $currency) . ' and applied it to these invoices.'],
        'summary' => $summary, 'strong' => 'Amount paid',
        'lines' => ['head' => ['Invoice', 'Applied', 'Balance'], 'align' => ['left', 'right', 'right'], 'rows' => $rows],
        'after' => ['Please keep this email as your receipt.'],
    ]];
}

/** An email to one of our own addresses - no greeting, from the invoice or quote address. Who it went to. */
function billingNotifyEmail($to, $subject, array $email, $from = 'invoice') {
    $to = trim((string) $to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return [];
    }
    return billingEmailQueue([['email' => $to, 'name' => '', 'greet' => '']], $subject, $email, ['from' => $from]);
}

/**
 * Tell our team a client answered a quote online - to the quote notification
 * address in the quote settings. $accepted or declined; $by, who answered
 * in the client portal, and the note they left with it.
 */
function billingQuoteAnsweredNotify($quote_id, $accepted, $by = '', $note = '') {
    global $mysqli, $config_base_url;
    $quote = billingRow('quote', $quote_id, false);
    $to = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT config_quote_notification_email FROM settings WHERE company_id = 1"))[0] ?? '');
    if (!$quote || $to === '') {
        return [];
    }
    $number = billingNumber('quote', $quote);
    $word = $accepted ? 'accepted' : 'declined';
    $by = trim((string) $by);
    $summary = ['Client' => $quote['client_name'], 'Quote' => $number, 'Total' => billingMoney($quote['quote_amount'], $quote['quote_currency_code'])];
    if ($by !== '') {
        $summary = ['Client' => $quote['client_name'], ucfirst($word) . ' by' => $by] + $summary;
    }
    return billingNotifyEmail($to, 'Quote ' . ucfirst($word) . " - {$quote['client_name']} - Quote $number", [
        'kind' => 'Quote ' . $word, 'tone' => $accepted ? 'success' : 'danger', 'title' => "{$quote['client_name']} $word $number",
        'intro' => ["{$quote['client_name']} $word quote $number " . ($by !== '' ? 'in the client portal' : 'online') . (trim((string) $quote['quote_scope']) !== '' ? " - \"{$quote['quote_scope']}\"" : '') . '.'
            . (trim((string) $note) !== '' ? ' They left a note:' : '')],
        'note' => trim((string) $note),
        'summary' => $summary,
        'button' => ['label' => 'Open in ITFlow', 'url' => "https://$config_base_url/agent/quote.php?quote_id=" . intval($quote['quote_id'])],
    ], 'quote');
}

/**
 * Tell the paid notification address (Invoice settings) that an invoice was
 * paid - for online and automatic payments. Nothing when none is set.
 */
function billingPaymentNotify(array $invoice, $amount, $how) {
    global $config_base_url;
    $to = billingEmailFrom('notify')['email'];
    if ($to === '') {
        return;
    }
    $number = billingNumber('invoice', $invoice);
    $currency = (string) $invoice['invoice_currency_code'];
    $balance = invoiceState($invoice, invoicePaid($invoice['invoice_id']))['balance'];
    billingNotifyEmail($to, "Payment received - {$invoice['client_name']} - invoice $number", [
        'kind' => 'Payment received', 'tone' => 'success', 'title' => "{$invoice['client_name']} paid $number",
        'intro' => ["$how payment of " . billingMoney($amount, $currency) . " on invoice $number for {$invoice['client_name']}."],
        'summary' => ['Client' => $invoice['client_name'], 'Invoice' => $number, 'Amount paid' => billingMoney($amount, $currency), 'Balance' => $balance > 0 ? billingMoney($balance, $currency) : 'Paid in full'],
        'strong' => 'Amount paid',
        'button' => ['label' => 'Open in ITFlow', 'url' => "https://$config_base_url/agent/invoice.php?invoice_id=" . intval($invoice['invoice_id'])],
    ]);
}

/**
 * Tell the paid notification address about one payment over several
 * invoices - the online payments the client portal takes.
 */
function billingPaymentNotifyMany(array $applied, $how) {
    global $config_base_url;
    $to = billingEmailFrom('notify')['email'];
    if ($to === '' || !$applied) {
        return;
    }
    $first = $applied[0]['invoice'];
    $currency = (string) $first['invoice_currency_code'];
    $total = array_sum(array_column($applied, 'amount'));
    $rows = [];
    foreach ($applied as $entry) {
        $balance = invoiceState($entry['invoice'], invoicePaid($entry['invoice']['invoice_id']))['balance'];
        $rows[] = [['text' => billingNumber('invoice', $entry['invoice']), 'url' => "https://$config_base_url/agent/invoice.php?invoice_id=" . intval($entry['invoice']['invoice_id'])],
            billingMoney($entry['amount'], $currency), $balance > 0 ? billingMoney($balance, $currency) : 'Paid'];
    }
    billingNotifyEmail($to, "Payment received - {$first['client_name']} - " . count($applied) . ' invoices', [
        'kind' => 'Payment received', 'tone' => 'success', 'title' => "{$first['client_name']} paid " . count($applied) . ' invoices',
        'intro' => ["$how payment of " . billingMoney($total, $currency) . " for {$first['client_name']}, over these invoices."],
        'summary' => ['Client' => $first['client_name'], 'Invoices' => (string) count($applied), 'Amount paid' => billingMoney($total, $currency)],
        'strong' => 'Amount paid',
        'lines' => ['head' => ['Invoice', 'Applied', 'Balance'], 'align' => ['left', 'right', 'right'], 'rows' => $rows],
    ]);
}

/**
 * Send the receipt for a payment on one invoice or several - $applied as
 * billingPaymentEmail() takes it - to the client's primary and billing
 * contacts, and log it on each invoice. $notify also tells the paid
 * notification address. The addresses it went to.
 */
function billingPaymentReceiptSend(array $applied, array $payment, $notify = false) {
    if (!$applied) {
        return [];
    }
    $first = $applied[0]['invoice'];
    [$subject, $email] = billingPaymentEmail($applied, $payment + ['currency' => $first['invoice_currency_code']]);
    $sent = billingEmailQueue(billingEmailRecipients($first['client_id']), $subject, $email);
    if ($sent) {
        foreach ($applied as $entry) {
            billingLog('invoice', $entry['invoice']['invoice_id'], 'Sent', 'Payment receipt emailed to ' . implode(', ', $sent));
        }
    }
    if ($notify && count($applied) === 1) {
        billingPaymentNotify($first, $applied[0]['amount'], $payment['how'] ?? 'Online');
    } elseif ($notify) {
        billingPaymentNotifyMany($applied, $payment['how'] ?? 'Online');
    }
    return $sent;
}

/**
 * Send a payment received email for a payment on one invoice - to its
 * client's primary and billing contacts - and log it on the invoice.
 * $notify also tells the paid notification address. The addresses it went to.
 */
function billingPaymentSend($invoice_id, $amount, array $payment, $notify = false) {
    $invoice = billingRow('invoice', $invoice_id, false);
    if (!$invoice) {
        return [];
    }
    return billingPaymentReceiptSend([['invoice' => $invoice, 'amount' => $amount]], $payment, $notify);
}

/* ------------------------------------------------------------ Statements */

/**
 * A client's statement - their invoices sent and not cancelled, dated up to
 * $to (and from $from), the open ones only unless $include_paid, with what
 * has been paid on each:
 *   client     the client, with its currency
 *   from, to   the period
 *   invoices   each with paid, balance and days (late, negative while not due)
 *   total      what is owed on them
 *   overdue    what of it is past due
 *   aging      current, 1-30, 31-60, 61-90 and 90+ days late
 */
function billingStatement($client_id, $from = '', $to = '', $include_paid = false) {
    global $mysqli, $session_company_currency;
    $client_id = intval($client_id);
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $to) ? $to : date('Y-m-d');
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $from) ? $from : '';
    $client = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_id, client_name, client_currency_code, client_reminders FROM clients WHERE client_id = $client_id")) ?: null;
    $statement = ['client' => $client, 'from' => $from, 'to' => $to, 'include_paid' => (bool) $include_paid, 'invoices' => [], 'total' => 0.0, 'overdue' => 0.0,
        'aging' => ['Current' => 0.0, '1-30 days' => 0.0, '31-60 days' => 0.0, '61-90 days' => 0.0, 'Over 90 days' => 0.0],
        'currency' => (string) (($client['client_currency_code'] ?? '') ?: ($session_company_currency ?? '') ?: (billingCompany()['company_currency'] ?? ''))];
    if (!$client) {
        return $statement;
    }
    $sql = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_scope, invoice_date, invoice_due, invoice_status, invoice_amount,
            invoice_currency_code, invoice_url_key, COALESCE(paid.amount, 0) AS paid
        FROM invoices
        LEFT JOIN (SELECT payment_invoice_id, SUM(payment_amount) AS amount FROM payments WHERE payment_archived_at IS NULL GROUP BY payment_invoice_id) AS paid
            ON paid.payment_invoice_id = invoice_id
        WHERE invoice_client_id = $client_id AND invoice_status NOT IN ('Draft', 'Cancelled', 'Non-Billable')
            AND invoice_date <= '$to'" . ($from !== '' ? " AND invoice_date >= '$from'" : '') . "
        ORDER BY invoice_date ASC, invoice_number ASC");
    $today = strtotime(date('Y-m-d'));
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['balance'] = round(floatval($row['invoice_amount']) - floatval($row['paid']), 2);
        if ($row['balance'] <= 0 && !$include_paid) {
            continue;
        }
        $row['days'] = intval(round(($today - strtotime($row['invoice_due'])) / 86400));
        $row['client_name'] = $client['client_name'];
        if ($row['balance'] > 0) {
            $statement['total'] += $row['balance'];
            $days = $row['days'];
            $bucket = $days <= 0 ? 'Current' : ($days <= 30 ? '1-30 days' : ($days <= 60 ? '31-60 days' : ($days <= 90 ? '61-90 days' : 'Over 90 days')));
            $statement['aging'][$bucket] += $row['balance'];
            if ($days > 0) {
                $statement['overdue'] += $row['balance'];
            }
        }
        $statement['invoices'][] = $row;
    }
    $statement['total'] = round($statement['total'], 2);
    $statement['overdue'] = round($statement['overdue'], 2);
    return $statement;
}

/** A statement's period in words - "up to Oct 16, 2026", "Jul 1, 2026 to Oct 16, 2026". */
function billingStatementPeriod(array $statement) {
    return ($statement['from'] !== '' ? billingEmailDate($statement['from']) . ' to ' : 'up to ') . billingEmailDate($statement['to']);
}

/** A statement as a PDF - ['name', 'data']. See functions/billing_pdf.php. */
function billingStatementPdf(array $statement) {
    require_once __DIR__ . '/../libs/TCPDF/tcpdf.php';
    require_once __DIR__ . '/billing_pdf.php';
    return billingStatementPdfRender($statement);
}

/** A statement email - [subject, email]. $opts: note. */
function billingStatementEmail(array $statement, array $opts = []) {
    $currency = $statement['currency'];
    $company = billingCompany();
    $rows = [];
    foreach ($statement['invoices'] as $invoice) {
        $late = $invoice['balance'] > 0 && $invoice['days'] > 0;
        $rows[] = [
            ['text' => billingNumber('invoice', $invoice), 'url' => billingGuestUrl('invoice', $invoice)],
            billingEmailDate($invoice['invoice_date']),
            ['text' => billingEmailDate($invoice['invoice_due']) . ($late ? ' (' . $invoice['days'] . 'd late)' : ''), 'tone' => $late ? 'danger' : ''],
            ['text' => $invoice['balance'] > 0 ? billingMoney($invoice['balance'], $currency) : 'Paid', 'tone' => $invoice['balance'] > 0 ? '' : 'success'],
        ];
    }
    $summary = ['Statement date' => billingEmailDate(date('Y-m-d')), 'Period' => ucfirst(billingStatementPeriod($statement)), 'Open invoices' => (string) count(array_filter($statement['invoices'], function ($invoice) {
        return $invoice['balance'] > 0;
    }))];
    if ($statement['overdue'] > 0) {
        $summary['Overdue'] = billingMoney($statement['overdue'], $currency);
    }
    $summary['Total due'] = billingMoney($statement['total'], $currency);
    return ['Statement from ' . ($company['company_name'] ?? '') . ' - ' . billingEmailDate($statement['to']), [
        'kind' => 'Statement', 'tone' => $statement['overdue'] > 0 ? 'warning' : 'primary', 'title' => 'Your account statement',
        'intro' => ['Here is your statement for ' . $statement['client']['client_name'] . ', ' . billingStatementPeriod($statement) . '.' . ($statement['total'] > 0 ? ' Open an invoice to view' . (billingOnlinePayments() ? ' or pay' : '') . ' it.' : ' Nothing is owed - thank you.')],
        'note' => $opts['note'] ?? '',
        'summary' => $summary, 'strong' => 'Total due',
        'lines' => ['head' => ['Invoice', 'Date', 'Due', 'Balance'], 'align' => ['left', 'left', 'left', 'right'], 'rows' => $rows],
        'after' => ['Questions about your account? Reply to this email' . (!empty($company['phone_display']) ? ' or call us on ' . $company['phone_display'] : '') . '.'],
        'footnote' => billingEmailFootnote($statement['client']['client_name']),
    ]];
}

/**
 * Email a statement to a client's contacts (the defaults when $contact_ids
 * is null), with its PDF when $attach. The addresses it went to.
 */
function billingStatementSend(array $statement, ?array $contact_ids = null, array $opts = []) {
    [$subject, $email] = billingStatementEmail($statement, $opts);
    $attach = [];
    if (!empty($opts['attach'])) {
        $file = billingEmailPdf(billingStatementPdf($statement));
        if ($file) {
            $attach[] = $file;
        }
    }
    return billingEmailQueue(billingEmailRecipients($statement['client']['client_id'], $contact_ids), $subject, $email, ['attach' => $attach]);
}
