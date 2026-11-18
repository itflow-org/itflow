<?php

/*
 * Purchasing - what is waiting to be ordered, purchase orders to vendors, and
 * receiving what comes in (database 3.9.3). Pages: agent/to_order.php,
 * agent/purchase_orders.php, agent/purchase_order.php, agent/receiving.php;
 * handlers in agent/post/purchase_order.php; settings in Admin > Settings >
 * Purchase orders.
 *
 * The To Order list holds items added by hand, or from products that are low
 * on stock - each for a client or for our own stock, with who it comes from
 * and what it costs. Ticked items become a purchase order to one vendor.
 * A PO goes draft -> pending (waiting for approval, when approval is on and
 * it is over the amount set, or a line has no cost) -> approved -> ordered ->
 * partial -> received, or is cancelled - its items go back on the list.
 *
 * Receiving records what came in against each line. A line for our own stock
 * of a product that tracks stock goes on the shelf; a line for a client can
 * become the client's assets, one a unit, with its serial. A receipt can be
 * undone - what it did is undone with it. The vendor's bill is recorded as an
 * expense against the PO.
 *
 * Permission: Procurement (module_procurement, Admin > Roles). View sees it
 * all; Edit adds items, makes and sends POs, adds tracking and receives;
 * Full approves, sends back, cancels a PO past its draft and undoes a
 * receipt. Admins always have Full. Mountaineer IT's build names the
 * permission the same.
 */

const PO_STATUSES = [
    'draft' => ['label' => 'Draft', 'tone' => 'secondary', 'icon' => 'fa-pencil-alt'],
    'pending' => ['label' => 'Awaiting approval', 'tone' => 'warning', 'icon' => 'fa-hourglass-half'],
    'approved' => ['label' => 'Approved', 'tone' => 'info', 'icon' => 'fa-thumbs-up'],
    'ordered' => ['label' => 'Ordered', 'tone' => 'primary', 'icon' => 'fa-paper-plane'],
    'partial' => ['label' => 'Partly received', 'tone' => 'warning', 'icon' => 'fa-truck-loading'],
    'received' => ['label' => 'Received', 'tone' => 'success', 'icon' => 'fa-check-double'],
    'cancelled' => ['label' => 'Cancelled', 'tone' => 'dark', 'icon' => 'fa-ban'],
];
/* Still being worked on - not received or cancelled */
const PO_OPEN_STATUSES = ['draft', 'pending', 'approved', 'ordered', 'partial'];

/* Carriers, and where each one's tracking page is ({n} - the tracking number) */
const PO_CARRIERS = [
    'ups' => ['UPS', 'https://www.ups.com/track?tracknum={n}'],
    'fedex' => ['FedEx', 'https://www.fedex.com/fedextrack/?trknbr={n}'],
    'usps' => ['USPS', 'https://tools.usps.com/go/TrackConfirmAction?tLabels={n}'],
    'dhl' => ['DHL', 'https://www.dhl.com/global-en/home/tracking/tracking-express.html?tracking-id={n}'],
    'canada_post' => ['Canada Post', 'https://www.canadapost-postescanada.ca/track-reperage/en#/search?searchFor={n}'],
    'royal_mail' => ['Royal Mail', 'https://www.royalmail.com/track-your-item#/tracking-results/{n}'],
    'amazon' => ['Amazon', ''],
    'other' => ['Other', ''],
];

/* At most this many assets are made from one line at a time */
const PO_ASSETS_MAX = 50;

/** Are the purchasing tables there yet - the 3.9.3 update? */
function poReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        $result = mysqli_query($mysqli, "SHOW TABLES LIKE 'purchase_orders'");
        $ready = $result && mysqli_num_rows($result) > 0;
    }
    return $ready;
}

/** The signed-in agent's Procurement level - 0 none (or invoicing off, or before 3.9.3), 1 view, 2 edit, 3 full. */
function poLevel() {
    global $config_module_enable_accounting;
    static $level = null;
    if ($level === null) {
        $level = poReady() && intval($config_module_enable_accounting ?? 0) === 1 ? intval(lookupUserPermission('module_procurement')) : 0;
    }
    return $level;
}

/** The purchase order settings - prefix, next, approval (bool), approval_over (0 - every PO). */
function poSettings() {
    global $mysqli;
    static $settings = null;
    if ($settings === null) {
        $row = poReady() ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_po_prefix, config_po_next_number, config_po_approval, config_po_approval_over FROM settings WHERE company_id = 1")) : null;
        $settings = [
            'prefix' => (string) ($row['config_po_prefix'] ?? 'PO-'),
            'next' => max(1, intval($row['config_po_next_number'] ?? 1)),
            'approval' => intval($row['config_po_approval'] ?? 0) === 1,
            'approval_over' => max(0, floatval($row['config_po_approval_over'] ?? 0)),
        ];
    }
    return $settings;
}

/** A PO's number as shown - PO-12. */
function poNumber(array $po) {
    return $po['po_prefix'] . $po['po_number'];
}

/** A status as a badge. */
function poStatusBadge($status) {
    $s = PO_STATUSES[$status] ?? ['label' => ucfirst((string) $status), 'tone' => 'secondary', 'icon' => 'fa-circle'];
    return '<span class="badge rounded-pill text-bg-' . $s['tone'] . '" data-po-status="' . escapeHtml($status) . '"><i class="fas fa-fw ' . $s['icon'] . ' me-1"></i>' . escapeHtml($s['label']) . '</span>';
}

/** Money in the PO's currency (the company's when it has none). */
function poMoney($amount, $currency = '') {
    global $session_company_currency;
    return billingMoney(floatval($amount), (string) ($currency !== '' ? $currency : ($session_company_currency ?? '')));
}

/** A tracking number's page at its carrier, or '' when there is none to link to. */
function poTrackingUrl($carrier, $number) {
    $pattern = PO_CARRIERS[$carrier][1] ?? '';
    return $pattern === '' ? '' : str_replace('{n}', rawurlencode(trim((string) $number)), $pattern);
}

/** A money or quantity typed in ("1,200", "$40") as a number rounded to cents - null when empty or not a number. */
function poNumberIn($value) {
    $text = trim(str_replace([',', ' ', '$', '£', '€'], '', (string) $value));
    return $text !== '' && is_numeric($text) ? round(floatval($text), 2) : null;
}

/** Who the signed-in agent is, raw (the session keeps the name escaped for SQL). */
function poWho() {
    global $session_name;
    return stripslashes((string) ($session_name ?? ''));
}

/** Add to a PO's history - the audit log, where the PO page shows it. $description raw. */
function poLog(array $po, $action, $description, $client_id = 0) {
    logAudit('Purchase Order', $action, escapeSql(mb_substr($description, 0, 1000)), intval($client_id), intval($po['po_id']));
}

/* ------------------------------------------------------------- Reading */

/** A PO with its vendor and who made, approved and ordered it - or null. Not scoped: see poAccessible(). */
function poRow($po_id) {
    global $mysqli;
    $po_id = intval($po_id);
    if (!$po_id || !poReady()) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT purchase_orders.*, vendor_name, vendor_email, vendor_contact_name, vendor_contact_email,
            vendor_phone, vendor_phone_country_code, vendor_account_number, vendor_website,
            creator.user_name AS created_by_name, approver.user_name AS approved_by_name, orderer.user_name AS ordered_by_name
        FROM purchase_orders
        LEFT JOIN vendors ON vendors.vendor_id = po_vendor_id
        LEFT JOIN users creator ON creator.user_id = po_created_by
        LEFT JOIN users approver ON approver.user_id = po_approved_by AND po_approved_by > 0
        LEFT JOIN users orderer ON orderer.user_id = po_ordered_by AND po_ordered_by > 0
        WHERE po_id = $po_id")) ?: null;
}

/** A PO's lines, in order, with the client each is for and its product. */
function poItems($po_id) {
    global $mysqli;
    $po_id = intval($po_id);
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT po_items.*, client_name, product_name, product_track_stock, product_asset_type, product_make, product_model, product_warranty_months
        FROM po_items
        LEFT JOIN clients ON client_id = po_item_client_id AND po_item_client_id > 0
        LEFT JOIN products ON product_id = po_item_product_id AND po_item_product_id > 0
        WHERE po_item_po_id = $po_id ORDER BY po_item_order ASC, po_item_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** What is still to come in on a line. */
function poItemLeft(array $item) {
    return max(0, round(floatval($item['po_item_quantity']) - floatval($item['po_item_received']), 2));
}

/*
 * SQL keeping to POs ($po_column) the agent can see every client of - " AND
 * ..." or '' for an agent who sees every client. A PO for our own stock is
 * everyone's.
 */
function poScopeSql($po_column) {
    $scope = clientScopeSql('scope_items.po_item_client_id');
    return $scope === '' ? '' : " AND $po_column NOT IN (SELECT scope_items.po_item_po_id FROM po_items scope_items WHERE scope_items.po_item_client_id > 0 AND NOT (1 = 1 $scope))";
}

/** Can the signed-in agent see every client on this PO? */
function poAccessible($po_id) {
    global $mysqli;
    $sql = mysqli_query($mysqli, "SELECT DISTINCT po_item_client_id FROM po_items WHERE po_item_po_id = " . intval($po_id) . " AND po_item_client_id > 0");
    while ($row = mysqli_fetch_row($sql)) {
        if (!hasClientAccess(intval($row[0]))) {
            return false;
        }
    }
    return true;
}

/** Why nobody can use Purchasing yet - the database update, or invoicing off - or '' when it is on. */
function poOffReason() {
    global $config_module_enable_accounting;
    if (!poReady()) {
        return 'Purchasing needs the database update - ask an admin to run it from Maintenance > Update.';
    }
    if (intval($config_module_enable_accounting ?? 0) !== 1) {
        return "Purchasing isn't on - it comes with invoicing, in Admin > Settings > Modules.";
    }
    return '';
}

/** Stop unless the signed-in agent has at least this Purchasing level - and Purchasing is on. A window gets the reason in JSON. */
function poRequireLevel($level = 1) {
    $level = max(1, intval($level));
    if (poLevel() >= $level) {
        return;
    }
    $off = poOffReason();
    if ($off === '') {
        enforceUserPermission('module_procurement', $level); // their role - doesn't return
    }
    if (defined('ITFLOW_MODAL')) {
        exit(json_encode(['error' => $off]));
    }
    flashAlert($off, 'error');
    redirect('dashboard.php');
}

/** The PO a request is about, once the agent's level and its clients are checked - or the request stops. */
function poForRequest($po_id, $level = 1) {
    poRequireLevel($level);
    $po = poRow($po_id);
    if (!$po || !poAccessible($po['po_id'])) {
        flashAlert("That purchase order doesn't exist, or it is for a client you can't see.", 'error');
        redirect('purchase_orders.php');
    }
    return $po;
}

/** The clients on a PO - client_id => name. */
function poClients($po_id) {
    global $mysqli;
    $clients = [];
    $sql = mysqli_query($mysqli, "SELECT DISTINCT client_id, client_name FROM po_items JOIN clients ON client_id = po_item_client_id WHERE po_item_po_id = " . intval($po_id) . " ORDER BY client_name");
    while ($row = mysqli_fetch_row($sql)) {
        $clients[intval($row[0])] = $row[1];
    }
    return $clients;
}

/** The client a PO is logged against - its one client, or none. */
function poLogClient($po_id) {
    $clients = poClients($po_id);
    return count($clients) === 1 ? array_key_first($clients) : 0;
}

/** Our vendors - vendor_id => name (the ones on products: not a client's own). */
function poVendors($keep_id = 0) {
    global $mysqli;
    $vendors = productVendors();
    $keep_id = intval($keep_id);
    if ($keep_id && !isset($vendors[$keep_id])) {
        $name = mysqli_fetch_row(mysqli_query($mysqli, "SELECT vendor_name FROM vendors WHERE vendor_id = $keep_id"));
        if ($name) {
            $vendors[$keep_id] = $name[0];
        }
    }
    return $vendors;
}

/* ------------------------------------------------------------- To order */

/** An item on the list, scoped to the agent's clients - or null. */
function orderItemRow($order_item_id) {
    global $mysqli;
    $order_item_id = intval($order_item_id);
    if (!$order_item_id || !poReady()) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT order_items.*, client_name, vendor_name, product_name
        FROM order_items
        LEFT JOIN clients ON client_id = order_item_client_id AND order_item_client_id > 0
        LEFT JOIN vendors ON vendor_id = order_item_vendor_id AND order_item_vendor_id > 0
        LEFT JOIN products ON product_id = order_item_product_id AND order_item_product_id > 0
        WHERE order_item_id = $order_item_id" . clientScopeSql('order_item_client_id'))) ?: null;
}

/*
 * Add an item to the list, or change one still waiting. $in holds raw values:
 * product (a product id - its name, vendor and cost fill what is left empty),
 * name, description, quantity, cost, vendor, client (0 - our own stock),
 * note, source (manual, reorder). The item's id, or 0 with $error.
 */
function orderItemSave(array $in, $order_item_id = 0, &$error = '') {
    global $mysqli, $session_user_id;
    $error = '';
    $order_item_id = intval($order_item_id);
    $existing = $order_item_id ? orderItemRow($order_item_id) : null;
    if ($order_item_id && (!$existing || $existing['order_item_status'] !== 'waiting')) {
        $error = $existing ? 'It is on a purchase order already - change it there.' : 'That item is no longer on the list.';
        return 0;
    }
    $product = intval($in['product'] ?? 0) ? productRow(intval($in['product'])) : null;
    $name = trim((string) ($in['name'] ?? ''));
    if ($name === '' && $product) {
        $name = (string) $product['product_name'];
    }
    if ($name === '') {
        $error = 'Say what to order.';
        return 0;
    }
    $quantity = poNumberIn($in['quantity'] ?? '');
    if ($quantity === null || $quantity <= 0 || $quantity > 9999999) {
        $error = 'How many - a number above zero.';
        return 0;
    }
    $cost_text = trim((string) ($in['cost'] ?? ''));
    $cost = poNumberIn($cost_text);
    if ($cost_text !== '' && ($cost === null || $cost < 0 || $cost > 9999999999)) {
        $error = 'The unit cost is a number, zero or more - or leave it empty.';
        return 0;
    }
    if ($cost_text === '' && $product && floatval($product['product_cost']) > 0) {
        $cost = round(floatval($product['product_cost']), 2);
    }
    $vendor = intval($in['vendor'] ?? 0);
    if (!$vendor && $product) {
        $vendor = intval($product['product_vendor_id']);
    }
    if ($vendor && !isset(poVendors($existing ? $existing['order_item_vendor_id'] : 0)[$vendor])) {
        $vendor = 0;
    }
    $client = intval($in['client'] ?? 0);
    if ($client && (!hasClientAccess($client) || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client")))) {
        $error = "That client isn't one you can order for.";
        return 0;
    }
    $source = in_array($in['source'] ?? '', ['manual', 'reorder'], true) ? $in['source'] : ($existing ? $existing['order_item_source'] : 'manual');
    $description = trim((string) ($in['description'] ?? ''));
    $note = mb_substr(trim((string) ($in['note'] ?? '')), 0, 255);

    $set = "order_item_name = '" . escapeSql(mb_substr($name, 0, 255)) . "', order_item_description = " . ($description === '' ? 'NULL' : "'" . escapeSql($description) . "'") . ",
        order_item_quantity = $quantity, order_item_cost = " . ($cost === null ? 'NULL' : $cost) . ", order_item_note = " . ($note === '' ? 'NULL' : "'" . escapeSql($note) . "'") . ",
        order_item_product_id = " . ($product ? intval($product['product_id']) : 0) . ", order_item_vendor_id = $vendor, order_item_client_id = $client, order_item_source = '$source'";
    if ($existing) {
        mysqli_query($mysqli, "UPDATE order_items SET $set WHERE order_item_id = $order_item_id");
        return $order_item_id;
    }
    mysqli_query($mysqli, "INSERT INTO order_items SET $set, order_item_status = 'waiting', order_item_created_by = " . intval($session_user_id));
    return intval(mysqli_insert_id($mysqli));
}

/** Items waiting to be ordered that the agent can see - $vendor_id -1 for none set yet, $client_id -1 for our own stock. */
function orderItemsWaiting($vendor_id = 0, $client_id = 0) {
    global $mysqli;
    $where = "order_item_status = 'waiting'" . clientScopeSql('order_item_client_id');
    $vendor_id = intval($vendor_id);
    $client_id = intval($client_id);
    if ($vendor_id) {
        $where .= " AND order_item_vendor_id = " . max(0, $vendor_id);
    }
    if ($client_id) {
        $where .= " AND order_item_client_id = " . max(0, $client_id);
    }
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT order_items.*, client_name, vendor_name, product_name, user_name AS created_by_name
        FROM order_items
        LEFT JOIN clients ON client_id = order_item_client_id AND order_item_client_id > 0
        LEFT JOIN vendors ON vendor_id = order_item_vendor_id AND order_item_vendor_id > 0
        LEFT JOIN products ON product_id = order_item_product_id AND order_item_product_id > 0
        LEFT JOIN users ON user_id = order_item_created_by AND order_item_created_by > 0
        WHERE $where
        ORDER BY vendor_name IS NULL, vendor_name ASC, order_item_client_id = 0, client_name ASC, order_item_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** Waiting items by id, that the agent can see. */
function orderItemsByIds(array $ids) {
    global $mysqli;
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids || !poReady()) {
        return [];
    }
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT order_items.*, product_vendor_part FROM order_items
        LEFT JOIN products ON product_id = order_item_product_id AND order_item_product_id > 0
        WHERE order_item_id IN (" . implode(',', $ids) . ") AND order_item_status = 'waiting'" . clientScopeSql('order_item_client_id') . " ORDER BY order_item_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** Take waiting items off the list. How many went. */
function orderItemsRemove(array $ids) {
    global $mysqli;
    $removed = 0;
    foreach (orderItemsByIds($ids) as $item) {
        mysqli_query($mysqli, "DELETE FROM order_items WHERE order_item_id = " . intval($item['order_item_id']) . " AND order_item_status = 'waiting'");
        $removed += mysqli_affected_rows($mysqli);
    }
    return $removed;
}

/*
 * Products that track stock and are at or under their reorder level, with
 * nothing for our stock already waiting or on order - what Add low-stock
 * products puts on the list. Each with 'want': its reorder quantity, or
 * enough to get back over the level.
 */
function orderItemsLowStock() {
    global $mysqli;
    if (!poReady()) {
        return [];
    }
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT product_id, product_name, product_cost, product_vendor_id, product_reorder_level, product_reorder_qty, vendor_name, " . productStockSql() . " AS on_hand
        FROM products LEFT JOIN vendors ON vendor_id = product_vendor_id AND product_vendor_id > 0
        WHERE " . productReorderSql() . "
            AND NOT EXISTS (SELECT 1 FROM order_items WHERE order_item_product_id = product_id AND order_item_client_id = 0 AND order_item_status = 'waiting')
            AND NOT EXISTS (SELECT 1 FROM po_items JOIN purchase_orders ON po_id = po_item_po_id
                WHERE po_item_product_id = product_id AND po_item_client_id = 0 AND po_status IN ('draft', 'pending', 'approved', 'ordered', 'partial') AND po_item_received < po_item_quantity)
        ORDER BY product_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $level = max(0, floatval($row['product_reorder_level']));
        $row['want'] = floatval($row['product_reorder_qty']) > 0 ? round(floatval($row['product_reorder_qty']), 2) : max(1, ceil($level - floatval($row['on_hand']) + 1));
        $rows[] = $row;
    }
    return $rows;
}

/** Put the low-stock products on the list. How many went on. */
function orderItemsAddLowStock() {
    $added = 0;
    foreach (orderItemsLowStock() as $row) {
        if (orderItemSave(['product' => $row['product_id'], 'quantity' => $row['want'], 'source' => 'reorder',
            'note' => 'Low on stock - ' . billingQuantity($row['on_hand']) . ' left, reorder at ' . billingQuantity($row['product_reorder_level'])])) {
            $added++;
        }
    }
    return $added;
}

/* ------------------------------------------------------------- Purchase orders */

/** Hold a named lock for the rest of the request (or until poUnlock) - false when someone else has it. */
function poLock($what) {
    global $mysqli;
    $lock = escapeSql('itflow_po_' . preg_replace('/[^a-z0-9_]/', '', strtolower((string) $what)));
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT GET_LOCK('$lock', 10)"))[0] ?? 0) === 1;
}

function poUnlock($what) {
    global $mysqli;
    $lock = escapeSql('itflow_po_' . preg_replace('/[^a-z0-9_]/', '', strtolower((string) $what)));
    mysqli_query($mysqli, "SELECT RELEASE_LOCK('$lock')");
}

/** The next PO number - taken, so no two get it. */
function poNextNumber() {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE settings SET config_po_next_number = LAST_INSERT_ID(config_po_next_number), config_po_next_number = config_po_next_number + 1 WHERE company_id = 1");
    return intval(mysqli_insert_id($mysqli));
}

/** An office's address for Ship to - its name (the company's for the main one) and address, raw. */
function poOfficeShipTo($office_id) {
    $office = office(intval($office_id)) ?? officeMain();
    $company = billingCompany();
    $name = intval($office['office_id']) === 0 ? (string) ($company['company_name'] ?? '') : (string) ($company['company_name'] ?? '') . ' - ' . $office['name'];
    return trim($name . "\n" . formatAddress($office['address'], $office['city'], $office['state'], $office['zip'], $office['country'], "\n"));
}

/** Add lines to a PO from items on the list - the items are marked ordered. How many went on. */
function poAddOrderItems(array $po, array $items) {
    global $mysqli;
    $po_id = intval($po['po_id']);
    $order = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(po_item_order), 0) + 1 FROM po_items WHERE po_item_po_id = $po_id"))[0]);
    $added = 0;
    foreach ($items as $item) {
        $description = trim((string) $item['order_item_description']);
        $part = trim((string) ($item['product_vendor_part'] ?? ''));
        mysqli_query($mysqli, "INSERT INTO po_items SET po_item_po_id = $po_id, po_item_order_item_id = " . intval($item['order_item_id']) . ",
            po_item_product_id = " . intval($item['order_item_product_id']) . ", po_item_client_id = " . intval($item['order_item_client_id']) . ",
            po_item_name = '" . escapeSql($item['order_item_name']) . "', po_item_description = " . ($description === '' ? 'NULL' : "'" . escapeSql($description) . "'") . ",
            po_item_vendor_part = " . ($part === '' ? 'NULL' : "'" . escapeSql($part) . "'") . ",
            po_item_quantity = " . round(floatval($item['order_item_quantity']), 2) . ", po_item_cost = " . round(floatval($item['order_item_cost'] ?? 0), 2) . ", po_item_order = " . ($order++));
        mysqli_query($mysqli, "UPDATE order_items SET order_item_status = 'ordered', order_item_vendor_id = " . intval($po['po_vendor_id']) . " WHERE order_item_id = " . intval($item['order_item_id']));
        $added++;
    }
    return $added;
}

/*
 * A draft PO to $vendor_id, from waiting items ($order_item_ids - none for an
 * empty one to add lines to). It ships to the agent's office. Under a lock,
 * so two buyers can't put the same items on two POs. The PO's id, or 0 with $error.
 */
function poCreate($vendor_id, array $order_item_ids, &$error = '') {
    global $mysqli, $session_user_id, $session_company_currency;
    $error = '';
    $vendor_id = intval($vendor_id);
    if (!$vendor_id || !isset(poVendors()[$vendor_id])) {
        $error = 'Pick who it is from.';
        return 0;
    }
    if (!poLock('order')) {
        $error = 'Someone else is making a purchase order right now - try again.';
        return 0;
    }
    try {
        $items = orderItemsByIds($order_item_ids);
        if ($order_item_ids && !$items) {
            $error = 'Those items are no longer waiting - someone may have ordered them.';
            return 0;
        }
        $office_id = officeIdForUser($session_user_id);
        $settings = poSettings();
        $po_id = 0;
        for ($attempt = 0; $attempt < 5 && !$po_id; $attempt++) {
            $number = poNextNumber();
            try {
                mysqli_query($mysqli, "INSERT INTO purchase_orders SET po_prefix = '" . escapeSql($settings['prefix']) . "', po_number = $number, po_status = 'draft',
                    po_vendor_id = $vendor_id, po_currency_code = '" . escapeSql((string) $session_company_currency) . "',
                    po_ship_to_office_id = $office_id, po_ship_to = '" . escapeSql(poOfficeShipTo($office_id)) . "', po_created_by = " . intval($session_user_id));
                $po_id = intval(mysqli_insert_id($mysqli));
            } catch (mysqli_sql_exception $e) {
                // That number is taken (the next number was set back) - take the one after
                if ($e->getCode() !== 1062) {
                    throw $e;
                }
            }
        }
        if (!$po_id) {
            $error = "A purchase order number couldn't be given - check the next number in Admin > Settings > Purchase orders.";
            return 0;
        }
        $po = poRow($po_id);
        $added = poAddOrderItems($po, $items);
        poRecalc($po_id);
        poLog($po, 'Create', poWho() . ' created ' . poNumber($po) . ' to ' . $po['vendor_name'] . ($added ? " with $added item" . ($added === 1 ? '' : 's') . ' from To Order' : ''), poLogClient($po_id));
        return $po_id;
    } finally {
        poUnlock('order');
    }
}

/** Put waiting items on a draft PO. How many went on, with $error when none. */
function poAddItems(array $po, array $order_item_ids, &$error = '') {
    $error = '';
    if ($po['po_status'] !== 'draft') {
        $error = poNumber($po) . " isn't a draft any more - items go on a draft.";
        return 0;
    }
    if (!poLock('order')) {
        $error = 'Someone else is making a purchase order right now - try again.';
        return 0;
    }
    try {
        $added = poAddOrderItems($po, orderItemsByIds($order_item_ids));
        if (!$added) {
            $error = 'Those items are no longer waiting - someone may have ordered them.';
            return 0;
        }
        poRecalc($po['po_id']);
        poLog($po, 'Edit', poWho() . " added $added item" . ($added === 1 ? '' : 's') . ' from To Order', poLogClient($po['po_id']));
        return $added;
    } finally {
        poUnlock('order');
    }
}

/*
 * A line typed on a draft PO, or one changed ($po_item_id). $in raw: product,
 * name, description, vendor_part, quantity, cost, client. The line's id, or 0 with $error.
 */
function poLineSave(array $po, array $in, $po_item_id = 0, &$error = '') {
    global $mysqli;
    $error = '';
    if ($po['po_status'] !== 'draft') {
        $error = 'Only a draft can be changed - send it back to draft first.';
        return 0;
    }
    $po_id = intval($po['po_id']);
    $po_item_id = intval($po_item_id);
    $line = $po_item_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM po_items WHERE po_item_id = $po_item_id AND po_item_po_id = $po_id")) : null;
    if ($po_item_id && !$line) {
        $error = 'That line is no longer on the purchase order.';
        return 0;
    }
    $product = intval($in['product'] ?? 0) ? productRow(intval($in['product'])) : null;
    $name = trim((string) ($in['name'] ?? ''));
    if ($name === '' && $product) {
        $name = (string) $product['product_name'];
    }
    if ($name === '') {
        $error = 'Say what it is.';
        return 0;
    }
    $quantity = poNumberIn($in['quantity'] ?? '');
    if ($quantity === null || $quantity <= 0 || $quantity > 9999999) {
        $error = 'How many - a number above zero.';
        return 0;
    }
    $cost_text = trim((string) ($in['cost'] ?? ''));
    $cost = $cost_text === '' && $product ? round(floatval($product['product_cost']), 2) : poNumberIn($cost_text);
    if ($cost === null || $cost < 0 || $cost > 9999999999) {
        $error = 'The unit cost is a number, zero or more.';
        return 0;
    }
    $client = intval($in['client'] ?? 0);
    if ($client && !hasClientAccess($client)) {
        $error = "That client isn't one you can order for.";
        return 0;
    }
    $part = trim((string) ($in['vendor_part'] ?? ''));
    if ($part === '' && $product && !$line) {
        $part = trim((string) $product['product_vendor_part']);
    }
    $description = trim((string) ($in['description'] ?? ''));
    $set = "po_item_name = '" . escapeSql(mb_substr($name, 0, 255)) . "', po_item_description = " . ($description === '' ? 'NULL' : "'" . escapeSql($description) . "'") . ",
        po_item_vendor_part = " . ($part === '' ? 'NULL' : "'" . escapeSql(mb_substr($part, 0, 200)) . "'") . ", po_item_quantity = $quantity, po_item_cost = $cost,
        po_item_product_id = " . ($product ? intval($product['product_id']) : 0) . ", po_item_client_id = $client";
    if ($line) {
        mysqli_query($mysqli, "UPDATE po_items SET $set WHERE po_item_id = $po_item_id");
        // The item on the list follows the line it became
        if (intval($line['po_item_order_item_id'])) {
            mysqli_query($mysqli, "UPDATE order_items SET order_item_quantity = $quantity, order_item_cost = $cost, order_item_client_id = $client WHERE order_item_id = " . intval($line['po_item_order_item_id']));
        }
        poRecalc($po_id);
        poLog($po, 'Edit', poWho() . " changed $name on " . poNumber($po), poLogClient($po_id));
        return $po_item_id;
    }
    $order = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(po_item_order), 0) + 1 FROM po_items WHERE po_item_po_id = $po_id"))[0]);
    mysqli_query($mysqli, "INSERT INTO po_items SET $set, po_item_po_id = $po_id, po_item_order = $order");
    $po_item_id = intval(mysqli_insert_id($mysqli));
    poRecalc($po_id);
    poLog($po, 'Edit', poWho() . ' added ' . billingQuantity($quantity) . " x $name to " . poNumber($po), poLogClient($po_id));
    return $po_item_id;
}

/** Take a line off a draft PO - an item from the list goes back on it. */
function poLineRemove(array $po, $po_item_id, &$error = '') {
    global $mysqli;
    $error = '';
    if ($po['po_status'] !== 'draft') {
        $error = 'Only a draft can be changed - send it back to draft first.';
        return false;
    }
    $line = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM po_items WHERE po_item_id = " . intval($po_item_id) . " AND po_item_po_id = " . intval($po['po_id'])));
    if (!$line) {
        $error = 'That line is no longer on the purchase order.';
        return false;
    }
    if (intval($line['po_item_order_item_id'])) {
        mysqli_query($mysqli, "UPDATE order_items SET order_item_status = 'waiting' WHERE order_item_id = " . intval($line['po_item_order_item_id']));
    }
    mysqli_query($mysqli, "DELETE FROM po_items WHERE po_item_id = " . intval($line['po_item_id']));
    poRecalc($po['po_id']);
    poLog($po, 'Edit', poWho() . ' took ' . $line['po_item_name'] . ' off ' . poNumber($po) . (intval($line['po_item_order_item_id']) ? ' - it is back on To Order' : ''), poLogClient($po['po_id']));
    return true;
}

/*
 * Who it is from, where it ships, shipping and the note. $in raw: vendor (a
 * draft's only), ship_to (an office id, or 'other' with ship_to_address),
 * shipping, note. True, or false with $error.
 */
function poDetailsSave(array $po, array $in, &$error = '') {
    global $mysqli;
    $error = '';
    if (in_array($po['po_status'], ['received', 'cancelled'], true)) {
        $error = poNumber($po) . ' is ' . strtolower(PO_STATUSES[$po['po_status']]['label']) . ' - it can no longer be changed.';
        return false;
    }
    $set = [];
    if ($po['po_status'] === 'draft' && isset($in['vendor'])) {
        $vendor = intval($in['vendor']);
        if (!isset(poVendors($po['po_vendor_id'])[$vendor])) {
            $error = 'Pick who it is from.';
            return false;
        }
        $set[] = "po_vendor_id = $vendor";
        mysqli_query($mysqli, "UPDATE order_items JOIN po_items ON po_item_order_item_id = order_item_id SET order_item_vendor_id = $vendor WHERE po_item_po_id = " . intval($po['po_id']));
    }
    if (isset($in['ship_to'])) {
        if ((string) $in['ship_to'] === 'other') {
            $address = trim(preg_replace("/\r\n?/", "\n", (string) ($in['ship_to_address'] ?? '')));
            if ($address === '') {
                $error = 'Type the address it ships to.';
                return false;
            }
            $set[] = "po_ship_to_office_id = NULL, po_ship_to = '" . escapeSql(mb_substr($address, 0, 1000)) . "'";
        } else {
            $office_id = officeIdFrom($in['ship_to']);
            $set[] = "po_ship_to_office_id = $office_id, po_ship_to = '" . escapeSql(poOfficeShipTo($office_id)) . "'";
        }
    }
    if (isset($in['shipping'])) {
        $shipping = trim((string) $in['shipping']) === '' ? 0.0 : poNumberIn($in['shipping']);
        if ($shipping === null || $shipping < 0 || $shipping > 9999999999) {
            $error = 'Shipping is an amount, zero or more.';
            return false;
        }
        $set[] = "po_shipping = $shipping";
    }
    if (isset($in['note'])) {
        $note = trim((string) $in['note']);
        $set[] = 'po_note = ' . ($note === '' ? 'NULL' : "'" . escapeSql(mb_substr($note, 0, 2000)) . "'");
    }
    if ($set) {
        mysqli_query($mysqli, "UPDATE purchase_orders SET " . implode(', ', $set) . " WHERE po_id = " . intval($po['po_id']));
        poRecalc($po['po_id']);
        poLog($po, 'Edit', poWho() . ' changed the details of ' . poNumber($po), poLogClient($po['po_id']));
    }
    return true;
}

/*
 * Its total from its lines and shipping - and once ordered, its status from
 * what has come in. A line all in marks its item on the list received.
 */
function poRecalc($po_id) {
    global $mysqli;
    $po_id = intval($po_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(SUM(ROUND(po_item_quantity * po_item_cost, 2)), 0) AS lines_total, COALESCE(SUM(po_item_quantity), 0) AS qty,
            COALESCE(SUM(LEAST(po_item_received, po_item_quantity)), 0) AS received FROM po_items WHERE po_item_po_id = $po_id"));
    mysqli_query($mysqli, "UPDATE purchase_orders SET po_total = ROUND(" . floatval($row['lines_total']) . " + po_shipping, 2) WHERE po_id = $po_id");
    $status = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT po_status FROM purchase_orders WHERE po_id = $po_id"))[0] ?? '');
    if (in_array($status, ['ordered', 'partial', 'received'], true)) {
        $now = floatval($row['received']) <= 0 ? 'ordered' : (floatval($row['received']) + 0.001 >= floatval($row['qty']) ? 'received' : 'partial');
        if ($now !== $status) {
            mysqli_query($mysqli, "UPDATE purchase_orders SET po_status = '$now', po_received_at = " . ($now === 'received' ? 'NOW()' : 'NULL') . " WHERE po_id = $po_id");
        }
        mysqli_query($mysqli, "UPDATE order_items JOIN po_items ON po_item_order_item_id = order_item_id
            SET order_item_status = IF(po_item_received + 0.001 >= po_item_quantity, 'received', 'ordered')
            WHERE po_item_po_id = $po_id AND order_item_status IN ('ordered', 'received')");
    }
}

/* ------------------------------------------------------------- Approval */

/** Does this PO need approving before it is ordered? Over the amount, or a line without a cost (it would look free). */
function poNeedsApproval(array $po) {
    global $mysqli;
    $settings = poSettings();
    if (!$settings['approval']) {
        return false;
    }
    if ($settings['approval_over'] <= 0 || floatval($po['po_total']) > $settings['approval_over']) {
        return true;
    }
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM po_items WHERE po_item_po_id = " . intval($po['po_id']) . " AND po_item_cost <= 0"))[0]) > 0;
}

/*
 * The agents who can approve this PO - user_id => [user_name, user_email]:
 * admins and roles with Full Procurement access, active, who can see every
 * client on it.
 */
function poApprovers(array $po) {
    global $mysqli;
    $clients = array_keys(poClients($po['po_id']));
    $approvers = [];
    $sql = mysqli_query($mysqli, "SELECT users.user_id, user_name, user_email FROM users
        JOIN user_roles ON role_id = user_role_id
        LEFT JOIN user_role_permissions ON user_role_permissions.user_role_id = role_id
            AND module_id = (SELECT module_id FROM modules WHERE module_name = 'module_procurement' LIMIT 1)
        WHERE user_type = 1" . serviceAccountsOutSql('users.user_service_account') . " AND user_status = 1 AND user_archived_at IS NULL
            AND (role_is_admin = 1 OR user_role_permission_level >= 3)
        GROUP BY users.user_id, user_name, user_email, role_is_admin");
    while ($row = mysqli_fetch_assoc($sql)) {
        $user_id = intval($row['user_id']);
        foreach ($clients as $client_id) {
            if (!agentCanSeeClient($user_id, $client_id)) {
                continue 2;
            }
        }
        $approvers[$user_id] = $row;
    }
    return $approvers;
}

/** Can that agent reach that client - their role's admin flag, then their allow and deny lists. */
function agentCanSeeClient($user_id, $client_id) {
    global $mysqli;
    $user_id = intval($user_id);
    $client_id = intval($client_id);
    $admin = mysqli_fetch_row(mysqli_query($mysqli, "SELECT role_is_admin FROM users JOIN user_roles ON role_id = user_role_id WHERE user_id = $user_id"));
    if ($admin && intval($admin[0]) === 1) {
        return true;
    }
    $rules = mysqli_fetch_all(mysqli_query($mysqli, "SELECT client_id, permission_type FROM user_client_permissions WHERE user_id = $user_id"), MYSQLI_ASSOC);
    if (!$rules) {
        return true;
    }
    $allow = [];
    foreach ($rules as $rule) {
        if (intval($rule['client_id']) === $client_id && $rule['permission_type'] === 'deny') {
            return false;
        }
        if ($rule['permission_type'] !== 'deny') {
            $allow[] = intval($rule['client_id']);
        }
    }
    return !$allow || in_array($client_id, $allow, true);
}

/*
 * May the signed-in agent approve this PO? Full Procurement access, and not
 * their own - unless they are an admin, or nobody else can, so a PO is never
 * stuck.
 */
function poMayApprove(array $po) {
    global $session_user_id, $session_is_admin;
    if (poLevel() < 3 || $po['po_status'] !== 'pending') {
        return false;
    }
    if (!empty($session_is_admin) || intval($po['po_created_by']) !== intval($session_user_id)) {
        return true;
    }
    return !array_diff(array_keys(poApprovers($po)), [intval($session_user_id)]);
}

/** Tell an agent - a notice in their bell, and an email when $email. */
function poTell($user_id, array $po, $text, $email = false, $subject = '') {
    global $mysqli, $config_base_url;
    $user_id = intval($user_id);
    if (!$user_id) {
        return;
    }
    mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Purchase Order', notification = '" . escapeSql(mb_substr($text, 0, 1000)) . "',
        notification_action = '/agent/purchase_order.php?po_id=" . intval($po['po_id']) . "', notification_user_id = $user_id, notification_entity_id = " . intval($po['po_id']));
    if (!$email) {
        return;
    }
    $user = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name, user_email FROM users WHERE user_id = $user_id"));
    if (!$user || !filter_var($user['user_email'], FILTER_VALIDATE_EMAIL)) {
        return;
    }
    emailQueue([['email' => $user['user_email'], 'name' => $user['user_name'], 'footnote' => 'You get this because you can approve purchase orders in ITFlow.']], $subject ?: $text, [
        'kind' => 'Purchase order', 'tone' => 'warning', 'preview' => $text,
        'title' => poNumber($po) . ' needs your approval',
        'intro' => [$text . '.'],
        'summary' => ['Purchase order' => poNumber($po), 'Vendor' => (string) $po['vendor_name'], 'Total' => poMoney($po['po_total'], $po['po_currency_code']), 'From' => poWho()],
        'button' => ['label' => 'Open ' . poNumber($po), 'url' => 'https://' . rtrim((string) $config_base_url, '/') . '/agent/purchase_order.php?po_id=' . intval($po['po_id'])],
    ]);
}

/** Send a draft for approval - its approvers are told. */
function poSendForApproval(array $po, &$error = '') {
    global $mysqli, $session_user_id;
    $error = '';
    if ($po['po_status'] !== 'draft' || !poItemsCount($po['po_id'])) {
        $error = $po['po_status'] !== 'draft' ? poNumber($po) . " isn't a draft." : 'Put something on it first.';
        return false;
    }
    $po_id = intval($po['po_id']);
    mysqli_query($mysqli, "UPDATE purchase_orders SET po_status = 'pending', po_submitted_at = NOW(), po_approved_by = 0, po_approved_at = NULL, po_approval_note = NULL WHERE po_id = $po_id");
    $po = poRow($po_id);
    poLog($po, 'Edit', poWho() . ' sent ' . poNumber($po) . ' for approval (' . poMoney($po['po_total'], $po['po_currency_code']) . ')', poLogClient($po_id));
    $text = poNumber($po) . ' (' . $po['vendor_name'] . ', ' . poMoney($po['po_total'], $po['po_currency_code']) . ') is waiting for your approval - from ' . poWho();
    foreach (array_keys(poApprovers($po)) as $user_id) {
        if ($user_id !== intval($session_user_id)) {
            poTell($user_id, $po, $text, true, poNumber($po) . ' needs your approval');
        }
    }
    return true;
}

/** How many lines a PO has. */
function poItemsCount($po_id) {
    global $mysqli;
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM po_items WHERE po_item_po_id = " . intval($po_id)))[0]);
}

/** An approver's answer - approved, or back to draft with why. Whoever made it is told. */
function poDecide(array $po, $approve, $note, &$error = '') {
    global $mysqli, $session_user_id;
    $error = '';
    if (!poMayApprove($po)) {
        $error = $po['po_status'] !== 'pending' ? poNumber($po) . " isn't waiting for approval." : "You can't approve this one - it needs someone with full Procurement access, other than whoever made it.";
        return false;
    }
    $po_id = intval($po['po_id']);
    $note = mb_substr(trim((string) $note), 0, 255);
    $note_sql = $note === '' ? 'NULL' : "'" . escapeSql($note) . "'";
    if ($approve) {
        mysqli_query($mysqli, "UPDATE purchase_orders SET po_status = 'approved', po_approved_by = " . intval($session_user_id) . ", po_approved_at = NOW(), po_approval_note = $note_sql WHERE po_id = $po_id");
        $own = intval($po['po_created_by']) === intval($session_user_id);
        poLog($po, 'Edit', poWho() . ($own ? ' approved their own ' : ' approved ') . poNumber($po) . ($note !== '' ? " - $note" : ''), poLogClient($po_id));
    } else {
        mysqli_query($mysqli, "UPDATE purchase_orders SET po_status = 'draft', po_approved_by = 0, po_approved_at = NULL, po_approval_note = $note_sql WHERE po_id = $po_id");
        poLog($po, 'Edit', poWho() . ' sent ' . poNumber($po) . ' back to draft' . ($note !== '' ? " - $note" : ''), poLogClient($po_id));
    }
    if (intval($po['po_created_by']) !== intval($session_user_id)) {
        poTell($po['po_created_by'], $po, poNumber($po) . ($approve ? ' was approved' : ' was sent back to draft') . ' by ' . poWho() . ($note !== '' ? ": $note" : ''));
    }
    return true;
}

/** Back to draft, to change it - an approved PO needs approving again. */
function poBackToDraft(array $po, &$error = '') {
    global $mysqli;
    $error = '';
    if (!in_array($po['po_status'], ['pending', 'approved'], true)) {
        $error = poNumber($po) . " can't go back to draft now.";
        return false;
    }
    mysqli_query($mysqli, "UPDATE purchase_orders SET po_status = 'draft', po_approved_by = 0, po_approved_at = NULL WHERE po_id = " . intval($po['po_id']));
    poLog($po, 'Edit', poWho() . ' took ' . poNumber($po) . ' back to draft to change it', poLogClient($po['po_id']));
    return true;
}

/** Can it be marked ordered (or emailed as the order) now - approved, or a draft that needs no approval? */
function poCanOrder(array $po) {
    return $po['po_status'] === 'approved' || ($po['po_status'] === 'draft' && poItemsCount($po['po_id']) && !poNeedsApproval($po));
}

/** Mark it ordered with the vendor - on $date, with their order number if they gave one. */
function poMarkOrdered(array $po, $date, $vendor_reference, &$error = '') {
    global $mysqli, $session_user_id;
    $error = '';
    if (!poCanOrder($po)) {
        $error = $po['po_status'] === 'draft' ? poNumber($po) . ' needs approving before it is ordered.' : poNumber($po) . ' is ' . strtolower(PO_STATUSES[$po['po_status']]['label'] ?? $po['po_status']) . '.';
        return false;
    }
    $po_id = intval($po['po_id']);
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) && strtotime($date) ? $date : date('Y-m-d');
    $reference = mb_substr(trim((string) $vendor_reference), 0, 200);
    // A draft that needed no approval is approved as it is ordered
    $approved = $po['po_status'] === 'draft' ? ", po_approved_by = " . intval($session_user_id) . ", po_approved_at = NOW(), po_approval_note = '" . escapeSql(poSettings()['approval'] ? 'Under the approval amount' : 'Approval is off') . "'" : '';
    mysqli_query($mysqli, "UPDATE purchase_orders SET po_status = 'ordered', po_order_date = '$date', po_ordered_by = " . intval($session_user_id) . ", po_ordered_at = NOW()$approved"
        . ($reference !== '' ? ", po_vendor_reference = '" . escapeSql($reference) . "'" : '') . " WHERE po_id = $po_id");
    poRecalc($po_id);
    poLog($po, 'Edit', poWho() . ' marked ' . poNumber($po) . " ordered on $date" . ($reference !== '' ? " - their order $reference" : ''), poLogClient($po_id));
    return true;
}

/** Cancel it - nothing has come in. Its items go back on the list. */
function poCancel(array $po, &$error = '') {
    global $mysqli;
    $error = '';
    $po_id = intval($po['po_id']);
    if (!in_array($po['po_status'], ['draft', 'pending', 'approved', 'ordered'], true)
        || floatval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(po_item_received), 0) FROM po_items WHERE po_item_po_id = $po_id"))[0]) > 0) {
        $error = 'Something on ' . poNumber($po) . ' has come in, so it can\'t be cancelled - undo the receipts first.';
        return false;
    }
    mysqli_query($mysqli, "UPDATE order_items JOIN po_items ON po_item_order_item_id = order_item_id SET order_item_status = 'waiting' WHERE po_item_po_id = $po_id");
    mysqli_query($mysqli, "UPDATE purchase_orders SET po_status = 'cancelled', po_cancelled_at = NOW() WHERE po_id = $po_id");
    poLog($po, 'Edit', poWho() . ' cancelled ' . poNumber($po) . ' - its items are back on To Order', poLogClient($po_id));
    return true;
}

/* ------------------------------------------------------------- Receiving */

/** Lines still to come in on ordered POs the agent can see - by po_id, each line with its PO. $po_id for one PO. */
function poAwaitingLines($po_id = 0) {
    global $mysqli;
    $po_id = intval($po_id);
    $by_po = [];
    if (!poReady()) {
        return $by_po;
    }
    $sql = mysqli_query($mysqli, "SELECT po_items.*, po_prefix, po_number, po_status, po_order_date, po_vendor_reference, po_currency_code, vendor_name, client_name,
            product_name, product_track_stock, product_asset_type
        FROM po_items
        JOIN purchase_orders ON po_id = po_item_po_id
        LEFT JOIN vendors ON vendors.vendor_id = po_vendor_id
        LEFT JOIN clients ON client_id = po_item_client_id AND po_item_client_id > 0
        LEFT JOIN products ON product_id = po_item_product_id AND po_item_product_id > 0
        WHERE po_status IN ('ordered', 'partial') AND po_item_received < po_item_quantity" . ($po_id ? " AND po_id = $po_id" : '') . poScopeSql('po_id') . "
        ORDER BY po_order_date ASC, po_id ASC, po_item_order ASC, po_item_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $by_po[intval($row['po_item_po_id'])][] = $row;
    }
    return $by_po;
}

/** The asset type a line's units become - its product's, else a guess from its name ('' when it is not hardware). */
function poAssetTypeFor(array $line) {
    $type = productAssetType($line['product_asset_type'] ?? '');
    if ($type !== '') {
        return $type;
    }
    $name = mb_strtolower((string) $line['po_item_name']);
    $guesses = [
        'Laptop' => ['laptop', 'notebook', 'latitude', 'thinkpad', 'macbook', 'elitebook', 'probook'],
        'Desktop' => ['desktop', 'optiplex', 'thinkcentre', 'imac', 'mini pc'],
        'Server' => ['server', 'poweredge', 'proliant', 'nas', 'synology', 'qnap'],
        'Monitor' => ['monitor', 'display'],
        'Switch' => ['switch'],
        'Firewall/Router' => ['firewall', 'router', 'fortigate', 'sonicwall'],
        'Access Point' => ['access point', 'wap'],
        'Printer' => ['printer', 'copier', 'mfp', 'laserjet'],
        'Mobile Phone' => ['iphone', 'galaxy', 'pixel', 'smartphone'],
        'Tablet' => ['ipad', 'tablet'],
        'Phone' => ['yealink', 'polycom', 'handset', 'desk phone'],
        'Camera' => ['camera', 'nvr'],
    ];
    foreach ($guesses as $guess => $words) {
        foreach ($words as $word) {
            if (preg_match('/(?<![a-z0-9])' . preg_quote($word, '/') . '(?:s|es)?(?![a-z0-9])/u', $name)) {
                return productAssetType($guess);
            }
        }
    }
    return '';
}

/** Serials typed one a line (or with commas) - cleaned up, each up to 200 characters. */
function poSerials($text) {
    $serials = [];
    foreach (preg_split('/[\r\n,;]+/', (string) $text) as $serial) {
        $serial = trim($serial);
        if ($serial !== '') {
            $serials[] = mb_substr($serial, 0, 200);
        }
    }
    return $serials;
}

/*
 * Record what came in. $in: po_item_id => ['qty', 'serials', 'asset_type'
 * ('' - don't add them to the client's assets)]. Each line on an ordered PO
 * the agent can see takes up to what is left. Our own stock of a product that
 * tracks it goes on the shelf (Sales edit); a client's line becomes their
 * assets, a unit each, when its type is picked (Support edit). Returns
 * ['lines', 'pos', 'stock', 'assets', 'skipped' => what wasn't done and why].
 */
function poReceive(array $in) {
    global $mysqli, $session_user_id, $session_name;
    $done = ['lines' => 0, 'pos' => [], 'stock' => 0, 'assets' => 0, 'skipped' => []];
    $can_stock = lookupUserPermission('module_sales') >= 2;
    $can_assets = lookupUserPermission('module_support') >= 2;
    foreach ($in as $po_item_id => $received) {
        $qty = poNumberIn(is_array($received) ? ($received['qty'] ?? '') : '');
        if ($qty === null || $qty <= 0) {
            continue;
        }
        $line = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT po_items.*, po_prefix, po_number, po_status, po_vendor_id, po_order_date,
                product_track_stock, product_asset_type, product_make, product_model, product_warranty_months
            FROM po_items JOIN purchase_orders ON po_id = po_item_po_id
            LEFT JOIN products ON product_id = po_item_product_id AND po_item_product_id > 0
            WHERE po_item_id = " . intval($po_item_id)));
        if (!$line || !in_array($line['po_status'], ['ordered', 'partial'], true) || !poAccessible($line['po_item_po_id'])) {
            continue;
        }
        $qty = min($qty, poItemLeft($line));
        if ($qty <= 0) {
            continue;
        }
        $po = ['po_id' => $line['po_item_po_id'], 'po_prefix' => $line['po_prefix'], 'po_number' => $line['po_number']];
        $number = poNumber($po);
        $client_id = intval($line['po_item_client_id']);
        $serials = poSerials($received['serials'] ?? '');
        $stock_id = 0;
        $asset_ids = [];

        mysqli_begin_transaction($mysqli);
        try {
            // Our own stock goes on the shelf
            if (!$client_id && intval($line['po_item_product_id']) && intval($line['product_track_stock'])) {
                if ($can_stock) {
                    $stock_id = productStockMove(intval($line['po_item_product_id']), $qty, 'received', ['unit_cost' => $line['po_item_cost'], 'note' => "Received on $number"]);
                    $done['stock'] += $qty;
                } else {
                    $done['skipped'][] = $line['po_item_name'] . ' was received, but not put on the shelf - that needs Sales edit access';
                }
            }

            // A client's hardware becomes their assets, one a unit, each with its serial
            $type = productAssetType($received['asset_type'] ?? '');
            if ($client_id && $type !== '') {
                if (!$can_assets) {
                    $done['skipped'][] = $line['po_item_name'] . ' was received, but not added to assets - that needs Support edit access';
                } elseif (floor($qty) != $qty) {
                    $done['skipped'][] = $line['po_item_name'] . ' came in as part of a unit, so no asset was made';
                } else {
                    $units = min(PO_ASSETS_MAX, intval($qty));
                    if (intval($qty) > PO_ASSETS_MAX) {
                        $done['skipped'][] = 'Only the first ' . PO_ASSETS_MAX . ' of ' . $line['po_item_name'] . ' became assets - add the rest by hand';
                    }
                    $status = mysqli_fetch_row(mysqli_query($mysqli, "SELECT category_name FROM categories WHERE category_type = 'asset_status' AND category_archived_at IS NULL ORDER BY category_order ASC, category_id ASC LIMIT 1"));
                    $status = escapeSql((string) ($status[0] ?? ''));
                    $date = $line['po_order_date'] ?: date('Y-m-d');
                    $warranty = productWarrantyUntil(date('Y-m-d'), intval($line['product_warranty_months']));
                    $make = escapeSql(mb_substr((string) ($line['product_make'] ?? ''), 0, 200));
                    $model = trim((string) ($line['product_model'] ?? '')) !== '' ? "'" . escapeSql(mb_substr($line['product_model'], 0, 200)) . "'" : 'NULL';
                    $by = poWho();
                    for ($i = 0; $i < $units; $i++) {
                        $name = mb_substr($line['po_item_name'], 0, 190) . ($units > 1 ? ' #' . ($i + 1) : '');
                        $serial = $serials[$i] ?? '';
                        mysqli_query($mysqli, "INSERT INTO assets SET asset_type = '" . escapeSql($type) . "', asset_name = '" . escapeSql($name) . "', asset_make = '$make', asset_model = $model,
                            asset_serial = " . ($serial === '' ? 'NULL' : "'" . escapeSql($serial) . "'") . ", asset_status = '$status',
                            asset_purchase_reference = '" . escapeSql($number) . "', asset_purchase_date = '" . escapeSql($date) . "',
                            asset_warranty_expire = " . ($warranty ? "'$warranty'" : 'NULL') . ", asset_vendor_id = " . intval($line['po_vendor_id']) . ",
                            asset_product_id = " . intval($line['po_item_product_id']) . ", asset_client_id = $client_id");
                        $asset_id = intval(mysqli_insert_id($mysqli));
                        mysqli_query($mysqli, "INSERT INTO asset_interfaces SET interface_name = '01', interface_primary = 1, interface_asset_id = $asset_id");
                        mysqli_query($mysqli, "INSERT INTO asset_history SET asset_history_status = '$status', asset_history_description = '" . escapeSql(mb_substr("$by created $name, received on $number", 0, 255)) . "', asset_history_asset_id = $asset_id");
                        logAudit('Asset', 'Create', escapeSql("$by created asset $name, received on $number"), $client_id, $asset_id);
                        $asset_ids[] = $asset_id;
                    }
                    $done['assets'] += count($asset_ids);
                }
            }

            mysqli_query($mysqli, "INSERT INTO po_receipts SET po_receipt_po_id = " . intval($line['po_item_po_id']) . ", po_receipt_item_id = " . intval($line['po_item_id']) . ",
                po_receipt_quantity = $qty, po_receipt_serials = " . ($serials ? "'" . escapeSql(implode("\n", $serials)) . "'" : 'NULL') . ",
                po_receipt_asset_ids = " . ($asset_ids ? "'" . implode(',', $asset_ids) . "'" : 'NULL') . ", po_receipt_stock_id = " . intval($stock_id) . ", po_receipt_by = " . intval($session_user_id));
            mysqli_query($mysqli, "UPDATE po_items SET po_item_received = po_item_received + $qty WHERE po_item_id = " . intval($line['po_item_id']));
            mysqli_commit($mysqli);
        } catch (Throwable $e) {
            mysqli_rollback($mysqli);
            throw $e;
        }

        poLog($po, 'Edit', poWho() . ' received ' . billingQuantity($qty) . ' x ' . $line['po_item_name'] . " on $number"
            . ($serials ? ' - serials ' . implode(', ', $serials) : '') . ($stock_id ? ' - on the shelf' : '') . ($asset_ids ? ' - ' . count($asset_ids) . ' asset' . (count($asset_ids) === 1 ? '' : 's') . ' made' : ''), $client_id);
        $done['lines']++;
        $done['pos'][intval($line['po_item_po_id'])] = $number;
    }
    foreach (array_keys($done['pos']) as $po_id) {
        poRecalc($po_id);
    }
    return $done;
}

/** A PO's receipts, newest first, with the line and who took it in. */
function poReceipts($po_id) {
    global $mysqli;
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT po_receipts.*, po_item_name, po_item_client_id, user_name FROM po_receipts
        JOIN po_items ON po_item_id = po_receipt_item_id
        LEFT JOIN users ON user_id = po_receipt_by AND po_receipt_by > 0
        WHERE po_receipt_po_id = " . intval($po_id) . " ORDER BY po_receipt_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/*
 * Take a receipt back - received by mistake. The line is waiting for it
 * again, the stock it put on the shelf comes off, and the assets it made are
 * deleted (Full Support access, as deleting an asset needs). True, or false with $error.
 */
function poUndoReceipt($po_receipt_id, &$error = '') {
    global $mysqli;
    $error = '';
    $receipt = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT po_receipts.*, po_item_name, po_item_product_id, po_item_cost, po_item_client_id, po_prefix, po_number, po_status
        FROM po_receipts JOIN po_items ON po_item_id = po_receipt_item_id JOIN purchase_orders ON po_id = po_receipt_po_id
        WHERE po_receipt_id = " . intval($po_receipt_id)));
    if (!$receipt || !poAccessible($receipt['po_receipt_po_id'])) {
        $error = 'That receipt no longer exists.';
        return false;
    }
    if (!in_array($receipt['po_status'], ['ordered', 'partial', 'received'], true)) {
        $error = 'Its purchase order is ' . strtolower(PO_STATUSES[$receipt['po_status']]['label'] ?? $receipt['po_status']) . '.';
        return false;
    }
    $po = ['po_id' => $receipt['po_receipt_po_id'], 'po_prefix' => $receipt['po_prefix'], 'po_number' => $receipt['po_number']];
    $number = poNumber($po);
    $qty = floatval($receipt['po_receipt_quantity']);
    $asset_ids = array_values(array_filter(array_map('intval', explode(',', (string) $receipt['po_receipt_asset_ids']))));
    if ($asset_ids) {
        $asset_ids = array_map('intval', array_column(mysqli_fetch_all(mysqli_query($mysqli, "SELECT asset_id FROM assets WHERE asset_id IN (" . implode(',', $asset_ids) . ")"), MYSQLI_NUM), 0));
    }
    if ($asset_ids && lookupUserPermission('module_support') < 3) {
        $error = 'Undoing it deletes the ' . count($asset_ids) . ' asset' . (count($asset_ids) === 1 ? '' : 's') . ' it made - that needs full Support access.';
        return false;
    }
    if (intval($receipt['po_receipt_stock_id']) && lookupUserPermission('module_sales') < 2) {
        $error = 'Undoing it takes stock off the shelf - that needs Sales edit access.';
        return false;
    }
    $also = [];
    mysqli_begin_transaction($mysqli);
    try {
        foreach ($asset_ids as $asset_id) {
            mysqli_query($mysqli, "DELETE FROM assets WHERE asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM rack_units WHERE unit_asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM ticket_assets WHERE asset_id = $asset_id");
            credentialsForget('asset', $asset_id);
            logAudit('Asset', 'Delete', escapeSql(poWho() . " deleted asset $asset_id - its receipt on $number was undone"), intval($receipt['po_item_client_id']), $asset_id);
        }
        if ($asset_ids) {
            $also[] = count($asset_ids) . ' asset' . (count($asset_ids) === 1 ? '' : 's') . ' deleted';
        }
        if (intval($receipt['po_receipt_stock_id']) && intval($receipt['po_item_product_id'])) {
            productStockMove(intval($receipt['po_item_product_id']), -$qty, 'received', ['unit_cost' => $receipt['po_item_cost'], 'note' => "Receipt on $number undone"]);
            $also[] = billingQuantity($qty) . ' taken off the shelf';
        }
        mysqli_query($mysqli, "UPDATE po_items SET po_item_received = GREATEST(0, po_item_received - $qty) WHERE po_item_id = " . intval($receipt['po_receipt_item_id']));
        mysqli_query($mysqli, "DELETE FROM po_receipts WHERE po_receipt_id = " . intval($receipt['po_receipt_id']));
        mysqli_commit($mysqli);
    } catch (Throwable $e) {
        mysqli_rollback($mysqli);
        $error = 'Not undone - ' . $e->getMessage();
        return false;
    }
    poRecalc($receipt['po_receipt_po_id']);
    poLog($po, 'Edit', poWho() . ' undid the receipt of ' . billingQuantity($qty) . ' x ' . $receipt['po_item_name'] . " on $number" . ($also ? ' (' . implode(', ', $also) . ')' : ''), intval($receipt['po_item_client_id']));
    return true;
}

/* ------------------------------------------------------------- Tracking */

/** A PO's tracking numbers, newest first. */
function poTracking($po_id) {
    global $mysqli;
    return mysqli_fetch_all(mysqli_query($mysqli, "SELECT * FROM po_tracking WHERE po_tracking_po_id = " . intval($po_id) . " ORDER BY po_tracking_id DESC"), MYSQLI_ASSOC);
}

/** The tracking of several POs - po_id => rows. */
function poTrackingFor(array $po_ids) {
    global $mysqli;
    $po_ids = array_values(array_filter(array_map('intval', $po_ids)));
    $by_po = [];
    if ($po_ids) {
        $sql = mysqli_query($mysqli, "SELECT * FROM po_tracking WHERE po_tracking_po_id IN (" . implode(',', $po_ids) . ") ORDER BY po_tracking_id DESC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $by_po[intval($row['po_tracking_po_id'])][] = $row;
        }
    }
    return $by_po;
}

/** A tracking number as a link to its carrier's page (or the number), escaped. */
function poTrackingHtml(array $row) {
    $label = (PO_CARRIERS[$row['po_tracking_carrier']][0] ?? 'Tracking') . ' ' . $row['po_tracking_number'];
    $url = poTrackingUrl($row['po_tracking_carrier'], $row['po_tracking_number']);
    $eta = $row['po_tracking_eta'] ? ' <span class="text-body-secondary">- due ' . escapeHtml(date('M j', strtotime($row['po_tracking_eta']))) . '</span>' : '';
    return ($url !== '' ? '<a href="' . escapeHtml($url) . '" target="_blank" rel="noopener noreferrer">' . escapeHtml($label) . ' <i class="fas fa-external-link-alt small"></i></a>' : escapeHtml($label)) . $eta;
}

/** Add a tracking number. True, or false with $error. */
function poTrackingAdd(array $po, $carrier, $number, $eta, &$error = '') {
    global $mysqli;
    $error = '';
    $carrier = isset(PO_CARRIERS[$carrier]) ? $carrier : 'other';
    $number = mb_substr(trim((string) $number), 0, 100);
    if ($number === '') {
        $error = 'Type the tracking number.';
        return false;
    }
    if (!in_array($po['po_status'], ['ordered', 'partial', 'received'], true)) {
        $error = 'Tracking goes on a purchase order once it is ordered.';
        return false;
    }
    $eta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $eta) && strtotime($eta) ? "'$eta'" : 'NULL';
    mysqli_query($mysqli, "INSERT INTO po_tracking SET po_tracking_po_id = " . intval($po['po_id']) . ", po_tracking_carrier = '$carrier', po_tracking_number = '" . escapeSql($number) . "', po_tracking_eta = $eta");
    poLog($po, 'Edit', poWho() . ' added ' . PO_CARRIERS[$carrier][0] . " tracking $number to " . poNumber($po), poLogClient($po['po_id']));
    return true;
}

/** Take a tracking number off. */
function poTrackingRemove(array $po, $po_tracking_id) {
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM po_tracking WHERE po_tracking_id = " . intval($po_tracking_id) . " AND po_tracking_po_id = " . intval($po['po_id'])));
    if (!$row) {
        return false;
    }
    mysqli_query($mysqli, "DELETE FROM po_tracking WHERE po_tracking_id = " . intval($row['po_tracking_id']));
    poLog($po, 'Edit', poWho() . ' took tracking ' . $row['po_tracking_number'] . ' off ' . poNumber($po), poLogClient($po['po_id']));
    return true;
}

/* ------------------------------------------------------------- The vendor's bill */

/** The expense its vendor bill was recorded as, or null. */
function poExpense(array $po) {
    global $mysqli;
    if (!intval($po['po_expense_id'])) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT expense_id, expense_amount, expense_currency_code, expense_date, expense_reference, expense_archived_at
        FROM expenses WHERE expense_id = " . intval($po['po_expense_id']))) ?: null;
}

/*
 * Record the vendor's bill as an expense - through the expense rules, so its
 * account, category and currency are checked as anywhere. $in raw: date,
 * amount, account, category, reference (their invoice number), description.
 * One client on the PO makes it theirs. The stock this PO put on the shelf
 * is noted as bought on it. The expense id, or 0 with $error.
 */
function poRecordBill(array $po, array $in, &$error = '') {
    global $mysqli;
    $error = '';
    if (!poLock('bill_' . intval($po['po_id']))) {
        $error = 'Someone else is recording this bill right now - try again.';
        return 0;
    }
    try {
        $po = poRow($po['po_id']) ?: $po;
        if (poExpense($po)) {
            $error = 'Its vendor bill is recorded already.';
            return 0;
        }
        if (in_array($po['po_status'], ['draft', 'pending', 'cancelled'], true)) {
            $error = 'The vendor bill is recorded once it is ordered.';
            return 0;
        }
        $number = poNumber($po);
        $reference = trim((string) ($in['reference'] ?? ''));
        $expense_id = expenseSave([
            'date' => (string) ($in['date'] ?? ''), 'amount' => (string) ($in['amount'] ?? ''), 'vendor' => intval($po['po_vendor_id']),
            'category' => intval($in['category'] ?? 0), 'account' => (string) ($in['account'] ?? ''), 'payment_method' => (string) ($in['payment_method'] ?? ''),
            'client_id' => poLogClient($po['po_id']), 'reference' => $reference !== '' ? $reference : $number,
            'description' => trim((string) ($in['description'] ?? '')) !== '' ? trim((string) $in['description']) : "$number - " . $po['vendor_name'],
        ], 0, $error);
        if (!$expense_id) {
            return 0;
        }
        mysqli_query($mysqli, "UPDATE purchase_orders SET po_expense_id = $expense_id WHERE po_id = " . intval($po['po_id']));
        // What this PO put on the shelf was bought on this bill
        mysqli_query($mysqli, "UPDATE product_stock JOIN po_receipts ON po_receipt_stock_id = stock_id SET stock_expense_id = $expense_id
            WHERE po_receipt_po_id = " . intval($po['po_id']) . " AND stock_expense_id IS NULL");
        poLog($po, 'Edit', poWho() . " recorded the vendor bill for $number as an expense" . ($reference !== '' ? " - their invoice $reference" : ''), poLogClient($po['po_id']));
        logAudit('Expense', 'Create', escapeSql(poWho() . " recorded the vendor bill for $number"), poLogClient($po['po_id']), $expense_id);
        return $expense_id;
    } finally {
        poUnlock('bill_' . intval($po['po_id']));
    }
}

/* ------------------------------------------------------------- The PDF, and emailing it */

/** The PO as a PDF, in the look of the invoices - ['name', 'data']. */
function poPdf(array $po) {
    require_once __DIR__ . '/../libs/TCPDF/tcpdf.php';
    require_once __DIR__ . '/billing_pdf.php';
    $company = billingCompany();
    $items = poItems($po['po_id']);
    $number = poNumber($po);
    $currency = (string) $po['po_currency_code'];
    $e = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    $money = function ($amount) use ($currency, $e) {
        return $e(poMoney($amount, $currency));
    };
    $grey = '#6c757d';
    $label = function ($text) use ($grey, $e) {
        return '<span style="font-size:6.5pt; color:' . $grey . '; font-weight:bold;">' . $e(strtoupper($text)) . '</span>';
    };

    $format = in_array($company['company_country'] ?? '', ['United States', 'USA', 'US', 'Canada'], true) ? 'LETTER' : 'A4';
    $pdf = new BillingPdf('P', 'mm', $format, true, 'UTF-8', false);
    $pdf->SetCreator('ITFlow');
    $pdf->SetAuthor((string) ($company['company_name'] ?? ''));
    $pdf->SetTitle("Purchase Order $number");
    $pdf->SetMargins(16, 14, 16);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(12);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->setFontSubsetting(true);
    $pdf->setImageScale(1.5);
    $pdf->footer_label = trim(($company['company_name'] ?? '') . " · Purchase Order $number", ' ·');
    $pdf->AddPage();
    $margins = $pdf->getMargins();
    $width = $pdf->getPageWidth() - $margins['left'] - $margins['right'];

    // Who it is from, and what it is
    $from_lines = array_filter([
        formatAddress($company['company_address'] ?? '', $company['company_city'] ?? '', $company['company_state'] ?? '', $company['company_zip'] ?? '', $company['company_country'] ?? '', "\n"),
        implode('  ·  ', array_filter([$company['company_email'] ?? '', $company['phone_display'] ?? ''])),
        $company['company_website'] ?? '',
    ]);
    $logo = !empty($company['logo_path']) ? '<img src="' . $e($company['logo_path']) . '" height="42"><br>' : '';
    $dates = ['Date' => $po['po_order_date'] ?: date('Y-m-d', strtotime($po['po_created_at']))];
    if ($po['po_vendor_reference']) {
        $dates['Your order'] = $po['po_vendor_reference'];
    }
    if ($po['vendor_account_number']) {
        $dates['Our account'] = $po['vendor_account_number'];
    }
    $dates_html = '';
    foreach ($dates as $date_label => $value) {
        $dates_html .= '<tr><td align="right" style="color:' . $grey . ';">' . $e($date_label) . '</td><td align="right" width="34mm">' . $e($value) . '</td></tr>';
    }
    $html = '<table cellpadding="0" cellspacing="0" width="100%"><tr>
        <td width="56%">' . $logo . '<span style="font-size:12pt; font-weight:bold;">' . $e($company['company_name'] ?? '') . '</span><br><span style="font-size:7.5pt; color:' . $grey . '; line-height:1.4;">' . nl2br($e(implode("\n", $from_lines))) . '</span></td>
        <td width="44%" align="right"><span style="font-size:8pt; color:#0a58ca; font-weight:bold; letter-spacing:2px;">PURCHASE ORDER</span><br><span style="font-size:15pt; font-weight:bold;">' . $e($number) . '</span><br>
            <table cellpadding="1.2" cellspacing="0" width="100%" style="font-size:8pt;">' . $dates_html . '</table></td>
    </tr></table>';
    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->SetDrawColor(222, 226, 230);
    $pdf->SetLineWidth(0.2);
    $pdf->Ln(1);
    $pdf->Line($margins['left'], $pdf->GetY(), $margins['left'] + $width, $pdf->GetY());
    $pdf->Ln(4);

    // Who it is to, and where it goes
    $vendor_lines = array_filter([
        $po['vendor_contact_name'] ?? '',
        implode('  ·  ', array_filter([$po['vendor_contact_email'] ?: ($po['vendor_email'] ?? ''), !empty($po['vendor_phone']) ? formatPhoneNumber($po['vendor_phone'], $po['vendor_phone_country_code']) : ''])),
    ]);
    $ship = preg_split('/\r?\n/', trim((string) $po['po_ship_to']), 2);
    $html = '<table cellpadding="0" cellspacing="0" width="100%"><tr>
        <td width="50%">' . $label('Vendor') . '<br><span style="font-size:10pt; font-weight:bold;">' . $e($po['vendor_name']) . '</span><br><span style="font-size:7.5pt; color:' . $grey . '; line-height:1.4;">' . nl2br($e(implode("\n", $vendor_lines))) . '</span></td>
        <td width="50%">' . $label('Ship to') . '<br><span style="font-size:10pt; font-weight:bold;">' . $e($ship[0] ?? '') . '</span><br><span style="font-size:7.5pt; color:' . $grey . '; line-height:1.4;">' . nl2br($e($ship[1] ?? '')) . '</span></td>
    </tr></table>';
    $pdf->writeHTML($html, true, false, true, false, '');

    // Not yet a firm order - or never one
    $stamp = null;
    if (in_array($po['po_status'], ['draft', 'pending'], true)) {
        $stamp = [$po['po_status'] === 'pending' ? 'NOT YET APPROVED' : 'DRAFT', [176, 42, 55]];
    } elseif ($po['po_status'] === 'cancelled') {
        $stamp = ['CANCELLED', [220, 53, 69]];
    }
    if ($stamp) {
        $y = $pdf->GetY();
        $pdf->SetAlpha(0.28);
        $pdf->StartTransform();
        $pdf->Rotate(12, $margins['left'] + $width / 2, 21);
        $pdf->SetFont('dejavusans', 'B', 18);
        $pdf->SetTextColor(...$stamp[1]);
        $pdf->SetDrawColor(...$stamp[1]);
        $pdf->SetLineWidth(0.8);
        $stamp_width = $pdf->GetStringWidth($stamp[0]) + 8;
        $pdf->SetXY($margins['left'] + $width / 2 - $stamp_width / 2, 15.5);
        $pdf->Cell($stamp_width, 11, $stamp[0], 1, 0, 'C');
        $pdf->StopTransform();
        $pdf->SetAlpha(1);
        $pdf->SetDrawColor(222, 226, 230);
        $pdf->SetLineWidth(0.2);
        $pdf->SetTextColor(33, 37, 41);
        $pdf->SetXY($margins['left'], $y);
    }
    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->Ln(3);

    // The lines
    $th = 'font-size:6.5pt; color:' . $grey . '; font-weight:bold; border-bottom:0.6px solid #adb5bd;';
    $td = 'border-bottom:0.3px solid #dee2e6;';
    $html = '<table cellpadding="4" cellspacing="0" width="100%"><thead><tr>
        <td width="56%" style="' . $th . '">ITEM</td><td width="10%" align="right" style="' . $th . '">QTY</td>
        <td width="16%" align="right" style="' . $th . '">UNIT COST</td><td width="18%" align="right" style="' . $th . '">AMOUNT</td></tr></thead>';
    $subtotal = 0;
    foreach ($items as $item) {
        $amount = round(floatval($item['po_item_quantity']) * floatval($item['po_item_cost']), 2);
        $subtotal += $amount;
        $html .= '<tr nobr="true"><td width="56%" style="' . $td . '"><b>' . $e($item['po_item_name']) . '</b>'
            . ($item['po_item_vendor_part'] ? '<br><span style="font-size:7.5pt;">Part <font face="courier">' . $e($item['po_item_vendor_part']) . '</font></span>' : '')
            . (trim((string) $item['po_item_description']) !== '' ? '<br><span style="font-size:7.5pt; color:' . $grey . ';">' . nl2br($e($item['po_item_description'])) . '</span>' : '') . '</td>
            <td width="10%" align="right" style="' . $td . '">' . $e(billingQuantity($item['po_item_quantity'])) . '</td>
            <td width="16%" align="right" style="' . $td . '">' . $money($item['po_item_cost']) . '</td>
            <td width="18%" align="right" style="' . $td . '"><b>' . $money($amount) . '</b></td></tr>';
    }
    if (!$items) {
        $html .= '<tr><td colspan="4" align="center" style="color:' . $grey . ';">Nothing on it yet.</td></tr>';
    }
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(3);

    // The note, and what it all comes to
    $rows = '<tr><td style="color:' . $grey . ';">Subtotal</td><td align="right">' . $money($subtotal) . '</td></tr>';
    if (floatval($po['po_shipping']) > 0) {
        $rows .= '<tr><td style="color:' . $grey . ';">Shipping</td><td align="right">' . $money($po['po_shipping']) . '</td></tr>';
    }
    $rows .= '<tr><td style="border-top:0.6px solid #adb5bd; font-size:10pt;"><b>Total</b></td><td align="right" style="border-top:0.6px solid #adb5bd; font-size:10pt;"><b>' . $money($po['po_total']) . '</b></td></tr>';
    $note = trim((string) $po['po_note']);
    $html = '<table cellpadding="0" cellspacing="0" width="100%"><tr nobr="true">
        <td width="54%">' . ($note !== '' ? $label('Notes') . '<br><span style="font-size:8pt;">' . nl2br($e($note)) . '</span>' : '') . '</td>
        <td width="6%"></td>
        <td width="40%"><table cellpadding="2.5" cellspacing="0" width="100%" style="font-size:8.5pt;">' . $rows . '</table></td>
    </tr></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(6);
    $pdf->SetFont('dejavusans', '', 7.5);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->MultiCell($width, 4, "Please quote $number on your invoice and packing slip.", 0, 'C', false, 1);

    $name = preg_replace('/[^A-Za-z0-9_\-]+/', '_', trim('Purchase_Order_' . $number . '_' . $po['vendor_name'], '_')) . '.pdf';
    return ['name' => $name, 'data' => $pdf->Output($name, 'S')];
}

/*
 * Email the PO to the vendor (or $to) with its PDF, from the invoice
 * mailbox - with the agent's own words. True, or false with $error.
 */
function poEmail(array $po, $to, $message, &$error = '') {
    $error = '';
    $to = trim((string) $to);
    if (!emailReady()) {
        $error = 'Mail isn\'t set up to send - Admin > Settings > Mail.';
        return false;
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = "$to isn't an email address.";
        return false;
    }
    $file = billingEmailPdf(poPdf($po));
    if (!$file) {
        $error = "The PDF couldn't be saved for the email - check uploads/tmp can be written to.";
        return false;
    }
    $number = poNumber($po);
    $company = billingCompany();
    $company_name = (string) ($company['company_name'] ?? '');
    $lines = [];
    foreach (poItems($po['po_id']) as $item) {
        $lines[] = [$item['po_item_name'] . ($item['po_item_vendor_part'] ? ' (' . $item['po_item_vendor_part'] . ')' : ''), billingQuantity($item['po_item_quantity'])];
    }
    $sent = emailQueue([['email' => $to, 'name' => (string) ($po['vendor_contact_name'] ?: $po['vendor_name']),
        'greet' => (string) ($po['vendor_contact_name'] ?: ''), 'footnote' => "You get this because $company_name buys from " . $po['vendor_name'] . '.']], "Purchase order $number - $company_name", [
        'kind' => 'Purchase order', 'tone' => 'primary', 'preview' => "Purchase order $number from $company_name",
        'title' => "Purchase order $number",
        'intro' => ["Please find our purchase order $number attached. Please quote it on your invoice and packing slip, and send your order number and tracking when it ships."],
        'note' => trim((string) $message),
        'summary' => ['Purchase order' => $number, 'Total' => poMoney($po['po_total'], $po['po_currency_code']), 'Ship to' => str_replace("\n", ', ', trim((string) $po['po_ship_to']))],
        'lines' => $lines ? ['head' => ['Item', 'Qty'], 'align' => ['left', 'right'], 'rows' => $lines] : null,
    ], ['from' => billingEmailFrom('invoice'), 'attachments' => [$file]]);
    if (!$sent) {
        $error = "It couldn't be queued.";
        return false;
    }
    poLog($po, 'Edit', poWho() . " emailed $number to $to", poLogClient($po['po_id']));
    return true;
}

/* ------------------------------------------------------------- The side menu */

/** What the PURCHASING menu counts - to_order, pending (waiting for this agent's approval), arriving (lines still to come in). */
function poNavCounts() {
    global $mysqli, $session_user_id, $session_is_admin;
    $counts = ['to_order' => 0, 'pending' => 0, 'arriving' => 0];
    if (poLevel() < 1) {
        return $counts;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
        (SELECT COUNT(*) FROM order_items WHERE order_item_status = 'waiting'" . clientScopeSql('order_item_client_id') . ") AS to_order,
        (SELECT COUNT(*) FROM purchase_orders WHERE po_status = 'pending'" . (poLevel() >= 3 && empty($session_is_admin) ? " AND po_created_by <> " . intval($session_user_id) : '') . poScopeSql('po_id') . ") AS pending,
        (SELECT COUNT(*) FROM po_items JOIN purchase_orders ON po_id = po_item_po_id WHERE po_status IN ('ordered', 'partial') AND po_item_received < po_item_quantity" . poScopeSql('po_id') . ") AS arriving"));
    $counts['to_order'] = intval($row['to_order'] ?? 0);
    $counts['pending'] = poLevel() >= 3 ? intval($row['pending'] ?? 0) : 0;
    $counts['arriving'] = intval($row['arriving'] ?? 0);
    return $counts;
}
