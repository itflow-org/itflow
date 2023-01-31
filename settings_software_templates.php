<?php include("inc_all_settings.php"); ?>

<?php 

if(!empty($_GET['sb'])){
  $sb = strip_tags(mysqli_real_escape_string($mysqli,$_GET['sb']));
}else{
  $sb = "software_name";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM software 
  LEFT JOIN logins ON login_software_id = software_id
  WHERE software_template = 1 
  AND (software_name LIKE '%$q%' OR software_type LIKE '%$q%' OR software_key LIKE '%$q%') 
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-cube"></i> Licenses Templates</h3>
    <button type="button" class="btn btn-dark dropdown-toggle ml-1" data-toggle="dropdown"></button>
    <div class="dropdown-menu">
      <a class="dropdown-item text-dark" href="client_software.php?client_id=<?php echo $client_id; ?>">Licenses</a>
    </div>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addSoftwareTemplateModal"><i class="fas fa-fw fa-plus"></i> New Template</button>
    </div>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <div class="row">
        
        <div class="col-md-4">
          <div class="input-group mb-3 mb-md-0">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){ echo strip_tags(htmlentities($q)); } ?>" placeholder="Search Licenses">
            <div class="input-group-append">
              <button class="btn btn-dark"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>

        <div class="col-md-8">
        </div>

      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=software_name&o=<?php echo $disp; ?>">Template</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=software_type&o=<?php echo $disp; ?>">Type</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=software_license_type&o=<?php echo $disp; ?>">License Type</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=software_seats&o=<?php echo $disp; ?>">Seats</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
    
          while($row = mysqli_fetch_array($sql)){    
            $software_id = $row['software_id'];
            $software_name = htmlentities($row['software_name']);
            $software_version = htmlentities($row['software_version']);
            $software_type = htmlentities($row['software_type']);
            $software_license_type = htmlentities($row['software_license_type']);
            $software_key = htmlentities($row['software_key']);
            $software_seats = htmlentities($row['software_seats']);
            $software_purchase = $row['software_purchase'];
            $software_expire = $row['software_expire'];
            $software_notes = htmlentities($row['software_notes']);

          ?>
          <tr>
            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editSoftwareTemplateModal<?php echo $software_id; ?>"><?php echo "$software_name<br><span class='text-secondary'>$software_version</span>"; ?></a></td>
            <td><?php echo $software_type; ?></td>
            <td><?php echo $software_license_type; ?></td>
            <td><?php echo "$software_seats"; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editSoftwareTemplateModal<?php echo $software_id; ?>">Edit</a>
                  <?php if($session_user_role == 3) { ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="post.php?delete_software=<?php echo $software_id; ?>">Delete</a>
                  <?php } ?>
                </div>
              </div> 
            </td>
          </tr>

          <?php

          include("client_software_template_edit_modal.php");
          }
          
          ?>

        </tbody>
      </table>      
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("client_software_template_add_modal.php"); ?>

<?php include("footer.php"); ?>