<?php include("header.php"); ?>

<?php 
 
  $sql = mysqli_query($mysqli,"SELECT * FROM expenses, categories, vendors, accounts
    WHERE expenses.category_id = categories.category_id
    AND expenses.vendor_id = vendors.vendor_id
    AND expenses.account_id = accounts.account_id
    ORDER BY expenses.expense_date DESC");
?>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-shopping-cart"></i> Expenses</h6>
    <button type="button" class="btn btn-primary btn-sm mr-auto float-right" data-toggle="modal" data-target="#addExpenseModal"><i class="fas fa-cart-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Date</th>
            <th class="text-right">Amount</th>
            <th>Vendor</th>
            <th>Category</th>
            <th>Account</th>
            <th></th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $expense_id = $row['expense_id'];
            $expense_date = $row['expense_date'];
            $expense_amount = $row['expense_amount'];
            $expense_description = $row['expense_description'];
            $expense_receipt = $row['expense_receipt'];
            $expense_reference = $row['expense_reference'];
            $vendor_id = $row['vendor_id'];
            $vendor_name = $row['vendor_name'];
            $category_id = $row['category_id'];
            $category_name = $row['category_name'];
            $account_name = $row['account_name'];
            $account_id = $row['account_id'];

            if(empty($expense_receipt)){
              $receipt_attached = "";
            }else{
              $receipt_attached = "<a class='btn btn-dark btn-sm' target='_blank' href='$expense_receipt'><i class='fa fa-file-pdf'></i></a>";
            }

          ?>

          <tr>
            <td><?php echo $expense_date; ?></td>
            <td class="text-right text-monospace">$<?php echo number_format($expense_amount,2); ?></td>
            <td><?php echo $vendor_name; ?></td>
            <td><?php echo $category_name; ?></td>
            <td><?php echo $account_name; ?></td>
            <td><?php echo $receipt_attached; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editExpenseModal<?php echo $expense_id; ?>">Edit</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addExpenseCopyModal<?php echo $expense_id; ?>">Copy</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addExpenseRefundModal<?php echo $expense_id; ?>">Refund</a>
                  <a class="dropdown-item" href="post.php?delete_expense=<?php echo $expense_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php

          include("edit_expense_modal.php");
          include("add_expense_copy_modal.php");
          include("add_expense_refund_modal.php");
          }

          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_expense_modal.php"); ?>

<?php include("footer.php");