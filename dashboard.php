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

//GET THE YEARS
$sql_payment_years = mysqli_query($mysqli,"SELECT DISTINCT YEAR(payment_date) AS payment_year FROM payments ORDER BY payment_year DESC");


//Get Total income Do not grab transfer payment as these have an invoice_id of 0
$sql_total_income = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_income FROM payments WHERE YEAR(payment_date) = $year AND invoice_id > 0");
$row = mysqli_fetch_array($sql_total_income);
$total_income = $row['total_income'];

//Get Total expenses and do not grab transfer expenses as these have a vendor of 0
$sql_total_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE vendor_id > 0 AND YEAR(expense_date) = $year");
$row = mysqli_fetch_array($sql_total_expenses);
$total_expenses = $row['total_expenses'];

//Total up all the Invoices that are not draft or cancelled
$sql_invoice_totals = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS invoice_totals FROM invoices WHERE invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Cancelled' AND YEAR(invoice_date) = $year");
$row = mysqli_fetch_array($sql_invoice_totals);
$invoice_totals = $row['invoice_totals'];

$recievables = $invoice_totals - $total_income; 

$profit = $total_income - $total_expenses;

$sql_accounts = mysqli_query($mysqli,"SELECT * FROM accounts ORDER BY account_id DESC");

$sql_latest_income_payments = mysqli_query($mysqli,"SELECT * FROM payments, invoices, clients 
	WHERE payments.invoice_id = invoices.invoice_id 
	AND invoices.client_id = clients.client_id 
	ORDER BY payment_id DESC LIMIT 5"
);

$sql_latest_expenses = mysqli_query($mysqli,"SELECT * FROM expenses, vendors, categories 
	WHERE expenses.vendor_id = vendors.vendor_id 
	AND expenses.category_id = categories.category_id
	ORDER BY expense_id DESC LIMIT 5"
);

?>

<form>
  <select onchange="this.form.submit()" class="form-control selectpicker mb-3" name="year">
    <?php 
            
    while($row = mysqli_fetch_array($sql_payment_years)){
      $payment_year = $row['payment_year'];
    ?>
    <option <?php if($year == $payment_year){ ?> selected <?php } ?> > <?php echo $payment_year; ?></option>
    
    <?php
    }
    ?>

  </select>
</form>

<!-- Icon Cards-->
<div class="row">
  <div class="col-xl-4 col-sm-6 mb-3">
    <div class="card text-white bg-primary o-hidden h-100">
      <div class="card-body">
        <div class="card-body-icon">
          <i class="fas fa-fw fa-money-check"></i>
        </div>
        <div class="mr-5">Total Incomes <h1>$<?php echo number_format($total_income,2); ?></h1></div>
        <hr>
        Recievables: $<?php echo number_format($recievables,2); ?>
      </div>
    </div>
  </div>
  <div class="col-xl-4 col-sm-6 mb-3">
    <div class="card text-white bg-danger o-hidden h-100">
      <div class="card-body">
        <div class="card-body-icon">
          <i class="fas fa-fw fa-shopping-cart"></i>
        </div>
        <div class="mr-5">Total Expenses <h1>$<?php echo number_format($total_expenses,2); ?></h1></div>
      </div>      
    </div>
  </div>
  <div class="col-xl-4 col-sm-6 mb-3">
    <div class="card text-white bg-success o-hidden h-100">
      <div class="card-body">
        <div class="card-body-icon">
          <i class="fas fa-fw fa-heart"></i>
        </div>
        <div class="mr-5">Total Profit <h1>$<?php echo number_format($profit,2); ?></h1></div>
      </div>
    </div>
  </div> 

  <div class="col-md-12">
    <!-- Area Chart Example-->
    <div class="card mb-3">
      <div class="card-header"><i class="fas fa-fw fa-chart-area"></i> Cash Flow</div>
      <div class="card-body">
        <canvas id="myAreaChart" width="100%" height="20"></canvas>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header">
        <i class="fas fa-chart-pie"></i>
        Income By Category
      </div>
      <div class="card-body">
        <canvas id="incomeByCategoryPieChart" width="100%" height="60"></canvas>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header">
        <i class="fas fa-chart-pie"></i>
        Expense By Category
      </div>
      <div class="card-body">
        <canvas id="expenseByCategoryPieChart" width="100%" height="60"></canvas>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header">
        <i class="fas fa-chart-pie"></i>
        Expense By Vendor
      </div>
      <div class="card-body">
        <canvas id="expenseByVendorPieChart" width="100%" height="60"></canvas>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card">
      <div class="card-header">
        Account Balance
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
	            $sql2 = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments FROM payments WHERE account_id = $account_id");
	            $row2 = mysqli_fetch_array($sql2);
	            
	            $sql3 = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE account_id = $account_id");
	            $row3 = mysqli_fetch_array($sql3);
	            
	            $balance = $opening_balance + $row2['total_payments'] - $row3['total_expenses'];
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
  <div class="col-md-4">
    <div class="card">
      <div class="card-header">
        Latest Payments
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
var ctx = document.getElementById("myAreaChart");
var myLineChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Nov", "Dec"],
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
          $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payment_amount_for_month FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND YEAR(payment_date) = $year AND MONTH(payment_date) = $month");
          $row = mysqli_fetch_array($sql_payments);
          $income_for_month = $row['payment_amount_for_month'];
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

// Pie Chart Example
var ctx = document.getElementById("incomeByCategoryPieChart");
var myPieChart = new Chart(ctx, {
  type: 'pie',
  data: {
    labels: [
      <?php
        $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, categories.category_id FROM categories, invoices WHERE invoices.category_id = categories.category_id AND invoice_status = 'Paid' AND YEAR(invoice_date) = $year");
        while($row = mysqli_fetch_array($sql_categories)){
          $category_name = $row['category_name'];
          echo "\"$category_name\",";
        }
      
      ?>

    ],
    datasets: [{
      data: [
        <?php
          $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, categories.category_id FROM categories, invoices WHERE invoices.category_id = categories.category_id AND invoice_status = 'Paid' AND YEAR(invoice_date) = $year");
          while($row = mysqli_fetch_array($sql_categories)){
            $category_id = $row['category_id'];

            $sql_invoices = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS income_amount_for_year FROM invoices WHERE category_id = $category_id AND YEAR(invoice_date) = $year");
            $row = mysqli_fetch_array($sql_invoices);
            $income_amount_for_year = $row['income_amount_for_year'];
            echo "$income_amount_for_year,";
          }
        
        ?>

      ],
      backgroundColor: [
        <?php
           $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, categories.category_id, category_color FROM categories, invoices WHERE invoices.category_id = categories.category_id AND YEAR(invoice_date) = $year");
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
        $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, categories.category_id FROM categories, expenses WHERE expenses.category_id = categories.category_id AND YEAR(expense_date) = $year");
        while($row = mysqli_fetch_array($sql_categories)){
          $category_name = $row['category_name'];
          echo "\"$category_name\",";
        }
      
      ?>

    ],
    datasets: [{
      data: [
        <?php
          $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, categories.category_id FROM categories, expenses WHERE expenses.category_id = categories.category_id AND YEAR(expense_date) = $year");
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
          $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, category_color FROM categories, expenses WHERE expenses.category_id = categories.category_id AND YEAR(expense_date) = $year");
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
        $sql_vendors = mysqli_query($mysqli,"SELECT DISTINCT vendor_name, vendors.vendor_id FROM vendors, expenses WHERE expenses.vendor_id = vendors.vendor_id AND YEAR(expense_date) = $year");
        while($row = mysqli_fetch_array($sql_vendors)){
          $vendor_name = $row['vendor_name'];
          echo "\"$vendor_name\",";
        }
      
      ?>

    ],
    datasets: [{
      data: [
        <?php
          $sql_vendors = mysqli_query($mysqli,"SELECT DISTINCT vendor_name, vendors.vendor_id FROM vendors, expenses WHERE expenses.vendor_id = vendors.vendor_id AND YEAR(expense_date) = $year");
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
          $sql_categories = mysqli_query($mysqli,"SELECT DISTINCT category_name, category_color FROM categories, expenses WHERE expenses.category_id = categories.category_id AND YEAR(expense_date) = $year");
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