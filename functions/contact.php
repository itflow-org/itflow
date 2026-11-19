<?php

/*
 * Contacts - how a contact is shown: their picture or initials, their flags
 * (Primary, Important, Billing, Technical, portal access), their phone
 * numbers, and the quick picker for their location. The contacts list, the
 * contact page and the quick look are built from these, and so is every
 * other place a contact turns up - a ticket, an asset, a client, the pickers
 * (selectOptionContact() in functions/format.php) and the search - so a
 * person looks the same everywhere.
 *
 * Everything here takes raw values from the database and escapes what it
 * prints.
 */

/** The colour behind a contact's initials - picked by id, so it is the same on every page and in every picker. */
function contactAvatarTone($contact_id) {
    return clientInitialsTone($contact_id);
}

/**
 * Their photo, or their initials on a soft colour that stays the same for
 * them (like the client avatars on the clients list). $size is '' (2.5rem),
 * 'xs' (in front of a name in a line of text or a pill), 'sm', 'md' (beside
 * a ticket reply, the size of the agents' pictures there) or 'lg'.
 * Takes values raw or already escaped.
 */
function contactAvatar($client_id, $contact_id, $name, $photo, $size = '') {
    $class = 'itflow-contact-avatar' . ($size ? " itflow-contact-avatar-$size" : '');
    $photo = html_entity_decode((string) $photo, ENT_QUOTES, 'UTF-8');
    if ($photo !== '') {
        return '<img class="' . $class . '" src="../uploads/clients/' . intval($client_id) . '/' . escapeHtml(rawurlencode($photo)) . '" alt="" loading="lazy">';
    }
    $tone = contactAvatarTone($contact_id);
    return '<span class="' . $class . ' bg-' . $tone . '-subtle text-' . $tone . '-emphasis" aria-hidden="true">' . escapeHtml(personInitials($name) ?: '?') . '</span>';
}

/**
 * A contact wherever a page mentions one: their avatar, then their name,
 * linked to their quick look - or to their page with 'page' => true.
 * Other options: 'size' of the avatar ('xs' unless given), 'class' on the
 * link, 'archived' to strike the name through.
 * Takes values raw or already escaped.
 */
function contactLink($client_id, $contact_id, $name, $photo = '', $options = []) {
    $client_id = intval($client_id);
    $contact_id = intval($contact_id);
    $name = html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8');
    $label = escapeHtml($name !== '' ? $name : 'Unnamed contact');
    $archived = !empty($options['archived']);
    $class = trim('text-truncate ' . ($options['class'] ?? '') . ($archived ? ' text-decoration-line-through text-body-secondary' : ''));
    $title = $archived ? ' title="Archived contact"' : '';
    $link = !empty($options['page'])
        ? "<a class=\"$class\" href=\"contact.php?client_id=$client_id&amp;contact_id=$contact_id\"$title>$label</a>"
        : "<a class=\"ajax-modal $class\" href=\"#\" data-modal-size=\"lg\" data-modal-url=\"modals/contact/contact.php?id=$contact_id\"$title>$label</a>";
    return '<span class="itflow-contact-ref">' . contactAvatar($client_id, $contact_id, $name, $photo, $options['size'] ?? 'xs') . $link . '</span>';
}

/** The quieter line under a contact in a picker: their title, and Primary or Technical. */
function contactOptionDescription($row) {
    $role = !empty($row['contact_primary']) ? 'Primary' : (!empty($row['contact_technical']) ? 'Technical' : '');
    return implode(' · ', array_filter([trim(html_entity_decode((string) ($row['contact_title'] ?? ''), ENT_QUOTES, 'UTF-8')), $role]));
}

/** For JSON (pickers, search): what contactAvatar() draws - the photo's URL, or their initials and colour. */
function contactAvatarData($client_id, $contact_id, $name, $photo) {
    $photo = html_entity_decode((string) $photo, ENT_QUOTES, 'UTF-8');
    if ($photo !== '') {
        return ['image' => '/uploads/clients/' . intval($client_id) . '/' . rawurlencode($photo)];
    }
    return ['initials' => personInitials($name) ?: '?', 'tone' => contactAvatarTone($contact_id)];
}

/**
 * How they log in to the client portal, or '' with no access: 'Password'
 * or 'Microsoft'.
 */
function contactPortalAccess($contact_user_id, $auth_method) {
    if (!intval($contact_user_id) || (string) $auth_method === '') {
        return '';
    }
    return $auth_method === 'azure' ? 'Microsoft' : 'Password';
}

/**
 * Their flags as pills: Primary (or Important when not primary - a primary
 * contact is always important), Billing, Technical, and Portal when they can
 * log in to the client portal.
 */
function contactFlagPills($row, $with_portal = true) {
    $pills = [];
    if (!empty($row['contact_primary'])) {
        $pills[] = ['Primary', 'text-bg-success', 'The client\'s main contact'];
    } elseif (!empty($row['contact_important'])) {
        $pills[] = ['Important', 'bg-warning-subtle text-warning-emphasis', 'Pinned to the top of the list'];
    }
    if (!empty($row['contact_billing'])) {
        $pills[] = ['Billing', 'bg-secondary-subtle text-secondary-emphasis', 'Receives invoices'];
    }
    if (!empty($row['contact_technical'])) {
        $pills[] = ['Technical', 'bg-secondary-subtle text-secondary-emphasis', 'Technical contact'];
    }
    if ($with_portal) {
        $portal = contactPortalAccess($row['contact_user_id'] ?? 0, $row['user_auth_method'] ?? '');
        if ($portal) {
            $pills[] = ['Portal', 'bg-info-subtle text-info-emphasis', "Client portal login ($portal)"];
        }
    }
    $html = '';
    foreach ($pills as [$label, $class, $title]) {
        $html .= '<span class="badge rounded-pill fw-normal ' . $class . '" title="' . escapeHtml($title) . '">' . $label . '</span> ';
    }
    return rtrim($html);
}

/** A phone number as a tel: link, with its extension. '' when there is no number. */
function contactPhoneLink($phone, $country_code, $extension = '', $class = '') {
    $phone = (string) $phone;
    if ($phone === '') {
        return '';
    }
    $display = escapeHtml(formatPhoneNumber($phone, $country_code));
    $dial = escapeHtml(phoneDialNumber($phone, $country_code));
    $extension = escapeHtml($extension);
    return '<a class="' . $class . '" href="tel:' . $dial . ($extension ? ",$extension" : '') . '">' . $display . '</a>'
        . ($extension ? ' <span class="text-body-secondary">x' . $extension . '</span>' : '');
}

/*
 * Quick picker (js/quick_pick.js) for a contact's location - the same picker
 * the assets use, with the contact's client as the scope.
 */

/** The contact and their location, by id. */
function contactQuickRow($contact_id) {
    global $mysqli;
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_client_id, contact_location_id,
            location_name, location_archived_at
        FROM contacts
        LEFT JOIN locations ON location_id = contact_location_id
        WHERE contact_id = " . intval($contact_id))) ?: null;
}

/** What the location picker shows - the asset location pill, fed the contact's row. */
function contactLocationHtml($row, $style = 'list') {
    return assetQuickHtml('asset_location', [
        'asset_client_id' => $row['contact_client_id'] ?? 0,
        'asset_location_id' => $row['contact_location_id'] ?? 0,
        'location_name' => $row['location_name'] ?? '',
        'location_archived_at' => $row['location_archived_at'] ?? null,
    ], $style);
}

/** Move a contact to one of their client's locations (0 for none), as Edit Contact would. Returns [ok, plain-text message]. */
function contactQuickSetLocation($contact_id, $value) {
    global $mysqli, $session_name;

    $row = contactQuickRow($contact_id);
    if (!$row) {
        return [false, 'That contact no longer exists.'];
    }
    $contact_id = intval($row['contact_id']);
    $client_id = intval($row['contact_client_id']);
    $location_id = assetClientOwnedId('location', $value, $client_id);
    if (intval($value) && !$location_id) {
        return [false, 'That location is not one of this client\'s.'];
    }
    if ($location_id === intval($row['contact_location_id'])) {
        return [true, ''];
    }
    $location_name = $location_id ? (string) mysqli_fetch_row(mysqli_query($mysqli, "SELECT location_name FROM locations WHERE location_id = $location_id"))[0] : '';
    mysqli_query($mysqli, "UPDATE contacts SET contact_location_id = $location_id WHERE contact_id = $contact_id");

    $contact_name = escapeSql($row['contact_name']);
    logAudit("Contact", "Edit", $location_id
        ? "$session_name set the location of contact $contact_name to " . escapeSql($location_name)
        : "$session_name removed the location of contact $contact_name", $client_id, $contact_id);

    return [true, $location_id ? "Moved to $location_name" : 'Location removed'];
}

/** What quick_pick.js redraws after a change. */
function contactQuickRecord($contact_id) {
    $row = contactQuickRow($contact_id);
    return [
        'current' => (string) intval($row['contact_location_id']),
        'html' => ['list' => contactLocationHtml($row, 'list'), 'detail' => contactLocationHtml($row, 'detail')],
    ];
}

/**
 * The + in a picker's input group that adds a contact, location or vendor there and then
 * (js/quick_add.js, agent/ajax.php quick_add_record) - '' for an agent who can't add them.
 * $client_id when the window's client is fixed; otherwise the form's client_id is used. Styled
 * like the + beside a category picker (categoryAddButtonHtml()) - $class to match it where it differs.
 */
function quickAddButton($type, $client_id = 0, $class = 'btn btn-outline-secondary') {
    if (lookupUserPermission('module_client') < 2) {
        return '';
    }
    $titles = ['contact' => 'New contact', 'location' => 'New location', 'vendor' => 'New vendor'];
    return '<button type="button" class="' . escapeHtml($class) . '" data-quick-add="' . $type . '"'
        . (intval($client_id) ? ' data-quick-add-client="' . intval($client_id) . '"' : '')
        . ' data-csrf="' . escapeHtml($_SESSION['csrf_token'] ?? '') . '" title="' . $titles[$type] . '"><i class="fas fa-fw fa-plus"></i></button>';
}

/**
 * A contact as a vCard (3.0) - for a phone's contacts, by QR code or as a .vcf
 * (modals/contact/contact_vcard.php, download_contact_vcard). Their name, the client as the
 * company, title and department, work phone (with any extension after a pause), mobile, email,
 * and the address of their location.
 */
function contactVcard($contact_id) {
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_title, contact_department, contact_email,
            contact_phone, contact_phone_country_code, contact_extension, contact_mobile, contact_mobile_country_code,
            client_name, location_address, location_city, location_state, location_zip, location_country
        FROM contacts
        LEFT JOIN clients ON client_id = contact_client_id
        LEFT JOIN locations ON location_id = contact_location_id
        WHERE contact_id = " . intval($contact_id)));
    if (!$row) {
        return '';
    }
    $esc = function ($value) {
        return str_replace(["\\", "\r\n", "\n", ',', ';'], ["\\\\", '\n', '\n', '\,', '\;'], trim((string) $value));
    };
    $name = trim((string) $row['contact_name']);
    $parts = preg_split('/\s+/u', $name);
    $family = count($parts) > 1 ? array_pop($parts) : '';
    $lines = ['BEGIN:VCARD', 'VERSION:3.0', 'FN:' . $esc($name), 'N:' . $esc($family) . ';' . $esc(implode(' ', $parts)) . ';;;'];
    if ($row['client_name']) {
        $lines[] = 'ORG:' . $esc($row['client_name']) . ($row['contact_department'] ? ';' . $esc($row['contact_department']) : '');
    }
    if ($row['contact_title']) {
        $lines[] = 'TITLE:' . $esc($row['contact_title']);
    }
    $phone = phoneDialNumber($row['contact_phone'], $row['contact_phone_country_code']);
    if ($phone !== '') {
        $lines[] = 'TEL;TYPE=WORK,VOICE:' . $phone . ($row['contact_extension'] ? ',' . preg_replace('/[^0-9]/', '', $row['contact_extension']) : '');
    }
    $mobile = phoneDialNumber($row['contact_mobile'], $row['contact_mobile_country_code']);
    if ($mobile !== '') {
        $lines[] = 'TEL;TYPE=CELL:' . $mobile;
    }
    if ($row['contact_email']) {
        $lines[] = 'EMAIL;TYPE=INTERNET,WORK:' . $esc($row['contact_email']);
    }
    if ($row['location_address'] || $row['location_city']) {
        $lines[] = 'ADR;TYPE=WORK:;;' . $esc($row['location_address']) . ';' . $esc($row['location_city']) . ';' . $esc($row['location_state']) . ';' . $esc($row['location_zip']) . ';' . $esc($row['location_country']);
    }
    $lines[] = 'END:VCARD';
    return implode("\r\n", $lines) . "\r\n";
}
