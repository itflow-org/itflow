<?php
    require_once("inc_all_reports.php");
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
            COALESCE(SUM(r.revenue_amount), 0) AS total_revenues,
            COALESCE(SUM(e.expense_amount), 0) AS total_expenses
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
                            $balance = $row['opening_balance'] + $row['total_payments'] + $row['total_revenues'] - $row['total_expenses'];
                            $account_type = $row['account_type'];
                            if ($account_type >= 11 && $account_type <= 19) {
                                // Display assets account row
                                echoAccountRow($row, $balance);
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
                            // Display liabilities account row
                            echoAccountRow($row, $balance);
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
                            // Display equity account row
                            echoAccountRow($row, $balance);
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
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once("footer.php"); ?>

<?php
function echoAccountRow($accountRow, $balance) {
    global $currency_format;
    $account_type_strings = [
        11 => "Current Assets",
        12 => "Fixed Assets",
        13 => "Other Assets",
        21 => "Current Liabilities",
        22 => "Long Term Liabilities",
        23 => "Other Liabilities",
        30 => "Equity"
    ];
    $account_type_string = $account_type_strings[$accountRow['account_type']] ?? "Unknown";
    $account_name_encoded_nulled = nullable_htmlentities(urlencode($accountRow['account_name']));
    $account_name_nulled = nullable_htmlentities($accountRow['account_name']);
    echo "
    <tr>
        <td>$account_type_string</td>
        <td><a class=\"text-dark\" href=\"account_details.php?account_name=$account_name_encoded_nulled\">$account_name_nulled</a></td>
        <td class=\"text-right\">" . numfmt_format_currency($currency_format, $balance, $accountRow['account_currency_code']) . "</td>
    </tr>
    ";
}

?>
