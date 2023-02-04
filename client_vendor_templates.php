<?php require_once("inc_all_client.php"); ?>

<?php

if (isset($_GET['q'])) {
  $q = strip_tags(mysqli_real_escape_string($mysqli,$_GET['q']));
  //Phone Numbers
  $phone_query = preg_replace("/[^0-9]/", '',$q);
  if (empty($phone_query)) {
    $phone_query = $q;
  }
}else{
  $q = "";
  $phone_query = "";
}

if (!empty($_GET['sb'])) {
  $sb = strip_tags(mysqli_real_escape_string($mysqli,$_GET['sb']));
}else{
  $sb = "vendor_name";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM vendors 
  WHERE vendor_template = 1 
  AND (vendor_name LIKE '%$q%' OR vendor_description LIKE '%$q%' OR vendor_account_number LIKE '%$q%' OR vendor_website LIKE '%$q%' OR vendor_contact_name LIKE '%$q%' OR vendor_email LIKE '%$q%' OR vendor_phone LIKE '%$phone_query%') ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2">
      <i class="fa fa-fw fa-building"></i> Vendor Templates
    </h3>
    <button type="button" class="btn btn-dark dropdown-toggle ml-1" data-toggle="dropdown"></button>
    <div class="dropdown-menu">
      <a class="dropdown-item text-dark" href="client_vendors.php?client_id=<?php echo $client_id; ?>">Vendors</a>
    </div>
    <div class="card-tools">
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addVendorTemplateModal">
          <i class="fas fa-fw fa-plus"></i> New Template
        </button>
    </div>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <div class="row">
        
        <div class="col-md-4">
          <div class="input-group mb-3 mb-md-0">
            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo strip_tags(htmlentities($q)); } ?>" placeholder="Search Vendors Templates">
            <div class="input-group-append">
              <button class="btn btn-dark"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>

      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
          <tr>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=vendor_name&o=<?php echo $disp; ?>">Vendor</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=vendor_description&o=<?php echo $disp; ?>">Description</a></th>
            <th>Contact</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while ($row = mysqli_fetch_array($sql)) {
            $vendor_id = $row['vendor_id'];
            $vendor_name = htmlentities($row['vendor_name']);
            $vendor_description = htmlentities($row['vendor_description']);
            if (empty($vendor_description)) {
              $vendor_description_display = "-";
            }else{
              $vendor_description_display = $vendor_description;
            }
            $vendor_account_number = htmlentities($row['vendor_account_number']);
            $vendor_contact_name = htmlentities($row['vendor_contact_name']);
            if (empty($vendor_contact_name)) {
              $vendor_contact_name_display = "-";
            }else{
              $vendor_contact_name_display = $vendor_contact_name;
            }
            $vendor_phone = formatPhoneNumber($row['vendor_phone']);
            $vendor_extension = htmlentities($row['vendor_extension']);
            $vendor_email = htmlentities($row['vendor_email']);
            $vendor_website = htmlentities($row['vendor_website']);
            $vendor_hours = htmlentities($row['vendor_hours']);
            $vendor_sla = htmlentities($row['vendor_sla']);
            $vendor_code = htmlentities($row['vendor_code']);
            $vendor_notes = htmlentities($row['vendor_notes']);
            $vendor_template = intval($row['vendor_template']);
              
          ?>
          <tr>
            <th>
              <i class="fa fa-fw fa-building text-secondary"></i> 
              <a class="text-dark" href="#" data-toggle="modal" data-target="#editVendorTemplateModal<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></a>
              <?php
              if (!empty($vendor_account_number)) {
              ?>
              <br>
              <small class="text-secondary"><?php echo $vendor_account_number; ?></small>
              <?php
              }
              ?>
            </th>
            <td><?php echo $vendor_description_display; ?></td>
            <td>
              <?php
              if (!empty($vendor_contact_name)) {
              ?>
              <i class="fa fa-fw fa-user text-secondary mr-2 mb-2"></i><?php echo $vendor_contact_name_display; ?>
              <br>
              <?php
              }else{
                echo $vendor_contact_name_display;
              }
              ?>
              <?php
              if (!empty($vendor_phone)) {
              ?>
              <i class="fa fa-fw fa-phone text-secondary mr-2 mb-2"></i><?php echo $vendor_phone; ?>
              <br>
              <?php
              }
              ?>
              <?php
              if (!empty($vendor_email)) {
              ?>
              <i class="fa fa-fw fa-envelope text-secondary mr-2 mb-2"></i><?php echo $vendor_email; ?>
              <br>
              <?php
              }
              ?>
            </td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editVendorTemplateModal<?php echo $vendor_id; ?>">Edit</a>
                  <?php if ($session_user_role == 3) { ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="post.php?delete_vendor=<?php echo $vendor_id; ?>">Delete</a>
                  <?php } ?>
                </div>
              </div>
            </td>
          </tr>

          <?php
          
          include("vendor_template_edit_modal.php");
          }
          
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("vendor_template_add_modal.php"); ?>

<?php include("footer.php"); ?>
