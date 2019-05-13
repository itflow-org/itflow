<?php include("header.php"); ?>

<?php 

$sql = mysqli_query($mysqli,"SELECT * FROM transfers ORDER BY transfer_date DESC"); 

?>


<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-exchange-alt mr-2"></i>Transfers</h6>
    <button type="button" class="btn btn-primary btn-sm mr-auto float-right" data-toggle="modal" data-target="#addTransferModal"><i class="fas fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead class="thead-dark">
          <tr>
            <th>Date</th>
            <th>From Account</th>
            <th>To Account</th>
            <th class="text-right">Amount</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $transfer_id = $row['transfer_id'];
            $transfer_date = $row['transfer_date'];
            $transfer_account_from = $row['transfer_account_from'];
            $transfer_account_to = $row['transfer_account_to'];
            $transfer_amount = $row['transfer_amount'];
            $expense_id = $row['expense_id'];
            $payment_id = $row['payment_id'];
            
            $sql2 = mysqli_query($mysqli,"SELECT * FROM accounts WHERE account_id = $transfer_account_from");
            $row = mysqli_fetch_array($sql2);
            $account_name_from = $row['account_name'];

            $sql2 = mysqli_query($mysqli,"SELECT * FROM accounts WHERE account_id = $transfer_account_to");
            $row = mysqli_fetch_array($sql2);
            $account_name_to = $row['account_name'];

          ?>
          <tr>
            <td><?php echo $transfer_date; ?></td>
            <td><?php echo $account_name_from; ?></td>
            <td><?php echo $account_name_to; ?></td>
            <td class="text-right text-monospace">$<?php echo number_format($transfer_amount,2); ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTransferModal<?php echo $transfer_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_transfer=<?php echo $transfer_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php
          include("edit_transfer_modal.php");
          }
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_transfer_modal.php"); ?>

<?php include("footer.php");