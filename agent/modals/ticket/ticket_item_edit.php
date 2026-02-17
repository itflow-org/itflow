<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$ticket_item_id = intval($_GET['ticket_item_id'] ?? 0);

$item_sql = mysqli_query(
    $mysqli,
    "SELECT
        ticket_items.*,
        tickets.ticket_client_id,
        tickets.ticket_closed_at
    FROM ticket_items
    INNER JOIN tickets ON ticket_items.ticket_item_ticket_id = tickets.ticket_id
    LEFT JOIN clients ON tickets.ticket_client_id = client_id
    WHERE ticket_items.ticket_item_id = $ticket_item_id
    $access_permission_query
    LIMIT 1"
);

if (mysqli_num_rows($item_sql) !== 1) {
    ob_start();
    ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title">Edit Product</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <div class="alert alert-danger mb-0">Item not found or no access.</div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
    <?php
    require_once '../../../includes/modal_footer.php';
}

$item = mysqli_fetch_array($item_sql);

$ticket_id = intval($item['ticket_item_ticket_id']);
$client_id = intval($item['ticket_client_id']);
$ticket_closed_at = nullable_htmlentities($item['ticket_closed_at']);

if (!empty($ticket_closed_at)) {
    ob_start();
    ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title">Edit Product</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <div class="alert alert-warning mb-0">Ticket is closed.</div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
    <?php
    require_once '../../../includes/modal_footer.php';
}

$product_id = intval($item['ticket_item_product_id'] ?? 0);
$name = nullable_htmlentities($item['ticket_item_name']);
$description = nullable_htmlentities($item['ticket_item_description']);
$quantity = floatval($item['ticket_item_quantity']);
$unit_price = floatval($item['ticket_item_unit_price']);
$tax_id = intval($item['ticket_item_tax_id'] ?? 0);
$billable = intval($item['ticket_item_billable'] ?? 0);
$invoiced_at = nullable_htmlentities($item['ticket_item_invoiced_at']);
$invoiced_ref = nullable_htmlentities($item['ticket_item_invoiced_ref']);

$products_sql = mysqli_query($mysqli, "SELECT product_id, product_name FROM products WHERE product_archived_at IS NULL ORDER BY product_name ASC");
$taxes_sql = mysqli_query($mysqli, "SELECT tax_id, tax_name, tax_percent FROM taxes WHERE tax_archived_at IS NULL ORDER BY tax_name ASC");

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-box-open mr-2"></i>Edit Product</h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="ticket_item_id" value="<?= $ticket_item_id ?>">
        <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
        <input type="hidden" name="client_id" value="<?= $client_id ?>">

        <div class="form-group">
            <label>Product</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-fw fa-box"></i></span>
                </div>
                <select class="form-control select2" name="product_id">
                    <option value="0" <?= $product_id === 0 ? 'selected' : '' ?>>Custom</option>
                    <?php while ($row = mysqli_fetch_array($products_sql)) { ?>
                        <?php $pid = intval($row['product_id']); ?>
                        <option value="<?= $pid ?>" <?= $pid === $product_id ? 'selected' : '' ?>><?= nullable_htmlentities($row['product_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-fw fa-tag"></i></span>
                </div>
                <input type="text" class="form-control" name="name" maxlength="255" value="<?= $name ?>" required autofocus>
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea class="form-control" name="description" rows="3"><?= $description ?></textarea>
        </div>

        <div class="form-row">
            <div class="col">
                <div class="form-group">
                    <label>Quantity <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-fw fa-hashtag"></i></span>
                        </div>
                        <input type="text" class="form-control" inputmode="decimal" pattern="[0-9]*\.?[0-9]{0,2}" name="quantity" value="<?= rtrim(rtrim(number_format($quantity, 2), '0'), '.') ?>" required>
                    </div>
                </div>
            </div>

            <div class="col">
                <div class="form-group">
                    <label>Unit price <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                        </div>
                        <input type="text" class="form-control" inputmode="decimal" pattern="-?[0-9]*\.?[0-9]{0,2}" name="unit_price" value="<?= number_format($unit_price, 2) ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Tax</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                </div>
                <select class="form-control select2" name="tax_id">
                    <option value="0" <?= $tax_id === 0 ? 'selected' : '' ?>>None</option>
                    <?php while ($row = mysqli_fetch_array($taxes_sql)) { ?>
                        <?php $tid = intval($row['tax_id']); ?>
                        <option value="<?= $tid ?>" <?= $tid === $tax_id ? 'selected' : '' ?>><?= nullable_htmlentities($row['tax_name']) ?> <?= rtrim(rtrim(number_format(floatval($row['tax_percent']), 2), '0'), '.') ?>%</option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Invoice reference</label>
            <input type="text" class="form-control" name="invoiced_ref" maxlength="64" value="<?= $invoiced_ref ?>">
            <?php if (!empty($invoiced_at)) { ?>
                <div class="text-muted small mt-2">Invoiced at: <?= $invoiced_at ?></div>
            <?php } ?>
        </div>

        <div class="form-group mb-0">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="billable" demonstrate="0" name="billable" value="1" <?= $billable === 1 ? 'checked' : '' ?>>
                <label class="custom-control-label" for="billable">Billable</label>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_ticket_item" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
