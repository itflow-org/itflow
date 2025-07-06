<div class="modal" id="editItemModal<?php echo $item_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-fw fa-edit mr-2"></i>Editing Line Item: <strong><?php echo $item_name; ?></strong></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <?php if (isset($invoice_id)) { ?>
          <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
        <?php }elseif (isset($quote_id)) { ?>
          <input type="hidden" name="quote_id" value="<?php echo $quote_id; ?>">
        <?php }else{ ?>
          <input type="hidden" name="recurring_id" value="<?php echo $recurring_id; ?>">
        <?php } ?>
        
        <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
        <div class="modal-body bg-white">
          
          <div class="form-group">
            <label>Item <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-box"></i></span>
              </div>
              <input type="text" class="form-control" name="name" maxlength="200" value="<?php echo $item_name; ?>" placeholder="Enter item name" required>
            </div>
          </div>

          <div class="form-row">
            <div class="col-sm">

              <div class="form-group">
                <label>Quantity <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                  </div>
                  <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="qty" value="<?php echo number_format($item_quantity,2); ?>" placeholder="0.00" required>
                </div>
              </div>

            </div>

            <div class="col-sm">

              <div class="form-group">
                <label>Price <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                  </div>
                  <input type="text" class="form-control" inputmode="numeric" pattern="-?[0-9]*\.?[0-9]{0,2}" name="price" value="<?php echo number_format($item_price, 2, '.', ''); ?>" placeholder="0.00" required>
                </div>
              </div>
            
            </div>
          
          </div>

          <div class="form-group">
            <label>Description</label>
            <div class="input-group">
              <textarea class="form-control" rows="5" name="description" placeholder="Enter a description"><?php echo $item_description; ?></textarea>
            </div>
          </div>

          <div class="form-group">
            <label>Tax <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
              </div>
              <select class="form-control select2" name="tax_id" required>
                <option value="0">No Tax</option>
                <?php 
                
                $taxes_sql = mysqli_query($mysqli, "SELECT * FROM taxes WHERE (tax_archived_at > '$item_created_at' OR tax_archived_at IS NULL) ORDER BY tax_name ASC"); 
                while ($row = mysqli_fetch_array($taxes_sql)) {
                  $tax_id_select = intval($row['tax_id']);
                  $tax_name = nullable_htmlentities($row['tax_name']);
                  $tax_percent = floatval($row['tax_percent']);
                ?>
                  <option <?php if ($tax_id_select == $tax_id) { echo "selected"; } ?> value="<?php echo $tax_id_select; ?>"><?php echo "$tax_name $tax_percent%"; ?></option>
                
                <?php
                }
                ?>
              </select>
              
            </div>
          </div>
          
        </div>
        <div class="modal-footer bg-white">
          <button type="submit" name="edit_item" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
          <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>