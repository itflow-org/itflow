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
  $sb = "domain_name";
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

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM domains 
  WHERE client_id = $client_id AND (domain_name LIKE '%$q%') 
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-globe"></i> Domains</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDomainModal"><i class="fas fa-fw fa-plus"></i> New Domain</button>
    </div>
  </div>

  <div class="card-body">
    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <input type="hidden" name="tab" value="<?php echo $_GET['tab']; ?>">
      <div class="input-group">
        <input type="search" class="form-control " name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search <?php echo ucwords($_GET['tab']); ?>">
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
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=domain_name&o=<?php echo $disp; ?>">Domain</a></th>
            <th>Registrar</th>
            <th>WebHost</th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=domain_expire&o=<?php echo $disp; ?>">Expire</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $domain_id = $row['domain_id'];
            $domain_name = $row['domain_name'];
            $domain_registrar = $row['domain_registrar'];
            $domain_webhost = $row['domain_webhost'];
            $domain_expire = $row['domain_expire'];

            $sql_domain_registrar = mysqli_query($mysqli,"SELECT vendor_name FROM vendors WHERE vendor_id = $domain_registrar");
            $row = mysqli_fetch_array($sql_domain_registrar);
            $domain_registrar_name = $row['vendor_name'];
            if(empty($domain_registrar_name)){
              $domain_registrar_name = "-";
            }

            $sql_domain_webhost = mysqli_query($mysqli,"SELECT vendor_name FROM vendors WHERE vendor_id = $domain_webhost");
            $row = mysqli_fetch_array($sql_domain_webhost);
            $domain_webhost_name = $row['vendor_name'];
            if(empty($domain_webhost_name)){
              $domain_webhost_name = "-";
            }

          ?>
          <tr>
            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editDomainModal<?php echo $domain_id; ?>"><?php echo $domain_name; ?></a></td>
            <td><?php echo $domain_registrar_name; ?></td>
            <td><?php echo $domain_webhost_name; ?></td>
            <td><?php echo $domain_expire; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editDomainModal<?php echo $domain_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="post.php?delete_domain=<?php echo $domain_id; ?>">Delete</a>
                </div>
              </div>
            </td>
          </tr>

          <?php

          include("edit_domain_modal.php");
          }
          
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("add_domain_modal.php"); ?>