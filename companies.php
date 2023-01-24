<?php require_once("inc_all_settings.php");

if (!empty($_GET['sb'])) {
  $sb = strip_tags(mysqli_real_escape_string($mysqli,$_GET['sb']));
}else{
  $sb = "company_name";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM companies, settings
  WHERE companies.company_id = settings.company_id
  AND (company_name LIKE '%$q%')
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-building"></i> Companies</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCompanyModal"><i class="fas fa-fw fa-plus"></i> New Company</button>
    </div>
  </div>
  <div class="card-body">
    <div class="alert alert-danger" role="alert">
      <b>The multi-company feature should be considered deprecated.</b> It may be removed in the near future - please see the <a href="https://forum.itflow.org/d/74-removing-the-multi-company-feature">forum post</a>.
    </div>
    <hr>
    <form autocomplete="off">
      <div class="input-group">
        <input type="search" class="form-control col-md-4" name="q" value="<?php if (isset($q)) { echo strip_tags(htmlentities($q)); } ?>" placeholder="Search Companies">
        <div class="input-group-append">
          <button class="btn btn-primary"><i class="fa fa-search"></i></button>
        </div>
      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
          <tr>
            <th class="text-center"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=company_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=company_address&o=<?php echo $disp; ?>">Address</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=company_phone&o=<?php echo $disp; ?>">Phone</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=company_email&o=<?php echo $disp; ?>">Email</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=company_website&o=<?php echo $disp; ?>">Website</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while ($row = mysqli_fetch_array($sql)) {
            $company_id = $row['company_id'];
            $company_name = htmlentities($row['company_name']);
            $company_country = htmlentities($row['company_country']);
            $company_address = htmlentities($row['company_address']);
            $company_city = htmlentities($row['company_city']);
            $company_state = htmlentities($row['company_state']);
            $company_zip = htmlentities($row['company_zip']);
            $company_phone = formatPhoneNumber($row['company_phone']);
            $company_email = htmlentities($row['company_email']);
            $company_website = htmlentities($row['company_website']);
            $company_logo = htmlentities($row['company_logo']);
            $company_locale = htmlentities($row['company_locale']);
            $company_currency = htmlentities($row['company_currency']);
            
            $company_initials = htmlentities(initials($company_name));

            
      
          ?>
          
          <tr>
            <td class="text-center">
              <a class="text-dark" href="#" data-toggle="modal" data-target="#editCompanyModal<?php echo $company_id; ?>">
                <?php if (!empty($company_logo)) { ?>
                <img height="48" width="48" class="img-fluid rounded-circle" src="<?php echo "uploads/settings/$company_id/$company_logo"; ?>">
                <?php }else{ ?>
                <span class="fa-stack fa-2x">
                  <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                  <span class="fa fa-stack-1x text-white"><?php echo $company_initials; ?></span>
                </span>
                <br>
                <?php } ?>

                <div class="text-secondary"><?php echo $company_name; ?></div>
              </a>
            </td>
            <td><?php echo $company_address; ?></td>
            <td><?php echo $company_phone; ?></td>
            <td><?php echo $company_email; ?></td>
            <td><?php echo $company_website; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editCompanyModal<?php echo $company_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="post.php?archive_company=<?php echo $company_id; ?>">Archive</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger text-bold" href="post.php?delete_company=<?php echo $company_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">Delete</a>
                </div>
              </div>
            </td>
          </tr>

          <?php
          
          include("company_edit_modal.php");
          }
          
          ?>

        </tbody>
      </table>      
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("company_add_modal.php"); ?>

<?php include("footer.php");
