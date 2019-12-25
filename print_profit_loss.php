<?php include("header.php"); ?>

<?php

if(isset($_GET['year'])){
  $year = intval($_GET['year']);
}else{
  $year = date('Y');
}

$sql_categories_income = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Income' AND company_id = $session_company_id ORDER BY category_name ASC");

$sql_categories_expense = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Expense' AND company_id = $session_company_id ORDER BY category_name ASC");


?>

<h3 class="text-center mb-3">Profit & Loss <?php echo $year; ?> <?php echo $config_company_name; ?></h3>
      <table class="table table-sm">
        <thead>
          <tr>
            <th></th>
            <th class="text-right">Jan-Mar</th>
            <th class="text-right">Apr-Jun</th>
            <th class="text-right">Jul-Sep</th>
            <th class="text-right">Oct-Dec</th>
            <th class="text-right">Total</th>
          </tr>
          <tr>
            <th><br><br>Income</th>
            <th colspan="5"></th>
          </tr>
        </thead>
        <tbody>
          <?php
          while($row = mysqli_fetch_array($sql_categories_income)){
            $category_id = $row['category_id'];
            $category_name = $row['category_name'];
          ?>

            <tr>
              <td><?php echo $category_name; ?></td>
              
              <?php
              
              for($month = 1; $month<=3; $month++) {
                $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payment_amount_for_month FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND invoices.category_id = $category_id AND YEAR(payment_date) = $year AND MONTH(payment_date) = $month");
                $row = mysqli_fetch_array($sql_payments);
                $payment_amount_for_month = $row['payment_amount_for_month'];

                $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS revenue_amount_for_month FROM revenues WHERE revenues.category_id = $category_id AND YEAR(revenue_date) = $year AND MONTH(revenue_date) = $month");
                $row = mysqli_fetch_array($sql_revenues);
                $revenue_amount_for_month = $row['revenue_amount_for_month'];

                $payment_amount_for_month = $payment_amount_for_month + $revenue_amount_for_month;
                
                $payment_amount_for_quarter_one = $payment_amount_for_quarter_one + $payment_amount_for_month;              
              }
              
              ?>
                
                <td class="text-right text-monospace">$<?php echo number_format($payment_amount_for_quarter_one,2); ?></td>

              <?php
              
              for($month = 4; $month<=6; $month++) {
                $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payment_amount_for_month FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND invoices.category_id = $category_id AND YEAR(payment_date) = $year AND MONTH(payment_date) = $month");
                $row = mysqli_fetch_array($sql_payments);
                $payment_amount_for_month = $row['payment_amount_for_month'];

                $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS revenue_amount_for_month FROM revenues WHERE revenues.category_id = $category_id AND YEAR(revenue_date) = $year AND MONTH(revenue_date) = $month");
                $row = mysqli_fetch_array($sql_revenues);
                $revenue_amount_for_month = $row['revenue_amount_for_month'];

                $payment_amount_for_month = $payment_amount_for_month + $revenue_amount_for_month;

                $payment_amount_for_quarter_two = $payment_amount_for_quarter_two + $payment_amount_for_month;
              }
              
              ?>

                <td class="text-right text-monospace">$<?php echo number_format($payment_amount_for_quarter_two,2); ?></td>

              <?php
              
              for($month = 7; $month<=9; $month++) {
                $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payment_amount_for_month FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND invoices.category_id = $category_id AND YEAR(payment_date) = $year AND MONTH(payment_date) = $month");
                $row = mysqli_fetch_array($sql_payments);
                $payment_amount_for_month = $row['payment_amount_for_month'];

                $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS revenue_amount_for_month FROM revenues WHERE revenues.category_id = $category_id AND YEAR(revenue_date) = $year AND MONTH(revenue_date) = $month");
                $row = mysqli_fetch_array($sql_revenues);
                $revenue_amount_for_month = $row['revenue_amount_for_month'];

                $payment_amount_for_month = $payment_amount_for_month + $revenue_amount_for_month;
                $payment_amount_for_quarter_three = $payment_amount_for_quarter_three + $payment_amount_for_month;
              }
              
              ?>

                <td class="text-right text-monospace">$<?php echo number_format($payment_amount_for_quarter_three,2); ?></td>

              <?php
              
              for($month = 10; $month<=12; $month++) {
                $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payment_amount_for_month FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND invoices.category_id = $category_id AND YEAR(payment_date) = $year AND MONTH(payment_date) = $month");
                $row = mysqli_fetch_array($sql_payments);
                $payment_amount_for_month = $row['payment_amount_for_month'];

                $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS revenue_amount_for_month FROM revenues WHERE revenues.category_id = $category_id AND YEAR(revenue_date) = $year AND MONTH(revenue_date) = $month");
                $row = mysqli_fetch_array($sql_revenues);
                $revenue_amount_for_month = $row['revenue_amount_for_month'];

                $payment_amount_for_month = $payment_amount_for_month + $revenue_amount_for_month;
                $payment_amount_for_quarter_four = $payment_amount_for_quarter_four + $payment_amount_for_month;
              }
              
              $total_payments_for_all_four_quarters = $payment_amount_for_quarter_one + $payment_amount_for_quarter_two + $payment_amount_for_quarter_three + $payment_amount_for_quarter_four;

              ?>

              <td class="text-right text-monospace">$<?php echo number_format($payment_amount_for_quarter_four,2); ?></td>        
              
              <td class="text-right text-monospace">$<?php echo number_format($total_payments_for_all_four_quarters,2); ?></td>
            </tr>
          
          <?php 
          
          $payment_amount_for_quarter_one = 0;
          $payment_amount_for_quarter_two = 0;
          $payment_amount_for_quarter_three = 0;
          $payment_amount_for_quarter_four = 0;
          $total_payment_for_all_months = 0;

          } 
          
          ?>
          
          <tr>
            <th>Gross Profit</th>
            <?php
              
            for($month = 1; $month<=3; $month++) {
              $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payment_total_amount_for_month FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND YEAR(payment_date) = $year AND MONTH(payment_date) = $month AND payments.company_id = $session_company_id");
              $row = mysqli_fetch_array($sql_payments);
              $payment_total_amount_for_month = $row['payment_total_amount_for_month'];

              $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS revenue_total_amount_for_month FROM revenues WHERE category_id > 0 AND YEAR(revenue_date) = $year AND MONTH(revenue_date) = $month AND revenues.company_id = $session_company_id");
              $row = mysqli_fetch_array($sql_revenues);
              $revenue_total_amount_for_month = $row['revenue_total_amount_for_month'];

              $payment_total_amount_for_month = $payment_total_amount_for_month + $revenue_total_amount_for_month;

              $payment_total_amount_for_quarter_one = $payment_total_amount_for_quarter_one + $payment_total_amount_for_month;
            }
            
            ?>  
            
              <th class="text-right text-monospace">$<?php echo number_format($payment_total_amount_for_quarter_one,2); ?></th>

            <?php
 
            for($month = 4; $month<=6; $month++) {
              $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payment_total_amount_for_month FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND YEAR(payment_date) = $year AND MONTH(payment_date) = $month AND payments.company_id = $session_company_id");
              $row = mysqli_fetch_array($sql_payments);
              $payment_total_amount_for_month = $row['payment_total_amount_for_month'];

              $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS revenue_total_amount_for_month FROM revenues WHERE category_id > 0 AND YEAR(revenue_date) = $year AND MONTH(revenue_date) = $month AND revenues.company_id = $session_company_id");
              $row = mysqli_fetch_array($sql_revenues);
              $revenue_total_amount_for_month = $row['revenue_total_amount_for_month'];

              $payment_total_amount_for_month = $payment_total_amount_for_month + $revenue_total_amount_for_month;

              $payment_total_amount_for_quarter_two = $payment_total_amount_for_quarter_two + $payment_total_amount_for_month;
            }
            
            ?>  
            
              <th class="text-right text-monospace">$<?php echo number_format($payment_total_amount_for_quarter_two,2); ?></th>

            <?php
 
            for($month = 7; $month<=9; $month++) {
              $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payment_total_amount_for_month FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND YEAR(payment_date) = $year AND MONTH(payment_date) = $month AND payments.company_id = $session_company_id");
              $row = mysqli_fetch_array($sql_payments);
              $payment_total_amount_for_month = $row['payment_total_amount_for_month'];

              $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS revenue_total_amount_for_month FROM revenues WHERE category_id > 0 AND YEAR(revenue_date) = $year AND MONTH(revenue_date) = $month AND revenues.company_id = $session_company_id");
              $row = mysqli_fetch_array($sql_revenues);
              $revenue_total_amount_for_month = $row['revenue_total_amount_for_month'];

              $payment_total_amount_for_month = $payment_total_amount_for_month + $revenue_total_amount_for_month;

              $payment_total_amount_for_quarter_three = $payment_total_amount_for_quarter_three + $payment_total_amount_for_month;
            }
            
            ?>  
            
              <th class="text-right text-monospace">$<?php echo number_format($payment_total_amount_for_quarter_three,2); ?></th>

            <?php
 
            for($month = 10; $month<=12; $month++) {
              $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payment_total_amount_for_month FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND YEAR(payment_date) = $year AND MONTH(payment_date) = $month AND payments.company_id = $session_company_id");
              $row = mysqli_fetch_array($sql_payments);
              $payment_total_amount_for_month = $row['payment_total_amount_for_month'];

              $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS revenue_total_amount_for_month FROM revenues WHERE category_id > 0 AND YEAR(revenue_date) = $year AND MONTH(revenue_date) = $month AND revenues.company_id = $session_company_id");
              $row = mysqli_fetch_array($sql_revenues);
              $revenue_total_amount_for_month = $row['revenue_total_amount_for_month'];

              $payment_total_amount_for_month = $payment_total_amount_for_month + $revenue_total_amount_for_month;

              $payment_total_amount_for_quarter_four = $payment_total_amount_for_quarter_four + $payment_total_amount_for_month;
            }
            
            $total_payments_for_all_four_quarters = $payment_total_amount_for_quarter_one + $payment_total_amount_for_quarter_two + $payment_total_amount_for_quarter_three + $payment_total_amount_for_quarter_four;

            ?>  
            
            <th class="text-right text-monospace">$<?php echo number_format($payment_total_amount_for_quarter_four,2); ?></th>

            <th class="text-right text-monospace">$<?php echo number_format($total_payments_for_all_four_quarters,2); ?></th>
          </tr>
         
          <tr>
            <th><br><br>Expenses</th>
            <th colspan="5"></th>
          </tr>
          <?php
          while($row = mysqli_fetch_array($sql_categories_expense)){
            $category_id = $row['category_id'];
            $category_name = $row['category_name'];
          ?>

            <tr>
              <td><?php echo $category_name; ?></td>
              
              <?php
              
              for($month = 1; $month<=3; $month++) {
                $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS expense_amount_for_month FROM expenses WHERE category_id = $category_id AND YEAR(expense_date) = $year AND MONTH(expense_date) = $month");
                $row = mysqli_fetch_array($sql_expenses);
                $expense_amount_for_quarter_one = $expense_amount_for_quarter_one + $row['expense_amount_for_month'];              
              }
              
              ?>
                
                <td class="text-right text-monospace">$<?php echo number_format($expense_amount_for_quarter_one,2); ?></td>

              <?php
              
              for($month = 4; $month<=6; $month++) {
                $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS expense_amount_for_month FROM expenses WHERE category_id = $category_id AND YEAR(expense_date) = $year AND MONTH(expense_date) = $month");
                $row = mysqli_fetch_array($sql_expenses);
                $expense_amount_for_quarter_two = $expense_amount_for_quarter_two + $row['expense_amount_for_month'];
              }
              
              ?>

                <td class="text-right text-monospace">$<?php echo number_format($expense_amount_for_quarter_two,2); ?></td>

              <?php
              
              for($month = 7; $month<=9; $month++) {
                $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS expense_amount_for_month FROM expenses WHERE category_id = $category_id AND YEAR(expense_date) = $year AND MONTH(expense_date) = $month");
                $row = mysqli_fetch_array($sql_expenses);
                $expense_amount_for_quarter_three = $expense_amount_for_quarter_three + $row['expense_amount_for_month'];
              }
              
              ?>

                <td class="text-right text-monospace">$<?php echo number_format($expense_amount_for_quarter_three,2); ?></td>

              <?php
              
              for($month = 10; $month<=12; $month++) {
                $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS expense_amount_for_month FROM expenses WHERE category_id = $category_id AND YEAR(expense_date) = $year AND MONTH(expense_date) = $month");
                $row = mysqli_fetch_array($sql_expenses);
                $expense_amount_for_quarter_four = $expense_amount_for_quarter_four + $row['expense_amount_for_month'];
              }
              
              $total_expenses_for_all_four_quarters = $expense_amount_for_quarter_one + $expense_amount_for_quarter_two + $expense_amount_for_quarter_three + $expense_amount_for_quarter_four;

              ?>

              <td class="text-right text-monospace">$<?php echo number_format($expense_amount_for_quarter_four,2); ?></td>        
              
              <td class="text-right text-monospace">$<?php echo number_format($total_expenses_for_all_four_quarters,2); ?></td>
            </tr>
          
          <?php 
          
          $expense_amount_for_quarter_one = 0;
          $expense_amount_for_quarter_two = 0;
          $expense_amount_for_quarter_three = 0;
          $expense_amount_for_quarter_four = 0;
          $total_expense_for_all_months = 0;

          } 
          
          ?>
          
          <tr>
            <th>Total Expenses<br><br><br></th>
            <?php
              
            for($month = 1; $month<=3; $month++) {
              $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS expense_total_amount_for_month FROM expenses WHERE category_id > 0 AND YEAR(expense_date) = $year AND MONTH(expense_date) = $month AND vendor_id > 0 AND company_id = $session_company_id");
              $row = mysqli_fetch_array($sql_expenses);
              $expense_total_amount_for_quarter_one = $expense_total_amount_for_quarter_one + $row['expense_total_amount_for_month'];
            }
            
            ?>  
            
              <th class="text-right text-monospace">$<?php echo number_format($expense_total_amount_for_quarter_one,2); ?></th>

            <?php
 
            for($month = 4; $month<=6; $month++) {
              $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS expense_total_amount_for_month FROM expenses WHERE category_id > 0 AND YEAR(expense_date) = $year AND MONTH(expense_date) = $month AND vendor_id > 0 AND company_id = $session_company_id");
              $row = mysqli_fetch_array($sql_expenses);
              $expense_total_amount_for_quarter_two = $expense_total_amount_for_quarter_two + $row['expense_total_amount_for_month'];
            }
            
            ?>  
            
              <th class="text-right text-monospace">$<?php echo number_format($expense_total_amount_for_quarter_two,2); ?></th>

            <?php
 
            for($month = 7; $month<=9; $month++) {
              $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS expense_total_amount_for_month FROM expenses WHERE category_id > 0 AND YEAR(expense_date) = $year AND MONTH(expense_date) = $month AND vendor_id > 0 AND company_id = $session_company_id");
              $row = mysqli_fetch_array($sql_expenses);
              $expense_total_amount_for_quarter_three = $expense_total_amount_for_quarter_three + $row['expense_total_amount_for_month'];
            }
            
            ?>  
            
              <th class="text-right text-monospace">$<?php echo number_format($expense_total_amount_for_quarter_three,2); ?></th>

            <?php
 
            for($month = 10; $month<=12; $month++) {
              $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS expense_total_amount_for_month FROM expenses WHERE category_id > 0 AND YEAR(expense_date) = $year AND MONTH(expense_date) = $month AND vendor_id > 0 AND company_id = $session_company_id");
              $row = mysqli_fetch_array($sql_expenses);
              $expense_total_amount_for_quarter_four = $expense_total_amount_for_quarter_four + $row['expense_total_amount_for_month'];
            }
            
            $total_expenses_for_all_four_quarters = $expense_total_amount_for_quarter_one + $expense_total_amount_for_quarter_two + $expense_total_amount_for_quarter_three + $expense_total_amount_for_quarter_four;

            ?>  
            
            <th class="text-right text-monospace">$<?php echo number_format($expense_total_amount_for_quarter_four,2); ?></th>

            <th class="text-right text-monospace">$<?php echo number_format($total_expenses_for_all_four_quarters,2); ?></th>
          </tr>
          <tr>
            <?php
              $net_profit_quarter_one = $payment_total_amount_for_quarter_one - $expense_total_amount_for_quarter_one;
              $net_profit_quarter_two = $payment_total_amount_for_quarter_two - $expense_total_amount_for_quarter_two;
              $net_profit_quarter_three = $payment_total_amount_for_quarter_three - $expense_total_amount_for_quarter_three;
              $net_profit_quarter_four = $payment_total_amount_for_quarter_four - $expense_total_amount_for_quarter_four;
              $net_profit_year = $total_payments_for_all_four_quarters - $total_expenses_for_all_four_quarters;
            ?>

            <th>Net Profit</th>
            <th class="text-right text-monospace">$<?php echo number_format($net_profit_quarter_one,2); ?></th>
            <th class="text-right text-monospace">$<?php echo number_format($net_profit_quarter_two,2); ?></th>
            <th class="text-right text-monospace">$<?php echo number_format($net_profit_quarter_three,2); ?></th>
            <th class="text-right text-monospace">$<?php echo number_format($net_profit_quarter_four,2); ?></th>
            <th class="text-right text-monospace">$<?php echo number_format($net_profit_year,2); ?></th>
          </tr>
        </tbody>
      </table>

<?php include("footer.php");