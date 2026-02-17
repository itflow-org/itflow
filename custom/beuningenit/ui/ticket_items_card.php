<?php

function bit_render_ticket_products_card($mysqli, int $ticket_id, string $ticket_closed_at, string $currency_symbol, string $csrf_token): string {
    $sql_ticket_items = mysqli_query(
        $mysqli,
        "SELECT 
            ticket_item_id,
            ticket_item_ticket_id,
            ticket_item_product_id,
            ticket_item_name,
            ticket_item_description,
            ticket_item_quantity,
            ticket_item_unit_price,
            ticket_item_tax_id,
            ticket_item_billable,
            ticket_item_invoiced_at,
            ticket_item_invoiced_ref,
            ticket_item_created_at,
            ticket_item_updated_at,
            product_name,
            tax_name,
            tax_percent
        FROM ticket_items
        LEFT JOIN products ON ticket_item_product_id = product_id
        LEFT JOIN taxes ON ticket_item_tax_id = tax_id
        WHERE ticket_item_ticket_id = $ticket_id
        ORDER BY ticket_item_id DESC"
    );

    ob_start();
    ?>
    <div class="card card-dark mb-3">
        <div class="card-header bg-dark">
            <h5 class="card-title">Products</h5>

            <?php if (lookupUserPermission('module_support') >= 2 && empty($ticket_closed_at)) { ?>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool ajax-modal" data-modal-url="modals/ticket/ticket_item_add.php?ticket_id=<?= $ticket_id ?>" data-modal-size="lg">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            <?php } ?>
        </div>

        <div class="card-body p-0">
            <?php $ticket_items_total = 0.00; ?>

            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th style="width: 40%">Item</th>
                            <th class="text-right" style="width: 10%">Qty</th>
                            <th class="text-right" style="width: 15%">Unit</th>
                            <th style="width: 15%">Tax</th>
                            <th class="text-right" style="width: 15%">Total</th>
                            <th style="width: 5%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($sql_ticket_items) == 0) { ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted p-3">No items</td>
                            </tr>
                        <?php } else { ?>
                            <?php while ($row = mysqli_fetch_array($sql_ticket_items)) { ?>
                                <?php
                                $ticket_item_id = intval($row['ticket_item_id']);
                                $item_name = nullable_htmlentities($row['ticket_item_name']);
                                $item_description = nullable_htmlentities($row['ticket_item_description']);
                                $qty = floatval($row['ticket_item_quantity']);
                                $unit_price = floatval($row['ticket_item_unit_price']);
                                $tax_name = nullable_htmlentities($row['tax_name']);
                                $tax_percent = isset($row['tax_percent']) ? floatval($row['tax_percent']) : null;
                                $billable = intval($row['ticket_item_billable']);
                                $invoiced_at = nullable_htmlentities($row['ticket_item_invoiced_at']);
                                $invoiced_ref = nullable_htmlentities($row['ticket_item_invoiced_ref']);

                                $line_total = $qty * $unit_price;
                                $ticket_items_total += $line_total;

                                $tax_display = '';
                                if (!empty($tax_name) && $tax_percent !== null) {
                                    $tax_display = $tax_name . ' (' . rtrim(rtrim(number_format($tax_percent, 2), '0'), '.') . '%)';
                                } elseif (!empty($tax_name)) {
                                    $tax_display = $tax_name;
                                } else {
                                    $tax_display = '-';
                                }

                                $status_badge = '';
                                if (!empty($invoiced_at)) {
                                    $status_badge = "<span class='badge badge-success'>Invoiced</span>";
                                } elseif ($billable == 1) {
                                    $status_badge = "<span class='badge badge-dark'>Billable</span>";
                                } else {
                                    $status_badge = "<span class='badge badge-secondary'>Not billable</span>";
                                }
                                ?>
                                <tr>
                                    <td>
                                        <div class="text-bold"><?= $item_name ?></div>
                                        <?php if (!empty($item_description)) { ?>
                                            <div class="text-muted small"><?= $item_description ?></div>
                                        <?php } ?>
                                        <div class="mt-1">
                                            <?= $status_badge ?>
                                            <?php if (!empty($invoiced_ref)) { ?>
                                                <span class="text-muted small ml-2"><?= $invoiced_ref ?></span>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <td class="text-right"><?= rtrim(rtrim(number_format($qty, 2), '0'), '.') ?></td>
                                    <td class="text-right"><?= $currency_symbol . number_format($unit_price, 2) ?></td>
                                    <td><?= $tax_display ?></td>
                                    <td class="text-right"><?= $currency_symbol . number_format($line_total, 2) ?></td>
                                    <td class="text-right">
                                        <?php if (lookupUserPermission('module_support') >= 2 && empty($ticket_closed_at)) { ?>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-light ajax-modal" data-modal-url="modals/ticket/ticket_item_edit.php?ticket_item_id=<?= $ticket_item_id ?>" data-modal-size="lg">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a class="btn btn-sm btn-light confirm-link" href="post.php?delete_ticket_item=<?= $ticket_item_id ?>&csrf_token=<?= $csrf_token ?>">
                                                    <i class="fas fa-trash text-danger"></i>
                                                </a>
                                            </div>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                    <?php if (mysqli_num_rows($sql_ticket_items) > 0) { ?>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-right">Total</th>
                                <th class="text-right"><?= $currency_symbol . number_format($ticket_items_total, 2) ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    <?php } ?>
                </table>
            </div>
        </div>
    </div>
    <?php

    return ob_get_clean();
}
