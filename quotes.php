<?php include("header.php"); ?>

<?php 
 
  $sql = mysqli_query($mysqli,"SELECT * FROM quotes, clients
    WHERE quotes.client_id = clients.client_id
    ORDER BY quotes.quote_number DESC");
?>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-file mr-2"></i>Quotes</h6>
    <button type="button" class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addQuoteModal"><i class="fas fa-fw fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead class="thead-dark">
          <tr>
            <th>Number</th>
            <th>Client</th>
            <th class="text-right">Amount</th>
            <th>Date</th>
            <th>Status</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $quote_id = $row['quote_id'];
            $quote_number = $row['quote_number'];
            $quote_status = $row['quote_status'];
            $quote_date = $row['quote_date'];
            $quote_amount = $row['quote_amount'];
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];

            if($quote_status == "Sent"){
              $quote_badge_color = "warning";
            }elseif($quote_status == "Viewed"){
              $quote_badge_color = "primary";
            }elseif($quote_status == "Approved"){
              $quote_badge_color = "success";
            }else{
              $quote_badge_color = "secondary";
            }

          ?>

          <tr>
            <td><a href="quote.php?quote_id=<?php echo $quote_id; ?>">QUO-<?php echo $quote_number; ?></a></td>
            <td><a href="client.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
            <td class="text-right text-monospace">$<?php echo number_format($quote_amount,2); ?></td>
            <td><?php echo $quote_date; ?></td>
            <td>
              <span class="p-2 badge badge-<?php echo $quote_badge_color; ?>">
                <?php echo $quote_status; ?>
              </span>
            </td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editquoteModal<?php echo $quote_id; ?>">Edit</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addquoteCopyModal<?php echo $quote_id; ?>">Copy</a>
                  <a class="dropdown-item" href="post.php?pdf_quote=<?php echo $quote_id; ?>">PDF</a>
                  <a class="dropdown-item" href="post.php?delete_quote=<?php echo $quote_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php

          //include("edit_quote_modal.php");
          include("add_quote_copy_modal.php");
          }

          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_quote_modal.php"); ?>

<?php include("footer.php");