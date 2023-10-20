<?php
    require_once "inc_all_reports.php";

    validateAccountantRole();

    // Fetch Accounts and their balances
    $sql_accounts = "
        SELECT 
            a.account_id, 
            a.account_name, 
            a.opening_balance, 
            a.account_currency_code, 
            a.account_notes, 
            a.account_type,
            COALESCE(SUM(p.payment_amount), 0) AS total_payments,
            COALESCE(SUM(CASE WHEN e.expense_vendor_id <> 0 THEN e.expense_amount ELSE 0 END), 0) AS total_expenses,
            COALESCE(SUM(CASE WHEN r.revenue_category_id <> 0 THEN r.revenue_amount ELSE 0 END), 0) AS total_revenues
        FROM accounts a
        LEFT JOIN payments p ON a.account_id = p.payment_account_id
        LEFT JOIN revenues r ON a.account_id = r.revenue_account_id
        LEFT JOIN expenses e ON a.account_id = e.expense_account_id
        GROUP BY a.account_id
        ORDER BY a.account_type, a.account_name ASC";

    $result_accounts = mysqli_query($mysqli, $sql_accounts);

    $total_assets = 0;
    $total_liabilities = 0;
    $total_equity = 0;
    $currency = $session_company_currency;
?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-balance-scale mr-2"></i>Balance Sheet</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive-sm">

        <div class="text-center">
            <h2 class="text-dark">
                <?php echo nullable_htmlentities($session_company_name);?>
            </h2>
            <h3 class="text-dark">Balance Sheet</h3>
            <h5 class="text-dark">As of <?php echo date("F j, Y"); ?></h5>
        </div>
            <table class="table table-sm">
                <thead class="text-dark">
                    <tr>
                        <th>Account Type</th>
                        <th>Account Name</th>
                        <th class="text-right">Account Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Assets Section -->
                    <tr>
                        <th colspan="3" >Assets</th>
                    </tr>
                    <?php
                        while ($row = mysqli_fetch_array($result_accounts)) {
                            $account_type = $row['account_type'];
                            if ($account_type >= 11 && $account_type <= 19) {
                                $balance = $row['opening_balance'] + $row['total_payments'] + $row['total_revenues'] - $row['total_expenses'];
                                print_row($row, $balance, $currency_format);
                                $total_assets += $balance;
                                $formatted_total_assets = numfmt_format_currency($currency_format, $total_assets, $row['account_currency_code']);
                            }
                            
                        }
                        ?>
                        <tr>
                            <th></th>
                            <th class="text-uppercase">Total Assets</th>
                            <th class="text-right"><?php echo $formatted_total_assets; ?></th>
                        </tr>

                    <!-- Liabilities Section -->
                    <tr>
                        <th colspan="3" >Liabilities</th>
                    </tr>
                    <?php
                    mysqli_data_seek($result_accounts, 0); // Reset the result pointer to the start
                    while ($row = mysqli_fetch_array($result_accounts)) {
                        $balance = $row['opening_balance'] + $row['total_payments'] + $row['total_revenues'] - $row['total_expenses'];
                        $account_type = $row['account_type'];
                        if ($account_type >= 21 && $account_type <= 29) {
                            $balance = $row['opening_balance'] + $row['total_payments'] + $row['total_revenues'] - $row['total_expenses'];
                            print_row($row, $balance, $currency_format);                            
                            $total_liabilities += $balance;
                            $formatted_total_liabilities = numfmt_format_currency($currency_format, $total_liabilities, $row['account_currency_code']);
                        }
                    }
                    ?>
                    <tr>
                        <th></th>
                        <th class="text-uppercase">Total Liabilities</th>
                        <th class="text-right"><?php echo $formatted_total_liabilities; ?></th>
                    </tr>

                    <!-- Equity Section -->
                    <tr>
                        <th colspan="3" >Equity</th>
                    </tr>
                    <?php
                    mysqli_data_seek($result_accounts, 0); // Reset the result pointer to the start
                    while ($row = mysqli_fetch_array($result_accounts)) {
                        $balance = $row['opening_balance'] + $row['total_payments'] + $row['total_revenues'] - $row['total_expenses'];
                        $account_type = $row['account_type'];     
                        if ($account_type >= 30) {
                            $balance = $row['opening_balance'] + $row['total_payments'] + $row['total_revenues'] - $row['total_expenses'];
                            print_row($row, $balance, $currency_format);                            
                            $total_equity += $balance;
                            $formatted_total_equity = numfmt_format_currency($currency_format, $total_equity, $row['account_currency_code']);
                        }
                    }
                    ?>
                    <tr>
                        <th></th>
                        <th class="text-uppercase">Total Equity</th>
                        <th class="text-right"><?php echo $formatted_total_equity; ?></th>
                    </tr>
                    <!-- Total Equity and Liabilities -->

                    <?php
                        $total_liabilities_and_equity = $total_liabilities + $total_equity;
                        $formatted_total_liabilities_and_equity = numfmt_format_currency($currency_format, $total_liabilities_and_equity, $currency);
                    ?>

                    <tr>
                        <th></th>
                        <th class="text-uppercase">Total Liabilities and Equity</th>
                        <th class="text-right"><?php echo $formatted_total_liabilities_and_equity; ?></th>
                    </tr>

                    <tr>
                        <th>
                            Unbalanced:
                            <div><?php 
                            $unbalanced = $total_assets + $total_liabilities_and_equity; 
                            echo numfmt_format_currency($currency_format, $unbalanced, $currency);
                            ?>
                            </div>
                        </th>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once "footer.php";


function print_row($row, $balance, $currency_format) {
    $account_name = nullable_htmlentities($row['account_name']);
    $formatted_balance = numfmt_format_currency($currency_format, $balance, $row['account_currency_code']);
    
    echo "<tr>";
    echo "<td></td>";
    echo "<td>$account_name</td>";
    echo "<td class='text-right'>$formatted_balance</td>";
    echo "</tr>";
}


?>
