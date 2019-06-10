<?php 
 
  $sql = mysqli_query($mysqli,"SELECT * FROM quotes, categories WHERE client_id = $client_id AND categories.category_id = quotes.category_id ORDER BY quote_number DESC");

?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-file"></i> Quotes</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addQuoteModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Number</th>
            <th class="text-right">Amount</th>
            <th>Date</th>
            <th>Category</th>
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
            $category_id = $row['category_id'];
            $category_name = $row['category_name'];

            if($quote_status == "Sent"){
              $quote_badge_color = "warning";
            }elseif($quote_status == "Viewed"){
              $quote_badge_color = "primary";
            }elseif($quote_status == "Approved"){
              $quote_badge_color = "success";
            }elseif($quote_status == "Rejected"){
              $quote_badge_color = "danger";
            }else{
              $quote_badge_color = "secondary";
            }

          ?>

          <tr>
            <td><a href="quote.php?quote_id=<?php echo $quote_id; ?>">QUO-<?php echo $quote_number; ?></a></td>
            <td class="text-right text-monospace">$<?php echo number_format($quote_amount,2); ?></td>
            <td><?php echo $quote_date; ?></td>
            <td><?php echo $category_name; ?></td>
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
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editQuoteModal<?php echo $quote_id; ?>">Edit</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addQuoteCopyModal<?php echo $quote_id; ?>">Copy</a>
                  <a class="dropdown-item" href="post.php?email_quote=<?php echo $quote_id; ?>">Send</a>
                  <a class="dropdown-item" href="post.php?approve_quote=<?php echo $quote_id; ?>">Approve</a>
                  <a class="dropdown-item" href="post.php?pdf_quote=<?php echo $quote_id; ?>">PDF</a>
                  <a class="dropdown-item" href="post.php?delete_quote=<?php echo $quote_id; ?>">Delete</a>
                </div>
              </div>
              <?php
          
              include("edit_quote_modal.php");
              include("add_quote_copy_modal.php");

              ?>      
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

<?php include("add_quote_modal.php"); ?>

<?php include("add_client_quote_modal.php"); ?>