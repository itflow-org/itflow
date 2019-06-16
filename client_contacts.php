<?php 

//Rebuild URL

$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

if(isset($_GET['p'])){
  $p = intval($_GET['p']);
  $record_from = (($p)-1)*10;
  $record_to =  10;
}else{
  $record_from = 0;
  $record_to = 10;
  $p = 1;
}
  
if(isset($_GET['q'])){
  $q = $_GET['q'];
}else{
  $q = "";
}

if(!empty($_GET['sb'])){
  $sb = $_GET['sb'];
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


$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM contacts WHERE client_id = $client_id AND (contact_name LIKE '%$q%') ORDER BY $sb $o LIMIT $record_from, $record_to"); 

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
$total_found_rows = $num_rows[0];
$total_pages = ceil($total_found_rows / 10);

?>

<div class="card">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-users"></i> Contacts</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addContactModal"><i class="fa fa-plus"></i> New</button>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <input type="hidden" name="tab" value="<?php echo $_GET['tab']; ?>">
      <div class="input-group">
        <input type="search" class="form-control " name="q" value="<?php if(isset($q)){echo $q;} ?>" placeholder="Search <?php echo ucwords($_GET['tab']); ?>">
        <div class="input-group-append">
          <button class="btn btn-secondary"><i class="fa fa-search"></i></button>
        </div>
      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark">
          <tr>
            <th class="text-center"><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=contact_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=contact_title&o=<?php echo $disp; ?>">Title</a></th>
            <th>Email</th>
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
            $contact_email = $row['contact_email'];
            $contact_photo = $row['contact_photo'];
            $contact_initials = initials($contact_name);
      
          ?>
          <tr>
            <td class="text-center">
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
            </td>
            
            <td><?php echo $contact_title; ?></td>
            <td><a href="mailto:<?php echo $contact_email; ?>"><?php echo $contact_email; ?></a></td>
            <td><?php echo $contact_phone; ?></td>
            
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editContactModal<?php echo $contact_id; ?>">Edit</a>
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

<?php include("add_contact_modal.php"); ?>