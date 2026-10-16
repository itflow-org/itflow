<?php

/*
 * Credentials - a client's logins: the firewall admin, the Microsoft 365
 * break-glass account, the registrar, the Wi-Fi key.
 *
 * Usernames and passwords are encrypted with the vault key that opens with
 * the agent's session (functions/security.php); nothing here changes that.
 * Passwords and 2FA codes are only ever fetched when asked for, through
 * agent/ajax.php (get_credential_via_id, get_totp_token_via_id), which check
 * the module and the client and log the view - the HTML these helpers draw
 * carries the credential id, never the secret.
 *
 * A credential belongs to one asset and/or one contact (columns on the
 * credential), and is linked to vendors, licenses and services through link
 * tables (functions/links.php).
 */

/** How long a password can go unchanged before it is called stale - the same 90 days the rotation report uses. */
const CREDENTIAL_ROTATE_DAYS = 90;

/** Changed within this many days is fresh; between this and a year, due; over a year, overdue. */
const CREDENTIAL_OVERDUE_DAYS = 365;

/** May this agent do this with credentials? 1 is to see them (and reveal, copy and share), 2 to change them, 3 to delete them. */
function credentialCan($level) {
    return lookupUserPermission('module_credential') >= $level;
}

/** One credential by id with its client, contact and asset - raw, or null. */
function credentialRow($credential_id) {
    global $mysqli;
    $credential_id = intval($credential_id);
    if (!$credential_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT credentials.*, client_name,
            contact_id, contact_name, contact_title, contact_photo, contact_archived_at,
            asset_id, asset_name, asset_type, asset_archived_at
        FROM credentials
        LEFT JOIN clients ON client_id = credential_client_id
        LEFT JOIN contacts ON contact_id = credential_contact_id AND contact_client_id = credential_client_id
        LEFT JOIN assets ON asset_id = credential_asset_id AND asset_client_id = credential_client_id
        WHERE credential_id = $credential_id
        LIMIT 1"));
    return $row ?: null;
}

/**
 * What kind of login it is, from where it opens and what it is called - an
 * icon and a tone for its tile: an admin account, Wi-Fi, a remote desktop,
 * a shell, a database, a mailbox, a web console, or a plain key.
 */
function credentialKind($row) {
    $uri = strtolower(trim((string) ($row['credential_uri'] ?? '')));
    $text = strtolower(($row['credential_name'] ?? '') . ' ' . ($row['credential_description'] ?? ''));
    $kinds = [
        ['/wi-?fi|wireless|ssid|psk/', '', 'fa-wifi', 'wireless'],
        ['/vpn/', '', 'fa-shield-alt', 'firewall'],
        ['/\b(admin|administrator|root|break ?glass|domain admin)\b/', '', 'fa-user-shield', 'firewall'],
        ['/database|\bsql\b|mysql|postgres/', '', 'fa-database', 'virtual'],
        ['/', '#^(rdp|vnc|rustdesk|anydesk|splashtop|connectwise)://#', 'fa-desktop', 'workstation'],
        ['/', '#^(ssh|telnet|sftp)://#', 'fa-terminal', 'server'],
        ['/', '#^(smb|file|dav|webdav|ftp|ftps)://#', 'fa-folder-open', 'peripheral'],
        ['/\b(mail|email|mailbox|365|exchange|workspace)\b/', '', 'fa-envelope', 'phone'],
        ['/', '#^https?://#', 'fa-globe', 'network'],
    ];
    foreach ($kinds as [$name_match, $uri_match, $icon, $tone]) {
        if ($uri_match !== '' && preg_match($uri_match, $uri)) {
            return ['icon' => $icon, 'tone' => $tone];
        }
        if ($uri_match === '' && $name_match !== '/' && preg_match($name_match, $text)) {
            return ['icon' => $icon, 'tone' => $tone];
        }
    }
    return ['icon' => 'fa-key', 'tone' => 'other'];
}

/** The credential's tile - its kind's icon on its tone. $size '' | 'sm' | 'lg'. */
function credentialTile($row, $size = '') {
    $kind = credentialKind($row);
    $size_class = in_array($size, ['sm', 'lg'], true) ? " itflow-asset-icon-$size" : '';
    return '<span class="itflow-asset-icon' . $size_class . ' itflow-asset-tone-' . $kind['tone'] . '" aria-hidden="true"><i class="fas fa-fw ' . $kind['icon'] . '"></i></span>';
}

/**
 * How old the password is: 'fresh' (changed in the last 90 days), 'due'
 * (90 days to a year) or 'overdue' (over a year), with the days and a line
 * to show. Null when it has never been recorded.
 */
function credentialAge($changed_at) {
    $changed_at = trim((string) $changed_at);
    if ($changed_at === '' || strpos($changed_at, '0000-00-00') === 0) {
        return null;
    }
    $changed = strtotime($changed_at);
    if (!$changed) {
        return null;
    }
    $days = max(0, intval(floor((time() - $changed) / 86400)));
    if ($days >= CREDENTIAL_OVERDUE_DAYS) {
        $state = 'overdue';
        $tone = 'danger';
    } elseif ($days >= CREDENTIAL_ROTATE_DAYS) {
        $state = 'due';
        $tone = 'warning';
    } else {
        $state = 'fresh';
        $tone = 'success';
    }
    $text = $days === 0 ? 'Changed today' : ($days === 1 ? 'Changed yesterday' : ($days < 60 ? "Changed $days days ago" : 'Changed ' . timeAgo(date('Y-m-d', $changed))));
    return ['state' => $state, 'tone' => $tone, 'days' => $days, 'text' => $text, 'date' => date('Y-m-d', $changed)];
}

/** The age as a soft pill - green, amber when due for rotation, red when overdue. '' when unknown. */
function credentialAgePill($age, $short = false) {
    if (!$age) {
        return '';
    }
    $tone = $age['tone'];
    $label = $short ? ($age['days'] < 60 ? $age['days'] . 'd' : ($age['days'] < 730 ? round($age['days'] / 30) . 'mo' : round($age['days'] / 365) . 'y')) : $age['text'];
    $title = $age['text'] . ($age['state'] === 'fresh' ? '' : ' - due for rotation');
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal" title="' . escapeHtml($title) . '">'
        . '<i class="fas fa-' . ($age['state'] === 'fresh' ? 'check' : 'history') . ' me-1"></i>' . escapeHtml($label) . '</span>';
}

/**
 * A 2FA seed as it is stored - the base32 secret, uppercase, without spaces
 * or dashes - from what was pasted: the secret as an app shows it ("abcd efgh
 * ...") or a whole otpauth:// link from the QR code. '' for nothing, false
 * when it is not a seed.
 */
function credentialOtpSeed($input) {
    $input = trim((string) $input);
    if ($input === '') {
        return '';
    }
    if (stripos($input, 'otpauth://') === 0) {
        $query = [];
        parse_str((string) parse_url($input, PHP_URL_QUERY), $query);
        $input = (string) ($query['secret'] ?? '');
    }
    $seed = strtoupper(preg_replace('/[\s\-]+/', '', $input));
    $seed = rtrim($seed, '=');
    if ($seed === '' || !preg_match('/^[A-Z2-7]+$/', $seed) || strlen($seed) < 16) {
        return false;
    }
    return $seed;
}

/**
 * The credential fields a New / Edit form posts, as a SQL SET list - the
 * username and password encrypted, the 2FA seed tidied, only the client's
 * own contact and asset. Null with $error set when it cannot be saved: no
 * name, a field too long, a 2FA seed that is not one, or a vault that did
 * not open with this session (encrypting would otherwise write nothing over
 * the password).
 */
function credentialFormSet($post, $client_id, &$error = '') {
    $error = '';
    if (trim((string) ($post['name'] ?? '')) === '') {
        $error = 'A credential needs a name.';
        return null;
    }
    if ($too_long = checkCredentialLengths($post)) {
        $error = "The $too_long is too long to store.";
        return null;
    }
    $otp = credentialOtpSeed($post['otp_secret'] ?? '');
    if ($otp === false) {
        $error = "That 2FA secret is not one - paste the key the site shows under \"can't scan the code\", or the otpauth:// link.";
        return null;
    }
    $username = encryptCredentialEntry(trim((string) ($post['username'] ?? '')));
    $password = encryptCredentialEntry(trim((string) ($post['password'] ?? '')));
    if ($username === false || $password === false) {
        $error = 'Your vault session has expired, so the login could not be encrypted - sign out and back in, then try again.';
        return null;
    }
    $text = function ($field, $length) use ($post) {
        return "'" . escapeSql(mb_substr(trim((string) ($post[$field] ?? '')), 0, $length)) . "'";
    };
    $fields = [
        'credential_name' => $text('name', 200),
        'credential_description' => $text('description', 500),
        'credential_uri' => $text('uri', 500),
        'credential_uri_2' => $text('uri_2', 500),
        'credential_username' => "'" . escapeSql($username) . "'",
        'credential_password' => "'" . escapeSql($password) . "'",
        'credential_otp_secret' => "'" . escapeSql($otp) . "'",
        'credential_note' => "'" . escapeSql(trim((string) ($post['note'] ?? ''))) . "'",
        'credential_favorite' => intval($post['favorite'] ?? 0) ? 1 : 0,
        'credential_contact_id' => assetClientOwnedId('contact', $post['contact'] ?? 0, $client_id),
        'credential_asset_id' => assetClientOwnedId('asset', $post['asset'] ?? 0, $client_id),
    ];
    $set = [];
    foreach ($fields as $column => $value) {
        $set[] = "$column = $value";
    }
    return implode(', ', $set);
}

/** Replace a credential's tags with these - credential tags (type 4) only. */
function credentialSetTags($credential_id, $tag_ids) {
    global $mysqli;
    $credential_id = intval($credential_id);
    mysqli_query($mysqli, "DELETE FROM credential_tags WHERE credential_id = $credential_id");
    $tag_ids = array_values(array_unique(array_filter(array_map('intval', (array) $tag_ids))));
    if (!$tag_ids) {
        return;
    }
    mysqli_query($mysqli, "INSERT IGNORE INTO credential_tags (credential_id, tag_id)
        SELECT $credential_id, tag_id FROM tags WHERE tag_type = 4 AND tag_id IN (" . implode(',', $tag_ids) . ")");
}

/** The username as stored, decrypted - '' when there is none or the vault is closed. */
function credentialUsername($row) {
    $ciphertext = (string) ($row['credential_username'] ?? '');
    if ($ciphertext === '') {
        return '';
    }
    $plain = decryptCredentialEntry($ciphertext);
    return $plain === false ? '' : (string) $plain;
}

/** The username with its copy button, or a dash. Raw in. */
function credentialUsernameHtml($username, $empty = '-') {
    $username = escapeHtml($username);
    if ($username === '') {
        return '<span class="text-body-secondary">' . $empty . '</span>';
    }
    return '<span class="itflow-credential-user text-truncate">' . $username . '</span><button type="button" class="itflow-copy clipboardjs" title="Copy username" data-clipboard-text="' . $username . '"><i class="far fa-copy"></i></button>';
}

/**
 * The password as dots, with an eye that fetches and shows it for a little
 * while and a copy button that fetches and copies it - both logged as a view
 * by agent/ajax.php. Nothing secret is in the page until asked for.
 */
function credentialPasswordHtml($credential_id) {
    $credential_id = intval($credential_id);
    // Copying is the point here, so its copy button shows all the time (.itflow-copy-always)
    return '<span class="itflow-credential-pw itflow-copy-always" data-credential-id="' . $credential_id . '">'
        . '<span class="itflow-credential-pw-mask" aria-hidden="true">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>'
        . '<span class="itflow-credential-pw-value font-monospace" hidden></span>'
        . '<button type="button" class="itflow-credential-reveal" title="Show password" aria-label="Show password" aria-pressed="false"><i class="fas fa-eye"></i></button>'
        . '<button type="button" class="itflow-copy itflow-credential-copy" title="Copy password"><i class="far fa-copy"></i></button>'
        . '</span>';
}

/**
 * The current 2FA code, fetched when clicked, with how long it has left - it
 * follows on to the next code while it is on show, and a second click
 * copies it. '' when the credential has no 2FA.
 */
function credentialOtpHtml($credential_id, $has_otp) {
    if (!$has_otp) {
        return '';
    }
    $credential_id = intval($credential_id);
    return '<button type="button" class="itflow-credential-otp" data-credential-otp="' . $credential_id . '" title="Show the 2FA code">'
        . '<i class="far fa-clock me-1"></i><span class="itflow-credential-otp-code">2FA</span><span class="itflow-credential-otp-time"></span></button>';
}

/** Where the login is used - the link, opening in a new tab, and a copy button. '' without one. Raw in. */
function credentialUriHtml($uri, $max = 34) {
    $uri = trim((string) $uri);
    if ($uri === '') {
        return '';
    }
    $href = escapeUrl($uri);
    $label = escapeHtml(truncate(preg_replace('#^https?://#i', '', $uri), $max));
    return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer" title="' . escapeHtml($uri) . '">' . $label . '<i class="fas fa-external-link-alt ms-1 small"></i></a>'
        . '<button type="button" class="itflow-copy clipboardjs" title="Copy link" data-clipboard-text="' . escapeHtml($uri) . '"><i class="far fa-copy"></i></button>';
}

/** A credential in a picker - its kind's icon and a quieter line under it (functions/format.php selectOptionData). */
function selectOptionCredential($row, $description = '') {
    return selectOptionData(['icon' => 'fas ' . credentialKind($row)['icon'], 'description' => $description, 'keywords' => $row['credential_uri'] ?? '']);
}

/**
 * Credentials to list somewhere - an asset's, a contact's, a vendor's or a
 * license's - each with its client, contact and asset like credentialRow(),
 * starred first then by name, keyed by id. $where is SQL the caller built
 * from intval'd ids. Archived ones are left out unless asked for.
 */
function credentialRows($where, $include_archived = false) {
    global $mysqli;
    $sql = mysqli_query($mysqli, "SELECT credentials.*, client_name,
            contact_id, contact_name, contact_title, contact_photo, contact_archived_at,
            asset_id, asset_name, asset_type, asset_archived_at
        FROM credentials
        LEFT JOIN clients ON client_id = credential_client_id
        LEFT JOIN contacts ON contact_id = credential_contact_id AND contact_client_id = credential_client_id
        LEFT JOIN assets ON asset_id = credential_asset_id AND asset_client_id = credential_client_id
        WHERE ($where)" . ($include_archived ? '' : ' AND credential_archived_at IS NULL') . "
        ORDER BY credential_favorite DESC, credential_name ASC
        LIMIT 500");
    $rows = [];
    while ($sql && $row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['credential_id'])] = $row;
    }
    return $rows;
}

/** The asset and contact a credential belongs to, each as a small link - HTML pieces, maybe none. */
function credentialOwnersHtml($row) {
    $pieces = [];
    if (!empty($row['asset_id'])) {
        $pieces[] = '<span class="itflow-contact-ref"><span class="itflow-asset-icon itflow-asset-icon-sm itflow-vendor-tile-xs itflow-asset-tone-' . assetTypeTone($row['asset_type']) . '" aria-hidden="true"><i class="fas fa-fw fa-' . getAssetIcon($row['asset_type']) . '"></i></span>'
            . '<a class="ajax-modal text-truncate" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?id=' . intval($row['asset_id']) . '">' . escapeHtml($row['asset_name']) . '</a></span>';
    }
    if (!empty($row['contact_id'])) {
        $pieces[] = contactLink(intval($row['credential_client_id']), $row['contact_id'], $row['contact_name'], $row['contact_photo'], ['archived' => !empty($row['contact_archived_at'])]);
    }
    return $pieces;
}

/**
 * Credentials as a table, the same wherever they are listed outside the
 * credentials page - an asset's or contact's tab, a vendor's logins, a
 * license's: the credential (opening its quick look), the login with the
 * password and 2FA code on asking, how old the password is, and the actions.
 * Options: 'for' adds the asset and contact it belongs to under its name
 * (on a vendor's or license's list, where that differs); 'unlink' is a function
 * of the row giving the URL that unlinks it from this page's record ('' for
 * none).
 */
function credentialTableHtml(array $rows, array $options = []) {
    $can_edit = credentialCan(2);
    $can_share = shareCanMake('Credential');
    $show_for = !empty($options['for']);
    $unlink = $options['unlink'] ?? null;
    $csrf = $_SESSION['csrf_token'] ?? '';
    $html = '<div class="table-responsive"><table class="table table-hover align-middle mb-0 itflow-credential-table">'
        . '<thead class="text-nowrap table-light"><tr><th>Credential</th><th>Login</th><th>Changed</th><th></th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $id = intval($row['credential_id']);
        $name = escapeHtml($row['credential_name']);
        $description = escapeHtml($row['credential_description']);
        $archived = !empty($row['credential_archived_at']);
        $quick = "modals/credential/credential.php?id=$id";
        $has_otp = trim((string) $row['credential_otp_secret']) !== '';

        $html .= '<tr><td class="itflow-credential-name-cell"><div class="d-flex align-items-center gap-2">' . credentialTile($row, 'sm') . '<div class="min-w-0">'
            . '<a class="fw-semibold text-body ajax-modal" href="#" data-modal-url="' . $quick . '">' . $name . '</a>'
            . (intval($row['credential_favorite']) ? '<i class="fas fa-star text-warning small ms-1" title="Starred"></i>' : '')
            . ($description !== '' ? '<div class="small text-body-secondary text-truncate itflow-asset-desc" title="' . $description . '">' . $description . '</div>' : '');
        $owners = $show_for ? credentialOwnersHtml($row) : [];
        if ($owners) {
            $html .= '<div class="small d-flex flex-wrap column-gap-3 row-gap-1 mt-1 itflow-credential-owners">' . implode('', $owners) . '</div>';
        }
        $html .= '</div></div></td>';

        $html .= '<td><div class="d-flex align-items-center small"><i class="fas fa-fw fa-user text-body-secondary me-1"></i>' . credentialUsernameHtml(credentialUsername($row)) . '</div>'
            . '<div class="d-flex align-items-center flex-wrap gap-2 mt-1 small">' . credentialPasswordHtml($id) . credentialOtpHtml($id, $has_otp) . '</div>';
        foreach (array_filter([$row['credential_uri'] ?? '', $row['credential_uri_2'] ?? '']) as $uri) {
            $html .= '<div class="small text-nowrap mt-1">' . credentialUriHtml($uri, 34) . '</div>';
        }
        $html .= '</td>';

        $html .= '<td class="text-nowrap">' . (credentialAgePill(credentialAge($row['credential_password_changed_at']), true) ?: '<span class="text-body-secondary">-</span>') . '</td>';

        $actions = '<a class="dropdown-item ajax-modal" href="#" data-modal-url="' . $quick . '"><i class="fas fa-fw fa-eye text-secondary me-2"></i>Quick look</a>';
        if ($can_edit && !$archived) {
            $actions .= '<a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/credential/credential_edit.php?id=' . $id . '"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>'
                . '<a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/credential/credential_rotate.php?id=' . $id . '"><i class="fas fa-fw fa-sync-alt text-secondary me-2"></i>Change password</a>';
        }
        if ($can_share && !$archived) {
            $actions .= '<a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/share/share_add.php?type=Credential&amp;id=' . $id . '"><i class="fas fa-fw fa-share-alt text-secondary me-2"></i>Share</a>';
        }
        $unlink_url = $unlink ? (string) $unlink($row) : '';
        if ($unlink_url !== '' || ($can_edit && !$archived)) {
            $actions .= '<div class="dropdown-divider"></div>';
        }
        if ($unlink_url !== '') {
            $actions .= '<a class="dropdown-item" href="' . escapeHtml($unlink_url) . '"><i class="fas fa-fw fa-unlink text-secondary me-2"></i>Unlink</a>';
        }
        if ($can_edit && !$archived) {
            $actions .= '<a class="dropdown-item text-danger confirm-link" href="post.php?archive_credential=' . $id . '&amp;csrf_token=' . escapeHtml($csrf) . '"><i class="fas fa-fw fa-archive me-2"></i>Archive</a>';
        }
        $html .= '<td class="text-end"><div class="dropdown dropstart"><button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>'
            . '<div class="dropdown-menu">' . $actions . '</div></div></td></tr>';
    }
    return $html . '</tbody></table></div>';
}

/**
 * Credentials as a short list for the quick looks (an asset's, a contact's):
 * each one's tile and name opening its own quick look, the login with the
 * password and 2FA code on asking, and how old the password is. Rows from
 * credentialRows().
 */
function credentialListHtml(array $rows) {
    $html = '<ul class="list-unstyled mb-0 itflow-links">';
    foreach ($rows as $row) {
        $link_row = $row + ['id' => $row['credential_id'], 'name' => $row['credential_name']];
        $html .= '<li class="itflow-link-row">' . linkRowHtml('credential', $link_row, $row['credential_client_id'])
            . credentialAgePill(credentialAge($row['credential_password_changed_at']), true) . '</li>';
    }
    return $html . '</ul>';
}

/**
 * What each credential is linked to beyond its own asset and contact -
 * vendors, licenses and services - for the whole page at once:
 * [credential_id => [['type', 'id', 'name'], ...]].
 */
function credentialLinks(array $credential_ids) {
    global $mysqli;
    $credential_ids = array_values(array_unique(array_filter(array_map('intval', $credential_ids))));
    $links = array_fill_keys($credential_ids, []);
    if (!$credential_ids) {
        return $links;
    }
    $in = implode(',', $credential_ids);
    $sources = [
        'vendor' => "SELECT l.credential_id, v.vendor_id, v.vendor_name FROM vendor_credentials l JOIN vendors v ON v.vendor_id = l.vendor_id AND v.vendor_archived_at IS NULL WHERE l.credential_id IN ($in) ORDER BY v.vendor_name",
        'software' => "SELECT l.credential_id, s.software_id, s.software_name FROM software_credentials l JOIN software s ON s.software_id = l.software_id AND s.software_archived_at IS NULL WHERE l.credential_id IN ($in) ORDER BY s.software_name",
        'service' => "SELECT l.credential_id, s.service_id, s.service_name FROM service_credentials l JOIN services s ON s.service_id = l.service_id AND s.service_archived_at IS NULL WHERE l.credential_id IN ($in) ORDER BY s.service_name",
    ];
    foreach ($sources as $type => $query) {
        $sql = mysqli_query($mysqli, $query);
        while ($row = mysqli_fetch_row($sql)) {
            $links[intval($row[0])][] = [$type, intval($row[1]), $row[2]];
        }
    }
    return $links;
}

/** The share links still open for each credential, for the whole page at once: [credential_id => [share rows]]. */
function credentialShares(array $credential_ids) {
    global $mysqli;
    $credential_ids = array_values(array_unique(array_filter(array_map('intval', $credential_ids))));
    $shares = array_fill_keys($credential_ids, []);
    if (!$credential_ids) {
        return $shares;
    }
    $sql = mysqli_query($mysqli, "SELECT item_id, item_related_id, item_recipient, item_note, item_views, item_view_limit, item_created_at, item_expire_at
        FROM shared_items
        WHERE item_type = 'Credential' AND " . shareOpenSql() . "
        AND item_related_id IN (" . implode(',', $credential_ids) . ")
        ORDER BY item_expire_at ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $shares[intval($row['item_related_id'])][] = $row;
    }
    return $shares;
}

/**
 * Who did what with a credential - its audit log entries (views, 2FA code
 * views, edits, rotations, links, shares), newest first.
 */
function credentialHistory($credential_id, $limit = 50) {
    global $mysqli;
    $credential_id = intval($credential_id);
    $limit = max(1, intval($limit));
    $sql = mysqli_query($mysqli, "SELECT log_action, log_description, log_created_at, user_name FROM logs
        LEFT JOIN users ON user_id = log_user_id
        WHERE log_type = 'Credential' AND log_entity_id = $credential_id
        ORDER BY log_id DESC LIMIT $limit");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** The last time someone looked at the password or the 2FA code, and who - or null. */
function credentialLastViewed($credential_id) {
    global $mysqli;
    $credential_id = intval($credential_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT log_created_at, user_name FROM logs
        LEFT JOIN users ON user_id = log_user_id
        WHERE log_type = 'Credential' AND log_entity_id = $credential_id AND log_action IN ('View', 'View TOTP')
        ORDER BY log_id DESC LIMIT 1"));
    return $row ?: null;
}

/**
 * An asset or contact is going for good - its credentials stay, for no asset
 * or no contact, rather than pointing at an id that no longer exists.
 */
function credentialsForget($kind, $id) {
    global $mysqli;
    $column = ['asset' => 'credential_asset_id', 'contact' => 'credential_contact_id'][$kind] ?? null;
    $id = intval($id);
    if (!$column || !$id) {
        return;
    }
    mysqli_query($mysqli, "UPDATE credentials SET $column = 0 WHERE $column = $id");
}

/** Deleting a credential for good - its open share links go with it. Links and tags cascade. */
function credentialDeleteForGood($credential_id) {
    global $mysqli;
    $credential_id = intval($credential_id);
    mysqli_query($mysqli, "DELETE FROM shared_items WHERE item_type = 'Credential' AND item_related_id = $credential_id");
    mysqli_query($mysqli, "DELETE FROM credentials WHERE credential_id = $credential_id");
}
