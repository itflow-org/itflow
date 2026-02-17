<?php

function bit_agent_ticket_items_dispatch($mysqli, int $session_user_id, string $session_name): bool {
    if (isset($_POST['add_ticket_item'])) {
        enforceUserPermission('module_support', 2);
        validateCSRFToken($_POST['csrf_token']);

        $ticket_id = intval($_POST['ticket_id']);
        $product_id = intval($_POST['product_id'] ?? 0);
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = mysqli_real_escape_string($mysqli, $_POST['description'] ?? '');
        $quantity = floatval($_POST['quantity'] ?? 1);
        $unit_price = floatval($_POST['unit_price'] ?? 0);
        $tax_id = intval($_POST['tax_id'] ?? 0);
        $billable = intval($_POST['billable'] ?? 0);

        $sql_ticket = mysqli_query($mysqli, "SELECT ticket_client_id, ticket_prefix, ticket_number, ticket_subject FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");
        if (mysqli_num_rows($sql_ticket) == 0) {
            flash_alert('Ticket not found', 'error');
            redirect('tickets.php');
        }

        $t = mysqli_fetch_array($sql_ticket);
        $client_id = intval($t['ticket_client_id']);
        $ticket_prefix = sanitizeInput($t['ticket_prefix']);
        $ticket_number = intval($t['ticket_number']);
        $ticket_subject = sanitizeInput($t['ticket_subject']);

        if ($product_id > 0) {
            $sql_product = mysqli_query($mysqli, "SELECT product_name, product_description, product_price, product_tax_id FROM products WHERE product_id = $product_id LIMIT 1");
            if (mysqli_num_rows($sql_product) == 1) {
                $p = mysqli_fetch_array($sql_product);
                if ($name === '') {
                    $name = sanitizeInput($p['product_name']);
                }
                if ($description === '') {
                    $description = mysqli_real_escape_string($mysqli, $p['product_description'] ?? '');
                }
                if ($unit_price == 0) {
                    $unit_price = floatval($p['product_price']);
                }
                if ($tax_id == 0) {
                    $tax_id = intval($p['product_tax_id']);
                }
            }
        }

        if ($name === '') {
            flash_alert('Item name is required', 'error');
            redirect("ticket.php?client_id=$client_id&ticket_id=$ticket_id");
        }

        $tax_id_sql = $tax_id > 0 ? $tax_id : 'NULL';
        $product_id_sql = $product_id > 0 ? $product_id : 'NULL';

        mysqli_query(
            $mysqli,
            "INSERT INTO ticket_items SET
                ticket_item_ticket_id = $ticket_id,
                ticket_item_product_id = $product_id_sql,
                ticket_item_name = '$name',
                ticket_item_description = '$description',
                ticket_item_quantity = $quantity,
                ticket_item_unit_price = $unit_price,
                ticket_item_tax_id = $tax_id_sql,
                ticket_item_billable = $billable,
                ticket_item_created_by = $session_user_id"
        );

        logAction('Ticket', 'Edit', "$session_name added item to ticket $ticket_prefix$ticket_number - $ticket_subject", $client_id, $ticket_id);
        flash_alert('Item added');
        redirect("ticket.php?client_id=$client_id&ticket_id=$ticket_id");
    }

    if (isset($_POST['edit_ticket_item'])) {
        enforceUserPermission('module_support', 2);
        validateCSRFToken($_POST['csrf_token']);

        $ticket_item_id = intval($_POST['ticket_item_id']);
        $product_id = intval($_POST['product_id'] ?? 0);
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = mysqli_real_escape_string($mysqli, $_POST['description'] ?? '');
        $quantity = floatval($_POST['quantity'] ?? 1);
        $unit_price = floatval($_POST['unit_price'] ?? 0);
        $tax_id = intval($_POST['tax_id'] ?? 0);
        $billable = intval($_POST['billable'] ?? 0);
        $invoiced_ref = sanitizeInput($_POST['invoiced_ref'] ?? '');

        $sql_item = mysqli_query($mysqli, "SELECT ticket_item_ticket_id FROM ticket_items WHERE ticket_item_id = $ticket_item_id LIMIT 1");
        if (mysqli_num_rows($sql_item) == 0) {
            flash_alert('Item not found', 'error');
            redirect('tickets.php');
        }

        $i = mysqli_fetch_array($sql_item);
        $ticket_id = intval($i['ticket_item_ticket_id']);

        $sql_ticket = mysqli_query($mysqli, "SELECT ticket_client_id FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");
        if (mysqli_num_rows($sql_ticket) == 0) {
            flash_alert('Ticket not found', 'error');
            redirect('tickets.php');
        }

        $t = mysqli_fetch_array($sql_ticket);
        $client_id = intval($t['ticket_client_id']);

        if ($name === '') {
            flash_alert('Item name is required', 'error');
            redirect("ticket.php?client_id=$client_id&ticket_id=$ticket_id");
        }

        $tax_id_sql = $tax_id > 0 ? $tax_id : 'NULL';
        $product_id_sql = $product_id > 0 ? $product_id : 'NULL';
        $invoiced_ref_sql = $invoiced_ref !== '' ? "'" . $invoiced_ref . "'" : 'NULL';

        mysqli_query(
            $mysqli,
            "UPDATE ticket_items SET
                ticket_item_product_id = $product_id_sql,
                ticket_item_name = '$name',
                ticket_item_description = '$description',
                ticket_item_quantity = $quantity,
                ticket_item_unit_price = $unit_price,
                ticket_item_tax_id = $tax_id_sql,
                ticket_item_billable = $billable,
                ticket_item_invoiced_ref = $invoiced_ref_sql,
                ticket_item_updated_at = NOW()
            WHERE ticket_item_id = $ticket_item_id"
        );

        logAction('Ticket', 'Edit', "$session_name updated ticket item", $client_id, $ticket_id);
        flash_alert('Item updated');
        redirect("ticket.php?client_id=$client_id&ticket_id=$ticket_id");
    }

    if (isset($_GET['delete_ticket_item'])) {
        enforceUserPermission('module_support', 2);
        validateCSRFToken($_GET['csrf_token']);

        $ticket_item_id = intval($_GET['delete_ticket_item']);

        $sql_item = mysqli_query($mysqli, "SELECT ticket_item_ticket_id FROM ticket_items WHERE ticket_item_id = $ticket_item_id LIMIT 1");
        if (mysqli_num_rows($sql_item) == 0) {
            flash_alert('Item not found', 'error');
            redirect('tickets.php');
        }

        $i = mysqli_fetch_array($sql_item);
        $ticket_id = intval($i['ticket_item_ticket_id']);

        $sql_ticket = mysqli_query($mysqli, "SELECT ticket_client_id FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");
        $t = mysqli_fetch_array($sql_ticket);
        $client_id = intval($t['ticket_client_id']);

        mysqli_query($mysqli, "DELETE FROM ticket_items WHERE ticket_item_id = $ticket_item_id");

        logAction('Ticket', 'Edit', "$session_name deleted ticket item", $client_id, $ticket_id);
        flash_alert('Item deleted', 'error');
        redirect("ticket.php?client_id=$client_id&ticket_id=$ticket_id");
    }

    return false;
}
