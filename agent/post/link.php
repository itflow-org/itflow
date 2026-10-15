<?php

/*
 * ITFlow - links between documents, files and what they are about, and the
 * devices, users, credentials and files on a license.
 *
 * One handler for every pair LINK_RELATIONS knows (functions/links.php):
 * link_items links any number of records at once, unlink_item drops one.
 * The older one-pair-each names (link_asset_to_document, unlink_contact_from_file
 * and so on) are still answered here, so a link built by any page or older
 * modal keeps working - and gets the same checks: the same client, nothing
 * archived, no 500 on a link that already exists.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** Link or unlink, permission and client checks included. Ends the request. */
function linkHandle($action, $from, $id, $to, $ids) {
    global $client_id;

    validateCSRFToken();

    $relation = linkRelation($from, $to);
    $source = linkRecord($from, $id);
    if (!$relation || !$source) {
        flashAlert('That link no longer exists.', 'error');
        redirect();
    }

    enforceUserPermission($relation['module'], 2);

    $client_id = intval($source['client_id']);
    enforceClientAccess();

    $to_entity = LINK_ENTITIES[$to];

    if ($action === 'link') {
        $added = linkAdd($from, $source['id'], $to, $ids);

        // A license that has just gone over its seats says so
        $seat_warning = '';
        if ($added && in_array($relation['table'], ['software_assets', 'software_contacts'], true)) {
            foreach ($from === 'software' ? [$source['id']] : (array) $ids as $software_id) {
                $seat_warning .= softwareSeatWarning($software_id);
            }
        }

        if (!$added) {
            flashAlert("Nothing new to link to <strong>{$source['name']}</strong>.", 'warning');
        } elseif (count($added) === 1) {
            flashAlert(ucfirst($to_entity['label']) . " <strong>{$added[0]}</strong> linked to <strong>{$source['name']}</strong>$seat_warning", $seat_warning ? 'warning' : 'success');
        } else {
            flashAlert("Linked <strong>" . count($added) . "</strong> " . strtolower($to_entity['plural']) . " to <strong>{$source['name']}</strong>$seat_warning", $seat_warning ? 'warning' : 'success');
        }
    } else {
        $removed = linkRemove($from, $source['id'], $to, intval($ids[0] ?? 0));
        if ($removed !== '') {
            flashAlert(ucfirst($to_entity['label']) . " <strong>$removed</strong> unlinked from <strong>{$source['name']}</strong>", 'error');
        }
    }

    redirect();
}

if (isset($_POST['link_items'])) {
    $ids = $_POST['ids'] ?? [];
    linkHandle('link', (string) ($_POST['from'] ?? ''), intval($_POST['id'] ?? 0), (string) ($_POST['to'] ?? ''), is_array($ids) ? $ids : [$ids]);
}

if (isset($_GET['unlink_item'])) {
    linkHandle('unlink', (string) ($_GET['from'] ?? ''), intval($_GET['id'] ?? 0), (string) ($_GET['to'] ?? ''), [intval($_GET['other'] ?? 0)]);
}

// The older names: [from, its id field, to, its id field]
$legacy_links = [
    'asset_to_document' => ['document', 'document_id', 'asset', 'asset_id'],
    'contact_to_document' => ['document', 'document_id', 'contact', 'contact_id'],
    'file_to_document' => ['document', 'document_id', 'file', 'file_id'],
    'software_to_document' => ['document', 'document_id', 'software', 'software_id'],
    'vendor_to_document' => ['document', 'document_id', 'vendor', 'vendor_id'],
    'asset_to_file' => ['file', 'file_id', 'asset', 'asset_id'],
    'contact_to_file' => ['file', 'file_id', 'contact', 'contact_id'],
    'software_to_asset' => ['asset', 'asset_id', 'software', 'software_id'],
    'software_to_contact' => ['contact', 'contact_id', 'software', 'software_id'],
];
foreach ($legacy_links as $legacy => [$from, $from_field, $to, $to_field]) {
    if (isset($_POST["link_$legacy"])) {
        linkHandle('link', $from, intval($_POST[$from_field] ?? 0), $to, [intval($_POST[$to_field] ?? 0)]);
    }
    $unlink = 'unlink_' . str_replace('_to_', '_from_', $legacy);
    if (isset($_GET[$unlink])) {
        linkHandle('unlink', $from, intval($_GET[$from_field] ?? 0), $to, [intval($_GET[$to_field] ?? 0)]);
    }
}
