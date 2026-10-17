<?php

/*
 * ITFlow - Paying online
 *
 * Paying invoices by card through Stripe from the client portal, and the
 * cards a client has saved for it.
 *
 * One card payment can pay several invoices. The PaymentIntent carries the
 * split in its metadata - itflow_invoices, "invoice_id:cents,..." - and
 * billingStripeBook() books it as one payment per invoice, all with the same
 * date and reference ("Stripe - pi_..."). That is how the portal's receipt
 * and payment history, the agent pages and the nightly Stripe fee
 * reconciliation all see it as the one payment it was.
 */

/** Most invoices one card payment can pay - the split has to fit in Stripe's metadata. */
const BILLING_PAY_MAX_INVOICES = 25;

/**
 * Stripe as set up under Admin > Payment Providers, when it is turned on and
 * has its keys - id, keys, threshold and the account payments go to. Null
 * when clients can't pay by card.
 */
function billingStripeProvider() {
    global $mysqli;
    static $provider = false;
    if ($provider === false) {
        $provider = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT payment_provider_id, payment_provider_public_key, payment_provider_private_key,
                payment_provider_threshold, payment_provider_account
            FROM payment_providers WHERE payment_provider_name = 'Stripe' AND payment_provider_active = 1 LIMIT 1")) ?: null;
        if ($provider && (trim((string) $provider['payment_provider_public_key']) === '' || trim((string) $provider['payment_provider_private_key']) === '')) {
            $provider = null;
        }
    }
    return $provider;
}

/** A Stripe client on the provider's secret key, or null when Stripe isn't set up. */
function billingStripeClient() {
    $provider = billingStripeProvider();
    if (!$provider) {
        return null;
    }
    require_once __DIR__ . '/../includes/stripe_init.php';
    return new \Stripe\StripeClient((string) $provider['payment_provider_private_key']);
}

/** The client's Stripe customer id ('' when they have none yet). */
function billingStripeCustomer($client_id) {
    global $mysqli;
    $provider = billingStripeProvider();
    if (!$provider) {
        return '';
    }
    $client_id = intval($client_id);
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT payment_provider_client FROM client_payment_provider
        WHERE client_id = $client_id AND payment_provider_id = " . intval($provider['payment_provider_id']) . " LIMIT 1"));
    return (string) ($row[0] ?? '');
}

/**
 * Whether an amount can be paid by card - Stripe's threshold (Admin > Payment
 * Providers) caps what is offered at checkout; none set means no cap.
 */
function billingOnlinePayAllowed($amount) {
    $provider = billingStripeProvider();
    if (!$provider) {
        return false;
    }
    $threshold = floatval($provider['payment_provider_threshold']);
    return $threshold == 0 || $threshold > round(floatval($amount), 2);
}

/* ------------------------------------------------------------- Saved cards */

/**
 * A saved card's description ("visa - 4242 | Exp 9/2028", as saved) in
 * parts: brand, last4, exp ("09/28"), expired, icon and label ("Visa ••••
 * 4242"). One saved some other way keeps its description as the label.
 */
function billingCardParts($description) {
    $description = trim((string) $description);
    $brands = [
        'visa' => ['Visa', 'fab fa-cc-visa'],
        'mastercard' => ['Mastercard', 'fab fa-cc-mastercard'],
        'amex' => ['American Express', 'fab fa-cc-amex'],
        'american express' => ['American Express', 'fab fa-cc-amex'],
        'discover' => ['Discover', 'fab fa-cc-discover'],
        'diners' => ['Diners Club', 'fab fa-cc-diners-club'],
        'jcb' => ['JCB', 'fab fa-cc-jcb'],
        'unionpay' => ['UnionPay', 'fas fa-credit-card'],
    ];
    if (!preg_match('/^(.+?)\s*-\s*(\d{4})\s*\|\s*Exp\s*(\d{1,2})\/(\d{2,4})$/i', $description, $m)) {
        return ['brand' => '', 'last4' => '', 'exp' => '', 'expired' => false, 'icon' => 'fas fa-credit-card', 'label' => $description !== '' ? $description : 'Card'];
    }
    $key = strtolower(trim($m[1]));
    [$brand, $icon] = $brands[$key] ?? [ucwords($key), 'fas fa-credit-card'];
    $month = intval($m[3]);
    $year = intval($m[4]);
    $year = $year < 100 ? 2000 + $year : $year;
    // A card is good to the end of the month it runs out
    $expired = $year * 100 + $month < intval(date('Ym'));
    return [
        'brand' => $brand,
        'last4' => $m[2],
        'exp' => sprintf('%02d/%02d', $month, $year % 100),
        'expired' => $expired,
        'icon' => $icon,
        'label' => "$brand •••• {$m[2]}",
    ];
}

/**
 * The client's cards saved with Stripe, oldest first - each row with its
 * billingCardParts() under 'card' and how many active recurring invoices
 * pay with it under 'autopay'.
 */
function billingSavedCards($client_id) {
    global $mysqli;
    $provider = billingStripeProvider();
    if (!$provider) {
        return [];
    }
    $client_id = intval($client_id);
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT saved_payment_id, saved_payment_description, saved_payment_provider_method, saved_payment_created_at,
            (SELECT COUNT(*) FROM recurring_payments
                JOIN recurring_invoices ON recurring_invoice_id = recurring_payment_recurring_invoice_id
                WHERE recurring_payment_saved_payment_id = saved_payment_id AND recurring_invoice_status = 1) AS autopay
        FROM client_saved_payment_methods
        WHERE saved_payment_client_id = $client_id AND saved_payment_provider_id = " . intval($provider['payment_provider_id']) . "
        ORDER BY saved_payment_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['card'] = billingCardParts($row['saved_payment_description']);
        $row['autopay'] = intval($row['autopay']);
        $rows[] = $row;
    }
    return $rows;
}

/* ------------------------------------------------------- Choosing and paying */

/**
 * The invoices a client chose to pay, checked: theirs, still open with
 * something left on them, in one currency, no more than a card payment can
 * carry. ['invoices' => [id => row, with balance], 'cents' => [id =>
 * cents], 'total', 'currency', 'numbers'] - or null, with $error for the
 * client.
 */
function billingPaySelection($client_id, array $invoice_ids, &$error = '') {
    $invoice_ids = array_values(array_unique(array_filter(array_map('intval', $invoice_ids))));
    if (!$invoice_ids) {
        $error = 'Tick the invoices you want to pay';
        return null;
    }
    if (count($invoice_ids) > BILLING_PAY_MAX_INVOICES) {
        $error = 'Pay up to ' . BILLING_PAY_MAX_INVOICES . ' invoices at a time';
        return null;
    }
    $open = [];
    foreach (billingClientOpenInvoices($client_id) as $row) {
        $open[intval($row['invoice_id'])] = $row;
    }
    $selection = ['invoices' => [], 'cents' => [], 'total' => 0.0, 'currency' => '', 'numbers' => []];
    foreach ($invoice_ids as $id) {
        if (!isset($open[$id])) {
            $error = 'One of those invoices has been paid or changed since the page loaded - check the list and try again';
            return null;
        }
        $row = $open[$id];
        $cents = (int) round((floatval($row['invoice_amount']) - floatval($row['paid'])) * 100);
        $currency = strtoupper((string) $row['invoice_currency_code']);
        if ($selection['currency'] !== '' && $currency !== $selection['currency']) {
            $error = 'Those invoices are in different currencies - pay each currency on its own';
            return null;
        }
        $selection['currency'] = $currency;
        $row['balance'] = $cents / 100;
        $selection['invoices'][$id] = $row;
        $selection['cents'][$id] = $cents;
        $selection['numbers'][] = billingNumber('invoice', $row);
    }
    $selection['total'] = array_sum($selection['cents']) / 100;
    if (!billingOnlinePayAllowed($selection['total'])) {
        $error = 'Card payments are limited to under ' . billingMoney(floatval(billingStripeProvider()['payment_provider_threshold'] ?? 0), $selection['currency'])
            . ' at a time - pay fewer invoices at once, or pay another way';
        return null;
    }
    return $selection;
}

/** A selection's split, as it goes in the payment's metadata - "id:cents,id:cents". */
function billingPaySplit(array $selection) {
    $parts = [];
    foreach ($selection['cents'] as $id => $cents) {
        $parts[] = intval($id) . ':' . intval($cents);
    }
    return implode(',', $parts);
}

/** A split back from a payment's metadata - [invoice_id => cents], or [] when it doesn't read. */
function billingPaySplitParse($split) {
    $out = [];
    foreach (explode(',', (string) $split) as $part) {
        if (!preg_match('/^(\d+):(\d+)$/', trim($part), $m)) {
            return [];
        }
        $out[intval($m[1])] = intval($m[2]);
    }
    return $out;
}

/**
 * What every PaymentIntent for a selection carries - amount, currency,
 * description and the metadata billingStripeBook() books from. $by: the
 * contact paying (name).
 */
function billingStripeIntentParams(array $selection, $client_id, array $by = []) {
    global $mysqli;
    $client_id = intval($client_id);
    $client_name = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id"))[0] ?? '');
    $numbers = implode(', ', $selection['numbers']);
    $metadata = [
        'itflow_client_id' => $client_id,
        'itflow_client_name' => mb_substr($client_name, 0, 450),
        'itflow_invoices' => billingPaySplit($selection),
        'itflow_invoice_number' => mb_substr($numbers, 0, 450),
        'itflow_paid_by' => mb_substr((string) ($by['name'] ?? ''), 0, 200),
        'itflow_source' => 'client_portal',
    ];
    if (count($selection['cents']) === 1) {
        // The one-invoice key the guest and autopay payments carry
        $metadata['itflow_invoice_id'] = intval(array_key_first($selection['cents']));
    }
    return [
        'amount' => array_sum($selection['cents']),
        'currency' => strtolower($selection['currency']),
        'description' => mb_substr("ITFlow: $client_name payment of {$selection['currency']} " . number_format($selection['total'], 2, '.', '') . " for $numbers", 0, 1000),
        'metadata' => $metadata,
        'payment_method_types' => ['card'],
    ];
}

/**
 * Charge one of the client's saved cards for a selection, there and then.
 * $idempotency_key keeps a form sent twice to one charge. ['ok' => true,
 * 'pi' => the PaymentIntent] or ['ok' => false, 'error' => for the client].
 */
function billingStripeChargeSaved(array $selection, $client_id, $saved_payment_id, $idempotency_key, array $by = []) {
    global $mysqli;
    $provider = billingStripeProvider();
    $client_id = intval($client_id);
    $saved_payment_id = intval($saved_payment_id);
    $customer = billingStripeCustomer($client_id);
    $card = $provider ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT saved_payment_provider_method, saved_payment_description FROM client_saved_payment_methods
        WHERE saved_payment_id = $saved_payment_id AND saved_payment_client_id = $client_id AND saved_payment_provider_id = " . intval($provider['payment_provider_id']) . " LIMIT 1")) : null;
    if (!$provider || !$card || $customer === '') {
        return ['ok' => false, 'error' => 'That card is no longer saved - choose another'];
    }
    if (billingCardParts($card['saved_payment_description'])['expired']) {
        return ['ok' => false, 'error' => 'That card has expired - choose another or pay with a new card'];
    }
    $params = billingStripeIntentParams($selection, $client_id, $by) + [
        'customer' => $customer,
        'payment_method' => (string) $card['saved_payment_provider_method'],
        'off_session' => true,
        'confirm' => true,
    ];
    try {
        $pi = billingStripeClient()->paymentIntents->create($params, ['idempotency_key' => 'itflow-portal-' . $idempotency_key]);
    } catch (\Stripe\Exception\CardException $e) {
        $code = (string) $e->getStripeCode();
        logApp("Stripe", "warning", "Saved card payment declined for client $client_id: " . $e->getMessage());
        return ['ok' => false, 'error' => $code === 'authentication_required'
            ? 'Your bank wants to confirm this payment - choose A new card and enter the card details to approve it'
            : 'Your card was declined: ' . rtrim($e->getMessage(), '.') . '. Try another card'];
    } catch (\Throwable $e) {
        logApp("Stripe", "error", "Saved card payment failed for client $client_id: " . $e->getMessage());
        return ['ok' => false, 'error' => 'The payment could not be made just now - nothing was charged. Try again in a moment'];
    }
    if ($pi->status !== 'succeeded') {
        return ['ok' => false, 'error' => 'The payment did not go through - nothing was charged. Try another card'];
    }
    return ['ok' => true, 'pi' => $pi];
}

/**
 * The PaymentIntent the checkout page takes a new card with - $reuse_id's,
 * when it is still waiting for a card and asks for the same amount and
 * split, else a new one (and the old one cancelled). Throws on Stripe errors.
 */
function billingStripeCheckoutIntent(array $selection, $client_id, array $by = [], $reuse_id = '') {
    $stripe = billingStripeClient();
    $params = billingStripeIntentParams($selection, $client_id, $by);
    $customer = billingStripeCustomer($client_id);
    if ($customer !== '') {
        $params['customer'] = $customer;
    }
    if (preg_match('/^pi_[A-Za-z0-9_]+$/', (string) $reuse_id)) {
        try {
            $old = $stripe->paymentIntents->retrieve($reuse_id);
            $waiting = in_array($old->status, ['requires_payment_method', 'requires_confirmation', 'requires_action'], true);
            if ($waiting && intval($old->amount) === intval($params['amount']) && ($old->metadata['itflow_invoices'] ?? '') === $params['metadata']['itflow_invoices']
                && intval($old->metadata['itflow_client_id'] ?? 0) === intval($client_id)) {
                return $old;
            }
            if ($waiting) {
                $stripe->paymentIntents->cancel($reuse_id);
            }
        } catch (\Throwable $e) {
            // Gone or not cancellable - a new one is made below either way
        }
    }
    return $stripe->paymentIntents->create($params);
}

/* ------------------------------------------------------------------ Booking */

/**
 * Book a card payment that went through: one payment per invoice in its
 * split, dated the day it was made, into Stripe's account, referenced
 * "Stripe - pi_...", and each invoice's status from its payments. The money
 * has been taken, so an invoice paid some other way meanwhile (now overpaid)
 * or no longer open is booked all the same and flagged to the agents. Booked
 * once: another call for the same payment finds it and books nothing.
 *
 * $pi: the PaymentIntent (or the same as an array). $client_id: whose it
 * must be. $by: name (who paid), how ('Online card', 'Saved card'), via
 * (words for the history - "in the client portal").
 *
 * ['ok', 'error', 'applied' => [['invoice' => billingRow(), 'amount']],
 *  'payment_id' (the first), 'already' (booked before)]
 */
function billingStripeBook($pi, $client_id, array $by = []) {
    global $mysqli;
    $pi = $pi instanceof \Stripe\StripeObject ? $pi->toArray() : (array) json_decode(json_encode($pi), true);
    $client_id = intval($client_id);
    $pi_id = (string) ($pi['id'] ?? '');
    $fail = function ($error) {
        return ['ok' => false, 'error' => $error, 'applied' => [], 'payment_id' => 0, 'already' => false];
    };
    if (!preg_match('/^pi_[A-Za-z0-9_]+$/', $pi_id)) {
        return $fail('not a payment');
    }
    if (($pi['status'] ?? '') !== 'succeeded') {
        return $fail('not succeeded');
    }
    $received = intval($pi['amount_received'] ?? 0);
    if ($received <= 0 || $received !== intval($pi['amount'] ?? -1)) {
        return $fail('amount not received in full');
    }
    $metadata = (array) ($pi['metadata'] ?? []);
    if (intval($metadata['itflow_client_id'] ?? 0) !== $client_id) {
        return $fail('not this client');
    }
    $split = billingPaySplitParse($metadata['itflow_invoices'] ?? '');
    if (!$split && intval($metadata['itflow_invoice_id'] ?? 0)) {
        $split = [intval($metadata['itflow_invoice_id']) => $received];
    }
    if (!$split || array_sum($split) !== $received) {
        return $fail('split does not add up');
    }

    $currency = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($pi['currency'] ?? '')));
    $invoices = [];
    foreach ($split as $invoice_id => $cents) {
        $invoice = billingRow('invoice', $invoice_id, false);
        if (!$invoice || intval($invoice['client_id']) !== $client_id || strtoupper((string) $invoice['invoice_currency_code']) !== $currency || $cents <= 0) {
            return $fail("invoice $invoice_id does not fit");
        }
        $invoices[$invoice_id] = $invoice;
    }

    $reference = escapeSql("Stripe - $pi_id");
    $lock = escapeSql('itflow_pi_' . $pi_id);
    if (intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT GET_LOCK('$lock', 15)"))[0] ?? 0) !== 1) {
        return $fail('busy');
    }

    $booked = mysqli_fetch_row(mysqli_query($mysqli, "SELECT payment_id FROM payments
        JOIN invoices ON invoice_id = payment_invoice_id
        WHERE payment_reference = '$reference' AND invoice_client_id = $client_id ORDER BY payment_id ASC LIMIT 1"));
    if ($booked) {
        mysqli_query($mysqli, "SELECT RELEASE_LOCK('$lock')");
        $applied = [];
        foreach ($invoices as $invoice_id => $invoice) {
            $applied[] = ['invoice' => billingRow('invoice', $invoice_id, false), 'amount' => $split[$invoice_id] / 100];
        }
        return ['ok' => true, 'error' => '', 'applied' => $applied, 'payment_id' => intval($booked[0]), 'already' => true];
    }

    $provider = billingStripeProvider();
    $account_id = intval($provider['payment_provider_account'] ?? 0);
    $date = date('Y-m-d', intval($pi['created'] ?? time()) ?: time());
    $total = $received / 100;
    $name = trim((string) ($by['name'] ?? ''));
    $via = trim((string) ($by['via'] ?? ''));
    $many = count($invoices) > 1;
    $dev = empty($pi['livemode']) ? ' (DEV MODE)' : '';
    $applied = [];
    $first_payment_id = 0;

    foreach ($invoices as $invoice_id => $invoice) {
        $amount = $split[$invoice_id] / 100;
        $status_before = (string) $invoice['invoice_status'];
        mysqli_query($mysqli, "INSERT INTO payments SET payment_date = '$date', payment_amount = $amount, payment_currency_code = '" . escapeSql($currency) . "',
            payment_account_id = $account_id, payment_method = 'Stripe', payment_reference = '$reference', payment_invoice_id = $invoice_id");
        $first_payment_id = $first_payment_id ?: intval(mysqli_insert_id($mysqli));
        $status = updateInvoiceStatusFromPayments($invoice_id);
        $invoice = billingRow('invoice', $invoice_id, false);
        $number = billingNumber('invoice', $invoice);
        $money = billingMoney($amount, $currency);

        billingLog('invoice', $invoice_id, $status, "Paid $money online by card" . ($name !== '' ? " - $name" : '') . ($via !== '' ? " $via" : '')
            . ($many ? ', in one payment of ' . billingMoney($total, $currency) . ' for ' . count($invoices) . ' invoices' : ''));

        $state = invoiceState($invoice, invoicePaid($invoice_id));
        if (!in_array($status_before, invoiceOpenStatuses(), true) || $state['balance'] < 0) {
            appNotify("Invoice Payment", escapeSql("Check $number for {$invoice['client_name']}: a card payment of $money went on it"
                . ($state['balance'] < 0 ? ' and it is now overpaid by ' . billingMoney(-$state['balance'], $currency) : " while it was $status_before")),
                "/agent/invoice.php?invoice_id=$invoice_id", $client_id);
        }
        if ($status === 'Paid') {
            triggerCustomAction('invoice_pay', $invoice_id);
        }
        logAudit("Invoice", "Payment", escapeSql(($name !== '' ? $name : 'Client') . " paid $money on invoice $number by card" . ($via !== '' ? " $via" : '') . " - $pi_id$dev"), $client_id, $invoice_id);
        $applied[] = ['invoice' => $invoice, 'amount' => $amount];
    }

    mysqli_query($mysqli, "SELECT RELEASE_LOCK('$lock')");

    $client_name = (string) $applied[0]['invoice']['client_name'];
    $who = $name !== '' ? " ($name" . ($via !== '' ? ", $via" : '') . ')' : '';
    if ($many) {
        appNotify("Invoice Paid", escapeSql("$client_name paid " . count($applied) . ' invoices online - ' . billingMoney($total, $currency) . $who), "/agent/invoices.php?client_id=$client_id", $client_id);
    } else {
        $number = billingNumber('invoice', $applied[0]['invoice']);
        appNotify("Invoice Paid", escapeSql("Invoice $number has been paid by $client_name$who"), "/agent/invoice.php?invoice_id=" . intval($applied[0]['invoice']['invoice_id']), $client_id);
    }

    if (billingEmailReady()) {
        billingPaymentReceiptSend($applied, ['date' => $date, 'method' => 'Card', 'reference' => "Stripe - $pi_id", 'how' => $by['how'] ?? 'Online card'], true);
    }

    return ['ok' => true, 'error' => '', 'applied' => $applied, 'payment_id' => $first_payment_id, 'already' => false];
}

/* --------------------------------------------------------- Payment history */

/** SQL that keys the payments made together - one card payment over several invoices shares a date, method and reference. */
function billingPaymentGroupKeySql() {
    return "IF(COALESCE(payment_reference, '') <> '', CONCAT(payment_date, '|', payment_method, '|', payment_reference, '|', payment_currency_code), CONCAT('#', payment_id))";
}

/**
 * A client's payments, newest first, the parts of one payment together:
 * id (the first), date, method, reference, currency, total, count and
 * numbers (the invoices). ['rows' => ..., 'total' => how many payments].
 */
function billingClientPayments($client_id, $limit = 20, $offset = 0) {
    global $mysqli;
    $client_id = intval($client_id);
    $limit = max(1, intval($limit));
    $offset = max(0, intval($offset));
    $key = billingPaymentGroupKeySql();
    $from = "FROM payments JOIN invoices ON invoice_id = payment_invoice_id
        WHERE invoice_client_id = $client_id AND payment_archived_at IS NULL";
    $total = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(DISTINCT $key) $from"))[0] ?? 0);
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT MIN(payment_id) AS payment_id, MAX(payment_date) AS payment_date, MAX(payment_method) AS payment_method,
            MAX(payment_reference) AS payment_reference, MAX(payment_currency_code) AS payment_currency_code, SUM(payment_amount) AS amount, COUNT(*) AS invoices,
            GROUP_CONCAT(CONCAT(invoice_prefix, invoice_number) ORDER BY invoice_date, invoice_number SEPARATOR ', ') AS numbers
        $from
        GROUP BY $key
        ORDER BY MAX(payment_date) DESC, MIN(payment_id) DESC
        LIMIT $limit OFFSET $offset");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return ['rows' => $rows, 'total' => $total];
}

/**
 * One payment as a receipt - the payment $payment_id belongs to, with the
 * other invoices it paid: date, method, reference, currency, total and
 * lines (each payment with its invoice). Null when it isn't the client's.
 */
function billingPaymentReceipt($payment_id, $client_id) {
    global $mysqli;
    $payment_id = intval($payment_id);
    $client_id = intval($client_id);
    $key = billingPaymentGroupKeySql();
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT $key AS group_key FROM payments JOIN invoices ON invoice_id = payment_invoice_id
        WHERE payment_id = $payment_id AND invoice_client_id = $client_id AND payment_archived_at IS NULL"));
    if (!$row) {
        return null;
    }
    $group = escapeSql($row['group_key']);
    $lines = [];
    $sql = mysqli_query($mysqli, "SELECT payment_id, payment_date, payment_amount, payment_currency_code, payment_method, payment_reference, payment_created_at,
            invoice_id, invoice_prefix, invoice_number, invoice_scope, invoice_date, invoice_amount, invoice_status, invoice_due, invoice_currency_code,
            (SELECT COALESCE(SUM(p2.payment_amount), 0) FROM payments p2 WHERE p2.payment_invoice_id = invoice_id) AS paid
        FROM payments JOIN invoices ON invoice_id = payment_invoice_id
        WHERE invoice_client_id = $client_id AND payment_archived_at IS NULL AND $key = '$group'
        ORDER BY invoice_date ASC, invoice_number ASC");
    while ($line = mysqli_fetch_assoc($sql)) {
        $lines[] = $line;
    }
    if (!$lines) {
        return null;
    }
    return [
        'id' => intval($lines[0]['payment_id']),
        'date' => $lines[0]['payment_date'],
        'method' => (string) $lines[0]['payment_method'],
        'reference' => (string) $lines[0]['payment_reference'],
        'currency' => (string) $lines[0]['payment_currency_code'],
        'total' => round(array_sum(array_map('floatval', array_column($lines, 'payment_amount'))), 2),
        'lines' => $lines,
    ];
}

/** How a payment was made, in words for the client - "Card", "Check", "Bank transfer". */
function billingPaymentMethodText($method, $reference = '') {
    $method = trim((string) $method);
    if (strcasecmp($method, 'Stripe') === 0 || stripos((string) $reference, 'Stripe - pi_') === 0) {
        return 'Card';
    }
    return $method !== '' ? $method : 'Payment';
}
