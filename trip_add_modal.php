<div class="modal" id="addTripModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-route"></i> New Trip</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">
        
          <div class="form-row">
            <div class="form-group col">
              <label>Date <strong class="text-danger">*</strong></label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" value="<?php echo date("Y-m-d"); ?>" required>
              </div>
            </div>
            
            <div class="form-group col">
              <label>Miles / Roundtrip <strong class="text-danger">*</strong></label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-bicycle"></i></span>
                </div>
                <input type="number" step="0.1" min="0" class="form-control" name="miles" placeholder="Enter miles" required autofocus>
                <div class="input-group-append">
                  <div class="input-group-text">
                    <input type="checkbox" name="roundtrip" value="1">
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="form-group">
            <label>Location <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
              </div>
              <input type="text" class="form-control" name="source" placeholder="Enter your starting location" required>
            </div>
          </div>
          
          <div class="form-group">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-arrow-right"></i></span>
              </div>
              <input type="text" class="form-control" name="destination" placeholder="Enter your destination" required>
            </div>
          </div>
          
          <div class="form-group">
            <label>Purpose <strong class="text-danger">*</strong></label>
            <textarea rows="4" class="form-control" placeholder="Enter a purpose" name="purpose" required></textarea>
          </div>

          <div class="form-group">
            <label>Driver</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
              </div>
              <select class="form-control select2" name="user" required>
                <option value="">- Driver -</option>
                <?php 
                
                // WIP Need to only show users within the session company
                $sql = mysqli_query($mysqli,"SELECT * FROM users ORDER BY user_name ASC"); 
                while($row = mysqli_fetch_array($sql)){
                  $user_id = $row['user_id'];
                  $user_name = $row['user_name'];
                ?>
                  <option <?php if($session_user_id = $user_id){ echo "selected"; } ?> value="<?php echo $user_id; ?>"><?php echo $user_name; ?></option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>

          <?php if(isset($_GET['client_id'])){ ?>
          <input type="hidden" name="client" value="<?php echo $client_id; ?>">
          <?php }else{ ?>
          
          <div class="form-group">
            <label>Client</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
              </div>
              <select class="form-control select2" name="client" required>
                <option value="0">- Client (Optional) -</option>
                <?php 
                
                $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE company_id = $session_company_id ORDER BY client_name ASC"); 
                while($row = mysqli_fetch_array($sql)){
                  $client_id = $row['client_id'];
                  $client_name = $row['client_name'];
                ?>
                  <option value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>

          <?php } ?>

        </div>

        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_trip" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div> <!-- Modal Content -->
  </div> <!-- Modal Dialog -->
</div> <!-- Modal -->