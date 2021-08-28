<?php 

//Paging
if(isset($_GET['p'])){
  $p = intval($_GET['p']);
  $record_from = (($p)-1)*$_SESSION['records_per_page'];
  $record_to = $_SESSION['records_per_page'];
}else{
  $record_from = 0;
  $record_to = $_SESSION['records_per_page'];
  $p = 1;
}
  
if(isset($_GET['q'])){
  $q = mysqli_real_escape_string($mysqli,$_GET['q']);
}else{
  $q = "";
}

if(!empty($_GET['sb'])){
  $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
}else{
  $sb = "contact_name";
}

if(isset($_GET['o'])){
  if($_GET['o'] == 'ASC'){
    $o = "ASC";
    $disp = "DESC";
  }else{
    $o = "DESC";
    $disp = "ASC";
  }
}else{
  $o = "ASC";
  $disp = "DESC";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM contacts WHERE contact_archived_at IS NULL AND (contact_name LIKE '%$q%') AND contact_client_id = $client_id ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-users"></i> Contacts</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addContactModal"><i class="fas fa-fw fa-plus"></i> New Contact</button>
    </div>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <input type="hidden" name="tab" value="<?php echo $_GET['tab']; ?>">
      <div class="row">
        
        <div class="col-md-4">
          <div class="input-group mb-3 mb-md-0">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search <?php echo ucwords($_GET['tab']); ?>">
            <div class="input-group-append">
              <button class="btn btn-dark"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>

        <div class="col-md-8">
          <div class="float-right">
            <a href="post.php?export_client_<?php echo $_GET['tab']; ?>_csv=<?php echo $client_id; ?>" class="btn btn-default"><i class="fa fa-fw fa-download"></i> Export</a>
            <a href="#" class="btn btn-default"><i class="fa fa-fw fa-upload"></i> Import</a>
          </div>
        </div>

      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table border">
        <thead class="thead-light <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th class="text-center"><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=contact_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=contact_title&o=<?php echo $disp; ?>">Title</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=contact_email&o=<?php echo $disp; ?>">Email</a></th>
            <th>Phone</th>
            <th>Mobile</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php

          while($row = mysqli_fetch_array($sql)){
            $contact_id = $row['contact_id'];
            $contact_name = $row['contact_name'];
            $contact_title = $row['contact_title'];
            if(empty($contact_title)){
              $contact_title_display = "-";
            }else{
              $contact_title_display = $contact_title;
            }
            $contact_phone = $row['contact_phone'];
            if(strlen($contact_phone)>2){ 
              $contact_phone = substr($row['contact_phone'],0,3)."-".substr($row['contact_phone'],3,3)."-".substr($row['contact_phone'],6,4);
            }
            if(empty($contact_phone)){
              $contact_phone_display = "-";
            }else{
              $contact_phone_display = "$contact_phone";
            }
            $contact_extension = $row['contact_extension'];
            $contact_mobile = $row['contact_mobile'];
            if(strlen($contact_mobile)>2){ 
              $contact_mobile = substr($row['contact_mobile'],0,3)."-".substr($row['contact_mobile'],3,3)."-".substr($row['contact_mobile'],6,4);
            }
            if(empty($contact_mobile)){
              $contact_mobile_display = "-";
            }else{
              $contact_mobile_display = "$contact_mobile";
            }
            $contact_email = $row['contact_email'];
            if(empty($contact_email)){
              $contact_email_display = "-";
            }else{
              $contact_email_display = "<a href='mailto:$contact_email'>$contact_email</a><button class='btn btn-sm' data-clipboard-text='$contact_email'><i class='far fa-copy text-secondary'></i></button>";
            }
            $contact_photo = $row['contact_photo'];
            $contact_initials = initials($contact_name);
            $contact_notes = $row['contact_notes'];
            $contact_created_at = $row['contact_created_at'];
            if($contact_id == $primary_contact){
              $primary_contact_display = "<p class='text-success'>Primary Contact</p>";
            }else{
              $primary_contact_display = "";
            }           
      
          ?>
          <tr>
            <th class="text-center">
              <a class="text-dark" href="#" data-toggle="modal" data-target="#editContactModal<?php echo $contact_id; ?>">
                <?php if(!empty($contact_photo)){ ?>
              
                <img height="48" width="48" class="img-fluid rounded-circle" src="<?php echo $contact_photo; ?>">
                
                <?php }else{ ?>
    
                <span class="fa-stack fa-2x">
                  <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                  <span class="fa fa-stack-1x text-white"><?php echo $contact_initials; ?></span>
                </span>
                <br>
                
                <?php } ?>
                <div class="text-dark"><?php echo $contact_name; ?></div>
                <div><?php echo $primary_contact_display; ?></div>
              </a>
            </th>
            
            <td><?php echo $contact_title_display; ?></td>
            <td><?php echo $contact_email_display; ?></td>
            <td><?php echo $contact_phone_display; ?> <?php if(!empty($contact_extension)){ echo "x$contact_extension"; } ?></td>
            <td><?php echo $contact_mobile_display; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editContactModal<?php echo $contact_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="post.php?archive_contact=<?php echo $contact_id; ?>">Archive</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger text-bold" href="post.php?delete_contact=<?php echo $contact_id; ?>">Delete</a>
                </div>
              </div> 
            </td>
            
          </tr>

          <?php
          
          include("edit_contact_modal.php");

          }
          
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("add_contact_modal.php"); ?>