<?php

/*
 * ITFlow - the budget behind Reports > Budget vs Actual: what is planned to
 * be spent each month of a year, by expense category.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** A budget year that makes sense, or 0. */
function budgetYear($value) {
    $year = intval($value);
    return ($year >= 2000 && $year <= intval(date('Y')) + 5) ? $year : 0;
}

if (isset($_POST['save_budget'])) {

    validateCSRFToken();
    enforceUserPermission('module_financial', 2);

    $year = budgetYear($_POST['year'] ?? 0);
    if (!$year) {
        flashAlert('That is not a year a budget can be set for.', 'error');
        redirect('/agent/reports/budget.php');
    }

    // Only expense categories take a budget
    $categories = [];
    $sql = mysqli_query($mysqli, "SELECT category_id FROM categories WHERE category_type = 'Expense'");
    while ($row = mysqli_fetch_assoc($sql)) {
        $categories[intval($row['category_id'])] = true;
    }

    // What is there now, one row per category and month (an older duplicate is folded away below)
    $existing = [];
    $sql = mysqli_query($mysqli, "SELECT budget_id, budget_category_id, budget_month FROM budget WHERE budget_year = $year ORDER BY budget_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $existing[intval($row['budget_category_id'])][intval($row['budget_month'])][] = intval($row['budget_id']);
    }

    $changed = 0;
    foreach ((array) ($_POST['budget'] ?? []) as $category_id => $months) {
        $category_id = intval($category_id);
        if (!isset($categories[$category_id]) || !is_array($months)) {
            continue;
        }
        foreach ($months as $month => $value) {
            $month = intval($month);
            if ($month < 1 || $month > 12) {
                continue;
            }
            $amount = round(max(0, floatval(str_replace([',', ' '], ['', ''], (string) $value))), 2);
            $ids = $existing[$category_id][$month] ?? [];

            if ($amount <= 0) {
                if ($ids) {
                    mysqli_query($mysqli, "DELETE FROM budget WHERE budget_id IN (" . implode(',', $ids) . ")");
                    $changed++;
                }
                continue;
            }
            if ($ids) {
                $keep = array_shift($ids);
                mysqli_query($mysqli, "UPDATE budget SET budget_amount = $amount WHERE budget_id = $keep AND budget_amount <> $amount");
                $changed += mysqli_affected_rows($mysqli) > 0 ? 1 : 0;
                if ($ids) {
                    mysqli_query($mysqli, "DELETE FROM budget WHERE budget_id IN (" . implode(',', $ids) . ")");
                }
            } else {
                mysqli_query($mysqli, "INSERT INTO budget SET budget_year = $year, budget_month = $month, budget_amount = $amount, budget_category_id = $category_id");
                $changed++;
            }
        }
    }

    if ($changed) {
        logAudit("Budget", "Edit", "$session_name updated the $year budget ($changed change" . ($changed === 1 ? '' : 's') . ")");
    }

    flashAlert($changed ? "The $year budget is saved" : "Nothing changed in the $year budget", $changed ? 'success' : 'info');

    redirect("/agent/reports/budget.php?year=$year");

}

if (isset($_POST['copy_budget'])) {

    validateCSRFToken();
    enforceUserPermission('module_financial', 2);

    $year = budgetYear($_POST['year'] ?? 0);
    if (!$year) {
        flashAlert('That is not a year a budget can be set for.', 'error');
        redirect('/agent/reports/budget.php');
    }
    $from_year = $year - 1;

    $count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM budget WHERE budget_year = $from_year"))[0]);
    if (!$count) {
        flashAlert("There is no $from_year budget to copy.", 'error');
        redirect("/agent/reports/budget.php?year=$year&view=edit");
    }

    mysqli_query($mysqli, "DELETE FROM budget WHERE budget_year = $year");
    mysqli_query($mysqli, "INSERT INTO budget (budget_month, budget_year, budget_amount, budget_description, budget_category_id)
        SELECT budget_month, $year, budget_amount, budget_description, budget_category_id FROM budget WHERE budget_year = $from_year");

    logAudit("Budget", "Edit", "$session_name copied the $from_year budget into $year");

    flashAlert("$from_year's budget is copied into $year - change any month and save");

    redirect("/agent/reports/budget.php?year=$year&view=edit");

}
