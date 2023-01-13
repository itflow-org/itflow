<div class="modal" id="editInvoiceModal<?php echo $invoice_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-file"></i> Editing invoice: <strong><?php echo "$invoice_prefix$invoice_number"; ?></strong> - <?php echo $client_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">

        <div class="modal-body bg-white">

          <div class="form-group">
            <label>Invoice Date <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
              </div>
              <input type="date" class="form-control" name="date" max="2999-12-31" value="<?php echo $invoice_date; ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Invoice Due <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar-alt"></i></span>
              </div>
              <input type="date" class="form-control" name="due" max="2999-12-31" value="<?php echo $invoice_due; ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Income Category <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
              </div>
              <select class="form-control select2" name="category" required>
                <option value="">- Category -</option>
                <?php 
                
                $sql_income_category = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Income' AND (category_archived_at > '$invoice_created_at' OR category_archived_at IS NULL) AND company_id = $session_company_id ORDER BY category_name ASC"); 
                while($row = mysqli_fetch_array($sql_income_category)){
                  $category_id_select= $row['category_id'];
                  $category_name_select = htmlentities($row['category_name']);
                ?>
                <option <?php if($category_id == $category_id_select){ echo "selected"; } ?> value="<?php echo $category_id_select; ?>"><?php echo $category_name_select; ?></option>
                
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
              <input type="text" class="form-control" name="scope" placeholder="Quick description" value="<?php echo $invoice_scope; ?>">
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_invoice" class="btn btn-primary"><strong><i class="fas fa-check"></i> Save</strong></button>
        </div>
      </form>
    </div>
  </div>
</div>