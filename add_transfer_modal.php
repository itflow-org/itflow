<div class="modal fade" id="addTransferModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-exchange-alt"></i> New Transfer</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group col-sm">
              <label>Date</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" required>
              </div>
            </div>
            <div class="form-group col-sm">
              <label>Amount</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-hand-holding-usd"></i></span>
                </div>
                <input type="number" class="form-control" step="0.01" min="0" name="amount" placeholder="Amount to transfer" required>
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-sm">
              <label>Account From</label>
              <div class="input-group"> 
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-university"></i></span>
                </div> 
                <select class="form-control" name="account_from" required>
                  <option value="">- Transfer From -</option>
                  <?php 
                  
                  $sql = mysqli_query($mysqli,"SELECT * FROM accounts"); 
                  while($row = mysqli_fetch_array($sql)){
                    $account_id = $row['account_id'];
                    $account_name = $row['account_name'];
                  ?>
                    <option value="<?php echo $account_id; ?>"><?php echo "$account_name"; ?></option>
                  
                  <?php
                  }
                  ?>
                </select>
              </div>
            </div>
            <div class="form-group col-sm">
               <label>Account To</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-arrow-right"></i></span>
                </div>
                <select class="form-control" name="account_to" required>
                  <option value="">- Transfer To -</option>
                  <?php 
                  
                  $sql = mysqli_query($mysqli,"SELECT * FROM accounts"); 
                  while($row = mysqli_fetch_array($sql)){
                    $account_id = $row['account_id'];
                    $account_name = $row['account_name'];
                  ?>
                    <option value="<?php echo $account_id; ?>"><?php echo "$account_name"; ?></option>
                  
                  <?php
                  }
                  ?>
                </select>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_transfer" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>