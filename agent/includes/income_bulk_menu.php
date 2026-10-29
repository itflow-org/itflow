<?php
/*
 * The Income list's Bulk menu - shown once rows are ticked (js/bulk_actions.js).
 * Set the category, account, payment method or client of the ticked rows, or
 * delete the other income among them. A payment's category is its invoice's,
 * its client is the invoice's, and it is deleted from the invoice.
 */
isset($mysqli) || die("Direct file access is not allowed");
?>
<div class="dropdown" id="bulkActionButton" hidden>
    <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
        <i class="fas fa-fw fa-layer-group"></i><span class="d-none d-xxl-inline ms-1">Bulk</span> (<span id="selectedCount">0</span>)
    </button>
    <div class="dropdown-menu dropdown-menu-end">
        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/income/income_bulk_edit.php?field=category" data-bulk="true"><i class="fas fa-fw fa-tag text-secondary me-2"></i>Set Category</a>
        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/income/income_bulk_edit.php?field=account" data-bulk="true"><i class="fas fa-fw fa-piggy-bank text-secondary me-2"></i>Set Account</a>
        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/income/income_bulk_edit.php?field=method" data-bulk="true"><i class="fas fa-fw fa-credit-card text-secondary me-2"></i>Set Payment Method</a>
        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/income/income_bulk_edit.php?field=client" data-bulk="true"><i class="fas fa-fw fa-building text-secondary me-2"></i>Set Client</a>
        <?php if (incomeCanDelete()) { ?>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item text-danger text-bold ajax-modal" href="#" data-modal-url="modals/income/income_bulk_delete.php" data-bulk="true"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
        <?php } ?>
    </div>
</div>
