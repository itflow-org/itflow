<div class="modal" id="editProductModal<?php echo $product_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-box"></i> <?php echo $product_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
        <div class="modal-body bg-white">
          <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="name" value="<?php echo $product_name; ?>" required>
          </div>
          
          <div class="form-group">
            <label>Category <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
              </div>
              <select class="form-control select2" name="category" required>
                <?php 
                
                $sql_select = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Income' AND company_id = $session_company_id"); 
                while($row = mysqli_fetch_array($sql_select)){
                  $category_id_select = $row['category_id'];
                  $category_name_select = $row['category_name'];
                ?>
                <option <?php if($category_id == $category_id_select){ echo "selected"; } ?> value="<?php echo $category_id_select; ?>"><?php echo $category_name_select; ?></option>
                <?php
                }
                
                ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Description</label>
            <input type="text" class="form-control" name="description" value="<?php echo $product_description; ?>">
          </div>
          
          <div class="form-group">
            <label>Cost <strong class="text-danger">*</strong></label>
            <input type="number" step="0.01" min="0" class="form-control" name="cost" value="<?php echo $product_cost; ?>" required>
          </div>

          <div class="form-group">
            <label>Tax</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
              </div>
              <select class="form-control select2" name="tax">
                <option value="0">None</option>
                <?php 
                
                $taxes_sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE company_id = $session_company_id ORDER BY tax_name ASC"); 
                while($row = mysqli_fetch_array($taxes_sql)){
                  $tax_id_select = $row['tax_id'];
                  $tax_name = $row['tax_name'];
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
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_product" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>