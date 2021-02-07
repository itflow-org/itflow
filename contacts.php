<?php include("header.php");

//Rebuild URL

$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

//Paging
if(isset($_GET['p'])){
  $p = intval($_GET['p']);
  $record_from = (($p)-1)*$config_records_per_page;
  $record_to = $config_records_per_page;
}else{
  $record_from = 0;
  $record_to = $config_records_per_page;
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

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM contacts, clients WHERE contacts.client_id = clients.client_id AND contacts.company_id = $session_company_id AND (contact_name LIKE '%$q%') ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
$total_found_rows = $num_rows[0];
$total_pages = ceil($total_found_rows / 10);

?>

<div class="card">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-users mr-2"></i>Contacts</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addContactModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <input type="hidden" name="tab" value="<?php echo $_GET['tab']; ?>">
      <div class="input-group">
        <input type="search" class="form-control " name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Contacts">
        <div class="input-group-append">
          <button class="btn btn-secondary"><i class="fa fa-search"></i></button>
        </div>
      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th class="text-center"><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=contact_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Client</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=contact_title&o=<?php echo $disp; ?>">Title</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=contact_email&o=<?php echo $disp; ?>">Email</a></th>
            <th>Phone</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php

          while($row = mysqli_fetch_array($sql)){
            $contact_id = $row['contact_id'];
            $contact_name = $row['contact_name'];
            $contact_title = $row['contact_title'];
            $contact_phone = $row['contact_phone'];
            if(strlen($contact_phone)>2){ 
              $contact_phone = substr($row['contact_phone'],0,3)."-".substr($row['contact_phone'],3,3)."-".substr($row['contact_phone'],6,4);
            }
            $contact_extension = $row['contact_extension '];
            $contact_mobile = $row['contact_mobile'];
            if(strlen($contact_mobile)>2){ 
              $contact_mobile = substr($row['contact_mobile'],0,3)."-".substr($row['contact_mobile'],3,3)."-".substr($row['contact_mobile'],6,4);
            }
            $contact_email = $row['contact_email'];
            $contact_photo = $row['contact_photo'];
            $contact_initials = initials($contact_name);
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];
            $contact_notes = $row['contact_notes'];

      
          ?>
          <tr>
            <td class="text-center">
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
                <div class="text-secondary"><?php echo $contact_name; ?></div>
              </a>
            </td>
            <td><?php echo $client_name; ?></td>
            <td><?php echo $contact_title; ?></td>
            <td><a href="mailto:<?php echo $contact_email; ?>"><?php echo $contact_email; ?></a></td>
            <td>
              <?php
              if(!empty($contact_phone)){
              ?>
              <i class="fa fa-fw fa-phone text-secondary mr-2 mb-2"></i><?php echo $contact_phone; ?> <?php echo $contact_extension; ?>
              <br>
              <?php
              }
              ?>
              <?php
              if(!empty($contact_mobile)){
              ?>
              <i class="fa fa-fw fa-mobile-alt text-secondary mr-2 mb-2"></i><?php echo $contact_mobile; ?>
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
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editContactModal<?php echo $contact_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="post.php?archive_contact=<?php echo $contact_id; ?>">Archive</a>
                  <a class="dropdown-item" href="post.php?delete_contact=<?php echo $contact_id; ?>">Delete</a>
                </div>
              </div>
              <?php include("edit_contact_modal.php"); ?>      
            </td>
          </tr>

          <?php
          
          }
          
          ?>

        </tbody>
      </table>

      <?php include("pagination.php"); ?>

    </div>
  </div>
</div>

<?php 

include("add_contact_modal.php"); 
include("footer.php");

?>