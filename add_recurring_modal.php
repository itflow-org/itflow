<div class="modal" id="addRecurringModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-copy mr-2"></i>New Recurring Invoice</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        
        <?php if(isset($_GET['client_id'])){ ?>
          <input type="hidden" name="client" value="<?php echo $client_id; ?>">
        <?php } ?>

        <div class="modal-body bg-white">
          <div class="form-group">
            <label>Client</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
              </div>
              <select class="form-control selectpicker show-tick" data-live-search="true" name="client" required <?php if(isset($_GET['client_id'])){ echo "disabled"; } ?>>
                <option value="">- Client -</option>
                <?php 
                
                $sql = mysqli_query($mysqli,"SELECT * FROM clients"); 
                while($row = mysqli_fetch_array($sql)){
                  $client_id = $row['client_id'];
                  $client_name = $row['client_name'];
                ?>
                  <option <?php if($_GET['client_id'] == $client_id) { echo "selected"; } ?> value="<?php echo "$client_id"; ?>"><?php echo "$client_name"; ?></option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>
         
          <div class="form-group">
            <label>Start Date</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
              </div>
              <input type="date" class="form-control" name="start_date" value="<?php echo date("Y-m-d"); ?>" required>
            </div>
          </div>
          
          <div class="form-group">
            <label>Frequency</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
              </div>
              <select class="form-control selectpicker show-tick" name="frequency" required>
                <option value="">- Frequency -</option>
                <option value="week">Weekly</option>
                <option value="month">Monthly</option>
                <option value="year">Yearly</option>
              </select>
            </div>
          </div>
         
        
          <div class="form-group">
            <label>Income Category</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
              </div>
              <select class="form-control selectpicker show-tick" name="category" required>
                <option value="">- Category -</option>
                <?php 
                
                $sql = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Income'"); 
                while($row = mysqli_fetch_array($sql)){
                  $category_id = $row['category_id'];
                  $category_name = $row['category_name'];
                ?>
                <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_recurring" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>