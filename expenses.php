<?php include("header.php"); ?>

<?php $sql = mysqli_query($mysqli,"SELECT * FROM expenses ORDER BY expense_id DESC"); ?>

<div class="card mb-3">
  <div class="card-header">
    <i class="fas fa-table"></i>
    Expenses
    <button type="button" class="btn btn-primary btn-sm ml-4" data-toggle="modal" data-target="#addExpenseModal"><i class="fas fa-plus"></i> Add New</button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dT" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Date</th>
            <th class="text-right">Amount</th>
            <th>Vendor</th>
            <th>Category</th>
            <th>Account</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $expense_id = $row['expense_id'];
            $expense_date = $row['expense_date'];
            $expense_amount = $row['expense_amount'];
            $vendor_name = $row['vendor_name'];
            $expense_category = $row['expense_category'];

          ?>
          <tr>
            <td><?php echo "$expense_date"; ?></td>
            <td class="text-right text-monospace">$<?php echo "$expense_amount"; ?></td>
            <td>Amazon</td>
            <td>Office Supplies</td>
            <td>PNC Bank</td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#">Edit</a>
                  <a class="dropdown-item" href="#">Duplicate</a>
                  <a class="dropdown-item" href="#">Refund</a>
                  <a class="dropdown-item" href="#">Delete</a>
                </div>
              </div>      
            </td>
          </tr>
          <tr>
            <td><a href="#">15 Nov 2018</a></td>
            <td class="text-right text-monospace">$14.53</td>
            <td>Amazon</td>
            <td>Office Supplies</td>
            <td>PNC Bank</td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-link btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#">Edit</a>
                  <a class="dropdown-item" href="#">Duplicate</a>
                  <a class="dropdown-item" href="#">Refund</a>
                  <a class="dropdown-item" href="#">Delete</a>
                </div>
              </div>      
            </td>
          </tr>
          <tr>
            <td><a href="#">15 Nov 2018</a></td>
            <td class="text-right text-monospace">$14.53</td>
            <td>Amazon</td>
            <td>Office Supplies</td>
            <td>PNC Bank</td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-dark btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#">Edit</a>
                  <a class="dropdown-item" href="#">Duplicate</a>
                  <a class="dropdown-item" href="#">Refund</a>
                  <a class="dropdown-item" href="#">Delete</a>
                </div>
              </div>      
            </td>
          </tr>
          <tr>
            <td><a href="#">15 Nov 2018</a></td>
            <td class="text-right text-monospace">$14.53</td>
            <td>Amazon</td>
            <td>Office Supplies</td>
            <td>PNC Bank</td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-light btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#">Edit</a>
                  <a class="dropdown-item" href="#">Duplicate</a>
                  <a class="dropdown-item" href="#">Refund</a>
                  <a class="dropdown-item" href="#">Delete</a>
                </div>
              </div>      
            </td>
          </tr>
          <tr>
            <td><a href="#">15 Nov 2018</a></td>
            <td class="text-right text-monospace">$1,014.53</td>
            <td>Amazon</td>
            <td>Office Supplies</td>
            <td>PNC Bank</td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#">Edit</a>
                  <a class="dropdown-item" href="#">Duplicate</a>
                  <a class="dropdown-item" href="#">Refund</a>
                  <a class="dropdown-item" href="#">Delete</a>
                </div>
              </div>      
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer small text-muted">Updated yesterday at 11:59 PM</div>
</div>

<?php include("add_account_modal.php"); ?>

<?php include("footer.php");