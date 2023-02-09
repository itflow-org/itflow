<?php require_once("inc_all_client.php"); ?>

<?php

if (!empty($_GET['sb'])) {
  $sb = strip_tags(mysqli_real_escape_string($mysqli, $_GET['sb']));
} else {
  $sb = "recurring_id";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli, "SELECT * FROM recurring
  LEFT JOIN categories ON recurring_category_id = category_id
  WHERE recurring_client_id = $client_id
  AND (CONCAT(recurring_prefix,recurring_number) LIKE '%$q%' OR recurring_frequency LIKE '%$q%' OR recurring_scope LIKE '%$q%' OR category_name LIKE '%$q%') 
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-sync-alt"></i> Recurring Invoices</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRecurringModal"><i class="fas fa-fw fa-plus"></i> New Recurring</button>
    </div>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <div class="row">

        <div class="col-md-4">
          <div class="input-group mb-3 mb-md-0">
            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo strip_tags(htmlentities($q)); } ?>" placeholder="Search Recurring Invoices">
            <div class="input-group-append">
              <button class="btn btn-dark"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>

        <div class="col-md-8">
          <div class="float-right">
            <a href="post.php?export_client_recurring_csv=<?php echo $client_id; ?>" class="btn btn-default"><i class="fa fa-fw fa-download"></i> Export</a>
          </div>
        </div>

      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
          <tr>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_number&o=<?php echo $disp; ?>">Number</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_scope&o=<?php echo $disp; ?>">Scope</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_frequency&o=<?php echo $disp; ?>">Frequency</a></th>
            <th class="text-right"><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_amount&o=<?php echo $disp; ?>">Amount</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_last_sent&o=<?php echo $disp; ?>">Last Sent</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_next_date&o=<?php echo $disp; ?>">Next Date</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=category_name&o=<?php echo $disp; ?>">Category</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_status&o=<?php echo $disp; ?>">Status</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php

           while ($row = mysqli_fetch_array($sql)) {
                $recurring_id = $row['recurring_id'];
                $recurring_prefix = htmlentities($row['recurring_prefix']);
                $recurring_number = htmlentities($row['recurring_number']);
                $recurring_scope = htmlentities($row['recurring_scope']);
                $recurring_frequency = htmlentities($row['recurring_frequency']);
                $recurring_status = htmlentities($row['recurring_status']);
                $recurring_last_sent = $row['recurring_last_sent'];
                if ($recurring_last_sent == 0) {
                  $recurring_last_sent = "-";
                }
                $recurring_next_date = $row['recurring_next_date'];
                $recurring_amount = floatval($row['recurring_amount']);
                $recurring_currency_code = htmlentities($row['recurring_currency_code']);
                $recurring_created_at = $row['recurring_created_at'];
                $category_id = $row['category_id'];
                $category_name = htmlentities($row['category_name']);
                if ($recurring_status == 1) {
                  $status = "Active";
                  $status_badge_color = "success";
                }else{
                  $status = "Inactive";
                  $status_badge_color = "secondary";
                }

              ?>

              <tr>
                <td><a href="recurring_invoice.php?recurring_id=<?php echo $recurring_id; ?>"><?php echo "$recurring_prefix$recurring_number"; ?></a></td>
                <td><?php echo $recurring_scope; ?></td>
                <td><?php echo ucwords($recurring_frequency); ?>ly</td>
                <td class="text-right"><?php echo numfmt_format_currency($currency_format, $recurring_amount, $recurring_currency_code); ?></td>
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
                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                      <i class="fas fa-ellipsis-h"></i>
                    </button>
                    <div class="dropdown-menu">
                      <a class="dropdown-item" href="recurring_invoice.php?recurring_id=<?php echo $recurring_id; ?>">Edit</a>
                      <div class="dropdown-divider"></div>
                      <a class="dropdown-item text-danger" href="post.php?delete_recurring=<?php echo $recurring_id; ?>">Delete</a>
                    </div>
                  </div>
                </td>
              </tr>

          <?php
          include("recurring_invoice_edit_modal.php");
          }

          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("recurring_invoice_add_modal.php"); ?>

<?php include("footer.php"); ?>
