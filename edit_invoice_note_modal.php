<div class="modal" id="editInvoiceNoteModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-edit"></i> Invoice Note</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
        <div class="modal-body">
          <div class="form-group">
            <textarea class="form-control" rows="8" name="invoice_note"><?php echo $invoice_note; ?></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_invoice_note" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>