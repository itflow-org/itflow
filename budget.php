<?php

// Default Column Sortby/Order Filter
$sort = "budget_year";
$order = "DESC";

require_once "inc_all.php";


//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM budget
    LEFT JOIN categories ON budget_category_id = category_id
    AND DATE(budget_created_at) BETWEEN '$dtf' AND '$dtt'
    AND (budget_description LIKE '%$q%'
    OR budget_amount LIKE '%$q%' OR budget_month LIKE '%$q%'
    OR budget_year LIKE '%$q%' OR category_name LIKE '%$q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-balance-scale mr-2"></i>Budget</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createBudgetModal">
                    <i class="fas fa-plus mr-2"></i>Create
                </button>
            </div>
        </div>

        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php
                            if (isset($q)) {
                                echo stripslashes(nullable_htmlentities($q));
                            } ?>" placeholder="Search...">
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button"
                                    data-toggle="collapse" data-target="#advancedFilter">
                                    <i class="fas fa-filter"></i>
                                </button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-8">
                    </div>
                </div>
                <div class="collapse mt-3 <?php
                if (!empty($_GET['dtf']) || $_GET['canned_date'] !== "custom" ) {
                    echo "show";
                    } ?>" id="advancedFilter">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Canned Date</label>
                                <select class="form-control select2" name="canned_date">
                                    <?php
                                    $dateOptions = [
                                        '' => 'Custom',
                                        'today' => 'Today',
                                        'yesterday' => 'Yesterday',
                                        'thisweek' => 'This Week',
                                        'lastweek' => 'Last Week',
                                        'thismonth' => 'This Month',
                                        'lastmonth' => 'Last Month',
                                        'thisyear' => 'This Year',
                                        'lastyear' => 'Last Year'
                                    ];

                                    foreach ($dateOptions as $value => $label) {
                                        echo '<option value="' . htmlspecialchars($value) . '"';
                                        if (isset($_GET['canned_date']) && $_GET['canned_date'] == $value) {
                                            echo ' selected';
                                        }
                                        echo '>' . htmlspecialchars($label) . '</option>';
                                    }
                                    ?>
                                </select>

                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date From</label>
                                <input type="date" class="form-control" name="dtf" max="2999-12-31"
                                    value="<?php echo nullable_htmlentities($dtf); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date To</label>
                                <input type="date" class="form-control" name="dtt" max="2999-12-31"
                                    value="<?php echo nullable_htmlentities($dtt); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <hr>
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <?php
                        $headers = [
                            'budget_year' => 'Year',
                            'budget_month' => 'Month',
                            'category_name' => 'Category',
                            'budget_description' => 'Description',
                            'budget_amount' => 'Amount'
                        ];

                        foreach ($headers as $sortParam => $headerName) {
                            $class = '';
                            if ($sortParam === 'budget_amount') {
                                $class = 'text-right';
                            }
                            
                            echo "<th class='$class'><a class='text-dark' href='?
                                $url_query_strings_sort&sort=$sortParam&order=$disp'>$headerName</a></th>";
                        }
                        ?>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $budget_id = intval($row['budget_id']);
                        $budget_description = nullable_htmlentities($row['budget_description']);
                        $budget_year = intval($row['budget_year']);
                        $budget_month = intval($row['budget_month']);
                        $budget_amount = floatval($row['budget_amount']);
                        $budget_category_id = intval($row['budget_category_id']);
                        $category_name = nullable_htmlentities($row['category_name']);

                        ?>

                        <tr>
                            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editBudgetModal
                                <?php echo $budget_id; ?>"><?php echo $budget_year; ?></a>
                            </td>
                            <td><?php echo $budget_month; ?></td>
                            <td><?php echo $category_name; ?></td>
                            <td><?php echo truncate($budget_description, 50); ?></td>
                            <td class="text-bold text-right">
                                <?php echo numfmt_format_currency(
                                        $currency_format,
                                        $budget_amount,
                                        $session_company_currency
                                    ); ?>
                            </td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal"
                                            data-target="#editBudgetModal<?php echo $budget_id; ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link"
                                            href="post.php?delete_budget=<?php echo $budget_id; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php

                        require "budget_edit_modal.php";


                    }

                    ?>

                    </tbody>
                </table>
            </div>
            <?php require_once "pagination.php";
 ?>
        </div>
    </div>

<?php
require_once "budget_create_modal.php";

require_once "footer.php";

