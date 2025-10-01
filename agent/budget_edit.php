<?php

require_once "includes/inc_all.php";

enforceUserPermission('module_financial', 2);

// Fetch categories
$query = "SELECT category_id, category_name FROM categories WHERE category_type ='Expense' AND category_archived_at IS NULL";
$result = mysqli_query($mysqli, $query);
$categories = [];
while($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row;
}

// Fetch current year budgets
$currentYear = date("Y");
if(isset($_GET['year'])) {
    $currentYear = intval($_GET['year']);
}

$query = "SELECT * FROM budget WHERE budget_year = $currentYear";
$result = mysqli_query($mysqli, $query);
$budgets = [];
while($row = mysqli_fetch_assoc($result)) {
    $budgets[] = $row;
}

$months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
$columnTotals = array_fill(0, 12, 0);
$grandTotal = 0;

?>
<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-balance-scale mr-2"></i>Editing Budget for <span id="currentYear"><?php echo $currentYear; ?></span></h3>
        <div class="card-tools">
            <a href="budget.php" class="btn btn-default text-dark">
                <i class="fas fa-eye mr-2"></i>View Budget
            </a>
            <button type="submit" name="save_budget" form="budgetForm" class="btn btn-primary"><i class="fas fa-fw fa-check mr-2"></i>Save Budget</button>
            <button type="submit" name="delete_budget" form="budgetForm" class="btn btn-danger"><i class="fas fa-fw fa-trash mr-2"></i>Delete Budget</button>
        </div>
    </div>
    <div class="card-body">
    
    <form id="yearForm" method="GET" action="budget.php">
        <div class="form-group">
            <select class="form-control" name="year" id="yearSelect" onchange="submit();">
                <?php for ($i = $currentYear - 10; $i <= $currentYear + 5; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php if ($i == $currentYear) echo 'selected'; ?>><?php echo $i; ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </form>
    <form id="budgetForm" method="POST" action="post.php">
        <input type="hidden" name="year" value="<?php echo $currentYear; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

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
                        <td><input type='text' inputmode='numeric' pattern='[0-9]*' class="form-control" name="budget[<?php echo intval($category['category_id']); ?>][<?php echo $index + 1; ?>]" value="<?php echo $amount; ?>"></td>
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
    </form>
    </div>

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
