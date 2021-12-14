<?php include("header.php"); ?>
<?php 

if(isset($_GET['year'])){
  $year = intval($_GET['year']);
}else{
  $year = date('Y');
}

$sql_expense_years = mysqli_query($mysqli,"SELECT DISTINCT YEAR(expense_date) AS expense_year FROM expenses WHERE expense_category_id > 0 AND company_id = $session_company_id ORDER BY expense_year DESC");

$sql_categories = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Expense' AND company_id = $session_company_id ORDER BY category_name ASC");

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-coins"></i> Expense Summary</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print"></i> Print</button>
    </div>
  </div>
  <div class="card-body">
    <form class="mb-3">
      <select onchange="this.form.submit()" class="form-control" name="year">
        <?php 
                
        while($row = mysqli_fetch_array($sql_expense_years)){
          $expense_year = $row['expense_year'];
        ?>
        <option <?php if($year == $expense_year){ ?> selected <?php } ?> > <?php echo $expense_year; ?></option>
        
        <?php
        }
        ?>

      </select>
    </form>
    <div class="table-responsive">
      <table class="table table-striped">
        <thead class="text-dark">
          <tr>
            <th>Category</th>
            <th class="text-right">January</th>
            <th class="text-right">February</th>
            <th class="text-right">March</th>
            <th class="text-right">April</th>
            <th class="text-right">May</th>
            <th class="text-right">June</th>
            <th class="text-right">July</th>
            <th class="text-right">August</th>
            <th class="text-right">September</th>
            <th class="text-right">October</th>
            <th class="text-right">November</th>
            <th class="text-right">December</th>
            <th class="text-right">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php
          while($row = mysqli_fetch_array($sql_categories)){
            $category_id = $row['category_id'];
            $category_name = $row['category_name'];
          ?>

            <tr>
              <td><?php echo $category_name; ?></td>
              
              <?php

              $total_expense_for_all_months = 0;
              for($month = 1; $month<=12; $month++) {
                $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS expense_amount_for_month FROM expenses WHERE expense_category_id = $category_id AND YEAR(expense_date) = $year AND MONTH(expense_date) = $month");
                $row = mysqli_fetch_array($sql_expenses);
                $expense_amount_for_month = $row['expense_amount_for_month'];
                $total_expense_for_all_months = $expense_amount_for_month + $total_expense_for_all_months;

              
              ?>
                <td class="text-right"><a class="text-dark" href="expenses.php?q=<?php echo $category_name; ?>&dtf=<?php echo "$year-$month"; ?>-01&dtt=<?php echo "$year-$month"; ?>-31"><?php echo get_currency_symbol($session_company_currency); ?> <?php echo number_format($expense_amount_for_month,2); ?></a></td>
              
              <?php
              
              }
              
              ?>
              
              <th class="text-right"><a class="text-dark" href="expenses.php?q=<?php echo $category_name; ?>&dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31"><?php echo get_currency_symbol($session_company_currency); ?> <?php echo number_format($total_expense_for_all_months,2); ?></a></th>
            </tr>
          
          <?php 

          } 
          
          ?>
          
          <tr>
            <th>Total</th>
            <?php
              
            for($month = 1; $month<=12; $month++) {
              $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS expense_total_amount_for_month FROM expenses WHERE YEAR(expense_date) = $year AND MONTH(expense_date) = $month AND expense_vendor_id > 0 AND company_id = $session_company_id");
              $row = mysqli_fetch_array($sql_expenses);
              $expense_total_amount_for_month = $row['expense_total_amount_for_month'];
              $total_expense_for_all_months = $expense_total_amount_for_month + $total_expense_for_all_months;
              
            
            ?>

              <th class="text-right"><a class="text-dark" href="expenses.php?dtf=<?php echo "$year-$month"; ?>-01&dtt=<?php echo "$year-$month"; ?>-31"><?php echo get_currency_symbol($session_company_currency); ?> <?php echo number_format($expense_total_amount_for_month,2); ?></a></th>
            <?php

            }

            ?>

            <th class="text-right"><a class="text-dark" href="expenses.php?dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31"><?php echo get_currency_symbol($session_company_currency); ?> <?php echo number_format($total_expense_for_all_months,2); ?></th>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("footer.php");