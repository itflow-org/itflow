<div class="modal" id="addAccountModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-piggy-bank"></i> New Account</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">
          
          <div class="form-group">
            <label>Account Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Account name" required autofocus>
            </div>
          </div>
          
          <div class="form-group">
            <label>Opening Balance</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
              </div>
              <input type="number" class="form-control" step="0.01" min="0" name="opening_balance" placeholder="Opening Balance" required>
            </div>
          </div>

          <div class="form-group">
            <label>Currency <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
              </div>
              <select class="form-control select2" name="currency_code" required>
                <option value="">- Currency -</option>
                <?php foreach($currencies_array as $currency_code => $currency_name) { ?>
                <option <?php if($session_company_currency == $currency_code){ echo "selected"; } ?> value="<?php echo $currency_code; ?>"><?php echo "$currency_code - $currency_name"; ?></option>
                <?php } ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Notes</label>
            <textarea class="form-control" rows="5" placeholder="Enter some notes" name="notes"></textarea>
          </div>
        
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_account" class="btn btn-primary text-bold"><i class="fa fa-fw fa-check"></i> Create</button>
        </div>
      </form>
    </div>
  </div>
</div>