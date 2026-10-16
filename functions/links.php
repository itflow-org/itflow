<?php

/*
 * Links between documents, files and the things they are about - the assets,
 * contacts, licenses and vendors a document covers, the files attached to a
 * document, the assets and contacts a file belongs to - and the seats on a
 * license (the devices and users on it) and the credentials and files that
 * go with it, the documents and files about a location (a floor plan, a
 * site runbook), and a vendor's portal logins, documents and files (the
 * contract, the escalation sheet), and what a service depends on - its
 * assets, logins, domains, certificates, people, vendors and documents.
 *
 * Every link is a row in a two-column table (asset_documents, document_files,
 * contact_files...) and they all work the same way, so they are described once
 * here and linked, unlinked, listed and offered by the same few functions -
 * one link modal (agent/modals/link/link.php), one handler
 * (agent/post/link.php) and one list (linkGroupsHtml()) on the document page
 * and the file and document quick looks.
 *
 * A type with a 'view' module (credentials) is only offered, listed or
 * linked for an agent who may see that module.
 *
 * A link only ever joins two records of the same client, and never to an
 * archived one; linkAdd() checks both rather than trusting the form.
 */

/** The record types a link can join. */
const LINK_ENTITIES = [
    'asset' => ['table' => 'assets', 'id' => 'asset_id', 'name' => 'asset_name', 'client' => 'asset_client_id', 'archived' => 'asset_archived_at', 'label' => 'asset', 'plural' => 'Assets', 'icon' => 'fa-desktop'],
    'contact' => ['table' => 'contacts', 'id' => 'contact_id', 'name' => 'contact_name', 'client' => 'contact_client_id', 'archived' => 'contact_archived_at', 'label' => 'contact', 'plural' => 'Contacts', 'icon' => 'fa-user'],
    'document' => ['table' => 'documents', 'id' => 'document_id', 'name' => 'document_name', 'client' => 'document_client_id', 'archived' => 'document_archived_at', 'label' => 'document', 'plural' => 'Documents', 'icon' => 'fa-file-alt'],
    'file' => ['table' => 'files', 'id' => 'file_id', 'name' => 'file_name', 'client' => 'file_client_id', 'archived' => 'file_archived_at', 'label' => 'file', 'plural' => 'Files', 'icon' => 'fa-paperclip'],
    'software' => ['table' => 'software', 'id' => 'software_id', 'name' => 'software_name', 'client' => 'software_client_id', 'archived' => 'software_archived_at', 'label' => 'license', 'plural' => 'Licenses', 'icon' => 'fa-cube'],
    'location' => ['table' => 'locations', 'id' => 'location_id', 'name' => 'location_name', 'client' => 'location_client_id', 'archived' => 'location_archived_at', 'label' => 'location', 'plural' => 'Locations', 'icon' => 'fa-map-marker-alt', 'view' => 'module_client'],
    'credential' => ['table' => 'credentials', 'id' => 'credential_id', 'name' => 'credential_name', 'client' => 'credential_client_id', 'archived' => 'credential_archived_at', 'label' => 'credential', 'plural' => 'Credentials', 'icon' => 'fa-key', 'view' => 'module_credential'],
    'vendor' => ['table' => 'vendors', 'id' => 'vendor_id', 'name' => 'vendor_name', 'client' => 'vendor_client_id', 'archived' => 'vendor_archived_at', 'label' => 'vendor', 'plural' => 'Vendors', 'icon' => 'fa-building'],
    'service' => ['table' => 'services', 'id' => 'service_id', 'name' => 'service_name', 'client' => 'service_client_id', 'archived' => 'service_archived_at', 'label' => 'service', 'plural' => 'Services', 'icon' => 'fa-stream'],
    'domain' => ['table' => 'domains', 'id' => 'domain_id', 'name' => 'domain_name', 'client' => 'domain_client_id', 'archived' => 'domain_archived_at', 'label' => 'domain', 'plural' => 'Domains', 'icon' => 'fa-globe'],
    'certificate' => ['table' => 'certificates', 'id' => 'certificate_id', 'name' => 'certificate_name', 'client' => 'certificate_client_id', 'archived' => 'certificate_archived_at', 'label' => 'certificate', 'plural' => 'Certificates', 'icon' => 'fa-lock'],
];

/**
 * Which pairs can be linked, keyed by the two types in alphabetical order, and
 * the permission it takes. Linking a contact to a file has always been a
 * contacts permission, a credential to a license a credentials one;
 * everything else is documentation, a support one. A device or user linked
 * to a license holds a seat on it.
 */
const LINK_RELATIONS = [
    'asset|document' => ['table' => 'asset_documents', 'module' => 'module_support'],
    'contact|document' => ['table' => 'contact_documents', 'module' => 'module_support'],
    'document|file' => ['table' => 'document_files', 'module' => 'module_support'],
    'document|software' => ['table' => 'software_documents', 'module' => 'module_support'],
    'document|vendor' => ['table' => 'vendor_documents', 'module' => 'module_support'],
    'asset|file' => ['table' => 'asset_files', 'module' => 'module_support'],
    'contact|file' => ['table' => 'contact_files', 'module' => 'module_client'],
    'asset|software' => ['table' => 'software_assets', 'module' => 'module_support'],
    'contact|software' => ['table' => 'software_contacts', 'module' => 'module_support'],
    'credential|software' => ['table' => 'software_credentials', 'module' => 'module_credential'],
    'file|software' => ['table' => 'software_files', 'module' => 'module_support'],
    'document|location' => ['table' => 'location_documents', 'module' => 'module_support'],
    'file|location' => ['table' => 'location_files', 'module' => 'module_support'],
    'credential|vendor' => ['table' => 'vendor_credentials', 'module' => 'module_credential'],
    'file|vendor' => ['table' => 'vendor_files', 'module' => 'module_support'],
    // What a service depends on - support documentation, its logins a credentials permission
    'asset|service' => ['table' => 'service_assets', 'module' => 'module_support'],
    'certificate|service' => ['table' => 'service_certificates', 'module' => 'module_support'],
    'contact|service' => ['table' => 'service_contacts', 'module' => 'module_support'],
    'credential|service' => ['table' => 'service_credentials', 'module' => 'module_credential'],
    'document|service' => ['table' => 'service_documents', 'module' => 'module_support'],
    'domain|service' => ['table' => 'service_domains', 'module' => 'module_support'],
    'service|vendor' => ['table' => 'service_vendors', 'module' => 'module_support'],
];

/** The relation between two types, or null when they cannot be linked. */
function linkRelation($a, $b) {
    $pair = [(string) $a, (string) $b];
    sort($pair);
    return LINK_RELATIONS[implode('|', $pair)] ?? null;
}

/**
 * What a $from record can be linked to, in the order the pages list them -
 * a license's seats first - leaving out types this agent may not see.
 */
function linkTypesFor($from) {
    $orders = [
        'software' => ['asset', 'contact', 'credential', 'document', 'file'],
        // A vendor's portal logins first - what is wanted on the phone to them
        'vendor' => ['credential', 'document', 'file', 'service'],
        // What it runs on first, then what it needs to work, then who to call
        'service' => SERVICE_LINK_TYPES,
    ];
    $order = $orders[$from] ?? ['file', 'document', 'asset', 'contact', 'location', 'software', 'credential', 'vendor', 'service'];
    return array_values(array_filter($order, function ($to) use ($from) {
        $view = LINK_ENTITIES[$to]['view'] ?? null;
        return $to !== $from && linkRelation($from, $to) && (!$view || lookupUserPermission($view) >= 1);
    }));
}

/** May this agent change links between these two types? */
function linkCanEdit($from, $to) {
    $relation = linkRelation($from, $to);
    return $relation && lookupUserPermission($relation['module']) >= 2;
}

/** One record's id, name, client and archived date - or null if it does not exist. */
function linkRecord($type, $id) {
    global $mysqli;
    $entity = LINK_ENTITIES[$type] ?? null;
    $id = intval($id);
    if (!$entity || !$id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT {$entity['id']} AS id, {$entity['name']} AS name,
        {$entity['client']} AS client_id, {$entity['archived']} AS archived_at
        FROM {$entity['table']} WHERE {$entity['id']} = $id LIMIT 1"));
    return $row ?: null;
}

/** Extra columns each type brings to a linked row, beyond id and name. */
function linkDisplayColumns($type) {
    return [
        'asset' => 'asset_type, asset_make, asset_model',
        'contact' => 'contact_title, contact_photo, contact_client_id',
        'document' => 'document_description, document_updated_at, document_created_at',
        'file' => 'file_ext, file_size, file_mime_type, file_description',
        'software' => 'software_type, software_version, software_license_type, software_seats, software_expire, ' . softwareSeatsUsedSql('t.software_id') . ' AS seats_used',
        'credential' => 'credential_description, credential_uri, credential_username, credential_otp_secret, credential_favorite, credential_password_changed_at',
        'vendor' => 'vendor_description, vendor_account_number, vendor_phone, vendor_phone_country_code, vendor_extension',
        'location' => 'location_address, location_city, location_state, location_zip, location_primary, location_photo, location_client_id',
        'service' => 'service_description, service_category, service_importance, service_client_id',
        'domain' => 'domain_description, domain_expire, domain_auto_renew',
        'certificate' => 'certificate_domain, certificate_expire, certificate_issued_at, certificate_check_status, certificate_checked_at',
    ][$type] ?? '';
}

/**
 * The $to records linked to one $from record, ready to draw: each row has id
 * and name plus linkDisplayColumns(). Archived records are left out - they
 * come back when they are restored.
 */
function linkedRecords($from, $id, $to) {
    global $mysqli;
    $relation = linkRelation($from, $to);
    $from_entity = LINK_ENTITIES[$from] ?? null;
    $to_entity = LINK_ENTITIES[$to] ?? null;
    if (!$relation || !$from_entity || !$to_entity) {
        return [];
    }
    $id = intval($id);
    $extra = linkDisplayColumns($to);
    $sql = mysqli_query($mysqli, "SELECT t.{$to_entity['id']} AS id, t.{$to_entity['name']} AS name" . ($extra ? ", $extra" : '') . "
        FROM {$relation['table']} l
        JOIN {$to_entity['table']} t ON t.{$to_entity['id']} = l.{$to_entity['id']}
        WHERE l.{$from_entity['id']} = $id AND t.{$to_entity['archived']} IS NULL
        ORDER BY t.{$to_entity['name']} ASC");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** The client's $to records not yet linked to this $from record, and not archived. */
function linkCandidates($from, $id, $to, $client_id) {
    global $mysqli;
    $relation = linkRelation($from, $to);
    $from_entity = LINK_ENTITIES[$from] ?? null;
    $to_entity = LINK_ENTITIES[$to] ?? null;
    if (!$relation || !$from_entity || !$to_entity) {
        return [];
    }
    $id = intval($id);
    $client_id = intval($client_id);
    $extra = linkDisplayColumns($to);
    // Files show their folder, so two files with the same name can be told apart
    $folder_join = $to === 'file' ? 'LEFT JOIN folders ON folder_id = t.file_folder_id' : '';
    $folder_column = $to === 'file' ? ', folder_name' : '';
    $sql = mysqli_query($mysqli, "SELECT t.{$to_entity['id']} AS id, t.{$to_entity['name']} AS name" . ($extra ? ", $extra" : '') . "$folder_column
        FROM {$to_entity['table']} t
        $folder_join
        LEFT JOIN {$relation['table']} l ON l.{$to_entity['id']} = t.{$to_entity['id']} AND l.{$from_entity['id']} = $id
        WHERE t.{$to_entity['client']} = $client_id AND t.{$to_entity['archived']} IS NULL AND l.{$to_entity['id']} IS NULL
        ORDER BY t.{$to_entity['name']} ASC");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** The audit log entry a link change is filed under - the service, document, file or license side of it. */
function linkLogSide($from, $id, $to, $other_id) {
    foreach (['service', 'document', 'file', 'software'] as $side) {
        if ($from === $side) {
            return [ucfirst($side), $id];
        }
        if ($to === $side) {
            return [ucfirst($side), $other_id];
        }
    }
    return [ucfirst($from), $id];
}

/**
 * Link $ids ($to records) to one $from record. Only records of the same
 * client that are not archived are linked; anything else in $ids is skipped,
 * as is anything already linked. The caller has checked the permission and
 * the client access of $from. Returns the names that were linked.
 */
function linkAdd($from, $id, $to, $ids) {
    global $mysqli, $session_name;
    $relation = linkRelation($from, $to);
    $source = linkRecord($from, $id);
    if (!$relation || !$source) {
        return [];
    }
    $from_entity = LINK_ENTITIES[$from];
    $to_entity = LINK_ENTITIES[$to];
    $client_id = intval($source['client_id']);
    $added = [];
    foreach (array_unique(array_map('intval', (array) $ids)) as $other_id) {
        $other = linkRecord($to, $other_id);
        if (!$other || intval($other['client_id']) !== $client_id || $other['archived_at'] !== null) {
            continue;
        }
        // The pair is the table's primary key, so a second link of the same two is a no-op rather than an error
        mysqli_query($mysqli, "INSERT IGNORE INTO {$relation['table']} SET {$from_entity['id']} = " . intval($source['id']) . ", {$to_entity['id']} = $other_id");
        if (mysqli_affected_rows($mysqli) < 1) {
            continue;
        }
        $added[] = $other['name'];
        [$log_type, $log_id] = linkLogSide($from, intval($source['id']), $to, $other_id);
        $log_from = $log_type === ucfirst($from) ? $source['name'] : $other['name'];
        $log_other = $log_type === ucfirst($from) ? $other['name'] : $source['name'];
        $log_other_label = $log_type === ucfirst($from) ? $to_entity['label'] : $from_entity['label'];
        logAudit($log_type, "Link", "$session_name linked $log_other_label " . escapeSql($log_other) . " to " . strtolower($log_type) . " " . escapeSql($log_from), $client_id, $log_id);
    }
    return $added;
}

/** Unlink one $to record from a $from record. Returns its name, or '' if there was no such link. */
function linkRemove($from, $id, $to, $other_id) {
    global $mysqli, $session_name;
    $relation = linkRelation($from, $to);
    $source = linkRecord($from, $id);
    $other = linkRecord($to, $other_id);
    if (!$relation || !$source || !$other) {
        return '';
    }
    $from_entity = LINK_ENTITIES[$from];
    $to_entity = LINK_ENTITIES[$to];
    mysqli_query($mysqli, "DELETE FROM {$relation['table']} WHERE {$from_entity['id']} = " . intval($source['id']) . " AND {$to_entity['id']} = " . intval($other['id']));
    if (mysqli_affected_rows($mysqli) < 1) {
        return '';
    }
    // A device or user off a license gives back any of its keys they had
    foreach (['asset', 'contact'] as $holder) {
        if ([$from, $to] === ['software', $holder]) {
            softwareFreeKeys($source['id'], $holder, $other['id']);
        } elseif ([$from, $to] === [$holder, 'software']) {
            softwareFreeKeys($other['id'], $holder, $source['id']);
        }
    }
    [$log_type, $log_id] = linkLogSide($from, intval($source['id']), $to, intval($other['id']));
    $log_from = $log_type === ucfirst($from) ? $source['name'] : $other['name'];
    $log_other = $log_type === ucfirst($from) ? $other['name'] : $source['name'];
    $log_other_label = $log_type === ucfirst($from) ? $to_entity['label'] : $from_entity['label'];
    logAudit($log_type, "Unlink", "$session_name unlinked $log_other_label " . escapeSql($log_other) . " from " . strtolower($log_type) . " " . escapeSql($log_from), intval($source['client_id']), $log_id);
    return $other['name'];
}

/** Drop every link a record has - for when it is deleted for good. */
function linkRemoveAll($type, $id) {
    global $mysqli;
    $entity = LINK_ENTITIES[$type] ?? null;
    if (!$entity) {
        return;
    }
    $id = intval($id);
    foreach (LINK_RELATIONS as $pair => $relation) {
        if (in_array($type, explode('|', $pair), true)) {
            mysqli_query($mysqli, "DELETE FROM {$relation['table']} WHERE {$entity['id']} = $id");
        }
    }
}

/** A candidate as an option in a picker (itflow_select.js) - its icon or avatar, and a line under it. Rows as linkCandidates() gives them. */
function linkOptionData($to, $row, $client_id) {
    switch ($to) {
        case 'asset':
            return selectOptionAsset($row['asset_type'], trim($row['asset_make'] . ' ' . $row['asset_model']));
        case 'contact':
            return selectOptionContact($row['contact_client_id'], $row['name'], $row['contact_photo'], $row['contact_title'], $row['id']);
        case 'file':
            $info = fileTypeInfo($row['file_ext']);
            return selectOptionData(['icon' => 'fas fa-' . $info['icon'], 'description' => ($row['folder_name'] ?: 'Top level') . ' · ' . formatBytes(intval($row['file_size'])), 'keywords' => $row['file_description']]);
        case 'document':
            return selectOptionData(['icon' => 'fas fa-file-alt', 'description' => $row['document_description'] ?: 'Updated ' . timeAgo($row['document_updated_at'] ?: $row['document_created_at'])]);
        case 'software':
            // How many seats are left, so a full license is seen before it is picked
            $seats = softwareSeats($row['seats_used'], $row['software_seats']);
            $seat_text = $seats['state'] === 'unlimited' ? '' : ($seats['state'] === 'over' || $seats['state'] === 'full'
                ? 'Full - ' . $seats['used'] . ' of ' . $seats['total'] . ' seats'
                : $seats['free'] . ' of ' . $seats['total'] . ' seats free');
            return selectOptionData(['icon' => 'fas ' . softwareTypeStyle($row['software_type'])['icon'], 'description' => implode(' · ', array_filter([$row['software_license_type'], $seat_text, $row['software_version']]))]);
        case 'credential':
            return selectOptionCredential(['credential_name' => $row['name'], 'credential_description' => $row['credential_description'], 'credential_uri' => $row['credential_uri']], $row['credential_description'] ?: $row['credential_uri']);
        case 'location':
            return selectOptionData(['icon' => 'fas fa-' . (intval($row['location_primary']) ? 'building' : 'map-marker-alt'), 'description' => implode(' · ', array_filter([intval($row['location_primary']) ? 'Primary' : '', locationAddressText($row)]))]);
        case 'vendor':
            return selectOptionVendor($row['name'], $row['vendor_description']);
        case 'service':
            return selectOptionService($row);
        case 'domain':
            $expiry = domainExpiry($row['domain_expire'], $row['domain_auto_renew']);
            return selectOptionData(['icon' => 'fas fa-globe', 'description' => implode(' · ', array_filter([$row['domain_description'], $expiry ? $expiry['text'] : '']))]);
        case 'certificate':
            return selectOptionCertificate(['certificate_name' => $row['name']] + $row);
    }
    return '';
}

/**
 * How one linked record is drawn: its icon or avatar, its name linked to where
 * it opens, and a quieter detail. Raw row in, HTML out.
 */
function linkRowHtml($to, $row, $client_id) {
    $id = intval($row['id']);
    $name = escapeHtml($row['name']);
    $extra = '';
    switch ($to) {
        case 'asset':
            $lead = '<span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-' . assetTypeTone($row['asset_type']) . '" aria-hidden="true"><i class="fas fa-fw fa-' . getAssetIcon($row['asset_type']) . '"></i></span>';
            $link = "<a class=\"ajax-modal text-truncate\" href=\"#\" data-modal-size=\"lg\" data-modal-url=\"modals/asset/asset.php?id=$id\">$name</a>";
            $detail = trim($row['asset_make'] . ' ' . $row['asset_model']);
            break;
        case 'contact':
            $lead = contactAvatar($row['contact_client_id'], $id, $row['name'], $row['contact_photo'], 'sm');
            $link = "<a class=\"ajax-modal text-truncate\" href=\"#\" data-modal-size=\"lg\" data-modal-url=\"modals/contact/contact.php?id=$id\">$name</a>";
            $detail = $row['contact_title'];
            break;
        case 'file':
            $lead = fileTypeTile($row['file_ext'], 'sm');
            $link = "<a class=\"ajax-modal text-truncate\" href=\"#\" data-modal-size=\"lg\" data-modal-url=\"modals/file/file.php?id=$id\">$name</a>";
            $detail = fileTypeInfo($row['file_ext'])['label'] . ($row['file_size'] ? ' · ' . formatBytes(intval($row['file_size'])) : '');
            break;
        case 'document':
            $lead = documentTile('sm');
            $link = "<a class=\"text-truncate\" href=\"document.php?client_id=" . intval($client_id) . "&amp;document_id=$id\">$name</a>";
            $detail = 'Updated ' . timeAgo($row['document_updated_at'] ?: $row['document_created_at']);
            break;
        case 'software':
            $lead = softwareTile($row['software_type'], 'sm');
            $link = "<a class=\"ajax-modal text-truncate\" href=\"#\" data-modal-size=\"lg\" data-modal-url=\"modals/software/software.php?id=$id\">$name</a>";
            $seats = softwareSeats($row['seats_used'] ?? 0, $row['software_seats'] ?? 0);
            $renewal = softwareRenewal($row['software_expire'] ?? '');
            $detail = implode(' · ', array_filter([
                $row['software_license_type'] ?? '',
                $seats['state'] === 'unlimited' ? '' : $seats['used'] . ' / ' . $seats['total'] . ' seats',
                $renewal && $renewal['state'] !== 'active' ? $renewal['text'] : '',
            ]));
            break;
        case 'credential':
            $credential = ['credential_name' => $row['name'], 'credential_description' => $row['credential_description'] ?? '', 'credential_uri' => $row['credential_uri'] ?? '', 'credential_username' => $row['credential_username'] ?? ''];
            $lead = credentialTile($credential, 'sm');
            $link = "<a class=\"ajax-modal text-truncate\" href=\"#\" data-modal-url=\"modals/credential/credential.php?id=$id\">$name</a>"
                . (intval($row['credential_favorite'] ?? 0) ? '<i class="fas fa-star text-warning small ms-1 align-self-center" title="Starred"></i>' : '');
            $detail = $row['credential_description'] ?: $row['credential_uri'];
            // The login itself - username to copy, the password and 2FA code on asking
            $username = credentialUsername($credential);
            $extra = '<div class="small d-flex flex-wrap align-items-center column-gap-3 row-gap-1 mt-1">'
                . ($username !== '' ? '<span class="d-inline-flex align-items-center min-w-0"><i class="fas fa-fw fa-user text-body-secondary me-1"></i>' . credentialUsernameHtml($username) . '</span>' : '')
                . credentialPasswordHtml($id) . credentialOtpHtml($id, trim((string) ($row['credential_otp_secret'] ?? '')) !== '')
                . '</div>';
            break;
        case 'location':
            $lead = locationTile($row, 'sm');
            $link = "<a class=\"ajax-modal text-truncate\" href=\"#\" data-modal-size=\"lg\" data-modal-url=\"modals/location/location.php?id=$id\">$name</a>";
            $detail = locationAddressText($row);
            break;
        case 'vendor':
            $lead = vendorTile($row['name'], 'sm');
            $link = "<a class=\"ajax-modal text-truncate\" href=\"#\" data-modal-size=\"lg\" data-modal-url=\"modals/vendor/vendor.php?id=$id\">$name</a>";
            $detail = implode(' · ', array_filter([$row['vendor_description'], $row['vendor_phone'] ? formatPhoneNumber($row['vendor_phone'], $row['vendor_phone_country_code']) : '']));
            break;
        case 'service':
            $lead = serviceTile($row['service_category'] ?? '', 'sm');
            $link = '<a class="text-truncate" href="' . escapeHtml(serviceUrl($id, $row['service_client_id'] ?? $client_id)) . "\">$name</a>";
            $detail = implode(' · ', array_filter([$row['service_category'] ?? '', ($row['service_importance'] ?? '') ? $row['service_importance'] . ' importance' : '']));
            break;
        case 'domain':
            $lead = domainTile('sm');
            $link = "<a class=\"ajax-modal text-truncate\" href=\"#\" data-modal-size=\"lg\" data-modal-url=\"modals/domain/domain.php?id=$id\">$name</a>";
            $expiry = domainExpiry($row['domain_expire'] ?? '', $row['domain_auto_renew'] ?? 0);
            $detail = '';
            $extra = '<div class="mt-1">' . domainExpiryPill($expiry, true) . '</div>';
            break;
        case 'certificate':
            $lead = certificateTile('sm');
            $link = "<a class=\"ajax-modal text-truncate\" href=\"#\" data-modal-size=\"lg\" data-modal-url=\"modals/certificate/certificate.php?id=$id\">$name</a>";
            $detail = ($row['certificate_domain'] ?? '') !== $row['name'] ? ($row['certificate_domain'] ?? '') : '';
            $problem = !in_array($row['certificate_check_status'] ?? '', [null, '', 'ok'], true);
            $extra = '<div class="mt-1 d-flex flex-wrap gap-1">' . certificateExpiryPill(certificateExpiry($row), true) . ($problem ? certificateStatusPill($row) : '') . '</div>';
            break;
        default:
            return '';
    }
    $detail = (string) $detail !== '' ? '<div class="small text-body-secondary text-truncate">' . escapeHtml($detail) . '</div>' : '';
    return "$lead<div class=\"min-w-0 flex-grow-1\"><div class=\"d-flex\">$link</div>$detail$extra</div>";
}

/**
 * Everything linked to one document or file, grouped by type - the "Linked"
 * card on the document page and the section in the file and document quick
 * looks. Each group has its count and, for an agent who may change it, a
 * Link button and an unlink button on each row (shown on hover, like the copy
 * buttons). Empty groups collapse to a single "Link a ..." line, so the card
 * says what can be linked without a wall of empty headings.
 */
function linkGroupsHtml($from, $id, $client_id, $archived = false) {
    $id = intval($id);
    $groups = '';
    $empty = [];
    foreach (linkTypesFor($from) as $to) {
        $entity = LINK_ENTITIES[$to];
        $rows = linkedRecords($from, $id, $to);
        $can_edit = !$archived && linkCanEdit($from, $to);
        $link_url = "modals/link/link.php?from=$from&amp;id=$id&amp;to=$to";
        if (!$rows) {
            if ($can_edit) {
                $empty[] = "<a class=\"ajax-modal btn btn-xs btn-outline-secondary\" href=\"#\" data-modal-url=\"$link_url\"><i class=\"fas {$entity['icon']} me-1\"></i>{$entity['plural']}</a>";
            }
            continue;
        }
        $items = '';
        foreach ($rows as $row) {
            $unlink = $can_edit
                ? '<a class="itflow-link-remove confirm-link" href="post.php?unlink_item&amp;from=' . $from . '&amp;id=' . $id . '&amp;to=' . $to . '&amp;other=' . intval($row['id']) . '&amp;csrf_token=' . escapeHtml($_SESSION['csrf_token'] ?? '') . '" title="Unlink ' . $entity['label'] . '"><i class="fas fa-unlink"></i></a>'
                : '';
            $items .= '<li class="itflow-link-row">' . linkRowHtml($to, $row, $client_id) . $unlink . '</li>';
        }
        $add = $can_edit ? "<a class=\"ajax-modal ms-auto small\" href=\"#\" data-modal-url=\"$link_url\" title=\"Link {$entity['label']}\"><i class=\"fas fa-plus me-1\"></i>Link</a>" : '';
        $groups .= "<div class=\"itflow-link-group\"><div class=\"itflow-link-group-head\"><i class=\"fas fa-fw {$entity['icon']} me-1\"></i>{$entity['plural']}<span class=\"badge rounded-pill ms-1\">" . count($rows) . "</span>$add</div><ul class=\"list-unstyled mb-0\">$items</ul></div>";
    }
    if ($empty) {
        $groups .= '<div class="itflow-link-empty"><span class="small text-body-secondary me-1">Link</span>' . implode(' ', $empty) . '</div>';
    }
    if ($groups === '') {
        $groups = '<div class="small text-body-secondary">Nothing linked.</div>';
    }
    return "<div class=\"itflow-links\">$groups</div>";
}
