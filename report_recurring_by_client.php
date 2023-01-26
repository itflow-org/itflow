<?php include("inc_all_reports.php");

$sql_clients = mysqli_query($mysqli,"SELECT * FROM clients WHERE company_id = $session_company_id");

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-sync"></i> Recurring Income By Client</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print"></i> Print</button>
    </div>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-sm">
        <thead>
          <tr>
            <th>Client</th>
            <th class="text-right">Monthly Recurring</th>
          </tr>
        </thead>
        <tbody>
          <?php

          while ($row = mysqli_fetch_array($sql_clients)) {
            $client_id = $row['client_id'];
            $client_name = htmlentities($row['client_name']);

            //Get Monthly Recurring Total
            $sql_recurring_monthly_total = mysqli_query($mysqli,"SELECT SUM(recurring_amount) AS recurring_monthly_total FROM recurring WHERE recurring_status = 1 AND recurring_frequency = 'month' AND recurring_client_id = $client_id AND company_id = $session_company_id");
            $row = mysqli_fetch_array($sql_recurring_monthly_total);

            $recurring_monthly_total = $row['recurring_monthly_total'];

            //Get Yearly Recurring Total
            $sql_recurring_yearly_total = mysqli_query($mysqli,"SELECT SUM(recurring_amount) AS recurring_yearly_total FROM recurring WHERE recurring_status = 1 AND recurring_frequency = 'year' AND recurring_client_id = $client_id AND company_id = $session_company_id");
            $row = mysqli_fetch_array($sql_recurring_yearly_total);

            $recurring_yearly_total = $row['recurring_yearly_total'] / 12;

            $recurring_monthly = $recurring_monthly_total + $recurring_yearly_total;
            $recurring_total = $recurring_total + $recurring_monthly;

            if ($recurring_monthly > 0) {

              ?>

              <tr>
                <td><?php echo $client_name; ?></td>
                <td class="text-right"><?php echo numfmt_format_currency($currency_format, $recurring_monthly, $session_company_currency); ?></td>
              </tr>
              <?php
              }
            }
            ?>
            <tr>
              <th>Total</th>
              <th class="text-right"><?php echo numfmt_format_currency($currency_format, $recurring_total, $session_company_currency); ?></th>
            </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("footer.php"); ?>
