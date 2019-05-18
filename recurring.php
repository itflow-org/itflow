<?php include("header.php"); ?>

<?php 
 
  $sql = mysqli_query($mysqli,"SELECT * FROM recurring, clients, categories
    WHERE recurring.client_id = clients.client_id
    AND recurring.category_id = categories.category_id
    ORDER BY recurring.recurring_id DESC");
?>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-copy mr-2"></i>Recurring Invoices</h6>
    <button type="button" class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addRecurringModal"><i class="fas fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead class="thead-dark">
          <tr>
            <th>Frequency</th>
            <th>Client</th>
            <th>Last Sent</th>
            <th>Next Date</th>
            <th>Category</th>
            <th>Status</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $recurring_id = $row['recurring_id'];
            $recurring_frequency = $row['recurring_frequency'];
            $recurring_status = $row['recurring_status'];
            $recurring_last_sent = $row['recurring_last_sent'];
            if($recurring_last_sent == 0){
              $recurring_last_sent = "-";
            }
            $recurring_next_date = $row['recurring_next_date'];
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];
            $category_id = $row['category_id'];
            $category_name = $row['category_name'];
            if($recurring_status == 1){
              $status = "Active";
              $status_badge_color = "success";
            }else{
              $status = "Inactive";
              $status_badge_color = "secondary";
            }

          ?>

          <tr>
            <td><?php echo ucwords($recurring_frequency); ?>ly</td>
            <td><a href="client.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
            <td><?php echo $recurring_last_sent; ?></td>
            <td><?php echo $recurring_next_date; ?></td>
            <td><?php echo $category_name; ?></td>
            <td>
               <span class="p-2 badge badge-<?php echo $status_badge_color; ?>">
                <?php echo $status; ?>
              </span>
                
            </td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="recurring_invoice.php?recurring_id=<?php echo $recurring_id; ?>">Edit</a>
                  <?php if($recurring_status == 1){ ?>
                    <a class="dropdown-item" href="post.php?recurring_deactivate=<?php echo $recurring_id; ?>">Deactivate</a>
                  <?php }else{ ?>
                    <a class="dropdown-item" href="post.php?recurring_activate=<?php echo $recurring_id; ?>">Activate</a>
                  <?php } ?>
                    <a class="dropdown-item" href="post.php?delete_recurring=<?php echo $recurring_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>
          
          <?php
          }
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_recurring_modal.php"); ?>

<?php include("footer.php");