<?php

/*
 * ITFlow - offices: the other places the company works from (database 3.9.0).
 * The main office is the company itself, as Admin > Settings > Company Details
 * has it (office 0); the others are the offices table, listed and changed in
 * the Offices section of that page (admin/modals/office/office.php, handled in
 * admin/post/office.php). Contact details only - name, address, phone and
 * email. The company name, logo, invoices, quotes and contracts stay the main
 * company's.
 *
 * Each agent works from one (users.user_office_id; 0 is the main one), picked
 * in the office's window or in Admin > Users - an invite carries it until it is
 * accepted (user_invites.invite_office_id). Where it shows:
 *   - Trips: a new trip starts From the driver's office, From offers every
 *     office, and on a new trip changing the driver moves an untouched From to
 *     theirs (agent/modals/trip/trip_form.php).
 *   - Ticket emails: the foot shows the address, phone and email of the office
 *     of the agent on the ticket - the one assigned, else the one sending it
 *     ('office' on the email, read by emailHtml in functions/email.php).
 * With no other office, everything is as it was.
 */

/** Whether the 3.9.0 update has run. */
function officesReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.9.0', '>=');
    }
    return $ready;
}

/** What is read once a request - the offices, and who works where. officesForget() after a change. */
function &officesCache() {
    static $cache = [];
    return $cache;
}

function officesForget() {
    $cache = &officesCache();
    $cache = [];
}

/** The other offices - office_id => office, by name. $archived takes in the removed ones. */
function offices($archived = false) {
    global $mysqli;
    $cache = &officesCache();
    if (!isset($cache['offices'])) {
        $cache['offices'] = [];
        if (officesReady()) {
            $sql = mysqli_query($mysqli, "SELECT office_id, office_name, office_address, office_city, office_state, office_zip, office_country,
                office_phone_country_code, office_phone, office_email, office_archived_at FROM offices ORDER BY office_name");
            while ($row = mysqli_fetch_assoc($sql)) {
                $cache['offices'][intval($row['office_id'])] = officeShape($row);
            }
        }
    }
    return $archived ? $cache['offices'] : array_filter($cache['offices'], function ($office) {
        return $office['archived_at'] === null;
    });
}

/** An office in one shape - an offices row, or the company for the main one. */
function officeShape(array $row) {
    $office = [
        'office_id' => intval($row['office_id'] ?? 0),
        'name' => (string) ($row['office_name'] ?? ''),
        'address' => (string) ($row['office_address'] ?? ''),
        'city' => (string) ($row['office_city'] ?? ''),
        'state' => (string) ($row['office_state'] ?? ''),
        'zip' => (string) ($row['office_zip'] ?? ''),
        'country' => (string) ($row['office_country'] ?? ''),
        'phone_country_code' => (string) ($row['office_phone_country_code'] ?? ''),
        'phone' => (string) ($row['office_phone'] ?? ''),
        'email' => (string) ($row['office_email'] ?? ''),
        'archived_at' => $row['office_archived_at'] ?? null,
    ];
    $office['phone_display'] = $office['phone'] !== '' ? formatPhoneNumber($office['phone'], $office['phone_country_code']) : '';
    return $office;
}

/** The main office - the company, as Company Details has it. */
function officeMain() {
    $company = billingCompany();
    return officeShape([
        'office_id' => 0,
        'office_name' => 'Main office',
        'office_address' => $company['company_address'] ?? '',
        'office_city' => $company['company_city'] ?? '',
        'office_state' => $company['company_state'] ?? '',
        'office_zip' => $company['company_zip'] ?? '',
        'office_country' => $company['company_country'] ?? '',
        'office_phone_country_code' => $company['company_phone_country_code'] ?? '',
        'office_phone' => $company['company_phone'] ?? '',
        'office_email' => $company['company_email'] ?? '',
    ]);
}

/** An office by id (0 is the main one) - null when there is none, or it was removed (unless $archived). */
function office($office_id, $archived = false) {
    $office_id = intval($office_id);
    return $office_id === 0 ? officeMain() : (offices($archived)[$office_id] ?? null);
}

/** Every office, the main one first - office_id => office. */
function officesAll() {
    return [0 => officeMain()] + offices();
}

/** An office's address on one line, without the country. */
function officeAddressLine(array $office, $separator = ' ') {
    return formatAddress($office['address'], $office['city'], $office['state'], $office['zip'], '', $separator);
}

/** The office an agent works from - 0 for the main one, and when theirs was removed. */
function officeIdForUser($user_id) {
    global $mysqli;
    $cache = &officesCache();
    if (!isset($cache['users'])) {
        $cache['users'] = [];
        if (officesReady()) {
            $sql = mysqli_query($mysqli, "SELECT user_id, user_office_id FROM users WHERE user_office_id > 0");
            while ($row = mysqli_fetch_row($sql)) {
                $cache['users'][intval($row[0])] = intval($row[1]);
            }
        }
    }
    $office_id = $cache['users'][intval($user_id)] ?? 0;
    return $office_id && isset(offices()[$office_id]) ? $office_id : 0;
}

/** The office an agent works from - the main one when none. */
function officeForUser($user_id) {
    return office(officeIdForUser($user_id)) ?? officeMain();
}

/** An office picked in a form - its id when it is there (0 for the main one), else 0. */
function officeIdFrom($value) {
    $office_id = intval($value);
    return $office_id && isset(offices()[$office_id]) ? $office_id : 0;
}

/** Put an agent in an office (0 is the main one). */
function officeSetUser($user_id, $office_id) {
    global $mysqli;
    $user_id = intval($user_id);
    if (!$user_id || !officesReady()) {
        return;
    }
    mysqli_query($mysqli, "UPDATE users SET user_office_id = " . officeIdFrom($office_id) . " WHERE user_id = $user_id");
    officesForget();
}

/** The agents an office can have - user_id => row (name and avatar): not archived, no API / service accounts. */
function officeAgents() {
    global $mysqli;
    $agents = [];
    $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE user_type = 1 AND user_archived_at IS NULL" . serviceAccountsOutSql('users.user_service_account') . " ORDER BY user_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $agents[intval($row['user_id'])] = $row;
    }
    return $agents;
}

/** Who works where - office_id => [user_id, ...]; those in no other office are under 0. */
function officePeople() {
    $people = [0 => []];
    foreach (array_keys(officeAgents()) as $user_id) {
        $people[officeIdForUser($user_id)][] = $user_id;
    }
    return $people;
}

/*
 * ---------- Admin > Settings > Company Details ----------
 */

/** The Offices section of Company Details - the main office and the others, who works in each, and Add an office. */
function officesSection() {
    if (!officesReady()) {
        return '';
    }
    $agents = officeAgents();
    $people = officePeople();
    ob_start(); ?>
    <?= settingsSectionStart('offices', 'Offices', 'Other places you work from, if you have them. Each agent works from one - their trips start there, and the emails about their tickets show its address and phone. Invoices, quotes and contracts keep the main address.') ?>
        <div class="itflow-settings-field" data-setting="offices" id="setting-offices">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-2" data-offices>
                    <thead>
                        <tr><th>Office</th><th>Who works there</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (officesAll() as $office_id => $office) {
                            $contact = array_filter([officeAddressLine($office, ', '), $office['phone_display'], $office['email']]);
                            $who = array_map(function ($user_id) use ($agents) {
                                return escapeHtml($agents[$user_id]['user_name'] ?? '');
                            }, $people[$office_id] ?? []);
                            $more = count($who) > 8 ? count($who) - 6 : 0; ?>
                            <tr data-office="<?= $office_id ?>">
                                <td>
                                    <div class="fw-semibold">
                                        <?php if ($office_id) { ?>
                                            <a href="#" class="text-body ajax-modal" data-modal-size="lg" data-modal-url="modals/office/office.php?office_id=<?= $office_id ?>"><?= escapeHtml($office['name']) ?></a>
                                        <?php } else { ?>
                                            <?= escapeHtml($office['name']) ?> <span class="badge text-bg-secondary ms-1" title="Its address and phone are the ones above">Above</span>
                                        <?php } ?>
                                    </div>
                                    <div class="small text-body-secondary"><?= $contact ? implode(' &middot; ', array_map('escapeHtml', $contact)) : 'No address yet' ?></div>
                                </td>
                                <td class="small"><?= $who ? implode(', ', $more ? array_slice($who, 0, 6) : $who) . ($more ? " and $more more" : '') : '<span class="text-body-secondary">Nobody</span>' ?></td>
                                <td class="text-end text-nowrap">
                                    <?php if ($office_id) { ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/office/office.php?office_id=<?= $office_id ?>" title="Edit"><i class="fas fa-edit"></i><span class="d-none d-md-inline ms-1">Edit</span></button>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/office/office.php" data-office-add><i class="fas fa-plus me-2"></i>Add an office</button>
            <div class="form-text">Agents not put in another office work from the main one. An agent's office can be changed in Users too.</div>
        </div>
    <?= settingsSectionEnd() ?>
    <?php return ob_get_clean();
}

/** Save an office from its window - [office_id, error]. $post: office_id (0 for a new one), the office_ fields, and office_people. */
function officeSave(array $post) {
    global $mysqli;
    $office_id = intval($post['office_id'] ?? 0);
    if ($office_id && !isset(offices()[$office_id])) {
        return [0, 'That office isn\'t there any more - it may have been removed.'];
    }
    $name = trim((string) ($post['office_name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 100) {
        return [0, 'An office needs a name, up to 100 characters.'];
    }
    $email = trim((string) ($post['office_email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [0, 'That is not an email address.'];
    }
    $country = trim((string) ($post['office_country'] ?? ''));
    if ($country !== '' && !in_array($country, settingsLocalizationArrays()['countries'], true)) {
        return [0, 'Pick the country from the list.'];
    }
    $phone = mb_substr(preg_replace('/[^0-9]/', '', (string) ($post['office_phone'] ?? '')), 0, 200);
    $code = $phone === '' ? '' : mb_substr(preg_replace('/[^0-9]/', '', (string) ($post['office_phone_country_code'] ?? '')), 0, 10);
    $text = function ($key) use ($post) {
        return escapeSql(mb_substr(trim((string) ($post[$key] ?? '')), 0, 200));
    };
    $set = "office_name = '" . escapeSql($name) . "', office_address = '" . $text('office_address') . "', office_city = '" . $text('office_city') . "',
        office_state = '" . $text('office_state') . "', office_zip = '" . $text('office_zip') . "', office_country = '" . escapeSql($country) . "',
        office_phone_country_code = '$code', office_phone = '$phone', office_email = '" . escapeSql($email) . "'";
    if ($office_id) {
        mysqli_query($mysqli, "UPDATE offices SET $set WHERE office_id = $office_id");
    } else {
        mysqli_query($mysqli, "INSERT INTO offices SET $set");
        $office_id = intval(mysqli_insert_id($mysqli));
    }
    officesForget();

    // Who works there: those picked move here, those taken off go back to the main office
    if (isset($post['office_people_shown'])) {
        $picked = array_map('intval', (array) ($post['office_people'] ?? []));
        foreach (officePeople() as $at => $user_ids) {
            foreach ($user_ids as $user_id) {
                if (in_array($user_id, $picked, true)) {
                    if ($at !== $office_id) {
                        officeSetUser($user_id, $office_id);
                    }
                } elseif ($at === $office_id) {
                    officeSetUser($user_id, 0);
                }
            }
        }
    }
    return [$office_id, ''];
}

/** Remove an office - those in it go back to the main one, and invites waiting with it too. Kept, archived, for the record. */
function officeRemove($office_id) {
    global $mysqli;
    $office_id = intval($office_id);
    if (!$office_id || !isset(offices()[$office_id])) {
        return false;
    }
    mysqli_query($mysqli, "UPDATE offices SET office_archived_at = NOW() WHERE office_id = $office_id");
    mysqli_query($mysqli, "UPDATE users SET user_office_id = 0 WHERE user_office_id = $office_id");
    mysqli_query($mysqli, "UPDATE user_invites SET invite_office_id = 0 WHERE invite_office_id = $office_id AND invite_accepted_at IS NULL");
    officesForget();
    return true;
}

/*
 * ---------- Admin > Users ----------
 */

/** The Office picker in the New / Edit user window - only once there is another office. $office_id is theirs. */
function officeUserField($office_id) {
    if (!officesReady() || !offices()) {
        return '';
    }
    $office_id = officeIdFrom($office_id);
    ob_start(); ?>
    <div class="col-12" data-user-office>
        <label class="form-label">Office</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-map-marker-alt"></i></span>
            <select class="form-select select2" name="office_id">
                <?php foreach (officesAll() as $id => $office) { ?>
                    <option value="<?= $id ?>" <?= $id === $office_id ? 'selected' : '' ?>><?= escapeHtml($office['name']) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="form-text">Where they work from - their trips start there, and the emails about their tickets show its address and phone.</div>
    </div>
    <?php return ob_get_clean();
}

/** Save the Office picker for an agent - nothing when the form didn't have it. What changed, for the audit log, or ''. */
function officeUserSave($user_id, array $post) {
    if (!isset($post['office_id']) || !officesReady()) {
        return '';
    }
    $before = officeIdForUser($user_id);
    $after = officeIdFrom($post['office_id']);
    if ($before === $after) {
        return '';
    }
    officeSetUser($user_id, $after);
    return 'office ' . office($after)['name'];
}

/*
 * ---------- Trips ----------
 */

/** Where a new trip by this driver starts - their office's address, '' when they work from the main one. */
function officeTripStart($driver_id) {
    $office_id = officesReady() ? officeIdForUser($driver_id) : 0;
    return $office_id ? officeAddressLine(office($office_id)) : '';
}

/** Every office's address, for the trip form's From list - the main one first. */
function officeTripPlaces() {
    $places = [];
    foreach (officesReady() ? officesAll() : [0 => officeMain()] as $office) {
        $line = officeAddressLine($office);
        if ($line !== '' && !in_array($line, $places, true)) {
            $places[] = $line;
        }
    }
    return $places;
}

/** Each driver's office address - driver id => address - for a new trip's From to follow the driver. Empty with no other office. */
function officeTripStarts(array $driver_ids) {
    if (!officesReady() || !offices()) {
        return [];
    }
    $starts = [];
    foreach ($driver_ids as $driver_id) {
        $starts[intval($driver_id)] = officeAddressLine(officeForUser($driver_id));
    }
    return $starts;
}

/*
 * ---------- Ticket emails ----------
 */

/**
 * The foot of an email about a ticket, when the agent on it works from another
 * office - the one assigned, else the agent sending it: ['address', 'contact'],
 * the office's phone and email standing in for the company's (the company's
 * when it has none). null for the main office - the company's foot then.
 */
function officeTicketFoot($row) {
    global $session_user_id;
    if (!is_array($row) || !officesReady() || !offices()) {
        return null;
    }
    $user_id = intval($row['ticket_assigned_to'] ?? 0) ?: intval($session_user_id ?? 0);
    $office_id = $user_id ? officeIdForUser($user_id) : 0;
    if (!$office_id) {
        return null;
    }
    $office = office($office_id);
    $company = billingCompany();
    return [
        'address' => formatAddress($office['address'], $office['city'], $office['state'], $office['zip'], $office['country'] !== '' ? $office['country'] : (string) ($company['company_country'] ?? ''), ', '),
        'contact' => implode('  ·  ', array_filter([
            $office['phone_display'] !== '' ? $office['phone_display'] : (string) ($company['phone_display'] ?? ''),
            $office['email'] !== '' ? $office['email'] : (string) (ticketEmailFrom()['email'] ?? ''),
            (string) ($company['company_website'] ?? ''),
        ])),
    ];
}
