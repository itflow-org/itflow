<div class="modal" id="editQuoteModal<?php echo $quote_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-file mr-2"></i><?php echo $quote_number; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="quote_id" value="<?php echo $quote_id; ?>">

        <div class="modal-body bg-white">

          <div class="form-group">
            <label>Quote Date</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
              </div>
              <input type="date" class="form-control" name="date" value="<?php echo $quote_date; ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Income Category</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
              </div>
              <select class="form-control select2" name="category" required>
                <option value="">- Category -</option>
                <?php 
                
                $sql_income_category = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Income' AND company_id = $session_company_id"); 
                while($row = mysqli_fetch_array($sql_income_category)){
                  $category_id_select = $row['category_id'];
                  $category_name_select = $row['category_name'];
                ?>
                <option <?php if($category_id_select == $category_id){ ?> selected <?php } ?> value="<?php echo $category_id_select; ?>"><?php echo $category_name_select; ?></option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_quote" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>