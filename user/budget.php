<?php

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_financial');

// Fetch categories
$query = "SELECT category_id, category_name FROM categories WHERE category_type ='Expense' AND category_archived_at IS NULL";
$result = mysqli_query($mysqli, $query);
$categories = [];
while($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row;
}

// Fetch years with budget
$query = "SELECT DISTINCT budget_year FROM budget ORDER BY budget_year ASC";
$result = mysqli_query($mysqli, $query);
$years = [];
while ($row = mysqli_fetch_assoc($result)) {
    $years[] = $row['budget_year'];
}

// Fetch current year budgets
$currentYear = date("Y");
if (isset($_GET['year'])) {
    $currentYear = intval($_GET['year']);
}

$query = "SELECT * FROM budget WHERE budget_year = $currentYear";
$result = mysqli_query($mysqli, $query);
$budgets = [];
while ($row = mysqli_fetch_assoc($result)) {
    $budgets[] = $row;
}

$months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
$columnTotals = array_fill(0, 12, 0);
$grandTotal = 0;

?>
<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-balance-scale mr-2"></i>Budget for <span id="currentYear"><?php echo $currentYear; ?></span></h3>
        <div class="card-tools">
            <a href="budget_edit.php" class="btn btn-primary">
                <i class="fas fa-edit mr-2"></i>Edit Budget
            </a>
        </div>
    </div>
    <div class="card-body">
    <form id="yearForm" method="GET" action="budget.php">
        <div class="form-group">
            <select class="form-control" name="year" id="yearSelect" onchange="submit();">
                <?php foreach ($years as $year): ?>
                    <option value="<?php echo $year; ?>" <?php if ($year == $currentYear) { echo 'selected'; } ?>><?php echo $year; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
    <table class="table table-bordered table-striped">
        <thead>
        <tr>
            <th>Expense</th>
            <?php foreach ($months as $month): ?>
                <th><?php echo $month; ?></th>
            <?php endforeach; ?>
            <th>Total</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $category): ?>
            <tr>
                <td><?php echo nullable_htmlentities($category['category_name']); ?></td>
                <?php
                $rowTotal = 0;
                foreach ($months as $index => $month):
                    $amount = getBudgetAmount($budgets, $category['category_id'], $index + 1);
                    $rowTotal += $amount;
                    $columnTotals[$index] += $amount;
                ?>
                    <td><?php echo $amount; ?></td>
                <?php endforeach; ?>
                <td><?php echo $rowTotal; ?></td>
            </tr>
        <?php
        $grandTotal += $rowTotal;
        endforeach; ?>
        </tbody>
        <tfoot>
        <tr>
            <th>Total</th>
            <?php foreach ($columnTotals as $total): ?>
                <th><?php echo $total; ?></th>
            <?php endforeach; ?>
            <th><?php echo $grandTotal; ?></th>
        </tr>
        </tfoot>
    </table>
</div>

<?php
function getBudgetAmount($budgets, $categoryId, $month) {
    foreach ($budgets as $budget) {
        if ($budget['budget_category_id'] == $categoryId && $budget['budget_month'] == $month) {
            return intval($budget['budget_amount']);
        }
    }
    return 0;
}

require_once "../includes/footer.php";
?>
