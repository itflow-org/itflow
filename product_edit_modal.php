<div class="modal" id="editProductModal<?php echo $product_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-box"></i> Editing product: <strong><?php echo $product_name; ?></strong></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
        <div class="modal-body bg-white">
          
          <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-fw fa-box"></i></span>
              </div>
              <input type="text" class="form-control" name="name" value="<?php echo $product_name; ?>" required>
            </div>
          </div>
          
          <div class="form-group">
            <label>Category <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
              </div>
              <select class="form-control select2" name="category" required>
                <?php 
                
                $sql_select = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Income' AND (category_archived_at > '$product_created_at' OR category_archived_at IS NULL) AND company_id = $session_company_id"); 
                while ($row = mysqli_fetch_array($sql_select)) {
                  $category_id_select = $row['category_id'];
                  $category_name_select = htmlentities($row['category_name']);
                ?>
                <option <?php if ($category_id == $category_id_select) { echo "selected"; } ?> value="<?php echo $category_id_select; ?>"><?php echo $category_name_select; ?></option>
                <?php
                }
                
                ?>
              </select>
              <div class="input-group-append">
                <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#addQuickCategoryIncomeModal"><i class="fas fa-fw fa-plus"></i></button>
              </div>
            </div>
          </div>

          <div class="form-row">
            <div class="col">
              <div class="form-group">
                <label>Price <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                  </div>
                  <input type="number" step="0.01" min="0" class="form-control" name="price" value="<?php echo $product_price; ?>" required>
                </div>
              </div>
            </div>
            <div class="col">
              <div class="form-group">
                <label>Tax</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                  </div>
                  <select class="form-control select2" name="tax">
                    <option value="0">None</option>
                    <?php 
                    
                    $taxes_sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE (tax_archived_at > '$product_created_at' OR tax_archived_at IS NULL) AND company_id = $session_company_id ORDER BY tax_name ASC"); 
                    while ($row = mysqli_fetch_array($taxes_sql)) {
                      $tax_id_select = $row['tax_id'];
                      $tax_name = htmlentities($row['tax_name']);
                      $tax_percent = htmlentities($row['tax_percent']);
                    ?>
                      <option <?php if ($tax_id_select == $product_tax_id) { echo "selected"; } ?> value="<?php echo $tax_id_select; ?>"><?php echo "$tax_name $tax_percent%"; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea class="form-control" rows="5" name="description"><?php echo $product_description; ?></textarea>
          </div>
        
        </div>
        
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_product" class="btn btn-primary text-bold"><i class="fa fa-check"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>