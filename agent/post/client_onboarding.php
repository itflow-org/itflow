<?php

/*
 * ITFlow - POST request handlers for onboarding a client
 * (agent/client_onboarding.php, functions/client_onboarding.php).
 *
 * Each step saves what it is about the way the rest of ITFlow does - the
 * client as New Client makes it, a location, a contact and their portal
 * access, a contract from a template (sent to sign), the client's billing and
 * the contract's recurring invoice, domains, vendors and licenses from
 * templates, a project from a project template - checked first, so a step
 * either saves or comes back with what to put right (settingsRefuse()). Then
 * it notes the step saved and goes on: Locations and People back to the same
 * step, to add another; the rest to the next.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The client a step posts - onboarded by this agent, there, not archived - or back with an error. */
function clientOnboardingPostClient() {
    global $mysqli;
    if (!clientOnboardingReady()) {
        flashAlert('Onboarding a client needs the database update - ask an admin to run it from Maintenance &gt; Update', 'error');
        redirect('/agent/clients.php');
    }
    enforceUserPermission('module_client', 2);
    $client_id = intval($_POST['client_id'] ?? 0);
    $client = $client_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id = $client_id LIMIT 1")) : null;
    if (!$client) {
        flashAlert('That client no longer exists.', 'error');
        redirect('/agent/clients.php');
    }
    enforceClientAccess($client_id);
    if ($client['client_archived_at']) {
        flashAlert('An archived client isn\'t onboarded - restore it first.', 'error');
        redirect("/agent/client_overview.php?client_id=$client_id");
    }
    clientOnboardingRow($client_id) ?? clientOnboardingStart($client_id);
    return $client;
}

/** Note a step saved, and go on to the next one - or stay, with $stay. */
function clientOnboardingSaved($client_id, $step, $stay = false) {
    clientOnboardingMark($client_id, $step);
    $steps = clientOnboardingSteps($client_id);
    redirect(clientOnboardingUrl($client_id, $stay ? $step : clientOnboardingAfter($step, $steps)));
}

if (isset($_POST['client_onboarding_company'])) {

    validateCSRFToken();

    // A new client - or one being onboarded, a lead made a client here
    $client_id = intval($_POST['client_id'] ?? 0);
    if ($client_id) {
        $client = clientOnboardingPostClient();
    } else {
        if (!clientOnboardingReady()) {
            flashAlert('Onboarding a client needs the database update - ask an admin to run it from Maintenance &gt; Update', 'error');
            redirect('/agent/clients.php');
        }
        enforceUserPermission('module_client', 2);
        $client = null;
    }

    $name = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? '')));
    $errors = [];
    if ($name === '') {
        $errors['name'] = 'The name you know them by.';
    } elseif (mb_strlen($name) > 200) {
        $errors['name'] = 'Up to 200 characters.';
    }
    if ($errors) {
        $_SESSION['co_kept_tags'] = array_map('intval', (array) ($_POST['tags'] ?? []));
        settingsRefuse('client_onboarding_company', $errors, clientOnboardingUrl($client_id, 'company'));
    }
    $name_sql = escapeSql($name);
    $type = escapeSql(mb_substr(trim((string) ($_POST['type'] ?? '')), 0, 200));
    $website = escapeSql(preg_replace('#^https?://#i', '', mb_substr(trim((string) ($_POST['website'] ?? '')), 0, 200)));
    $abbreviation = strtoupper(mb_substr(trim((string) ($_POST['abbreviation'] ?? '')), 0, 6));
    $abbreviation = escapeSql($abbreviation !== '' ? $abbreviation : shortenClientName($name_sql));
    $notes = escapeSql(trim((string) ($_POST['notes'] ?? '')));
    // A referral typed in that isn't one yet is made one; one that is takes its own spelling
    $referral = escapeSql(categoryEnsure('Referral', $_POST['referral'] ?? ''));

    if (!$client) {
        // As New Client makes it (agent/post/client.php add_client) - in the company's currency, with payment reminders
        mysqli_query($mysqli, "INSERT INTO clients SET client_name = '$name_sql', client_type = '$type', client_website = '$website', client_referral = '$referral',
            client_currency_code = '" . escapeSql($session_company_currency) . "', client_reminders = 1, client_lead = 0, client_abbreviation = '$abbreviation', client_notes = '$notes',
            client_accessed_at = NOW()" . billingClientLegacySql());
        $client_id = intval(mysqli_insert_id($mysqli));
        $client_folder = $_SERVER['DOCUMENT_ROOT'] . "/uploads/clients/$client_id";
        if (!file_exists($client_folder)) {
            mkdir($client_folder);
            file_put_contents("$client_folder/index.php", "");
        }
        tagSetForRecord('client', $client_id, (array) ($_POST['tags'] ?? []));

        // The website's domain and certificate, looked up like any other
        $extended = '';
        $website_domain = domainNormalizeName(stripslashes($website));
        if ($website_domain !== '') {
            $website_domain_sql = escapeSql($website_domain);
            mysqli_query($mysqli, "INSERT INTO domains SET domain_name = '$website_domain_sql', domain_description = 'Website', domain_client_id = $client_id");
            $domain_id = intval(mysqli_insert_id($mysqli));
            $extended .= ", domain $website_domain_sql added";
            if (domainApplyLookup($domain_id, domainLookup($website_domain)) && domainAddCertificate($domain_id, $website_domain, $client_id)) {
                $extended .= ", SSL certificate $website_domain_sql added";
            }
        }
        logAudit("Client", "Create", "$session_name created client $name_sql$extended", $client_id, $client_id);
        clientOnboardingStart($client_id);
        flashAlert("Client <strong>" . escapeHtml($name) . "</strong> added - now where they work");
        clientOnboardingSaved($client_id, 'company');
    }

    $was_lead = intval($client['client_lead']) === 1;
    mysqli_query($mysqli, "UPDATE clients SET client_name = '$name_sql', client_type = '$type', client_website = '$website', client_referral = '$referral',
        client_abbreviation = '$abbreviation', client_notes = '$notes', client_lead = 0 WHERE client_id = $client_id");
    tagSetForRecord('client', $client_id, (array) ($_POST['tags'] ?? []));
    logAudit("Client", "Edit", "$session_name " . ($was_lead ? "made lead $name_sql a client" : "edited client $name_sql") . ", onboarding them", $client_id, $client_id);
    flashAlert("<strong>" . escapeHtml($name) . "</strong> " . ($was_lead ? 'is a client now' : 'saved'));
    clientOnboardingSaved($client_id, 'company');

}

if (isset($_POST['client_onboarding_location'])) {

    validateCSRFToken();

    $client = clientOnboardingPostClient();
    $client_id = intval($client['client_id']);

    $set = locationFormSet($_POST, $client_id);
    if ($set === null) {
        settingsRefuse('client_onboarding_locations', ['name' => 'Give it a name - Main office, say.'], clientOnboardingUrl($client_id, 'locations'));
    }
    $first = !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL LIMIT 1"));
    mysqli_query($mysqli, "INSERT INTO locations SET $set, location_client_id = $client_id");
    $location_id = intval(mysqli_insert_id($mysqli));
    if ($first || !empty($_POST['primary'])) {
        locationMakePrimary($location_id, $client_id);
    }
    $location_name = trim((string) $_POST['name']);
    logAudit("Location", "Create", "$session_name created location " . escapeSql($location_name) . ($first || !empty($_POST['primary']) ? ', the primary' : ''), $client_id, $location_id);

    flashAlert("Location <strong>" . escapeHtml($location_name) . "</strong> added" . ($first ? ' as the primary' : (!empty($_POST['primary']) ? ', and made the primary' : '')) . " - add another, or carry on");
    clientOnboardingSaved($client_id, 'locations', true);

}

if (isset($_POST['client_onboarding_contact'])) {

    validateCSRFToken();

    $client = clientOnboardingPostClient();
    $client_id = intval($client['client_id']);

    $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($_POST['name'] ?? ''))));
    $email = trim((string) ($_POST['email'] ?? ''));
    // Portal access goes with its email - with no mail set up, it is given from the contact's page later
    $portal = !empty($_POST['portal']) && intval($config_client_portal_enable) === 1 && portalMailReady();
    $errors = [];
    if ($name === '') {
        $errors['name'] = 'Their name.';
    } elseif (mb_strlen($name) > 200) {
        $errors['name'] = 'Up to 200 characters.';
    }
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 200)) {
        $errors['email'] = "That isn't an email address.";
    } elseif ($portal && $email === '') {
        $errors['email'] = 'Client portal access needs their email address - they sign in with it.';
    } elseif ($portal && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM users WHERE user_email = '" . escapeSql($email) . "' AND user_archived_at IS NULL LIMIT 1"))) {
        $errors['email'] = 'That address signs in already - an agent or another client\'s contact - so it can\'t be given portal access too. Leave portal access off, or use another address.';
    }
    if ($errors) {
        settingsRefuse('client_onboarding_people', $errors, clientOnboardingUrl($client_id, 'people'));
    }

    $digits = function ($field, $length = 200) {
        return substr(preg_replace('/[^0-9]/', '', (string) ($_POST[$field] ?? '')), 0, $length);
    };
    $first = !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL AND contact_primary = 1 LIMIT 1"));
    $primary = $first || !empty($_POST['contact_primary']) ? 1 : 0;
    $important = $primary || !empty($_POST['contact_important']) ? 1 : 0;
    $billing = !empty($_POST['contact_billing']) ? 1 : 0;
    $technical = !empty($_POST['contact_technical']) ? 1 : 0;
    $location_id = intval(assetClientOwnedId('location', $_POST['location'] ?? 0, $client_id));
    $name_sql = escapeSql($name);
    $email_sql = escapeSql($email);

    mysqli_query($mysqli, "INSERT INTO contacts SET contact_name = '$name_sql', contact_title = '" . escapeSql(mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 200)) . "',
        contact_phone_country_code = '" . $digits('phone_country_code', 10) . "', contact_phone = '" . $digits('phone') . "',
        contact_mobile_country_code = '" . $digits('mobile_country_code', 10) . "', contact_mobile = '" . $digits('mobile') . "', contact_email = '$email_sql',
        contact_important = $important, contact_billing = $billing, contact_technical = $technical, contact_location_id = $location_id, contact_user_id = 0, contact_client_id = $client_id");
    $contact_id = intval(mysqli_insert_id($mysqli));
    if ($primary) {
        mysqli_query($mysqli, "UPDATE contacts SET contact_primary = 0 WHERE contact_client_id = $client_id AND contact_id <> $contact_id");
        mysqli_query($mysqli, "UPDATE contacts SET contact_primary = 1, contact_important = 1 WHERE contact_id = $contact_id");
    }

    // Their portal access - a link to set their own password, never the password (functions/portal.php)
    $invited = null;
    if ($portal) {
        $hash = escapeSql(password_hash(randomString(32), PASSWORD_DEFAULT));
        mysqli_query($mysqli, "INSERT INTO users SET user_name = '$name_sql', user_email = '$email_sql', user_password = '$hash', user_auth_method = 'local', user_type = 2");
        $user_id = intval(mysqli_insert_id($mysqli));
        mysqli_query($mysqli, "UPDATE contacts SET contact_user_id = $user_id WHERE contact_id = $contact_id");
        $invited = portalMailInvite($user_id);
    }

    logAudit("Contact", "Create", "$session_name created contact $name_sql" . ($primary ? ', the primary contact' : '') . ($invited ? ' and emailed a client portal invite' : ($portal ? ', with portal access' : '')), $client_id, $contact_id);
    triggerCustomAction('contact_create', $contact_id);

    flashAlert("<strong>" . escapeHtml($name) . "</strong> added" . ($primary ? ' as the primary contact' : '')
        . ($invited ? ' and emailed a link to the client portal' : ($invited === false ? ' - with portal access, but the email with their link could not be sent' : '')) . " - add someone else, or carry on", $invited === false ? 'warning' : 'success');
    clientOnboardingSaved($client_id, 'people', true);

}

if (isset($_POST['client_onboarding_agreement'])) {

    validateCSRFToken();

    $client = clientOnboardingPostClient();
    $client_id = intval($client['client_id']);
    if (!contractClientPickReady()) {
        flashAlert('Starting a contract needs Sales at Modify, with accounting on.', 'error');
        redirect(clientOnboardingUrl($client_id, 'agreement'));
    }

    $owner_id = intval($_POST['owner_id'] ?? 0);
    if ($owner_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM users WHERE user_id = $owner_id AND user_type = 1 AND user_archived_at IS NULL"))) {
        $owner_id = 0;
    }
    $send = !empty($_POST['send']) && contractSignaturesReady();
    $errors = [];
    $signer = null;
    if ($send) {
        $signer = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email FROM contacts
            WHERE contact_id = " . intval($_POST['signer_id'] ?? 0) . " AND contact_client_id = $client_id AND contact_archived_at IS NULL"));
        if (!$signer || !filter_var(trim((string) $signer['contact_email']), FILTER_VALIDATE_EMAIL)) {
            $errors['signer_id'] = 'Pick who signs it - one of their people with an email address.';
        }
    }
    $template = contractTemplateRow($_POST['template_id'] ?? 0);
    if (!$template || $template['contract_template_archived_at']) {
        $errors['template_id'] = 'Pick a template.';
    }
    if ($errors) {
        settingsRefuse('client_onboarding_agreement', $errors, clientOnboardingUrl($client_id, 'agreement'));
    }

    [$contract_id, $error] = contractFromTemplateAdd($client_id, $template['contract_template_id'], (string) ($_POST['start'] ?? ''), $owner_id, '', $send ? 'Draft' : 'Active');
    if (!$contract_id) {
        settingsRefuse('client_onboarding_agreement', [stripos($error, 'date') !== false ? 'start' : 'template_id' => $error], clientOnboardingUrl($client_id, 'agreement'));
    }
    $row = contractRow($contract_id);
    logAudit("Contract", "Create", "$session_name added " . ($send ? 'draft' : 'active') . " contract " . escapeSql($row['contract_name']) . " (" . escapeSql(contractTypeLabel($row['contract_type'])) . ") from "
        . escapeSql($row['contract_start_date']) . ($row['contract_end_date'] ? " to " . escapeSql($row['contract_end_date']) : '') . ", from its template, onboarding the client", $client_id, $contract_id);
    clientOnboardingMade($client_id, 'contract', $contract_id);

    $message = "Contract <strong>" . escapeHtml($row['contract_name']) . "</strong> started";
    $tone = 'success';
    if ($send) {
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 2000);
        [$round_id, $queued] = contractSignatureSend($row, (string) $signer['contact_name'], trim((string) $signer['contact_email']), intval($signer['contact_id']), $note, $session_user_id);
        logAudit("Contract", "Send", "$session_name sent contract " . escapeSql($row['contract_name']) . " for signature to " . escapeSql("{$signer['contact_name']} <{$signer['contact_email']}>"), $client_id, $contract_id);
        if ($queued) {
            $message .= " and sent to <strong>" . escapeHtml($signer['contact_name']) . "</strong> to sign - it is a draft until you countersign it";
        } else {
            $message .= " - it is ready to sign, but nothing was emailed as mail isn't set up. Copy its signing link from the contract and send it yourself";
            $tone = 'warning';
        }
    } else {
        $message .= ", active from " . date('M j, Y', strtotime($row['contract_start_date']));
    }
    flashAlert($message, $tone);
    clientOnboardingSaved($client_id, 'agreement');

}

if (isset($_POST['client_onboarding_billing'])) {

    validateCSRFToken();

    $client = clientOnboardingPostClient();
    $client_id = intval($client['client_id']);

    // The agreement's recurring invoice - checked before anything is saved
    $recurring = null;
    $contract = null;
    if (!empty($_POST['recurring']) && contractBillingReady() && lookupUserPermission('module_sales') >= 2) {
        $contract = clientOnboardingContract($client_id);
        if (!$contract || contractRecurringRow($contract) || (!contractRecurringShouldRun($contract) && $contract['contract_status'] !== 'Draft')) {
            flashAlert("There's no agreement to bill now - it may have a recurring invoice already, or have ended.", 'error');
            redirect(clientOnboardingUrl($client_id, 'billing'));
        }
        [$recurring, $error] = contractRecurringFromInput([
            'amount' => $_POST['amount'] ?? '', 'frequency' => $_POST['frequency'] ?? 'month', 'start' => $_POST['first'] ?? '',
            'item_name' => $_POST['item_name'] ?? '', 'description' => $_POST['description'] ?? '', 'category' => $_POST['category'] ?? 0,
            'tax_id' => $_POST['tax_id'] ?? 0, 'auto_send' => $_POST['auto_send'] ?? '',
        ]);
        if ($error !== '') {
            $field = stripos($error, 'amount') !== false ? 'amount' : (stripos($error, 'first invoice') !== false ? 'first' : 'item_name');
            settingsRefuse('client_onboarding_billing', [$field => $error], clientOnboardingUrl($client_id, 'billing'));
        }
    }

    // Who gets the invoices
    $billing_contact_id = intval(assetClientOwnedId('contact', $_POST['billing_contact_id'] ?? 0, $client_id));
    if ($billing_contact_id) {
        mysqli_query($mysqli, "UPDATE contacts SET contact_billing = 1 WHERE contact_id = $billing_contact_id AND contact_client_id = $client_id");
    }

    // Its tax ID, tax rate or exemption, and reminders (as the client's Billing tab)
    $tax_input = isset($_POST['tax_fields']) ? [
        'default_tax_id' => $_POST['default_tax_id'] ?? 0,
        'tax_exempt' => !empty($_POST['tax_exempt']),
        'tax_exempt_number' => $_POST['tax_exempt_number'] ?? '',
        'tax_exempt_expires' => $_POST['tax_exempt_expires'] ?? '',
    ] : null;
    $tax_current = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_default_tax_id, client_tax_exempt, client_tax_exempt_number, client_tax_exempt_expires FROM clients WHERE client_id = $client_id")) ?: [];
    $reminders = isset($_POST['reminders_field']) ? (!empty($_POST['reminders']) ? 1 : 0) : intval($client['client_reminders']);
    mysqli_query($mysqli, "UPDATE clients SET client_tax_id_number = '" . escapeSql(mb_substr(trim((string) ($_POST['tax_id_number'] ?? '')), 0, 255)) . "', client_reminders = $reminders"
        . ($tax_input !== null ? ', ' . taxClientSetSql($tax_input, $tax_current) : '') . " WHERE client_id = $client_id");
    $client_name_sql = escapeSql($client['client_name']);
    logAudit("Client", "Edit", "$session_name set the billing of client $client_name_sql, onboarding them", $client_id, $client_id);

    $message = "Billing saved";
    if ($recurring) {
        [$recurring_id, $number, $state] = contractRecurringCreate($contract, $recurring, stripslashes($session_name));
        logAudit("Contract", "Edit", "$session_name made recurring invoice " . escapeSql($number) . " for contract " . escapeSql($contract['contract_name']), $client_id, intval($contract['contract_id']));
        clientOnboardingMade($client_id, 'recurring_invoice', $recurring_id);
        $message .= ", and recurring invoice <strong>" . escapeHtml($number) . "</strong> made for <strong>" . escapeHtml($contract['contract_name']) . "</strong>"
            . ($state === 'started' ? ' - the first invoice is on ' . date('M j, Y', strtotime($recurring['start'])) : ' - paused until the contract is active');
    }
    flashAlert($message);
    clientOnboardingSaved($client_id, 'billing');

}

if (isset($_POST['client_onboarding_it'])) {

    validateCSRFToken();

    $client = clientOnboardingPostClient();
    $client_id = intval($client['client_id']);
    $support = lookupUserPermission('module_support') >= 2;

    // Domains, one to a line - every one a domain name, or nothing is added
    $domain_names = [];
    $bad = [];
    if ($support) {
        foreach (array_unique(array_filter(preg_split('/[\s,;]+/', (string) ($_POST['domains'] ?? '')), 'strlen')) as $input) {
            $name = domainNormalizeName($input);
            if ($name === '') {
                $bad[] = mb_substr($input, 0, 60);
            } else {
                $domain_names[$name] = true;
            }
        }
    }
    if ($bad) {
        $_SESSION['co_kept_it'] = ['vendor_templates' => (array) ($_POST['vendor_templates'] ?? []), 'license_templates' => (array) ($_POST['license_templates'] ?? [])];
        settingsRefuse('client_onboarding_it', ['domains' => 'Not domain names: ' . implode(', ', $bad) . ' - something like example.com.'], clientOnboardingUrl($client_id, 'it'));
    }

    set_time_limit(0);
    $made = ['domains' => 0, 'vendors' => 0, 'licenses' => 0];
    $started = time();
    foreach (array_keys($domain_names) as $name) {
        $form = domainFormSet(['name' => $name], $client_id, 0, $error);
        if ($form === null) {
            continue; // on the client already
        }
        mysqli_query($mysqli, "INSERT INTO domains SET {$form['set']}, domain_client_id = $client_id");
        $domain_id = intval(mysqli_insert_id($mysqli));
        logAudit("Domain", "Create", "$session_name created domain " . escapeSql($form['name']) . ", onboarding the client", $client_id, $domain_id);
        $made['domains']++;
        // Looked up while there is time - the refresher takes the rest on its next run
        if (time() - $started <= 20 && domainApplyLookup($domain_id, domainLookup($form['name']))) {
            domainAddCertificate($domain_id, $form['name'], $client_id);
        }
    }

    // Vendors from templates - the client's own, made from each
    foreach (array_unique(array_map('intval', (array) ($_POST['vendor_templates'] ?? []))) as $template_id) {
        $vendor_made = false;
        $vendor_id = softwareVendorFromTemplate($template_id, $client_id, true, $vendor_made);
        if ($vendor_made) {
            logAudit("Vendor", "Create", "$session_name created vendor " . escapeSql((string) getFieldById('vendors', $vendor_id, 'vendor_name')) . " from a template, onboarding the client", $client_id, $vendor_id);
            $made['vendors']++;
        }
    }

    // Licenses from templates - with the template's vendor, made for the client when it hasn't got it
    if ($support) {
        foreach (array_unique(array_map('intval', (array) ($_POST['license_templates'] ?? []))) as $template_id) {
            if (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM software WHERE software_client_id = $client_id AND software_template_id = $template_id AND software_archived_at IS NULL LIMIT 1"))) {
                continue;
            }
            $vendor_made = false;
            $software_id = softwareTemplateMakeLicense($template_id, $client_id, [], true, $vendor_made);
            if (!$software_id) {
                continue;
            }
            $license_name = (string) getFieldById('software', $software_id, 'software_name');
            if ($vendor_made) {
                $vendor_id = intval(getFieldById('software', $software_id, 'software_vendor_id'));
                logAudit("Vendor", "Create", "$session_name created vendor " . escapeSql((string) getFieldById('vendors', $vendor_id, 'vendor_name')) . " from a template for license " . escapeSql($license_name), $client_id, $vendor_id);
                $made['vendors']++;
            }
            logAudit("Software", "Create", "$session_name created license " . escapeSql($license_name) . " from its template, onboarding the client", $client_id, $software_id);
            $made['licenses']++;
        }
    }

    $parts = [];
    foreach (['domains' => 'domain', 'vendors' => 'vendor', 'licenses' => 'license'] as $key => $word) {
        if ($made[$key]) {
            $parts[] = '<strong>' . $made[$key] . '</strong> ' . $word . ($made[$key] === 1 ? '' : 's');
        }
    }
    $last = array_pop($parts);
    flashAlert($last === null ? 'Nothing new to add - IT basics saved' : 'Added ' . ($parts ? implode(', ', $parts) . ' and ' : '') . $last);
    clientOnboardingSaved($client_id, 'it');

}

if (isset($_POST['client_onboarding_kickoff'])) {

    validateCSRFToken();

    $client = clientOnboardingPostClient();
    $client_id = intval($client['client_id']);
    if (intval($config_module_enable_ticketing) !== 1 || lookupUserPermission('module_support') < 2) {
        flashAlert('Starting a project needs Support at Modify, with ticketing on.', 'error');
        redirect(clientOnboardingUrl($client_id, 'kickoff'));
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $start = projectDateSql($_POST['start'] ?? '');
    $due = projectDateSql($_POST['due'] ?? '');
    $errors = [];
    if ($name === '') {
        $errors['name'] = 'A project needs a name.';
    }
    if ($start !== 'NULL' && $due !== 'NULL' && $due < $start) {
        $errors['due'] = 'The due date is before the start.';
    }
    if ($errors) {
        settingsRefuse('client_onboarding_kickoff', $errors, clientOnboardingUrl($client_id, 'kickoff'));
    }
    $template_id = intval($_POST['project_template_id'] ?? 0);
    if ($template_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM project_templates WHERE project_template_id = $template_id AND project_template_archived_at IS NULL"))) {
        $template_id = 0;
    }
    $manager = ticketAgentId($_POST['manager'] ?? 0);
    $contact = projectContactId($_POST['contact_id'] ?? 0, $client_id);
    $name_sql = escapeSql(mb_substr($name, 0, 255));

    $project_number = projectNextNumber();
    mysqli_query($mysqli, "INSERT INTO projects SET project_prefix = '" . escapeSql($config_project_prefix) . "', project_number = $project_number, project_name = '$name_sql',
        project_description = '', project_start = $start, project_due = $due, project_budget_hours = NULL,
        project_manager = $manager, project_contact_id = $contact, project_client_id = $client_id");
    $project_id = intval(mysqli_insert_id($mysqli));
    $row = projectRow($project_id);
    $raised = $template_id ? projectApplyTemplate($row, $template_id, !empty($_POST['assign_to_manager']) ? $manager : 0) : 0;
    projectNotifyManager($row, $manager);
    logAudit("Project", "Create", "$session_name created project " . escapeSql(projectReference($row)) . " - $name_sql, onboarding the client", $client_id, $project_id);
    clientOnboardingMade($client_id, 'project', $project_id);

    flashAlert("Project <strong>" . escapeHtml(projectReference($row) . ' - ' . $row['project_name']) . "</strong> started" . ($raised ? " with <strong>$raised</strong> ticket" . ($raised === 1 ? '' : 's') . " from the template" : ''));
    clientOnboardingSaved($client_id, 'kickoff');

}

if (isset($_POST['client_onboarding_skip'])) {

    validateCSRFToken();

    $client = clientOnboardingPostClient();
    $client_id = intval($client['client_id']);
    $step = (string) $_POST['client_onboarding_skip'];
    $steps = clientOnboardingSteps($client_id);
    if (!isset($steps[$step]) || $step === 'company') {
        redirect(clientOnboardingUrl($client_id));
    }
    clientOnboardingMark($client_id, $step, 'skipped');
    redirect(clientOnboardingUrl($client_id, clientOnboardingAfter($step, $steps)));

}

if (isset($_POST['client_onboarding_finish'])) {

    validateCSRFToken();

    $client = clientOnboardingPostClient();
    $client_id = intval($client['client_id']);
    if (clientOnboardingFinish($client_id)) {
        logAudit("Client", "Finish", "$session_name finished onboarding client " . escapeSql($client['client_name']), $client_id, $client_id);
    }
    flashAlert("Onboarding <strong>" . escapeHtml($client['client_name']) . "</strong> finished - it is in the client's menu if anything is left to do");
    redirect("/agent/client_overview.php?client_id=$client_id");

}

if (isset($_POST['client_onboarding_dismiss'])) {

    // The client overview's card closed - it doesn't come back; the wizard is still in the client's menu
    validateCSRFToken();

    $client = clientOnboardingPostClient();
    clientOnboardingDismiss(intval($client['client_id']));
    redirect("/agent/client_overview.php?client_id=" . intval($client['client_id']));

}
