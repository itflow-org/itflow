<?php

/*
 * The Link picker (js/link_picker.js) - a Link button opens the picker every
 * field has (js/itflow_select.js) right under it, instead of a window that only
 * held one. What is linked already is ticked; tick to link, untick to unlink,
 * and it is saved as the picker closes (agent/ajax.php link_picker_save).
 *
 * The kinds of link:
 *   - the pairs in LINK_RELATIONS (functions/links.php) - a document's assets,
 *     a license's seats, what a service depends on and the rest. With no $to
 *     (a document's or file's Link) every kind it can be linked to is offered,
 *     in groups - their values "type:id";
 *   - a contact's assets and credentials, and an asset's credentials - the one
 *     person or device each belongs to (asset_contact_id, credential_contact_id,
 *     credential_asset_id). One that is someone else's moves;
 *   - a project's tickets, a service's tickets, a ticket's services, and a
 *     ticket's related tickets.
 * Each goes through the same code as the windows it replaces did.
 */

/** The links that aren't LINK_RELATIONS pairs, "from|to" => the permissions they take (module => level). */
const LINK_PICKER_OWN = [
    'contact|asset' => ['module_client' => 2],
    'contact|credential' => ['module_client' => 2, 'module_credential' => 2],
    'asset|credential' => ['module_support' => 2, 'module_credential' => 2],
    'project|ticket' => ['module_support' => 2],
    'service|ticket' => ['module_support' => 2],
    'ticket|service' => ['module_support' => 2],
    'ticket|ticket' => ['module_support' => 2],
];

/*
 * A Link button: $from and $id are the record, $to what it links ('' for
 * everything it can be linked to, in groups). In a .dropdown-menu, give it the
 * dropdown-item class - the picker opens under the menu's button. One with
 * neither a btn nor a dropdown-item class is a link in the text. $label is
 * plain text.
 */
function linkPickerButton($from, $id, $to, $label, $class = 'btn btn-sm btn-outline-secondary', $icon = 'fas fa-link me-2', $title = '') {
    $is_button = preg_match('/\b(btn|dropdown-item)\b/', $class);
    return '<' . ($is_button ? 'button type="button"' : 'a href="#" role="button"') . ' class="' . escapeHtml($class) . '" data-link-picker data-link-from="' . escapeHtml($from)
        . '" data-link-id="' . intval($id) . '" data-link-to="' . escapeHtml($to) . '" data-csrf="' . escapeHtml($_SESSION['csrf_token'] ?? '') . '"'
        . ($title !== '' ? ' title="' . escapeHtml($title) . '"' : '') . ' aria-expanded="false">'
        . ($icon !== '' ? '<i class="' . escapeHtml($icon) . '"></i>' : '') . escapeHtml($label) . '</' . ($is_button ? 'button' : 'a') . '>';
}

/*
 * The record the links are made from, after the permission and client checks:
 * [row - id, name, client_id - or null, why not]. Sets the global $client_id.
 */
function linkPickerSource($from, $id, $to) {
    global $client_id;
    $from = (string) $from;
    $to = (string) $to;
    $id = intval($id);
    $own = LINK_PICKER_OWN["$from|$to"] ?? null;

    if ($own) {
        foreach ($own as $module => $level) {
            if (lookupUserPermission($module) < $level) {
                return [null, permissionRefusedText($module, $level, intval(lookupUserPermission($module)))];
            }
        }
        $row = linkPickerOwnSource($from, $id);
    } elseif ($to === '') {
        if (!isset(LINK_ENTITIES[$from])) {
            return [null, 'Pick what to link.'];
        }
        $row = linkRecord($from, $id);
        if ($row && !array_filter(linkTypesFor($from), function ($type) use ($from) {
            return linkCanEdit($from, $type);
        })) {
            return [null, 'You do not have permission to change these links.'];
        }
    } else {
        if (!linkRelation($from, $to)) {
            return [null, 'Those two can\'t be linked.'];
        }
        if (!in_array($to, linkTypesFor($from), true) || !linkCanEdit($from, $to)) {
            return [null, 'You do not have permission to change these links.'];
        }
        $row = linkRecord($from, $id);
    }

    if (!$row) {
        return [null, 'That record no longer exists.'];
    }
    if (!empty($row['archived_at'])) {
        return [null, 'It is archived - restore it to link things to it.'];
    }
    $client_id = intval($row['client_id']);
    if ($client_id && !hasClientAccess($client_id)) {
        return [null, "You don't have access to that client."];
    }
    if ($from === 'project' && !projectIsOpen($row['project'])) {
        return [null, 'Project ' . projectReference($row['project']) . ' is ' . (!empty($row['project']['project_archived_at']) ? 'archived' : 'completed') . ' - reopen it first.'];
    }
    if ($from === 'ticket' && $to === 'service' && !$client_id) {
        return [null, 'A ticket with no client has no services to link.'];
    }
    if ($from === 'ticket' && $to === 'ticket' && !ticketLinksReady()) {
        return [null, 'Related tickets need the database update.'];
    }
    return [$row, ''];
}

/** A contact, asset, project, service or ticket as linkPickerSource() needs it - or null. */
function linkPickerOwnSource($from, $id) {
    global $mysqli;
    $id = intval($id);
    switch ($from) {
        case 'contact':
        case 'asset':
            return linkRecord($from, $id);
        case 'project':
            $project = projectRow($id);
            return $project ? ['id' => $id, 'name' => projectReference($project) . ' - ' . $project['project_name'], 'client_id' => intval($project['project_client_id']), 'archived_at' => null, 'project' => $project] : null;
        case 'service':
            $service = serviceRow($id);
            return $service ? ['id' => $id, 'name' => $service['service_name'], 'client_id' => intval($service['service_client_id']), 'archived_at' => $service['service_archived_at'], 'service' => $service] : null;
        case 'ticket':
            $ticket = ticketRow($id);
            return $ticket ? ['id' => $id, 'name' => ticketReference($ticket), 'client_id' => intval($ticket['ticket_client_id']), 'archived_at' => $ticket['ticket_archived_at'] ?? null, 'ticket' => $ticket] : null;
    }
    return null;
}

/** One <option> for the picker - selected when it is linked already. */
function linkPickerOption($value, $label, $linked, $data) {
    return '<option value="' . escapeHtml($value) . '"' . ($linked ? ' selected' : '') . $data . '>' . escapeHtml($label) . '</option>';
}

/*
 * The client's $to records for one record of a LINK_RELATIONS pair, linked
 * or not (linked first) - linkCandidates() with the linked ones in too.
 */
function linkPickerRecords($from, $id, $to, $client_id) {
    global $mysqli;
    $relation = linkRelation($from, $to);
    $from_entity = LINK_ENTITIES[$from];
    $to_entity = LINK_ENTITIES[$to];
    $id = intval($id);
    $client_id = intval($client_id);
    $extra = linkDisplayColumns($to);
    $folder_join = $to === 'file' ? 'LEFT JOIN folders ON folder_id = t.file_folder_id' : '';
    $folder_column = $to === 'file' ? ', folder_name' : '';
    $sql = mysqli_query($mysqli, "SELECT t.{$to_entity['id']} AS id, t.{$to_entity['name']} AS name" . ($extra ? ", $extra" : '') . "$folder_column,
            l.{$to_entity['id']} IS NOT NULL AS linked
        FROM {$to_entity['table']} t
        $folder_join
        LEFT JOIN {$relation['table']} l ON l.{$to_entity['id']} = t.{$to_entity['id']} AND l.{$from_entity['id']} = $id
        WHERE t.{$to_entity['client']} = $client_id AND t.{$to_entity['archived']} IS NULL
        ORDER BY linked DESC, t.{$to_entity['name']} ASC");
    return mysqli_fetch_all($sql, MYSQLI_ASSOC);
}

/*
 * What the picker offers: ['title', 'options' (HTML), 'count' (choices), 'linked']
 * or ['error']. Choices are the client's own and not archived.
 */
function linkPickerOptions($from, $id, $to) {
    global $mysqli;
    [$source, $why_not] = linkPickerSource($from, $id, $to);
    if (!$source) {
        return ['error' => $why_not];
    }
    $client_id = intval($source['client_id']);
    $options = '';
    $count = 0;
    $linked = 0;
    $add = function ($value, $label, $is_linked, $data) use (&$options, &$count, &$linked) {
        $options .= linkPickerOption($value, $label, $is_linked, $data);
        $count++;
        $linked += $is_linked ? 1 : 0;
    };

    switch ("$from|$to") {
        case 'contact|asset':
            // Its own, and those nobody has - one of someone else's is moved on its own page
            $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, asset_contact_id FROM assets
                WHERE asset_client_id = $client_id AND asset_archived_at IS NULL AND asset_contact_id IN (0, " . intval($source['id']) . ")
                ORDER BY asset_contact_id = " . intval($source['id']) . " DESC, asset_name ASC");
            while ($row = mysqli_fetch_assoc($sql)) {
                $add($row['asset_id'], $row['asset_name'], intval($row['asset_contact_id']) === intval($source['id']), selectOptionAsset($row['asset_type'], trim($row['asset_make'] . ' ' . $row['asset_model'])));
            }
            return ['title' => 'Assets of ' . $source['name'], 'options' => $options, 'count' => $count, 'linked' => $linked];

        case 'contact|credential':
        case 'asset|credential':
            // A credential belongs to one person and one device - one of someone else's moves here
            $owner = $from === 'contact' ? 'contact' : 'asset';
            $rows = credentialRows("credential_client_id = $client_id");
            uasort($rows, function ($a, $b) use ($owner, $source) {
                $rank = function ($row) use ($owner, $source) {
                    return intval($row[$owner . '_id']) === intval($source['id']) ? 0 : (empty($row[$owner . '_id']) ? 1 : 2);
                };
                return [$rank($a), strtolower($a['credential_name'])] <=> [$rank($b), strtolower($b['credential_name'])];
            });
            foreach ($rows as $credential_id => $row) {
                $is_linked = intval($row[$owner . '_id']) === intval($source['id']);
                $note = !$is_linked && !empty($row[$owner . '_id'])
                    ? ($owner === 'contact' ? $row['contact_name'] . "'s - moves here" : 'On ' . $row['asset_name'] . ' - moves here')
                    : ($row['credential_description'] ?: $row['credential_uri']);
                $add($credential_id, $row['credential_name'], $is_linked, selectOptionCredential($row, $note));
            }
            return ['title' => 'Credentials of ' . $source['name'], 'options' => $options, 'count' => $count, 'linked' => $linked];

        case 'project|ticket':
            // Its tickets, then its client's open ones and those closed in the last 90 days - any client's for an internal project
            $project_id = intval($source['id']);
            $client_sql = $client_id ? "AND ticket_client_id = $client_id" : clientScopeSql('ticket_client_id');
            $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_resolved_at, ticket_closed_at, ticket_project_id,
                    ticket_status_name, client_name, project_prefix, project_number
                FROM tickets
                LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
                LEFT JOIN clients ON client_id = ticket_client_id
                LEFT JOIN projects ON project_id = ticket_project_id AND ticket_project_id > 0
                WHERE ticket_archived_at IS NULL $client_sql
                AND (ticket_project_id = $project_id OR " . TICKET_OPEN_SQL . " OR COALESCE(ticket_closed_at, ticket_resolved_at) > NOW() - INTERVAL 90 DAY)
                ORDER BY ticket_project_id = $project_id DESC, " . TICKET_OPEN_SQL . " DESC, ticket_number DESC
                LIMIT 600");
            while ($row = mysqli_fetch_assoc($sql)) {
                $on_this = intval($row['ticket_project_id']) === $project_id;
                $elsewhere = !$on_this && intval($row['ticket_project_id']) ? 'On ' . $row['project_prefix'] . $row['project_number'] . ' - moves here' : '';
                $add($row['ticket_id'], ticketReference($row) . ' ' . $row['ticket_subject'], $on_this, selectOptionData([
                    'icon' => 'fas fa-' . (ticketIsOpen($row) ? 'life-ring' : 'check-circle'),
                    'description' => implode(' · ', array_filter([$client_id ? '' : ($row['client_name'] ?? 'Internal'), $row['ticket_status_name'], $elsewhere])),
                    'keywords' => $row['client_name'] ?? '',
                ]));
            }
            return ['title' => 'Tickets on ' . $source['name'], 'options' => $options, 'count' => $count, 'linked' => $linked];

        case 'service|ticket':
            // Its tickets, then the client's open ones and those from the last year
            $service_id = intval($source['id']);
            $sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_created_at, ticket_resolved_at, ticket_closed_at,
                    service_tickets.ticket_id IS NOT NULL AS linked
                FROM tickets
                LEFT JOIN service_tickets ON service_tickets.ticket_id = tickets.ticket_id AND service_tickets.service_id = $service_id
                WHERE ticket_client_id = $client_id AND ticket_archived_at IS NULL
                AND (service_tickets.ticket_id IS NOT NULL OR " . TICKET_OPEN_SQL . " OR ticket_created_at > NOW() - INTERVAL 1 YEAR)
                ORDER BY linked DESC, " . TICKET_OPEN_SQL . " DESC, ticket_number DESC LIMIT 400");
            while ($row = mysqli_fetch_assoc($sql)) {
                $is_open = ticketIsOpen($row);
                $add($row['ticket_id'], $row['ticket_subject'], (bool) $row['linked'], selectOptionData([
                    'icon' => 'fas fa-' . ($is_open ? 'life-ring' : 'check-circle'),
                    'description' => ticketReference($row) . ' · ' . ($is_open ? 'Open' : 'Closed') . ' · ' . date('M j, Y', strtotime($row['ticket_created_at'])),
                    'keywords' => ticketReference($row),
                ]));
            }
            return ['title' => 'Tickets about ' . $source['name'], 'options' => $options, 'count' => $count, 'linked' => $linked];

        case 'ticket|service':
            $ticket_id = intval($source['id']);
            $sql = mysqli_query($mysqli, "SELECT services.service_id, service_name, service_description, service_category, service_importance,
                    service_tickets.service_id IS NOT NULL AS linked
                FROM services
                LEFT JOIN service_tickets ON service_tickets.service_id = services.service_id AND service_tickets.ticket_id = $ticket_id
                WHERE service_client_id = $client_id AND service_archived_at IS NULL
                ORDER BY linked DESC, FIELD(service_importance, 'High', 'Medium', 'Low'), service_name");
            while ($row = mysqli_fetch_assoc($sql)) {
                $add($row['service_id'], $row['service_name'], (bool) $row['linked'], selectOptionService($row));
            }
            return ['title' => 'Services ' . $source['name'] . ' is about', 'options' => $options, 'count' => $count, 'linked' => $linked];

        case 'ticket|ticket':
            // The ones it is related to, then any the agent can open - open, or closed in the last 90 days
            $ticket_id = intval($source['id']);
            $related = ticketRelated($ticket_id);
            foreach (array_merge($related, ticketLinkCandidates($ticket_id)) as $n => $row) {
                $is_open = ticketIsOpen($row);
                $client = $row['client_name'] !== null ? $row['client_name'] : 'Internal';
                $add($row['ticket_id'], ticketReference($row) . ' ' . $row['ticket_subject'], $n < count($related), selectOptionData([
                    'icon' => 'fas fa-life-ring',
                    'description' => $client . ' · ' . ($row['ticket_status_name'] ?? '') . ($is_open ? '' : ' - ' . timeAgo($row['ticket_closed_at'] ?: $row['ticket_resolved_at'])),
                    'keywords' => $client,
                ]));
            }
            return ['title' => 'Related to ' . $source['name'], 'options' => $options, 'count' => $count, 'linked' => $linked];
    }

    // LINK_RELATIONS - one kind, or every kind a document or file can be linked to, in groups
    $types = $to !== '' ? [$to] : array_values(array_filter(linkTypesFor($from), function ($type) use ($from) {
        return linkCanEdit($from, $type);
    }));
    foreach ($types as $type) {
        $group = '';
        foreach (linkPickerRecords($from, $source['id'], $type, $client_id) as $row) {
            $item = linkPickerOption($to !== '' ? $row['id'] : "$type:{$row['id']}", $row['name'], (bool) $row['linked'], linkOptionData($type, $row, $client_id));
            $group .= $item;
            $count++;
            $linked += $row['linked'] ? 1 : 0;
        }
        if ($group !== '') {
            $options .= $to !== '' ? $group : '<optgroup label="' . escapeHtml(LINK_ENTITIES[$type]['plural']) . '">' . $group . '</optgroup>';
        }
    }
    $title = $to !== '' ? LINK_ENTITIES[$to]['plural'] . ' linked to ' . $source['name'] : 'Linked to ' . $source['name'];
    return ['title' => $title, 'options' => $options, 'count' => $count, 'linked' => $linked];
}

/*
 * Link $add and unlink $remove (ids, or "type:id" when $to is ''), as the
 * picker closes. ['message', 'type'] for the message after, or ['error'].
 */
function linkPickerSave($from, $id, $to, array $add, array $remove) {
    global $mysqli, $session_name;
    [$source, $why_not] = linkPickerSource($from, $id, $to);
    if (!$source) {
        return ['error' => $why_not];
    }
    $client_id = intval($source['client_id']);
    $source_id = intval($source['id']);
    $ids = function (array $list) {
        return array_values(array_unique(array_filter(array_map('intval', $list))));
    };
    $linked = [];
    $unlinked = [];
    $warning = '';

    switch ("$from|$to") {
        case 'contact|asset':
            foreach ($ids($add) as $asset_id) {
                $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_name FROM assets WHERE asset_id = $asset_id AND asset_client_id = $client_id AND asset_archived_at IS NULL"));
                if ($row) {
                    mysqli_query($mysqli, "UPDATE assets SET asset_contact_id = $source_id WHERE asset_id = $asset_id");
                    logAudit("Asset", "Link", "$session_name linked asset " . escapeSql($row['asset_name']) . " to contact " . escapeSql($source['name']), $client_id, $asset_id);
                    $linked[] = $row['asset_name'];
                }
            }
            foreach ($ids($remove) as $asset_id) {
                $name = (string) getFieldById('assets', $asset_id, 'asset_name');
                // Only if it is still this contact's - not one given to someone else since
                mysqli_query($mysqli, "UPDATE assets SET asset_contact_id = 0 WHERE asset_id = $asset_id AND asset_contact_id = $source_id");
                if (mysqli_affected_rows($mysqli) > 0) {
                    logAudit("Asset", "Unlink", "$session_name unlinked contact " . escapeSql($source['name']) . " from asset " . escapeSql($name), $client_id, $asset_id);
                    $unlinked[] = $name;
                }
            }
            break;

        case 'contact|credential':
        case 'asset|credential':
            $column = $from === 'contact' ? 'credential_contact_id' : 'credential_asset_id';
            foreach ($ids($add) as $credential_id) {
                $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT credential_name FROM credentials WHERE credential_id = $credential_id AND credential_client_id = $client_id AND credential_archived_at IS NULL"));
                if ($row) {
                    mysqli_query($mysqli, "UPDATE credentials SET $column = $source_id WHERE credential_id = $credential_id");
                    logAudit("Credential", "Link", "$session_name linked credential " . escapeSql($row['credential_name']) . " to $from " . escapeSql($source['name']), $client_id, $credential_id);
                    $linked[] = $row['credential_name'];
                }
            }
            foreach ($ids($remove) as $credential_id) {
                $name = (string) getFieldById('credentials', $credential_id, 'credential_name');
                mysqli_query($mysqli, "UPDATE credentials SET $column = 0 WHERE credential_id = $credential_id AND $column = $source_id");
                if (mysqli_affected_rows($mysqli) > 0) {
                    logAudit("Credential", "Unlink", "$session_name unlinked $from " . escapeSql($source['name']) . " from credential " . escapeSql($name), $client_id, $credential_id);
                    $unlinked[] = $name;
                }
            }
            break;

        case 'project|ticket':
            $project = $source['project'];
            $skipped = [];
            foreach ($ids($add) as $ticket_id) {
                $ticket = ticketRow($ticket_id);
                if (!$ticket || intval($ticket['ticket_project_id']) === $source_id) {
                    continue;
                }
                $why = projectAddTicket($project, $ticket);
                if ($why === '') {
                    $linked[] = ticketReference($ticket);
                } else {
                    $skipped[] = ticketReference($ticket) . " ($why)";
                }
            }
            foreach ($ids($remove) as $ticket_id) {
                $ticket = ticketRow($ticket_id);
                if ($ticket && intval($ticket['ticket_project_id']) === $source_id && (!intval($ticket['ticket_client_id']) || hasClientAccess($ticket['ticket_client_id']))) {
                    projectRemoveTicket($ticket);
                    $unlinked[] = ticketReference($ticket);
                }
            }
            if ($linked || $unlinked) {
                logAudit("Project", "Edit", "$session_name " . implode(' and ', array_filter([
                    $linked ? 'added ' . count($linked) . ' ticket(s) to' : '',
                    $unlinked ? 'took ' . count($unlinked) . ' ticket(s) out of' : '',
                ])) . " project " . escapeSql($source['name']), $client_id, $source_id);
            }
            if ($skipped) {
                $warning = ' - skipped ' . escapeHtml(implode(', ', $skipped));
            }
            break;

        case 'service|ticket':
        case 'ticket|service':
            // service id => ticket ids, either way round
            $pairs = function (array $others) use ($from, $source_id) {
                return array_map(function ($other) use ($from, $source_id) {
                    return $from === 'service' ? [$source_id, $other] : [$other, $source_id];
                }, $others);
            };
            foreach ($pairs($ids($add)) as [$service_id, $ticket_id]) {
                $service = serviceRow($service_id);
                $ticket = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number FROM tickets WHERE ticket_id = " . intval($ticket_id)));
                if ($service && $ticket && intval($service['service_client_id']) === $client_id && !$service['service_archived_at']
                    && serviceLinkTickets($service_id, $client_id, [$ticket_id])) {
                    logAudit("Service", "Link", "$session_name linked ticket " . escapeSql($ticket['ticket_prefix'] . $ticket['ticket_number']) . " to service " . escapeSql($service['service_name']), $client_id, $service_id);
                    $linked[] = $from === 'service' ? $ticket['ticket_prefix'] . $ticket['ticket_number'] : $service['service_name'];
                }
            }
            foreach ($pairs($ids($remove)) as [$service_id, $ticket_id]) {
                mysqli_query($mysqli, "DELETE service_tickets FROM service_tickets JOIN services ON services.service_id = service_tickets.service_id
                    WHERE service_tickets.service_id = " . intval($service_id) . " AND ticket_id = " . intval($ticket_id) . " AND service_client_id = $client_id");
                if (mysqli_affected_rows($mysqli) > 0) {
                    $service_name = (string) getFieldById('services', $service_id, 'service_name');
                    $ticket = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number FROM tickets WHERE ticket_id = " . intval($ticket_id)));
                    $reference = $ticket ? $ticket['ticket_prefix'] . $ticket['ticket_number'] : "#$ticket_id";
                    logAudit("Service", "Unlink", "$session_name unlinked ticket " . escapeSql($reference) . " from service " . escapeSql($service_name), $client_id, $service_id);
                    $unlinked[] = $from === 'service' ? $reference : $service_name;
                }
            }
            break;

        case 'ticket|ticket':
            $ticket = $source['ticket'];
            $join = [$source_id];
            foreach ($ids($add) as $related_id) {
                $related = ticketRow($related_id);
                if ($related && $related_id !== $source_id && (!intval($related['ticket_client_id']) || hasClientAccess($related['ticket_client_id']))) {
                    $join[] = $related_id;
                    $linked[] = ticketReference($related);
                }
            }
            if (count($join) > 1) {
                ticketLinkTogether($join);
                logAudit("Ticket", "Link", "$session_name linked ticket " . escapeSql(ticketReference($ticket)) . " with " . (count($join) - 1) . " other ticket(s)", $client_id, $source_id);
            }
            $group = ticketLinkGroup($source_id);
            foreach ($ids($remove) as $related_id) {
                $related = ticketRow($related_id);
                if ($related && $group && ticketLinkGroup($related_id) === $group && (!intval($related['ticket_client_id']) || hasClientAccess($related['ticket_client_id']))
                    && ticketUnlink($related_id)) {
                    logAudit("Ticket", "Unlink", "$session_name took ticket " . escapeSql(ticketReference($related)) . " out of its related tickets", intval($related['ticket_client_id']), $related_id);
                    $unlinked[] = ticketReference($related);
                }
            }
            break;

        default:
            // LINK_RELATIONS - ids of one kind, or "type:id" for a document's or file's Link
            $by_type = function (array $list) use ($to) {
                $out = [];
                foreach ($list as $value) {
                    [$type, $other] = $to !== '' ? [$to, $value] : array_pad(explode(':', (string) $value, 2), 2, 0);
                    if (intval($other)) {
                        $out[$type][] = intval($other);
                    }
                }
                return $out;
            };
            foreach ($by_type($add) as $type => $others) {
                if ($to === '' && (!in_array($type, linkTypesFor($from), true) || !linkCanEdit($from, $type))) {
                    continue;
                }
                $added = linkAdd($from, $source_id, $type, $others);
                $linked = array_merge($linked, $added);
                if ($added && in_array(linkRelation($from, $type)['table'], ['software_assets', 'software_contacts'], true)) {
                    foreach ($from === 'software' ? [$source_id] : $others as $software_id) {
                        $warning .= softwareSeatWarning($software_id);
                    }
                }
            }
            foreach ($by_type($remove) as $type => $others) {
                if ($to === '' && (!in_array($type, linkTypesFor($from), true) || !linkCanEdit($from, $type))) {
                    continue;
                }
                foreach ($others as $other) {
                    $name = linkRemove($from, $source_id, $type, $other);
                    if ($name !== '') {
                        $unlinked[] = $name;
                    }
                }
            }
    }

    // One message for the lot - what was linked and what was unlinked
    $plural = $to === 'ticket' ? 'tickets' : ($to !== '' ? strtolower(LINK_ENTITIES[$to]['plural']) : 'records');
    $names = function (array $list) use ($plural) {
        return count($list) === 1 ? '<strong>' . escapeHtml($list[0]) . '</strong>' : '<strong>' . count($list) . "</strong> $plural";
    };
    $source_html = '<strong>' . escapeHtml($source['name']) . '</strong>';
    if (!$linked && !$unlinked) {
        return ['message' => 'Nothing changed' . $warning, 'type' => $warning ? 'warning' : 'info'];
    }
    $parts = [];
    if ($linked) {
        $parts[] = (count($linked) === 1 ? '' : 'Linked ') . $names($linked) . (count($linked) === 1 ? ' linked to ' : ' to ') . $source_html;
    }
    if ($unlinked) {
        $parts[] = ($linked ? 'unlinked ' : 'Unlinked ') . $names($unlinked) . ($linked ? '' : ' from ' . $source_html);
    }
    return ['message' => implode(', ', $parts) . $warning, 'type' => $warning ? 'warning' : ($linked ? 'success' : 'error')];
}
