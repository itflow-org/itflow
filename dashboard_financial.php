<?php include("inc_all.php"); ?>

<?php 

function roundUpToNearestMultiple($n, $increment = 1000)
{
    return (int) ($increment * ceil($n / $increment));
}

if(isset($_GET['year'])){
  $year = intval($_GET['year']);
}else{
  $year = date('Y');
}

//GET unique years from expenses, payments and revenues
$sql_payment_years = mysqli_query($mysqli,"SELECT YEAR(expense_date) AS all_years FROM expenses WHERE company_id = $session_company_id UNION DISTINCT SELECT YEAR(payment_date) FROM payments WHERE company_id = $session_company_id UNION DISTINCT SELECT YEAR(revenue_date) FROM revenues WHERE company_id = $session_company_id ORDER BY all_years DESC");


//GET unique years from expenses, payments and revenues
$sql_payment_years = mysqli_query($mysqli,"SELECT YEAR(expense_date) AS all_years FROM expenses WHERE company_id = $session_company_id UNION DISTINCT SELECT YEAR(payment_date) FROM payments WHERE company_id = $session_company_id UNION DISTINCT SELECT YEAR(revenue_date) FROM revenues WHERE company_id = $session_company_id ORDER BY all_years DESC");
//Define var so it doesnt throw errors in logs
$largest_income_month = 0;

//Get Total income
$sql_total_payments_to_invoices = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments_to_invoices FROM payments WHERE YEAR(payment_date) = $year AND company_id = $session_company_id");
$row = mysqli_fetch_array($sql_total_payments_to_invoices);
$total_payments_to_invoices = $row['total_payments_to_invoices'];
//Do not grab transfer payment as these have an category_id of 0
$sql_total_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE YEAR(revenue_date) = $year AND revenue_category_id > 0 AND company_id = $session_company_id");
$row = mysqli_fetch_array($sql_total_revenues);
$total_revenues = $row['total_revenues'];

$total_income = $total_payments_to_invoices + $total_revenues;

//Get Total expenses and do not grab transfer expenses as these have a vendor of 0
$sql_total_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_vendor_id > 0 AND YEAR(expense_date) = $year AND company_id = $session_company_id");
$row = mysqli_fetch_array($sql_total_expenses);
$total_expenses = $row['total_expenses'];

//Total up all the Invoices that are not draft or cancelled
$sql_invoice_totals = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS invoice_totals FROM invoices WHERE invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Cancelled' AND YEAR(invoice_date) = $year AND company_id = $session_company_id");
$row = mysqli_fetch_array($sql_invoice_totals);
$invoice_totals = $row['invoice_totals'];

//Quaeries from Receivables
$sql_total_payments_to_invoices_all_years = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments_to_invoices_all_years FROM payments WHERE company_id = $session_company_id");
$row = mysqli_fetch_array($sql_total_payments_to_invoices_all_years);
$total_payments_to_invoices_all_years = $row['total_payments_to_invoices_all_years'];

$sql_invoice_totals_all_years = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS invoice_totals_all_years FROM invoices WHERE invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Cancelled' AND company_id = $session_company_id");
$row = mysqli_fetch_array($sql_invoice_totals_all_years);
$invoice_totals_all_years = $row['invoice_totals_all_years'];

$receivables = $invoice_totals_all_years - $total_payments_to_invoices_all_years; 

$profit = $total_income - $total_expenses;

$sql_accounts = mysqli_query($mysqli,"SELECT * FROM accounts WHERE company_id = $session_company_id");

$sql_latest_invoice_payments = mysqli_query($mysqli,"SELECT * FROM payments, invoices, clients 
	WHERE payment_invoice_id = invoice_id 
	AND invoice_client_id = client_id
  AND clients.company_id = $session_company_id
	ORDER BY payment_id DESC LIMIT 5"
);

$sql_latest_expenses = mysqli_query($mysqli,"SELECT * FROM expenses, vendors, categories 
	WHERE expense_vendor_id = vendor_id 
	AND expense_category_id = category_id
  AND expenses.company_id = $session_company_id
	ORDER BY expense_id DESC LIMIT 5"
);

//Get Monthly Recurring Total
$sql_recurring_monthly_total = mysqli_query($mysqli,"SELECT SUM(recurring_amount) AS recurring_monthly_total FROM recurring WHERE recurring_status = 1 AND recurring_frequency = 'month' AND company_id = $session_company_id");
$row = mysqli_fetch_array($sql_recurring_monthly_total);
$recurring_monthly_total = $row['recurring_monthly_total'];

//Get Yearly Recurring Total
$sql_recurring_yearly_total = mysqli_query($mysqli,"SELECT SUM(recurring_amount) AS recurring_yearly_total FROM recurring WHERE recurring_status = 1 AND recurring_frequency = 'year' AND company_id = $session_company_id");
$row = mysqli_fetch_array($sql_recurring_yearly_total);
$recurring_yearly_total = $row['recurring_yearly_total'];

//Get Total Miles Driven
$sql_miles_driven = mysqli_query($mysqli,"SELECT SUM(trip_miles) AS total_miles FROM trips WHERE YEAR(trip_date) = $year AND company_id = $session_company_id");
$row = mysqli_fetch_array($sql_miles_driven);
$total_miles = $row['total_miles'];

//Get Total Clients added
$row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_id') AS clients_added FROM clients WHERE YEAR(client_created_at) = $year AND company_id = $session_company_id"));
$clients_added = $row['clients_added'];

//Get Total Vendors added
$row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS vendors_added FROM vendors WHERE YEAR(vendor_created_at) = $year AND vendor_client_id = 0 AND company_id = $session_company_id"));
$vendors_added = $row['vendors_added'];

?>

<form class="mb-3">
  <select onchange="this.form.submit()" class="form-control" name="year">
    <?php 
            
    while($row = mysqli_fetch_array($sql_payment_years)){
      $payment_year = $row['all_years'];
      if(empty($payment_year)){
        $payment_year = date('Y');
      }
    ?>
    <option <?php if($year == $payment_year){ echo "selected"; } ?> > <?php echo $payment_year; ?></option>
    
    <?php
    }
    ?>

  </select>
</form>

<!-- Icon Cards-->
<div class="row">
  <div class="col-lg-4 col-md-6 col-sm-12">
    <!-- small box -->
    <a class="small-box bg-primary" href="payments.php?dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31">
      <div class="inner">
        <h3><?php echo numfmt_format_currency($currency_format, $total_income, "$session_company_currency"); ?></h3>
        <p>Income</p>
        <hr>
        <small>Receivables: <?php echo numfmt_format_currency($currency_format, $receivables, "$session_company_currency"); ?></h3></small>
      </div>
      <div class="icon">
        <i class="fa fa-money-check"></i>
      </div>
    </a>
  </div>
  <!-- ./col -->

  <div class="col-lg-4 col-md-6 col-sm-12">
    <!-- small box -->
    <a class="small-box bg-danger" href="expenses.php?dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31">
      <div class="inner">
        <h3><?php echo numfmt_format_currency($currency_format, $total_expenses, "$session_company_currency"); ?></h3>
        <p>Expenses</p>
      </div>
      <div class="icon">
        <i class="fa fa-shopping-cart"></i>
      </div>
    </a>
  </div>
  <!-- ./col -->

  <div class="col-lg-4 col-md-6 col-sm-12">
    <!-- small box -->
    <div class="small-box bg-success">
      <div class="inner">
        <h3><?php echo numfmt_format_currency($currency_format, $profit, "$session_company_currency"); ?></h3>
        <p>Profit</p>
      </div>
      <div class="icon">
        <i class="fa fa-heart"></i>
      </div>
    </div>
  </div>
  <!-- ./col -->

  <div class="col-lg-4 col-md-6 col-sm-12">
    <!-- small box -->
    <div class="small-box bg-info">
      <div class="inner">
        <h3><?php echo numfmt_format_currency($currency_format, $recurring_monthly_total, "$session_company_currency"); ?></h3>
        <p>Monthly Recurring</p>
      </div>
      <div class="icon">
        <i class="fa fa-sync-alt"></i>
      </div>
    </div>
  </div>
  <!-- ./col -->

  <div class="col-lg-4 col-md-6 col-sm-12">
    <!-- small box -->
    <div class="small-box bg-info">
      <div class="inner">
        <h3><?php echo numfmt_format_currency($currency_format, $recurring_yearly_total, "$session_company_currency"); ?></h3>
        <p>Yearly Recurring</p>
      </div>
      <div class="icon">
        <i class="fa fa-sync-alt"></i>
      </div>
    </div>
  </div>
  <!-- ./col -->

  <div class="col-lg-4 col-md-6 col-sm-12">
    <!-- small box -->
    <a class="small-box bg-secondary" href="trips.php?dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31">
      <div class="inner">
        <h3><?php echo number_format($total_miles,2); ?></h3>
        <p>Miles Traveled</p>
      </div>
      <div class="icon">
        <i class="fa fa-route"></i>
      </div>
    </a>
  </div>
  <!-- ./col -->

  <div class="col-lg-4 col-6">
    <!-- small box -->
    <a class="small-box bg-secondary" href="clients.php?date_from=<?php echo $year; ?>-01-01&date_to=<?php echo $year; ?>-12-31">
      <div class="inner">
        <h3><?php echo $clients_added; ?></h3>
        <p>New Clients</p>
      </div>
      <div class="icon">
        <i class="fa fa-users"></i>
      </div>
    </a>
  </div>
  <!-- ./col -->

  <div class="col-lg-4 col-6">
    <!-- small box -->
    <a class="small-box bg-secondary" href="vendors.php?dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31">
      <div class="inner">
        <h3><?php echo $vendors_added; ?></h3>
        <p>New Vendors</p>
      </div>
      <div class="icon">
        <i class="fa fa-building"></i>
      </div>
    </a>
  </div>
  <!-- ./col -->

  <div class="col-md-12">
    <div class="card card-dark mb-3">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-fw fa-chart-area"></i> Cash Flow</h3>
        <div class="card-tools">
          <a href="report_income_summary.php" class="btn btn-tool">
            <i class="fas fa-eye"></i>
          </a>
          <button type="button" class="btn btn-tool" data-card-widget="remove">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <canvas id="cashFlow" width="100%" height="20"></canvas>
      </div>
    </div>
  </div>

  <div class="col-md-12">
    <div class="card card-dark mb-3">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-fw fa-route"></i> Trip Flow</h3>
        <div class="card-tools">
          <a href="trips.php" class="btn btn-tool">
            <i class="fas fa-eye"></i>
          </a>
          <button type="button" class="btn btn-tool" data-card-widget="remove">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <canvas id="tripFlow" width="100%" height="20"></canvas>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card card-dark mb-3">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-pie"></i> Income By Category</h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="remove">
            <i class="fas fa-times"></i>
          </button>
        </div>       
      </div>
      <div class="card-body">
        <canvas id="incomeByCategoryPieChart" width="100%" height="60"></canvas>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card card-dark mb-3">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-pie"></i> Expenses By Category</h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="remove">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <canvas id="expenseByCategoryPieChart" width="100%" height="60"></canvas>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card card-dark mb-3">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-pie"></i> Expenses By Vendor</h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="remove">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <canvas id="expenseByVendorPieChart" width="100%" height="60"></canvas>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card card-dark mb-3">
      <div class="card-header">
        <h3 class="card-title">Account Balance</h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="remove">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-borderless">
          <tbody>
          	<?php
          	while($row = mysqli_fetch_array($sql_accounts)){
	            $account_id = $row['account_id'];
	            $account_name = htmlentities($row['account_name']);
	            $opening_balance = $row['opening_balance'];

	          ?>
            <tr>
	            <td><?php echo $account_name; ?></a></td>
	            <?php
	            $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments FROM payments WHERE payment_account_id = $account_id");
              $row = mysqli_fetch_array($sql_payments);
              $total_payments = $row['total_payments'];
	            
	            $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE revenue_account_id = $account_id");
              $row = mysqli_fetch_array($sql_revenues);
              $total_revenues = $row['total_revenues'];
              
              $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_account_id = $account_id");
              $row = mysqli_fetch_array($sql_expenses);
              $total_expenses = $row['total_expenses'];
	            
              $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;

	            if($balance == ''){
	              $balance = '0.00'; 
	            }
	            ?>
	            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $balance, "$session_company_currency"); ?></td>
	          </tr>
	          <?php
	        	}
	        	?>

          </tbody>
        </table>
      </div>
    </div>
  </div> <!-- .col -->
  <div class="col-md-6">
    <div class="card card-dark mb-3">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-credit-card"></i> Latest Income</h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="remove">
            <i class="fas fa-times"></i>
          </button>
        </div>
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
          	while($row = mysqli_fetch_array($sql_latest_invoice_payments)){
	            $payment_date = $row['payment_date'];
	            $payment_amount = floatval($row['payment_amount']);
	            $invoice_prefix = htmlentities($row['invoice_prefix']);
              $invoice_number = htmlentities($row['invoice_number']);
	            $client_name = htmlentities($row['client_name']);
		        ?>
            <tr>
              <td><?php echo $payment_date; ?></td>
              <td><?php echo $client_name; ?></td>
              <td><?php echo "$invoice_prefix$invoice_number"; ?></td>
              <td class="text-right"><?php echo numfmt_format_currency($currency_format, $payment_amount, "$session_company_currency"); ?></td>
            </tr>
            <?php
		        }
		        ?>
          </tbody>
        </table>
      </div>
    </div>
  </div> <!-- .col -->
  <div class="col-md-6">
    <div class="card card-dark mb-3">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-shopping-cart"></i> Latest Expenses</h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="remove">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-borderless">
          <thead>
            <tr>
              <th>Date</th>
          		<th>Vendor</th>
              <th>Category</th>
              <th class="text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
          	<?php
          	while($row = mysqli_fetch_array($sql_latest_expenses)){
	            $expense_date = $row['expense_date'];
	            $expense_amount = floatval($row['expense_amount']);
	            $vendor_name = htmlentities($row['vendor_name']);
	            $category_name = htmlentities($row['category_name']);

		        ?>
            <tr>
              <td><?php echo $expense_date; ?></td>
              <td><?php echo $vendor_name; ?></td>
              <td><?php echo $category_name; ?></td>
              <td class="text-right"><?php echo numfmt_format_currency($currency_format, $expense_amount, "$session_company_currency"); ?></td>
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
<script>
// Set new default font family and font color to mimic Bootstrap's default styling
Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
Chart.defaults.global.defaultFontColor = '#292b2c';

// Area Chart Example
var ctx = document.getElementById("cashFlow");
var myLineChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
    datasets: [{
      label: "Income",
      fill: false,
      borderColor: "#007bff",
      pointBackgroundColor: "#007bff",
      pointBorderColor: "#007bff",
      pointHoverRadius: 5,
      pointHoverBackgroundColor: "#007bff",
      pointHitRadius: 50,
      pointBorderWidth: 2,
      data: [
      <?php
      for($month = 1; $month<=12; $month++) {
          $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payment_amount_for_month FROM payments, invoices WHERE payment_invoice_id = invoice_id AND YEAR(payment_date) = $year AND MONTH(payment_date) = $month AND payments.company_id = $session_company_id");
          $row = mysqli_fetch_array($sql_payments);
          $payments_for_month = $row['payment_amount_for_month'];

          $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS revenue_amount_for_month FROM revenues WHERE revenue_category_id > 0 AND YEAR(revenue_date) = $year AND MONTH(revenue_date) = $month AND company_id = $session_company_id");
          $row = mysqli_fetch_array($sql_revenues);
          $revenues_for_month = $row['revenue_amount_for_month'];

          $income_for_month = $payments_for_month + $revenues_for_month;
          
          if($income_for_month > 0 && $income_for_month > $largest_income_month){
            $largest_income_month = $income_for_month;
          }
          

        ?>
          <?php echo "$income_for_month,"; ?>
        
        <?php
        
        }

        ?>

      ],
    },
    {
      label: "LY Income",
      fill: false,
      borderColor: "#9932CC",
      pointBackgroundColor: "#9932CC",
      pointBorderColor: "#9932CC",
      pointHoverRadius: 5,
      pointHoverBackgroundColor: "#9932CC",
      pointHitRadius: 50,
      pointBorderWidth: 2,
      data: [
      <?php
      for($month = 1; $month<=12; $month++) {
          $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payment_amount_for_month FROM payments, invoices WHERE payment_invoice_id = invoice_id AND YEAR(payment_date) = $year-1 AND MONTH(payment_date) = $month AND payments.company_id = $session_company_id");
          $row = mysqli_fetch_array($sql_payments);
          $payments_for_month = $row['payment_amount_for_month'];

          $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS revenue_amount_for_month FROM revenues WHERE revenue_category_id > 0 AND YEAR(revenue_date) = $year-1 AND MONTH(revenue_date) = $month AND company_id = $session_company_id");
          $row = mysqli_fetch_array($sql_revenues);
          $revenues_for_month = $row['revenue_amount_for_month'];

          $income_for_month = $payments_for_month + $revenues_for_month;
          
          if($income_for_month > 0 && $income_for_month > $largest_income_month){
            $largest_income_month = $income_for_month;
          }
          

        ?>
          <?php echo "$income_for_month,"; ?>
        
        <?php
        
        }

        ?>

      ],
    },
    {
    label: "Projected",
      fill: false,
      borderColor: "black",
      pointBackgroundColor: "black",
      pointBorderColor: "black",
      pointHoverRadius: 5,
      pointHoverBackgroundColor: "black",
      pointHitRadius: 50,
      pointBorderWidth: 2,
      data: [
      <?php

      $largest_invoice_month = 0;

      for($month = 1; $month<=12; $month++) {
          $sql_projected = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS invoice_amount_for_month FROM invoices WHERE YEAR(invoice_due) = $year AND MONTH(invoice_due) = $month AND invoice_status NOT LIKE 'Cancelled' AND invoice_status NOT LIKE 'Draft' AND company_id = $session_company_id");
          $row = mysqli_fetch_array($sql_projected);
          $invoice_for_month = $row['invoice_amount_for_month'];

          if($invoice_for_month > 0 && $invoice_for_month > $largest_invoice_month){
            $largest_invoice_month = $invoice_for_month;
          }
          
        ?>
          <?php echo "$invoice_for_month,"; ?>
        
        <?php
        
        }

        ?>

      ],
    }, 
    {
      label: "Expense",
      lineTension: 0.3,
      fill: false,
      borderColor: "#dc3545",
      pointBackgroundColor: "#dc3545",
      pointBorderColor: "#dc3545",
      pointHoverRadius: 5,
      pointHoverBackgroundColor: "#dc3545",
      pointHitRadius: 50,
      pointBorderWidth: 2,
      data: [
      <?php
      
      $largest_expense_month = 0;
      
      for($month = 1; $month<=12; $month++) {
          $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS expense_amount_for_month FROM expenses WHERE YEAR(expense_date) = $year AND MONTH(expense_date) = $month AND expense_vendor_id > 0 AND expenses.company_id = $session_company_id");
          $row = mysqli_fetch_array($sql_expenses);
          $expenses_for_month = $row['expense_amount_for_month'];
          
          if($expenses_for_month > 0 && $expenses_for_month > $largest_expense_month){
            $largest_expense_month = $expenses_for_month;
          }
          

        ?>
          <?php echo "$expenses_for_month,"; ?>
        
        <?php
        
        }

        ?>

      ],
    }],
  },
  options: {
    scales: {
      xAxes: [{
        time: {
          unit: 'date'
        },
        gridLines: {
          display: false
        },
        ticks: {
          maxTicksLimit: 12
        }
      }],
      yAxes: [{
        ticks: {
          min: 0,
          max: <?php $max = max(1000, $largest_expense_month, $largest_income_month, $largest_invoice_month); echo roundUpToNearestMultiple($max); ?>,
          maxTicksLimit: 5
        },
        gridLines: {
          color: "rgba(0, 0, 0, .125)",
        }
      }],
    },
    legend: {
      display: true
    }
  }
});

// Set new default font family and font color to mimic Bootstrap's default styling
Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
Chart.defaults.global.defaultFontColor = '#292b2c';

// Area Chart Example
var ctx = document.getElementById("tripFlow");
var myLineChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
    datasets: [{
      label: "Trip",
      lineTension: 0.3,
      backgroundColor: "red",
      borderColor: "darkred",
      pointRadius: 5,
      pointBackgroundColor: "red",
      pointBorderColor: "red",
      pointHoverRadius: 5,
      pointHoverBackgroundColor: "darkred",
      pointHitRadius: 50,
      pointBorderWidth: 2,
      data: [
      <?php
      for($month = 1; $month<=12; $month++) {
          $sql_trips = mysqli_query($mysqli,"SELECT SUM(trip_miles) AS trip_miles_for_month FROM trips WHERE YEAR(trip_date) = $year AND MONTH(trip_date) = $month AND trips.company_id = $session_company_id");
          $row = mysqli_fetch_array($sql_trips);
          $trip_miles_for_month = $row['trip_miles_for_month'];
          $largest_trip_miles_month = 0;
          
          if($trip_miles_for_month > 0 && $trip_miles_for_month > $largest_trip_miles_month){
            $largest_trip_miles_month = $trip_miles_for_month;
          }
          

        ?>
          <?php echo "$trip_miles_for_month,"; ?>
        
        <?php
        
        }

        ?>

      ],
    }],
  },
  options: {
    scales: {
      xAxes: [{
        time: {
          unit: 'date'
        },
        gridLines: {
          display: false
        },
        ticks: {
          maxTicksLimit: 12
        }
      }],
      yAxes: [{
        ticks: {
          min: 0,
          max: <?php $max = max(1000, $largest_trip_miles_month); echo roundUpToNearestMultiple($max); ?>,
          maxTicksLimit: 5
        },
        gridLines: {
          color: "rgba(0, 0, 0, .125)",
        }
      }],
    },
    legend: {
      display: false
    }
  }
});

// Set new default font family and font color to mimic Bootstrap's default styling
Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
Chart.defaults.global.defaultFontColor = '#292b2c';

// Pie Chart Example
var ctx = document.getElementById("incomeByCategoryPieChart");
var myPieChart = new Chart(ctx, {
  type: 'doughnut',
  data: {
    labels: [
      <?php
        $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, category_id FROM categories, invoices WHERE invoice_category_id = category_id AND invoice_status = 'Paid' AND YEAR(invoice_date) = $year AND categories.company_id = $session_company_id");
        while($row = mysqli_fetch_array($sql_categories)){
          $category_name = json_encode($row['category_name']);
          echo "$category_name,";
        }
      
      ?>

    ],
    datasets: [{
      data: [
        <?php
          $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, category_id FROM categories, invoices WHERE invoice_category_id = category_id AND invoice_status = 'Paid' AND YEAR(invoice_date) = $year AND categories.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql_categories)){
            $category_id = $row['category_id'];

            $sql_invoices = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS income_amount_for_year FROM invoices WHERE invoice_category_id = $category_id AND YEAR(invoice_date) = $year AND company_id = $session_company_id");
            $row = mysqli_fetch_array($sql_invoices);
            $income_amount_for_year = $row['income_amount_for_year'];
            echo "$income_amount_for_year,";
          }
        
        ?>

      ],
      backgroundColor: [
        <?php
           $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, category_id, category_color FROM categories, invoices WHERE invoice_category_id = category_id AND YEAR(invoice_date) = $year AND categories.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql_categories)){
            $category_color = json_encode($row['category_color']);
            echo "$category_color,";
          }
        
        ?>

      ],
    }],
  },
  options: {
  	legend: {
    	display: true,
    	position: 'right'
  	}
  }
});

// Set new default font family and font color to mimic Bootstrap's default styling
Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
Chart.defaults.global.defaultFontColor = '#292b2c';

// Pie Chart Example
var ctx = document.getElementById("expenseByCategoryPieChart");
var myPieChart = new Chart(ctx, {
  type: 'doughnut',
  data: {
    labels: [
      <?php
        $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, categories.category_id FROM categories, expenses WHERE expense_category_id = category_id AND expense_vendor_id > 0 AND YEAR(expense_date) = $year AND categories.company_id = $session_company_id");
        while($row = mysqli_fetch_array($sql_categories)){
          $category_name = json_encode($row['category_name']);
          echo "$category_name,";
        }
      
      ?>

    ],
    datasets: [{
      data: [
        <?php
          $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, categories.category_id FROM categories, expenses WHERE expense_category_id = category_id AND expense_vendor_id > 0 AND YEAR(expense_date) = $year AND categories.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql_categories)){
            $category_id = $row['category_id'];

            $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS expense_amount_for_year FROM expenses WHERE expense_category_id = $category_id AND YEAR(expense_date) = $year");
            $row = mysqli_fetch_array($sql_expenses);
            $expense_amount_for_year = $row['expense_amount_for_year'];
            echo "$expense_amount_for_year,";
          }
        
        ?>

      ],
      backgroundColor: [
        <?php
          $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, category_color FROM categories, expenses WHERE expense_category_id = categories.category_id AND expense_vendor_id > 0 AND YEAR(expense_date) = $year AND categories.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql_categories)){
            $category_color = json_encode($row['category_color']);
            echo "$category_color,";
          }
        
        ?>

      ],
    }],
  },
  options: {
  	legend: {
    	display: true,
    	position: 'right'
  	}
  }
});

// Pie Chart Example
var ctx = document.getElementById("expenseByVendorPieChart");
var myPieChart = new Chart(ctx, {
  type: 'doughnut',
  data: {
    labels: [
      <?php
        $sql_vendors = mysqli_query($mysqli,"SELECT DISTINCT vendor_name, vendor_id FROM vendors, expenses WHERE expense_vendor_id = vendor_id AND YEAR(expense_date) = $year AND vendors.company_id = $session_company_id");
        while($row = mysqli_fetch_array($sql_vendors)){
          $vendor_name = json_encode($row['vendor_name']);
          echo "$vendor_name,";
        }
      
      ?>

    ],
    datasets: [{
      data: [
        <?php
          $sql_vendors = mysqli_query($mysqli,"SELECT DISTINCT vendor_name, vendor_id FROM vendors, expenses WHERE expense_vendor_id = vendor_id AND YEAR(expense_date) = $year AND vendors.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql_vendors)){
            $vendor_id = $row['vendor_id'];

            $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS expense_amount_for_year FROM expenses WHERE expense_vendor_id = $vendor_id AND YEAR(expense_date) = $year");
            $row = mysqli_fetch_array($sql_expenses);
            $expense_amount_for_year = $row['expense_amount_for_year'];
            echo "$expense_amount_for_year,";
          }
        
        ?>

      ],
      backgroundColor: [
        <?php
          $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, category_color FROM categories, expenses WHERE expense_category_id = category_id AND YEAR(expense_date) = $year AND categories.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql_categories)){
            $category_color = json_encode($row['category_color']);
            echo "$category_color,";
          }
        
        ?>

      ],
    }],
  },
  options: {
  	legend: {
    	display: true,
    	position: 'right'
  	}
  }
});

</script>
