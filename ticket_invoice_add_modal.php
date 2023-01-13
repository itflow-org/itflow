<div class="modal" id="addInvoiceFromTicketModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-file"></i> Invoice ticket</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        
        <div class="modal-body bg-white">
         
          <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
         
          <div class="form-group">
            <label>Exisiting Invoice?</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-file-invoice-dollar"></i></span>
              </div>
              <select class="form-control select2" name="invoice_id">
                <option value="0">New Invoice</option>
                <?php 
                
                $sql_invoices = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_status NOT LIKE 'Paid' AND invoice_client_id = $client_id AND company_id = $session_company_id ORDER BY invoice_number ASC"); 
                while($row = mysqli_fetch_array($sql_invoices)){
                  $invoice_id = $row['invoice_id'];
                  $invoice_prefix = htmlentities($row['invoice_prefix']);
                  $invoice_number = $row['invoice_number'];
                  $invoice_scope = htmlentities($row['invoice_scope']);
                  $invoice_satus = htmlentities($row['invoice_status']);
                  $invoice_date = $row['invoice_date'];
                  $invoice_due = $row['invoice_due'];
                  $invoice_amount = $row['invoice_amount'];

                ?>
                <option value="<?php echo $invoice_id; ?>"><?php echo "$invoice_prefix$invoice_number $invoice_scope"; ?></option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Invoice Date <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
              </div>
              <input type="date" class="form-control" name="date" max="2999-12-31" value="<?php echo date("Y-m-d"); ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Invoice Category <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
              </div>
              <select class="form-control select2" name="category" required>
                <option value="">- Category -</option>
                <?php 
                
                $sql = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Income' AND category_archived_at IS NULL AND company_id = $session_company_id ORDER BY category_name ASC"); 
                while($row = mysqli_fetch_array($sql)){
                  $category_id = $row['category_id'];
                  $category_name = htmlentities($row['category_name']);
                ?>
                <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                
                <?php
                }
                ?>
              </select>
              <div class="input-group-append">
                <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#addQuickCategoryIncomeModal"><i class="fas fa-fw fa-plus"></i></button>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label>Scope</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
              </div>
              <input type="text" class="form-control" name="scope" placeholder="Quick description" value="Ticket <?php echo "$ticket_prefix$ticket_number - $ticket_subject"; ?>">
            </div>
          </div>

          <hr>

          <div class="form-group">
            <label>Item <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-box"></i></span>
              </div>
              <input type="text" class="form-control" name="item_name" placeholder="Item" required>
            </div>
          </div>

          <div class="form-group">
            <label>Item Description</label>
            <div class="input-group">
              <textarea class="form-control" rows="5" name="item_description"><?php echo "# $contact_name - $asset_name - $ticket_date\nTicket $ticket_prefix$ticket_number\n$ticket_subject"; ?></textarea>
            </div>
          </div>
          
          <div class="form-row">
            <div class="col">

              <div class="form-group">
                <label>QTY <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                  </div>
                  <input type="number" class="form-control" step="0.01" min="0" name="qty" value="1" required>
                </div>
              </div>

            </div>

            <div class="col">

              <div class="form-group">
                <label>Price <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                  </div>
                  <input type="number" class="form-control" step="0.01" min="0" name="price" value="0.00" required>
                </div>
              </div>
            
            </div>
          
          </div>

          <div class="form-group">
            <label>Tax <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
              </div>
              <select class="form-control select2" name="tax_id" required>
                <option value="0">None</option>
                <?php 
                
                $taxes_sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE (tax_archived_at > '$item_created_at' OR tax_archived_at IS NULL) AND company_id = $session_company_id ORDER BY tax_name ASC"); 
                while($row = mysqli_fetch_array($taxes_sql)){
                  $tax_id_select = $row['tax_id'];
                  $tax_name = htmlentities($row['tax_name']);
                  $tax_percent = $row['tax_percent'];
                ?>
                  <option <?php if($tax_id_select == $tax_id){ echo "selected"; } ?> value="<?php echo $tax_id_select; ?>"><?php echo "$tax_name $tax_percent%"; ?></option>
                
                <?php
                }
                ?>
              </select>
              
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_invoice_from_ticket" class="btn btn-primary text-bold"><i class="fa fa-check"></i> Create Invoice</button>
        </div>
      </form>
    </div>
  </div>
</div>