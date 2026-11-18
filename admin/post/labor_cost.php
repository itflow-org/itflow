<?php

/*
 * Admin > Labor Costs (admin/labor_costs.php, functions/labor_cost.php). The audit log names the
 * agent, never the amount - it's close to their pay.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_labor_cost_default'])) {

    validateCSRFToken();

    if (!laborCostReady()) {
        flashAlert("Labor costs need the database update - Maintenance > Update.", 'error');
        redirect();
    }

    [$amount, $problem] = laborCostParse($_POST['labor_cost_default'] ?? '');

    if ($problem !== '') {
        flashAlert($problem, 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE settings SET config_labor_cost_default = " . ($amount === null ? 'NULL' : $amount) . " WHERE company_id = 1");

    logAudit("Settings", "Edit", "$session_name changed the company default labor cost");

    flashAlert($amount === null ? "No company default - agents with no rate cost nothing." : "Company default saved.");

    redirect();
}

if (isset($_POST['add_labor_cost_rate'])) {

    validateCSRFToken();

    $user_id = intval($_POST['user_id'] ?? 0);

    $problem = laborCostRateSave($user_id, $_POST['from'] ?? '', $_POST['hourly'] ?? '', $session_user_id);

    if ($problem !== '') {
        flashAlert(escapeHtml($problem), 'error');
        redirect();
    }

    $agent_name = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_id = $user_id"))[0] ?? "agent $user_id");

    logAudit("Settings", "Edit", "$session_name set a labor cost for " . escapeSql($agent_name), 0, $user_id);

    flashAlert("Labor cost saved for " . escapeHtml($agent_name) . ".");

    redirect();
}

if (isset($_GET['delete_labor_cost_rate'])) {

    validateCSRFToken();

    $user_id = laborCostRateDelete(intval($_GET['delete_labor_cost_rate']));

    if ($user_id) {
        $agent_name = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_id = $user_id"))[0] ?? "agent $user_id");
        logAudit("Settings", "Edit", "$session_name took away a labor cost of " . escapeSql($agent_name), 0, $user_id);
        flashAlert("Rate taken away.");
    }

    redirect();
}
