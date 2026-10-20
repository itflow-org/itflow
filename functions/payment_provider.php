<?php

/*
 * ITFlow - Payment providers (Admin > Payment Providers)
 *
 * The services clients pay invoices by card through - Stripe, for now. Each
 * has its keys (live or test, as the keys say), whether it is on, the account
 * its payments go into, the invoices it is too small for (the card limit), and
 * whether its fees are recorded as expenses - each night, by
 * cron/nightly_tasks.php. Paying through one is functions/billing_pay.php.
 *
 * A provider is saved only once it is right: its keys look like that
 * provider's, come from the same mode, and - when they are new or changed -
 * the provider takes the secret one. The Add and Edit window asks first
 * (check_payment_provider), so what is wrong is said beside it while the
 * window stays open.
 */

/* The providers ITFlow can take payments through - by name, what their keys look like and where they are. */
function paymentProviderKinds() {
    return [
        'Stripe' => [
            'icon' => 'fab fa-stripe-s',
            'public' => '/^pk_(live|test)_[A-Za-z0-9]{8,}$/',
            'secret' => '/^(sk|rk)_(live|test)_[A-Za-z0-9]{8,}$/',
            'public_example' => 'pk_live_...',
            'secret_example' => 'sk_live_...',
            'keys_url' => 'https://dashboard.stripe.com/apikeys',
        ],
    ];
}

/* Live or test, as a key says - '' when it says neither. */
function paymentProviderMode($key) {
    return preg_match('/^[a-z]{2}_(live|test)_/', (string) $key, $m) ? $m[1] : '';
}

/* One provider's row, with its account, vendor and category names - null when there is none. */
function paymentProviderRow($provider_id) {
    global $mysqli;
    $provider_id = intval($provider_id);
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT payment_providers.*, account_name, account_archived_at, vendor_name, category_name
        FROM payment_providers
        LEFT JOIN accounts ON account_id = payment_provider_account
        LEFT JOIN vendors ON vendor_id = payment_provider_expense_vendor AND payment_provider_expense_vendor > 0
        LEFT JOIN categories ON category_id = payment_provider_expense_category AND payment_provider_expense_category > 0
        WHERE payment_provider_id = $provider_id LIMIT 1")) ?: null;
}

/* What goes with a provider when it is deleted - its saved cards, the recurring invoices that autopay with them, and the clients it knows. */
function paymentProviderTies($provider_id) {
    global $mysqli;
    $provider_id = intval($provider_id);
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT
        (SELECT COUNT(*) FROM client_saved_payment_methods WHERE saved_payment_provider_id = $provider_id),
        (SELECT COUNT(*) FROM recurring_payments JOIN client_saved_payment_methods ON saved_payment_id = recurring_payment_saved_payment_id WHERE saved_payment_provider_id = $provider_id),
        (SELECT COUNT(*) FROM client_payment_provider WHERE payment_provider_id = $provider_id)"));
    return ['cards' => intval($row[0]), 'autopay' => intval($row[1]), 'clients' => intval($row[2])];
}

/* "3 saved cards, autopay on 2 recurring invoices" - what paymentProviderTies() counted, in words ('' when nothing). */
function paymentProviderTiesText(array $ties) {
    $parts = [];
    if ($ties['cards']) {
        $parts[] = $ties['cards'] . ' saved card' . ($ties['cards'] === 1 ? '' : 's');
    }
    if ($ties['autopay']) {
        $parts[] = 'autopay on ' . $ties['autopay'] . ' recurring invoice' . ($ties['autopay'] === 1 ? '' : 's');
    }
    if ($ties['clients']) {
        $parts[] = 'the link to ' . $ties['clients'] . ' client' . ($ties['clients'] === 1 ? '' : 's') . ' as its customers';
    }
    if (count($parts) > 1) {
        $last = array_pop($parts);
        return implode(', ', $parts) . ' and ' . $last;
    }
    return $parts[0] ?? '';
}

/* A card limit as it reads - "Under $1,000.00", or "No limit". */
function paymentProviderLimitText($threshold) {
    $threshold = round(floatval($threshold), 2);
    return $threshold > 0 ? 'Under ' . billingMoney($threshold) : 'No limit';
}

/*
 * What a provider's form sent, checked against what is saved ($before, null
 * for a new one) - [the columns to save, errors by field]. Keys: one that
 * isn't that provider's shape, or one live and one test, is wrong; a secret
 * left empty stays as saved. Fees are recorded with a vendor and a category,
 * or not at all.
 */
function paymentProviderCheck(array $post, $before = null) {
    global $mysqli;
    $errors = [];
    $kinds = paymentProviderKinds();
    $name = $before ? (string) $before['payment_provider_name'] : trim((string) ($post['provider'] ?? ''));
    $kind = $kinds[$name] ?? null;
    if (!$before) {
        if (!$kind) {
            $errors['provider'] = 'Pick one of these.';
        } elseif (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM payment_providers WHERE payment_provider_name = '" . escapeSql($name) . "' LIMIT 1"))) {
            $errors['provider'] = "$name is set up already - edit it instead.";
        }
    }

    $description = trim(preg_replace('/\s+/u', ' ', (string) ($post['description'] ?? '')));
    if (mb_strlen($description) > 250) {
        $errors['description'] = 'Up to 250 characters.';
    }

    // The keys - the secret one left empty stays as saved
    $public_key = trim((string) ($post['public_key'] ?? ''));
    $secret_typed = trim((string) ($post['private_key'] ?? ''));
    $secret_key = $secret_typed !== '' ? $secret_typed : (string) ($before['payment_provider_private_key'] ?? '');
    if ($kind) {
        if ($public_key === '') {
            $errors['public_key'] = "The publishable key - it starts {$kind['public_example']}";
        } elseif (mb_strlen($public_key) > 250 || !preg_match($kind['public'], $public_key)) {
            $errors['public_key'] = "That isn't a publishable key - it starts {$kind['public_example']}";
        }
        if ($secret_key === '') {
            $errors['private_key'] = "The secret key - it starts {$kind['secret_example']}";
        } elseif ($secret_typed !== '' && (mb_strlen($secret_typed) > 250 || !preg_match($kind['secret'], $secret_typed))) {
            $errors['private_key'] = "That isn't a secret key - it starts {$kind['secret_example']}, or rk_ for a restricted key.";
        }
        if (!isset($errors['public_key']) && !isset($errors['private_key']) && paymentProviderMode($secret_key) === '') {
            $errors['private_key'] = "The saved secret key doesn't say whether it is live or test - type it in again.";
        } elseif (!isset($errors['public_key']) && !isset($errors['private_key']) && paymentProviderMode($public_key) !== paymentProviderMode($secret_key)) {
            $errors['private_key'] = 'One key is a ' . paymentProviderMode($public_key) . ' key and the other a ' . paymentProviderMode($secret_key) . ' one - both have to be live, or both test.';
        }
    }

    // Where payments go - an open account, or the one it is set to
    $account_id = intval($post['account'] ?? 0);
    $account = $account_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT account_id, account_archived_at FROM accounts WHERE account_id = $account_id LIMIT 1")) : null;
    if (!$account || ($account['account_archived_at'] && $account_id !== intval($before['payment_provider_account'] ?? 0))) {
        $errors['account'] = 'Pick the account its payments go into.';
    }

    // The card limit - empty, or 0, for none
    $threshold_text = str_replace([',', ' '], '', trim((string) ($post['threshold'] ?? '')));
    $threshold = 0.0;
    if ($threshold_text !== '') {
        if (!preg_match('/^\d{1,13}(\.\d{1,2})?$/', $threshold_text)) {
            $errors['threshold'] = 'A sum of money, as 1000 or 1000.50 - or empty for no limit.';
        } else {
            $threshold = round(floatval($threshold_text), 2);
        }
    }

    // Its fees - recorded under a vendor of yours and an expense category, or not at all
    $vendor_id = 0;
    $category_id = 0;
    if (!empty($post['record_fees'])) {
        $vendor_id = intval($post['expense_vendor'] ?? 0);
        $category_id = intval($post['expense_category'] ?? 0);
        $vendor = $vendor_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT vendor_id, vendor_archived_at FROM vendors WHERE vendor_id = $vendor_id AND vendor_client_id = 0 LIMIT 1")) : null;
        if (!$vendor || ($vendor['vendor_archived_at'] && $vendor_id !== intval($before['payment_provider_expense_vendor'] ?? 0))) {
            $errors['expense_vendor'] = 'Pick the vendor its fees are paid to - one of your own, as ' . ($name !== '' ? $name : 'Stripe') . '.';
        }
        $category = $category_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT category_id, category_archived_at FROM categories WHERE category_id = $category_id AND category_type = 'Expense' LIMIT 1")) : null;
        if (!$category || ($category['category_archived_at'] && $category_id !== intval($before['payment_provider_expense_category'] ?? 0))) {
            $errors['expense_category'] = 'Pick the expense category they go under, as Processing Fee.';
        }
    }

    $after = [
        'payment_provider_name' => $name,
        'payment_provider_description' => $description,
        'payment_provider_public_key' => $public_key,
        'payment_provider_private_key' => $secret_key,
        'payment_provider_threshold' => $threshold,
        'payment_provider_active' => $before ? (!empty($post['active']) ? 1 : 0) : 1,
        'payment_provider_account' => $account_id,
        'payment_provider_expense_vendor' => $vendor_id,
        'payment_provider_expense_category' => $category_id,
    ];
    return [$after, $errors];
}

/* Whether the keys are new or changed - only then is the provider asked about them. */
function paymentProviderKeysChanged(array $after, $before = null) {
    return !$before || $after['payment_provider_public_key'] !== (string) $before['payment_provider_public_key']
        || $after['payment_provider_private_key'] !== (string) $before['payment_provider_private_key'];
}

/*
 * Ask the provider whether it takes the secret key - ['ok' => bool, 'error'
 * => what is wrong, for the secret key field, 'account' => its name, email
 * and country, as it gives them].
 */
function paymentProviderAsk($name, $secret_key) {
    if ($name !== 'Stripe') {
        return ['ok' => true, 'error' => '', 'account' => []];
    }
    require_once __DIR__ . '/../includes/stripe_init.php';
    try {
        $stripe = new \Stripe\StripeClient((string) $secret_key);
        $account = $stripe->accounts->retrieve();
    } catch (\Stripe\Exception\AuthenticationException $e) {
        return ['ok' => false, 'error' => 'Stripe doesn\'t know that secret key - copy it again from Developers > API keys.', 'account' => []];
    } catch (\Stripe\Exception\PermissionException $e) {
        return ['ok' => false, 'error' => 'That restricted key can\'t read the account - give it Read on Account, or use the secret key.', 'account' => []];
    } catch (\Stripe\Exception\ApiConnectionException $e) {
        return ['ok' => false, 'error' => 'Stripe couldn\'t be reached to check the key - ' . $e->getMessage(), 'account' => []];
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => 'Stripe turned the key away - ' . $e->getMessage(), 'account' => []];
    }
    $display = (string) ($account->settings->dashboard->display_name ?? '');
    if ($display === '') {
        $display = (string) ($account->business_profile->name ?? '');
    }
    return ['ok' => true, 'error' => '', 'account' => [
        'name' => $display,
        'email' => (string) ($account->email ?? ''),
        'country' => (string) ($account->country ?? ''),
        'id' => (string) ($account->id ?? ''),
    ]];
}

/* What changed on a provider, a line each - "Card limit Under $1,000.00 -> No limit", "Secret key changed", "Turned off". */
function paymentProviderChangeLines(array $before, array $after) {
    global $mysqli;
    $name_of = function ($table, $id) use ($mysqli) {
        $id = intval($id);
        if (!$id) {
            return 'none';
        }
        $column = ['accounts' => ['account_name', 'account_id'], 'vendors' => ['vendor_name', 'vendor_id'], 'categories' => ['category_name', 'category_id']][$table];
        $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT {$column[0]} FROM $table WHERE {$column[1]} = $id"));
        return $row ? $row[0] : "#$id";
    };
    $lines = [];
    if ((string) $before['payment_provider_description'] !== $after['payment_provider_description']) {
        $lines[] = 'Description ' . ((string) $before['payment_provider_description'] !== '' ? $before['payment_provider_description'] : 'blank') . ' → ' . ($after['payment_provider_description'] !== '' ? $after['payment_provider_description'] : 'blank');
    }
    $was_mode = paymentProviderMode($before['payment_provider_private_key']);
    $now_mode = paymentProviderMode($after['payment_provider_private_key']);
    if ($was_mode !== $now_mode) {
        $lines[] = "Mode $was_mode → $now_mode";
    }
    if ((string) $before['payment_provider_public_key'] !== $after['payment_provider_public_key']) {
        $lines[] = 'Publishable key changed';
    }
    if ((string) $before['payment_provider_private_key'] !== $after['payment_provider_private_key']) {
        $lines[] = 'Secret key changed';
    }
    if (intval($before['payment_provider_account']) !== $after['payment_provider_account']) {
        $lines[] = 'Payments go into ' . $name_of('accounts', $before['payment_provider_account']) . ' → ' . $name_of('accounts', $after['payment_provider_account']);
    }
    if (round(floatval($before['payment_provider_threshold']), 2) !== round($after['payment_provider_threshold'], 2)) {
        $lines[] = 'Card limit ' . paymentProviderLimitText($before['payment_provider_threshold']) . ' → ' . paymentProviderLimitText($after['payment_provider_threshold']);
    }
    $fees = function ($vendor_id, $category_id) use ($name_of) {
        return intval($vendor_id) && intval($category_id) ? $name_of('vendors', $vendor_id) . ', ' . $name_of('categories', $category_id) : 'not recorded';
    };
    $fees_before = $fees($before['payment_provider_expense_vendor'], $before['payment_provider_expense_category']);
    $fees_after = $fees($after['payment_provider_expense_vendor'], $after['payment_provider_expense_category']);
    if ($fees_before !== $fees_after) {
        $lines[] = "Fees $fees_before → $fees_after";
    }
    if (intval($before['payment_provider_active']) !== $after['payment_provider_active']) {
        $lines[] = $after['payment_provider_active'] ? 'Turned on' : 'Turned off';
    }
    return $lines;
}

/* Save a provider's columns - new ($provider_id 0) or changed. Its id. */
function paymentProviderWrite(array $after, $provider_id = 0) {
    global $mysqli;
    $sets = [];
    foreach ($after as $column => $value) {
        $column_sql = '`' . preg_replace('/[^a-z_]/', '', $column) . '`';
        if (is_int($value)) {
            $sets[] = "$column_sql = $value";
        } elseif (is_float($value)) {
            $sets[] = "$column_sql = " . sprintf('%.2F', $value);
        } else {
            // Keys exactly as given - escapeSql() would trim them and take out anything like a tag
            $sets[] = "$column_sql = '" . mysqli_real_escape_string($mysqli, (string) $value) . "'";
        }
    }
    $provider_id = intval($provider_id);
    if ($provider_id) {
        mysqli_query($mysqli, "UPDATE payment_providers SET " . implode(', ', $sets) . " WHERE payment_provider_id = $provider_id");
        return $provider_id;
    }
    mysqli_query($mysqli, "INSERT INTO payment_providers SET " . implode(', ', $sets));
    return intval(mysqli_insert_id($mysqli));
}
