<?php

/*
 * Admin > Labor Costs - the company default: the cost per hour of an agent with no rate of their
 * own (admin/post/labor_cost.php, functions/labor_cost.php). Empty leaves their time uncosted.
 */

require_once '../../includes/modal_header.php';

$default = laborCostDefault();

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-users me-2"></i>Company Default</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <p class="small text-body-secondary">The cost per hour of an agent with no rate of their own - a blended figure for your team, say. Leave it empty and their time costs nothing in Client Profitability, which says how many hours that is.</p>
        <label class="form-label" for="laborCostDefault">Cost per hour</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-dollar-sign"></i></span>
            <input type="text" inputmode="decimal" class="form-control" name="labor_cost_default" id="laborCostDefault" placeholder="None" maxlength="12" value="<?= $default === null ? '' : escapeHtml(number_format($default, 2, '.', '')) ?>" autofocus>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_labor_cost_default" class="btn btn-primary fw-bold"><i class="fas fa-fw fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../../../includes/modal_footer.php";
