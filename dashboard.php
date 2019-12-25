<?php include("header.php"); ?>

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
$sql_total_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE YEAR(revenue_date) = $year AND category_id > 0 AND company_id = $session_company_id");
$row = mysqli_fetch_array($sql_total_revenues);
$total_revenues = $row['total_revenues'];

$total_income = $total_payments_to_invoices + $total_revenues;

//Get Total expenses and do not grab transfer expenses as these have a vendor of 0
$sql_total_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE vendor_id > 0 AND YEAR(expense_date) = $year AND company_id = $session_company_id");
$row = mysqli_fetch_array($sql_total_expenses);
$total_expenses = $row['total_expenses'];

//Total up all the Invoices that are not draft or cancelled
$sql_invoice_totals = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS invoice_totals FROM invoices WHERE invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Cancelled' AND YEAR(invoice_date) = $year AND company_id = $session_company_id");
$row = mysqli_fetch_array($sql_invoice_totals);
$invoice_totals = $row['invoice_totals'];

$recievables = $invoice_totals - $total_payments_to_invoices; 

$profit = $total_income - $total_expenses;

$sql_accounts = mysqli_query($mysqli,"SELECT * FROM accounts WHERE company_id = $session_company_id");

$sql_latest_invoice_payments = mysqli_query($mysqli,"SELECT * FROM payments, invoices, clients 
	WHERE payments.invoice_id = invoices.invoice_id 
	AND invoices.client_id = clients.client_id
  AND clients.company_id = $session_company_id
	ORDER BY payment_id DESC LIMIT 5"
);

$sql_latest_expenses = mysqli_query($mysqli,"SELECT * FROM expenses, vendors, categories 
	WHERE expenses.vendor_id = vendors.vendor_id 
	AND expenses.category_id = categories.category_id
  AND expenses.company_id = $session_company_id
	ORDER BY expense_id DESC LIMIT 5"
);

//Get Total Miles Driven
$sql_miles_driven = mysqli_query($mysqli,"SELECT SUM(trip_miles) AS total_miles FROM trips WHERE YEAR(trip_date) = $year AND company_id = $session_company_id");
$row = mysqli_fetch_array($sql_miles_driven);
$total_miles = $row['total_miles'];

//Get Total Clients added
$row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_id') AS clients_added FROM clients WHERE YEAR(client_created_at) = $year AND company_id = $session_company_id"));
$clients_added = $row['clients_added'];

//Get Total Vendors added
$row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS vendors_added FROM vendors WHERE YEAR(vendor_created_at) = $year AND client_id = 0 AND company_id = $session_company_id"));
$vendors_added = $row['vendors_added'];

//Get Total of Recurring Invoices
$sql_total_recurring_invoice_amount = mysqli_query($mysqli,"SELECT SUM(recurring_amount) AS total_recurring_invoice_amount FROM recurring WHERE YEAR(payment_date) = $year AND company_id = $session_company_id");
$row = mysqli_fetch_array($sql_total_recurring_invoice_amount);
$total_recurring_invoice_amount = $row['total_recurring_invoice_amount'];

?>

<form class="mb-3">
  <select onchange="this.form.submit()" class="form-control" name="year">
    <?php 
            
    while($row = mysqli_fetch_array($sql_payment_years)){
      $payment_year = $row['all_years'];
    ?>
    <option <?php if($year == $payment_year){ ?> selected <?php } ?> > <?php echo $payment_year; ?></option>
    
    <?php
    }
    ?>

  </select>
</form>


<!-- Icon Cards-->
<div class="row">
  <div class="col-lg-3 col-6">
    <!-- small box -->
    <a class="small-box bg-primary" href="payments.php?dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31">
      <div class="inner">
        <h3>$<?php echo number_format($total_income,2); ?></h3>
        <p>Total Incomes</p>
        <hr>
        <small>Recievables: $<?php echo number_format($recievables,2); ?></small>
      </div>
      <div class="icon">
        <i class="fa fa-money-check"></i>
      </div>
    </a>
  </div>
  <!-- ./col -->

  <div class="col-lg-3 col-6">
    <!-- small box -->
    <a class="small-box bg-danger" href="expenses.php?dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31">
      <div class="inner">
        <h3>$<?php echo number_format($total_expenses,2); ?></h3>
        <p>Total Expenses</p>
      </div>
      <div class="icon">
        <i class="fa fa-shopping-cart"></i>
      </div>
    </a>
  </div>
  <!-- ./col -->

  <div class="col-lg-3 col-6">
    <!-- small box -->
    <div class="small-box bg-success">
      <div class="inner">
        <h3>$<?php echo number_format($profit,2); ?></h3>
        <p>Profit</p>
      </div>
      <div class="icon">
        <i class="fa fa-heart"></i>
      </div>
    </div>
  </div>
  <!-- ./col -->

  <div class="col-lg-3 col-6">
    <!-- small box -->
    <a class="small-box bg-info" href="trips.php?dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31">
      <div class="inner">
        <h3><?php echo $total_miles; ?></h3>
        <p>Miles Driven</p>
      </div>
      <div class="icon">
        <i class="fa fa-bicycle"></i>
      </div>
    </a>
  </div>
  <!-- ./col -->

  <div class="col-lg-3 col-6">
    <!-- small box -->
    <a class="small-box bg-secondary" href="clients.php?dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31">
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

  <div class="col-lg-3 col-6">
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
    <div class="card mb-3">
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
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-fw fa-chart-area"></i> Expense Flow</h3>
        <div class="card-tools">
          <a href="report_expense_summary.php" class="btn btn-tool">
            <i class="fas fa-eye"></i>
          </a>
          <button type="button" class="btn btn-tool" data-card-widget="remove">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <canvas id="expenseFlow" width="100%" height="20"></canvas>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card mb-3">
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
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-pie"></i> Expense By Category</h3>
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
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-pie"></i> Expense By Vendor</h3>
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
    <div class="card">
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
	            $account_name = $row['account_name'];
	            $opening_balance = $row['opening_balance'];

	          ?>
            <tr>
	            <td><?php echo $account_name; ?></a></td>
	            <?php
	            $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments FROM payments WHERE account_id = $account_id");
              $row = mysqli_fetch_array($sql_payments);
              $total_payments = $row['total_payments'];
	            
	            $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE account_id = $account_id");
              $row = mysqli_fetch_array($sql_revenues);
              $total_revenues = $row['total_revenues'];
              
              $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE account_id = $account_id");
              $row = mysqli_fetch_array($sql_expenses);
              $total_expenses = $row['total_expenses'];
	            
              $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;

	            if($balance == ''){
	              $balance = '0.00'; 
	            }
	            ?>
	            <td class="text-right text-monospace">$<?php echo number_format($balance,2); ?></td>
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
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-credit-card"></i> Latest Invoice Payments</h3>
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
	            $payment_amount = $row['payment_amount'];
	            $invoice_number = $row['invoice_number'];
	            $client_name = $row['client_name'];
		        ?>
            <tr>
              <td><?php echo $payment_date; ?></td>
              <td><?php echo $client_name; ?></td>
              <td><?php echo $invoice_number; ?></td>
              <td class="text-right text-monospace">$<?php echo number_format($payment_amount,2); ?></td>
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
    <div class="card">
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
	            $expense_amount = $row['expense_amount'];
	            $vendor_name = $row['vendor_name'];
	            $category_name = $row['category_name'];

		        ?>
            <tr>
              <td><?php echo $expense_date; ?></td>
              <td><?php echo $vendor_name; ?></td>
              <td><?php echo $category_name; ?></td>
              <td class="text-right text-monospace">$<?php echo number_format($expense_amount,2); ?></td>
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
      lineTension: 0.3,
      backgroundColor: "rgba(2,117,216,0.2)",
      borderColor: "rgba(2,117,216,1)",
      pointRadius: 5,
      pointBackgroundColor: "rgba(2,117,216,1)",
      pointBorderColor: "rgba(255,255,255,0.8)",
      pointHoverRadius: 5,
      pointHoverBackgroundColor: "rgba(2,117,216,1)",
      pointHitRadius: 50,
      pointBorderWidth: 2,
      data: [
      <?php
      for($month = 1; $month<=12; $month++) {
          $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payment_amount_for_month FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND YEAR(payment_date) = $year AND MONTH(payment_date) = $month AND payments.company_id = $session_company_id");
          $row = mysqli_fetch_array($sql_payments);
          $payments_for_month = $row['payment_amount_for_month'];

          $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS revenue_amount_for_month FROM revenues WHERE category_id > 0 AND YEAR(revenue_date) = $year AND MONTH(revenue_date) = $month AND company_id = $session_company_id");
          $row = mysqli_fetch_array($sql_revenues);
          $revenues_for_month = $row['revenue_amount_for_month'];

          $income_for_month = $payments_for_month + $revenues_for_month;
          
          if($income_for_month > 0 AND $income_for_month > $largest_income_month){
            $largest_income_month = $income_for_month;
          }
          

        ?>
          <?php echo "$income_for_month,"; ?>
        
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
          max: <?php echo roundUpToNearestMultiple($largest_income_month); ?>,
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

// Area Chart Example
var ctx = document.getElementById("expenseFlow");
var myLineChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
    datasets: [{
      label: "Expense",
      lineTension: 0.3,
      backgroundColor: "rgba(2,117,216,0.2)",
      borderColor: "rgba(2,117,216,1)",
      pointRadius: 5,
      pointBackgroundColor: "rgba(2,117,216,1)",
      pointBorderColor: "rgba(255,255,255,0.8)",
      pointHoverRadius: 5,
      pointHoverBackgroundColor: "rgba(2,117,216,1)",
      pointHitRadius: 50,
      pointBorderWidth: 2,
      data: [
      <?php
      for($month = 1; $month<=12; $month++) {
          $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS expense_amount_for_month FROM expenses WHERE YEAR(expense_date) = $year AND MONTH(expense_date) = $month AND vendor_id > 0 AND expenses.company_id = $session_company_id");
          $row = mysqli_fetch_array($sql_expenses);
          $expenses_for_month = $row['expense_amount_for_month'];
          
          if($expenses_for_month > 0 AND $expenses_for_month > $largest_expense_month){
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
          max: <?php echo roundUpToNearestMultiple($largest_expense_month); ?>,
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
  type: 'pie',
  data: {
    labels: [
      <?php
        $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, categories.category_id FROM categories, invoices WHERE invoices.category_id = categories.category_id AND invoice_status = 'Paid' AND YEAR(invoice_date) = $year AND categories.company_id = $session_company_id");
        while($row = mysqli_fetch_array($sql_categories)){
          $category_name = $row['category_name'];
          echo "\"$category_name\",";
        }
      
      ?>

    ],
    datasets: [{
      data: [
        <?php
          $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, categories.category_id FROM categories, invoices WHERE invoices.category_id = categories.category_id AND invoice_status = 'Paid' AND YEAR(invoice_date) = $year AND categories.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql_categories)){
            $category_id = $row['category_id'];

            $sql_invoices = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS income_amount_for_year FROM invoices WHERE category_id = $category_id AND YEAR(invoice_date) = $year AND company_id = $session_company_id");
            $row = mysqli_fetch_array($sql_invoices);
            $income_amount_for_year = $row['income_amount_for_year'];
            echo "$income_amount_for_year,";
          }
        
        ?>

      ],
      backgroundColor: [
        <?php
           $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, categories.category_id, category_color FROM categories, invoices WHERE invoices.category_id = categories.category_id AND YEAR(invoice_date) = $year AND categories.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql_categories)){
            $category_color = $row['category_color'];
            echo "\"$category_color\",";
          }
        
        ?>

      ],
    }],
  },
});

// Set new default font family and font color to mimic Bootstrap's default styling
Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
Chart.defaults.global.defaultFontColor = '#292b2c';

// Pie Chart Example
var ctx = document.getElementById("expenseByCategoryPieChart");
var myPieChart = new Chart(ctx, {
  type: 'pie',
  data: {
    labels: [
      <?php
        $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, categories.category_id FROM categories, expenses WHERE expenses.category_id = categories.category_id AND expenses.vendor_id > 0 AND YEAR(expense_date) = $year AND categories.company_id = $session_company_id");
        while($row = mysqli_fetch_array($sql_categories)){
          $category_name = $row['category_name'];
          echo "\"$category_name\",";
        }
      
      ?>

    ],
    datasets: [{
      data: [
        <?php
          $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, categories.category_id FROM categories, expenses WHERE expenses.category_id = categories.category_id AND expenses.vendor_id > 0 AND YEAR(expense_date) = $year AND categories.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql_categories)){
            $category_id = $row['category_id'];

            $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS expense_amount_for_year FROM expenses WHERE category_id = $category_id AND YEAR(expense_date) = $year");
            $row = mysqli_fetch_array($sql_expenses);
            $expense_amount_for_year = $row['expense_amount_for_year'];
            echo "$expense_amount_for_year,";
          }
        
        ?>

      ],
      backgroundColor: [
        <?php
          $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, category_color FROM categories, expenses WHERE expenses.category_id = categories.category_id AND expenses.vendor_id > 0 AND YEAR(expense_date) = $year AND categories.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql_categories)){
            $category_color = $row['category_color'];
            echo "\"$category_color\",";
          }
        
        ?>

      ],
    }],
  },
});

// Pie Chart Example
var ctx = document.getElementById("expenseByVendorPieChart");
var myPieChart = new Chart(ctx, {
  type: 'pie',
  data: {
    labels: [
      <?php
        $sql_vendors = mysqli_query($mysqli,"SELECT DISTINCT vendor_name, vendors.vendor_id FROM vendors, expenses WHERE expenses.vendor_id = vendors.vendor_id AND YEAR(expense_date) = $year AND vendors.company_id = $session_company_id");
        while($row = mysqli_fetch_array($sql_vendors)){
          $vendor_name = $row['vendor_name'];
          echo "\"$vendor_name\",";
        }
      
      ?>

    ],
    datasets: [{
      data: [
        <?php
          $sql_vendors = mysqli_query($mysqli,"SELECT DISTINCT vendor_name, vendors.vendor_id FROM vendors, expenses WHERE expenses.vendor_id = vendors.vendor_id AND YEAR(expense_date) = $year AND vendors.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql_vendors)){
            $vendor_id = $row['vendor_id'];

            $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS expense_amount_for_year FROM expenses WHERE vendor_id = $vendor_id AND YEAR(expense_date) = $year");
            $row = mysqli_fetch_array($sql_expenses);
            $expense_amount_for_year = $row['expense_amount_for_year'];
            echo "$expense_amount_for_year,";
          }
        
        ?>

      ],
      backgroundColor: [
        <?php
          $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, category_color FROM categories, expenses WHERE expenses.category_id = categories.category_id AND YEAR(expense_date) = $year AND categories.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql_categories)){
            $category_color = $row['category_color'];
            echo "\"$category_color\",";
          }
        
        ?>

      ],
    }],
  },
});

</script>