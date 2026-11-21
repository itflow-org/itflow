<?php

/*
 * Admin > Labor Costs - one agent's rates: what an hour of them costs from each date, a new one,
 * and taking one away (admin/post/labor_cost.php). functions/labor_cost.php.
 */

require_once '../../includes/modal_header.php';

$user_id = intval($_GET['user_id'] ?? 0);
$agent = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_id, user_name, user_archived_at FROM users WHERE user_id = $user_id AND user_type = 1"));
$list = $agent && laborCostReady() ? array_reverse(laborCostRates()[$user_id] ?? []) : [];
$default = laborCostDefault();
$money = function ($amount) use ($currency_format, $session_company_currency) {
    return escapeHtml(billingMoney($amount, $session_company_currency));
};

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-tag me-2"></i>Labor Cost<?= $agent ? ' <span class="fw-normal text-white-50 ms-1">' . escapeHtml($agent['user_name']) . '</span>' : '' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<?php if (!$agent || !laborCostReady()) { ?>
    <div class="modal-body">
        <div class="alert alert-danger mb-0"><?= laborCostReady() ? 'That agent wasn\'t found.' : 'Labor costs need the database update - Maintenance &gt; Update.' ?></div>
    </div>
<?php } else { ?>
    <form action="post.php" method="post" autocomplete="off" data-labor-rate-form>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="user_id" value="<?= $user_id ?>">

        <div class="modal-body">
            <p class="small text-body-secondary">What an hour of <?= escapeHtml($agent['user_name']) ?> costs the company - wage, payroll taxes and benefits. A new rate counts from its date; time before it keeps the rate it had.</p>

            <h6 class="mb-2">Rates</h6>
            <?php if ($list) { ?>
                <ul class="list-group mb-3" data-labor-rates>
                    <?php foreach ($list as $i => [$from, $hourly, $rate_id]) { ?>
                        <li class="list-group-item d-flex align-items-center gap-2" data-labor-rate="<?= intval($rate_id) ?>">
                            <span class="fw-semibold"><?= $money($hourly) ?> / h</span>
                            <span class="text-body-secondary small">from <?= escapeHtml(date('M j, Y', strtotime($from))) ?><?= $i === count($list) - 1 ? ', and before' : '' ?></span>
                            <a class="btn btn-sm btn-link text-danger ms-auto confirm-link" href="post.php?delete_labor_cost_rate=<?= intval($rate_id) ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                data-confirm-title="Take this rate away?" data-confirm-text="<?= escapeHtml($agent['user_name']) ?>'s time is costed at the rate before it<?= count($list) === 1 ? ', or the company default' : '' ?>." data-confirm-button="Take it away" title="Take this rate away">
                                <i class="fas fa-fw fa-times"></i>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            <?php } else { ?>
                <p class="small mb-3 <?= $default === null ? 'text-warning-emphasis' : 'text-body-secondary' ?>" data-labor-rates-none>
                    None yet - <?= $default === null ? 'their time costs nothing in Client Profitability, as there\'s no company default either.' : 'their time is costed at the company default, ' . $money($default) . ' an hour.' ?>
                </p>
            <?php } ?>

            <h6 class="mb-2"><?= $list ? 'A new rate' : 'Their rate' ?></h6>
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label" for="laborCostHourly">Cost per hour <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-dollar-sign"></i></span>
                        <input type="text" inputmode="decimal" class="form-control" name="hourly" id="laborCostHourly" placeholder="0.00" maxlength="12" required autofocus>
                    </div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="laborCostFrom">Counts from <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-calendar-day"></i></span>
                        <input type="date" class="form-control" name="from" id="laborCostFrom" value="<?= escapeHtml($list ? date('Y-m-d') : date('Y-01-01')) ?>" max="2999-12-31" required>
                    </div>
                </div>
            </div>
            <div class="form-text">A rate on a date that already has one replaces it.</div>
        </div>
        <div class="modal-footer">
            <button type="submit" name="add_labor_cost_rate" class="btn btn-primary text-bold"><i class="fas fa-fw fa-check me-2"></i>Save the rate</button>
            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
        </div>
    </form>
<?php } ?>

<?php
require_once "../../../includes/modal_footer.php";
