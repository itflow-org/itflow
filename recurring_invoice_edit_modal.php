<div class="modal" id="editRecurringModal<?php echo $recurring_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-sync-alt"></i> <?php echo "$recurring_prefix$recurring_number"; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="recurring_id" value="<?php echo $recurring_id; ?>">

        <div class="modal-body bg-white">

          <div class="form-group">
            <label>Frequency <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
              </div>
              <select class="form-control select2" name="frequency" required>
                <option value="">- Frequency -</option>
                <option <?php if($recurring_frequency == 'week'){ echo "selected"; } ?> value="week">Weekly</option>
                <option <?php if($recurring_frequency == 'month'){ echo "selected"; } ?> value="month">Monthly</option>
                <option <?php if($recurring_frequency == 'year'){ echo "selected"; } ?> value="year">Yearly</option>
              </select>
            </div>
          </div>

          
          
          <div class="form-group">
            <label>Income Category <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
              </div>
              <select class="form-control select2" name="category" required>
                <option value="">- Category -</option>
                <?php 
                
                $sql_income_category = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Income' AND (category_archived_at > '$recurring_created_at' OR category_archived_at IS NULL) AND company_id = $session_company_id ORDER BY category_name ASC"); 
                while($row = mysqli_fetch_array($sql_income_category)){
                  $category_id_select= $row['category_id'];
                  $category_name_select = htmlentities($row['category_name']);
                ?>
                <option <?php if($category_id == $category_id_select){ ?> selected <?php } ?> value="<?php echo $category_id_select; ?>"><?php echo $category_name_select; ?></option>
                
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
              <input type="text" class="form-control" name="scope" placeholder="Quick description" value="<?php echo $recurring_scope; ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Status <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
              </div>
              <select class="form-control select2" name="status" required>
                <option <?php if($recurring_status == 1){ echo "selected"; } ?> value="1">Active</option>
                <option <?php if($recurring_status == 0){ echo "selected"; } ?> value="0">InActive</option>
              </select>
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_recurring" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>