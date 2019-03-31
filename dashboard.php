<?php include("header.php"); ?>

<?php

$sql_total_income = mysqli_query($mysqli,"SELECT SUM(invoice_payment_amount) AS total_income FROM invoice_payments");
$row = mysqli_fetch_array($sql_total_income);
$total_income = $row['total_income'];
$sql_total_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses");
$row = mysqli_fetch_array($sql_total_expenses);
$total_expenses = $row['total_expenses'];

$profit = $total_income - $total_expenses;

$sql_accounts = mysqli_query($mysqli,"SELECT * FROM accounts ORDER BY account_id DESC");

$sql_latest_income_payments = mysqli_query($mysqli,"SELECT * FROM invoice_payments, invoices, clients 
	WHERE invoice_payments.invoice_id = invoices.invoice_id 
	AND invoices.client_id = clients.client_id 
	ORDER BY invoice_payments.invoice_payment_id DESC LIMIT 5"
);

$sql_latest_expenses = mysqli_query($mysqli,"SELECT * FROM expenses, categories 
	WHERE expenses.category_id = categories.category_id
	ORDER BY expense_id DESC LIMIT 5");

?>

<!-- Icon Cards-->
<div class="row">
  <div class="col-xl-4 col-sm-6 mb-3">
    <div class="card text-white bg-primary o-hidden h-100">
      <div class="card-body">
        <div class="card-body-icon">
          <i class="fas fa-fw fa-money-check"></i>
        </div>
        <div class="mr-5">Total Incomes <h1>$<?php echo $total_income; ?></h1></div>
      </div>
    </div>
  </div>
  <div class="col-xl-4 col-sm-6 mb-3">
    <div class="card text-white bg-danger o-hidden h-100">
      <div class="card-body">
        <div class="card-body-icon">
          <i class="fas fa-fw fa-shopping-cart"></i>
        </div>
        <div class="mr-5">Total Expenses <h1>$<?php echo $total_expenses; ?></h1></div>
      </div>      
    </div>
  </div>
  <div class="col-xl-4 col-sm-6 mb-3">
    <div class="card text-white bg-success o-hidden h-100">
      <div class="card-body">
        <div class="card-body-icon">
          <i class="fas fa-fw fa-heart"></i>
        </div>
        <div class="mr-5">Total Profit <h1>$<?php echo $profit; ?></h1></div>
      </div>
    </div>
  </div> 
</div>

  <!-- Area Chart Example-->
  <div class="card mb-3">
    <div class="card-header">
      <i class="fas fa-chart-area"></i>
      Cash Flow</div>
    <div class="card-body">
      <canvas id="myAreaChart" width="100%" height="30"></canvas>
    </div>
    <div class="card-footer small text-muted">Updated yesterday at 11:59 PM</div>
  </div>

  <!-- DataTables Example -->
  <div class="row mb-3">
    <div class="col-md-4">
      <div class="card">
        <div class="card-header">Account Balance</div>
          <div class="table-responsive">
            <table class="table table-borderless">
              <tbody>
              	<?php
              	while($row = mysqli_fetch_array($sql_accounts)){
			            $account_id = $row['account_id'];
			            $account_name = $row['account_name'];
			            $opening_balance = $row['opening_balance'];

			          ?>
                <tr>
			            <td><?php echo "$account_name"; ?></a></td>
			            <?php
			            $sql2 = mysqli_query($mysqli,"SELECT SUM(invoice_payment_amount) AS total_payments FROM invoice_payments WHERE account_id = $account_id");
			            $row2 = mysqli_fetch_array($sql2);
			            
			            $sql3 = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE account_id = $account_id");
			            $row3 = mysqli_fetch_array($sql3);
			            
			            $balance = $opening_balance + $row2['total_payments'] - $row3['total_expenses'];
			            if($balance == ''){
			              $balance = '0.00'; 
			            }
			            ?>

			            <td class="text-right text-monospace">$<?php echo $balance; ?></td>
			          </tr>
			          <?php
			        	}
			        	?>

              </tbody>
            </table>
          </div>
      </div>
    </div> <!-- .col -->
    <div class="col-md-4">
      <div class="card">
        <div class="card-header">
          Latest Incomes
        </div>
        <div class="table-responsive">
          <table class="table table-borderless">
            <thead>
              <tr>
                <th>Date</th>
                <th>Customer</th>
                <th>Invoice</th>
                <th class="text-right">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php
            	while($row = mysqli_fetch_array($sql_latest_income_payments)){
		            $invoice_payment_date = $row['invoice_payment_date'];
		            $invoice_payment_amount = $row['invoice_payment_amount'];
		            $invoice_number = $row['invoice_number'];
		            $client_name = $row['client_name'];
			        ?>
              <tr>
                <td><?php echo $invoice_payment_date; ?></td>
                <td><?php echo $client_name; ?></td>
                <td><?php echo $invoice_number; ?></td>
                <td class="text-right text-monospace">$<?php echo $invoice_payment_amount; ?></td>
              </tr>
              <?php
			        }
			        ?>
            </tbody>
          </table>
        </div>
      </div>
    </div> <!-- .col -->
    <div class="col-md-4">
      <div class="card">
        <div class="card-header">
          Latest Expenses
        </div>
        <div class="table-responsive">
          <table class="table table-borderless">
            <thead>
              <tr>
                <th>Date</th>
                <th>Category</th>
                <th class="text-right">Amount</th>
              </tr>
            </thead>
            <tbody>
            	<?php
            	while($row = mysqli_fetch_array($sql_latest_expenses)){
		            $expense_date = $row['expense_date'];
		            $expense_amount = $row['expense_amount'];
		            $category_name = $row['category_name'];
			        ?>
              <tr>
                <td><?php echo $expense_date; ?></td>
                <td><?php echo $category_name; ?></td>
                <td class="text-right text-monospace">$<?php echo $expense_amount; ?></td>
              </tr>
             	<?php
			        }
			        ?>
            </tbody>
          </table>
        </div>
      </div>
    </div> <!-- .col -->
  </div> <!-- row -->


<?php include("footer.php"); ?>