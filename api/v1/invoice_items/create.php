<?php

/*
 * API - Invoice Items - Create
 * POST /api/v1/invoice_items/create.php
 *
 * Adds a line item to an existing invoice.
 *
 * Parameters (POST, JSON body):
 *   api_key          required - Your API key
 *   client_id        required - Must match the invoice's client (restricted
 *                               keys only; unrestricted/admin keys may omit)
 *   invoice_id       required - Invoice to add the item to
 *   name             required - Item name
 *   description      optional - Item description
 *   qty              required - Quantity
 *   price            required - Unit price
 *   tax_id           optional - Tax ID (default 0, no tax), or 'default' for the client's usual rate
 *   item_order       optional - Display order (default 0)
 *   product_id       optional - Product ID for inventory tracking
 *
 * Security:
 *   - The parent invoice is loaded through apiClientScopeSql(), so a restricted
 *     key cannot modify another client's invoice.
 *   - The supplied client_id must match the invoice's client.
 *   - Inventory is only adjusted for products whose stock is tracked.
 *   - Invoice totals are automatically recalculated.
 */

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse input
$invoice_id = intval($_POST['invoice_id'] ?? 0);
$name = escapeSql(substr($_POST['name'], 0, 200) ?? '');
$description = escapeSql($_POST['description'] ?? '');
$qty = floatval($_POST['qty'] ?? 0);
$price = floatval($_POST['price'] ?? 0);
// 'default' takes the rate a new line for the client gets (functions/tax.php: taxDefaultFor())
$tax_id_posted = (string) ($_POST['tax_id'] ?? '0');
$tax_id = intval($tax_id_posted);
$item_order = intval($_POST['item_order'] ?? 0);
$product_id = intval($_POST['product_id'] ?? 0);

$insert_id = false;

if (
    !empty($invoice_id)
    && !empty($name)
    && $qty > 0
) {

    // Load invoice, scoped to API key permissions
    $invoice_sql = mysqli_query(
        $mysqli,
        "SELECT *
         FROM invoices
         WHERE invoice_id = $invoice_id
         AND invoice_status != 'Paid'
           AND 1=1 " . apiClientScopeSql('invoice_client_id') . "
         LIMIT 1"
    );

    $invoice_row = $invoice_sql ? mysqli_fetch_assoc($invoice_sql) : null;

    // Ensure supplied client matches invoice client
    if ($invoice_row && $client_id != 0 && intval($invoice_row['invoice_client_id']) !== $client_id) {
        $invoice_row = null;
    }

    if ($invoice_row) {

        $client_id = intval($invoice_row['invoice_client_id']);
        $invoice_prefix = escapeSql($invoice_row['invoice_prefix']);
        $invoice_number = intval($invoice_row['invoice_number']);
        $invoice_discount = floatval($invoice_row['invoice_discount_amount']);

        $subtotal = $price * $qty;
        $take_stock = false;

        // Product inventory
        if ($product_id) {

            // Only products whose stock is tracked move stock
            if (intval(getFieldById('products', $product_id, 'product_track_stock')) === 1) {

                $stock_sql = mysqli_query(
                    $mysqli,
                    "SELECT COALESCE(SUM(stock_qty),0) AS available_stock
                     FROM product_stock
                     WHERE stock_product_id = $product_id"
                );

                $stock_row = mysqli_fetch_assoc($stock_sql);
                $available_stock = floatval($stock_row['available_stock']);

                if ($available_stock >= $qty) {

                    // Taken off the shelf once the line is in
                    $take_stock = true;

                } else {

                    logAudit(
                        "API",
                        "Failure",
                        "Failed adding item $name to invoice $invoice_prefix$invoice_number via API ($api_key_name) due to insufficient stock",
                        $client_id
                    );

                    require_once '../create_output.php';
                    exit;

                }

            }

        }

        // Tax - at the rate's parts, each to the cent; a rate that doesn't exist is none
        if ($tax_id_posted === 'default') {
            $tax_id = $product_id ? taxDefaultFor($client_id, 'product', intval(getFieldById('products', $product_id, 'product_tax_id'))) : taxDefaultFor($client_id);
        }
        if ($tax_id > 0 && !taxRow($tax_id)) {
            $tax_id = 0;
        }
        [$subtotal, $tax_amount, $total] = billingLineTax($qty, $price, $tax_id);

        $insert_sql = mysqli_query(
            $mysqli,
            "INSERT INTO invoice_items SET
                item_name = '$name',
                item_description = '$description',
                item_quantity = $qty,
                item_price = $price,
                item_subtotal = $subtotal,
                item_tax = $tax_amount,
                item_total = $total,
                item_order = $item_order,
                item_tax_id = $tax_id,
                item_product_id = $product_id,
                item_invoice_id = $invoice_id"
        );

        if ($insert_sql) {

            $insert_id = mysqli_insert_id($mysqli);

            if ($take_stock) {
                productStockMove($product_id, -$qty, 'sold', ['invoice_id' => $invoice_id, 'item_id' => $insert_id, 'note' => stripslashes($invoice_prefix) . "$invoice_number - API"]);
            }

            // The invoice's total - and each line's tax after any discount (functions/billing.php)
            billingRecalc('invoice', $invoice_id);

            logAudit(
                "Invoice",
                "Edit",
                "Added item $name to invoice $invoice_prefix$invoice_number via API ($api_key_name)",
                $client_id,
                $invoice_id
            );

            logAudit(
                "API",
                "Success",
                "Added item $name to invoice $invoice_prefix$invoice_number via API ($api_key_name)",
                $client_id
            );

        }

    }

}

// Output
require_once '../create_output.php';