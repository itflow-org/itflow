<?php include("header.php"); ?>

<?php $sql = mysqli_query($mysqli,"SELECT * FROM categories WHERE category type =  'Expense' ORDER BY vendor_id DESC"); ?>


<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-coins"></i> Profit & Loss</h6>
    <button type="button" class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#print"><i class="fas fa-print"></i> Print</button>
    <select class="form-control mt-5">
      <option>2019</option>
      <option>2018</option>
      <option>2017</option>
    </select>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table">
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
          <tr>
            <td>Income Category Type</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
          </tr>
          <tr>
            <td>Income Category Type 2</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
          </tr>
          <tr>
            <th>Gross Profit</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
          </tr>
          <tr>
            <th><br><br>Expenses</th>
            <th colspan="5"></th>
          </tr>
          <tr>
            <td>Expense Category Type</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
          </tr>
          <tr>
            <td>Expense Category Type 2</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
          </tr>
          <tr>
            <th>Total Expenses<br><br><br></th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
          </tr>
          <tr>
            <th>Net Profit</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("footer.php");