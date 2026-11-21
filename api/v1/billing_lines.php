<?php
/*
 * API - the lines of an invoice, quote or recurring invoice
 *
 * Included by invoice_items/, quote_items/ and recurring_invoice_items/ after
 * they set $billing_type ('invoice', 'quote' or 'recurring') and $billing_op
 * ('read', 'create', 'update' or 'delete'). A line changes as it does on the
 * document's page (billingItemSave(), billingItemDelete()): the document's
 * total follows, a tracked product's stock moves with an invoice line, and a
 * paid, cancelled or non-billable invoice, or a quote that has been answered
 * or invoiced, keeps its lines as they are.
 *
 * Parameters:
 *   api_key        required - Your API key
 *   item_id        the line - for read (or the document's id), update and delete
 *   invoice_id / quote_id / recurring_invoice_id
 *                  the document - for read (or item_id) and create
 *   client_id      the document's client - needed, as for every write, when the
 *                  key's user is limited to certain clients
 *   name, description, qty, price
 *   tax_id         a tax's id, 0 for none, or 'default' for the client's usual rate
 *   product_id     create only: a product gives the name, description and price not sent
 *   frequency      a quote line: once (left out), month, quarter or year
 *   seat_source    a recurring line: count its quantity from the client - users,
 *                  contacts, assets, managed or license - with seat_tag, seat_types,
 *                  seat_rmm, seat_software_id, seat_count and seat_minimum; none to stop
 */

// Only through an endpoint, after its key is checked (apiDeny() comes with enforce_api_rbac.php)
if (!function_exists('apiDeny') || !in_array($billing_type ?? '', ['invoice', 'quote', 'recurring'], true)) {
    http_response_code(404);
    exit();
}

$billing_t = billingType($billing_type);
$billing_doc_param = ['invoice' => 'invoice_id', 'quote' => 'quote_id', 'recurring' => 'recurring_invoice_id'][$billing_type];
$billing_col = $billing_t['col'];
$billing_label = ['invoice' => 'Invoice', 'quote' => 'Quote', 'recurring' => 'Recurring Invoice'][$billing_type];

// What failed and why, as the document's page would say it
function billingLinesFail($message) {
    global $return_arr;
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = $message;
    echo json_encode($return_arr);
    exit();
}

// --- Read ---
if ($billing_op === 'read') {

    $select = "SELECT li.*, t.tax_name, t.tax_percent
        FROM {$billing_t['items']} li
        INNER JOIN {$billing_t['table']} d ON d.{$billing_col}_id = li.{$billing_t['item_fk']}
        LEFT JOIN taxes t ON t.tax_id = li.item_tax_id";

    if (isset($_GET['item_id'])) {
        $item_id = intval($_GET['item_id']);
        $sql = mysqli_query($mysqli, "$select WHERE li.item_id = $item_id AND 1=1 " . apiClientScopeSql("d.{$billing_col}_client_id") . " LIMIT 1");
    } elseif (isset($_GET[$billing_doc_param])) {
        $doc_id = intval($_GET[$billing_doc_param]);
        $sql = mysqli_query($mysqli, "$select WHERE li.{$billing_t['item_fk']} = $doc_id AND 1=1 " . apiClientScopeSql("d.{$billing_col}_client_id") . "
            ORDER BY li.item_order ASC, li.item_id ASC LIMIT $limit OFFSET $offset");
    } else {
        http_response_code(400);
        echo json_encode(['success' => 'False', 'message' => "A filter is required. Please supply either $billing_doc_param or item_id.", 'count' => 0, 'data' => []]);
        exit;
    }

    require_once __DIR__ . '/read_output.php';
}

// --- Create, update, delete: the document, scoped to the key's user, on the client named ---
if ($billing_op === 'create') {
    $doc_id = intval($_POST[$billing_doc_param] ?? 0);
    $item = null;
} else {
    $item_id = intval($_POST['item_id'] ?? 0);
    $item = $item_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM {$billing_t['items']} WHERE item_id = $item_id LIMIT 1")) : null;
    $doc_id = $item ? intval($item[$billing_t['item_fk']]) : 0;
}

$doc = $doc_id ? billingRow($billing_type, $doc_id) : null;
if ($doc && $client_id != 0 && intval($doc["{$billing_col}_client_id"]) !== $client_id) {
    $doc = null;
}

$insert_id = false;
$update_count = false;
$delete_count = false;

if ($doc) {

    $doc_client_id = intval($doc["{$billing_col}_client_id"]);
    $number = escapeSql(billingNumber($billing_type, $doc));

    if (!billingCanEditItems($billing_type, $doc)) {
        billingLinesFail(billingNumber($billing_type, $doc) . " can't be changed now - " . ($billing_type === 'quote' ? 'it has been answered or invoiced.' : 'it is paid, cancelled or non-billable.'));
    }

    if ($billing_op === 'delete') {

        $deleted = billingItemDelete($billing_type, $doc_id, $item_id);
        if ($deleted) {
            $delete_count = 1;
            $name = escapeSql($deleted['item_name']);
            logAudit($billing_label, "Edit", "Removed line $name from $number via API ($api_key_name)", $doc_client_id, $doc_id);
            logAudit("API", "Success", "Removed line $name from $number via API ($api_key_name)", $doc_client_id);
        }

    } else {

        // The line as it is sent - on an update, what isn't sent stays as the line has it
        if ($billing_op === 'create') {
            $in = billingLineFromApi((array) $_POST, $doc_client_id, $error);
            if (!$in) {
                billingLinesFail($error);
            }
        } else {
            $in = [
                'name' => (string) ($_POST['name'] ?? $item['item_name']),
                'description' => (string) ($_POST['description'] ?? $item['item_description']),
                'qty' => (string) ($_POST['qty'] ?? $item['item_quantity']),
                'price' => (string) ($_POST['price'] ?? $item['item_price']),
                'tax_id' => array_key_exists('tax_id', $_POST) ? ((string) $_POST['tax_id'] === 'default' ? taxDefaultFor($doc_client_id) : intval($_POST['tax_id'])) : intval($item['item_tax_id']),
            ];
            if (billingNumberInput($in['qty']) === null || billingNumberInput($in['qty']) <= 0) {
                billingLinesFail('The quantity has to be a number above 0.');
            }
        }

        // A quote line's frequency, and a recurring line's count of the client's users or devices - only when sent
        if ($billing_type === 'quote' && array_key_exists('frequency', $_POST)) {
            $in['frequency'] = (string) $_POST['frequency'];
        }
        if ($billing_type === 'recurring' && array_key_exists('seat_source', $_POST)) {
            foreach (['seat_source', 'seat_tag', 'seat_types', 'seat_rmm', 'seat_software_id', 'seat_count', 'seat_minimum'] as $key) {
                if (array_key_exists($key, $_POST)) {
                    $in[$key] = $_POST[$key];
                }
            }
            $in['seat_source'] = (string) ($in['seat_source'] ?? '');
        }

        $saved = billingItemSave($billing_type, $doc_id, $in, $billing_op === 'update' ? $item_id : 0, $error);
        if (!$saved) {
            billingLinesFail($error);
        }

        $name = escapeSql(trim($in['name']));
        if ($billing_op === 'create') {
            $insert_id = $saved;
            logAudit($billing_label, "Edit", "Added line $name to $number via API ($api_key_name)", $doc_client_id, $doc_id);
            logAudit("API", "Success", "Added line $name to $number via API ($api_key_name)", $doc_client_id);
        } else {
            $update_count = 1;
            logAudit($billing_label, "Edit", "Edited line $name on $number via API ($api_key_name)", $doc_client_id, $doc_id);
            logAudit("API", "Success", "Edited line $name on $number via API ($api_key_name)", $doc_client_id);
        }
    }
}

// Output
require_once __DIR__ . '/' . ($billing_op === 'create' ? 'create' : ($billing_op === 'update' ? 'update' : 'delete')) . '_output.php';
