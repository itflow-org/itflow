<?php include("header.php"); ?>

<?php $sql = mysqli_query($mysqli,"SELECT * FROM categories WHERE category type =  'Expense' ORDER BY vendor_id DESC"); ?>


<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-coins"></i> Expense Summary</h6>
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
          <tr>
            <td>Expense Category Type</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
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
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
            <td class="text-right">$0.00</td>
          </tr>
          <tr>
            <th>Total</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
            <th class="text-right">$0.00</th>
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