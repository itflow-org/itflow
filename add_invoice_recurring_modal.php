<div class="modal" id="addInvoiceRecurringModal<?php echo $invoice_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-copy mr-2"></i>Make INV-<?php echo $invoice_number; ?> Recurring</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>"> 
        <div class="modal-body bg-white">
          
          <div class="form-group">
            <label>Frequency</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
              </div>
              <select class="form-control" name="frequency" required>
                <option value="">- Frequency -</option>
                <option value="week">Weekly</option>
                <option value="month">Monthly</option>
                <option value="year">Yearly</option>
              </select>
            </div>
          </div>
        
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_invoice_recurring" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>