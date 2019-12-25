<div class="modal" id="addQuoteToInvoiceModal<?php echo $quote_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-file mr-2"></i><?php echo $quote_number; ?> <i class="fa fa-fw fa-arrow-right"></i> Invoice</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="quote_id" value="<?php echo $quote_id; ?>">
        <input type="hidden" name="client_net_terms" value="<?php echo $client_net_terms; ?>">
        
        <div class="modal-body bg-white">
         
          <div class="form-group">
            <label>Invoice Date <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
              </div>
              <input type="date" class="form-control" name="date" value="<?php echo date("Y-m-d"); ?>" required>
            </div>
          </div>
      
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_quote_to_invoice" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>