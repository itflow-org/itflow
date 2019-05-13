<?php include("header.php"); ?>

<?php 
 
  $sql = mysqli_query($mysqli,"SELECT * FROM recurring_invoices, invoices, clients
    WHERE invoices.client_id = clients.client_id
    AND invoices.invoice_id = recurring_invoices.invoice_id
    ORDER BY recurring_invoices.recurring_invoice_id DESC");
?>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-copy mr-2"></i>Recurring Invoices</h6>
    <button type="button" class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addRecurringInvoiceModal"><i class="fas fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Frequency</th>
            <th>Client</th>
            <th>Start Date</th>
            <th>Last Sent</th>
            <th>Next Date</th>
            <th>Status</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $recurring_invoice_id = $row['recurring_invoice_id'];
            $recurring_invoice_frequency = $row['recurring_invoice_frequency'];
            $recurring_invoice_status = $row['recurring_invoice_status'];
            $recurring_invoice_start_date = $row['recurring_invoice_start_date'];
            $recurring_invoice_last_sent = $row['recurring_invoice_last_sent'];
            if($recurring_invoice_last_sent == 0){
              $recurring_invoice_last_sent = "-";
            }
            $recurring_invoice_next_date = $row['recurring_invoice_next_date'];
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];
            $invoice_id = $row['invoice_id'];
            if($recurring_invoice_status == 1){
              $status = "Active";
              $status_badge_color = "success";
            }else{
              $status = "Inactive";
              $status_badge_color = "secondary";
            }


          ?>

          <tr>
            <td><?php echo ucwords($recurring_invoice_frequency); ?>ly</td>
            <td><a href="client.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
            <td><?php echo $recurring_invoice_start_date; ?></td>
            <td><?php echo $recurring_invoice_last_sent; ?></td>
            <td><?php echo $recurring_invoice_next_date; ?></td>
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
                  <a class="dropdown-item" href="recurring_invoice.php?recurring_invoice_id=<?php echo $recurring_invoice_id; ?>">Edit</a>
                  <?php if($recurring_invoice_status == 1){ ?>
                    <a class="dropdown-item" href="post.php?recurring_deactivate=<?php echo $recurring_invoice_id; ?>">Deactivate</a>
                  <?php }else{ ?>
                    <a class="dropdown-item" href="post.php?recurring_activate=<?php echo $recurring_invoice_id; ?>">Activate</a>
                  <?php } ?>
                    <a class="dropdown-item" href="post.php?delete_invoice=<?php echo $invoice_id; ?>">Delete</a>
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

<?php include("add_recurring_invoice_modal.php"); ?>

<?php include("footer.php");