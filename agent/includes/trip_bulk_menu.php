<?php
/*
 * The trips list's Bulk menu - shown once trips are ticked (js/bulk_actions.js).
 * Set the driver, client, vehicle or billable of the ticked trips, pay one
 * driver's mileage, or delete them.
 */
isset($mysqli) || die("Direct file access is not allowed");
?>
<div class="dropdown" id="bulkActionButton" hidden>
    <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
        <i class="fas fa-fw fa-layer-group"></i><span class="d-none d-xxl-inline ms-1">Bulk</span> (<span id="selectedCount">0</span>)
    </button>
    <div class="dropdown-menu dropdown-menu-end">
        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/trip/trip_bulk_edit.php?field=driver" data-bulk="true"><i class="fas fa-fw fa-user text-secondary me-2"></i>Set Driver</a>
        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/trip/trip_bulk_edit.php?field=client" data-bulk="true"><i class="fas fa-fw fa-building text-secondary me-2"></i>Set Client</a>
        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/trip/trip_bulk_edit.php?field=vehicle" data-bulk="true"><i class="fas fa-fw fa-car-side text-secondary me-2"></i>Set Vehicle</a>
        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/trip/trip_bulk_edit.php?field=billable" data-bulk="true"><i class="fas fa-fw fa-file-invoice-dollar text-secondary me-2"></i>Set Billable</a>
        <?php if (tripPayBackOn()) { ?>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/trip/trip_claim.php" data-bulk="true"><i class="fas fa-fw fa-hand-holding-usd text-secondary me-2"></i>Pay Mileage</a>
        <?php } ?>
        <?php if (lookupUserPermission('module_financial') >= 3) { ?>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item text-danger text-bold ajax-modal" href="#" data-modal-url="modals/trip/trip_bulk_delete.php" data-bulk="true"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
        <?php } ?>
    </div>
</div>
