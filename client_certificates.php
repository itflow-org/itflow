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
  $sb = "certificate_name";
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

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM certificates 
  WHERE certificate_client_id = $client_id AND (certificate_name LIKE '%$q%' OR certificate_domain LIKE '%$q%' OR certificate_issued_by LIKE '%$q%') 
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-lock"></i> Certificates</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCertificateModal"><i class="fas fa-fw fa-plus"></i> New Certificate</button>
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
          </div>
        </div>

      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=certificate_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=certificate_domain&o=<?php echo $disp; ?>">Domain</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=certificate_issued_by&o=<?php echo $disp; ?>">Issued By</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=certificate_expire&o=<?php echo $disp; ?>">Expire</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $certificate_id = $row['certificate_id'];
            $certificate_name = $row['certificate_name'];
            $certificate_domain = $row['certificate_domain'];
            $certificate_issued_by = $row['certificate_issued_by'];
            $certificate_expire = $row['certificate_expire'];
            $certificate_updated_at = $row['certificate_updated_at'];
            $certificate_public_key = $row['certificate_public_key'];
            
$url = "https://$certificate_domain";
$orignal_parse = parse_url($url, PHP_URL_HOST);
$get = stream_context_create(array("ssl" => array("capture_peer_cert" => TRUE)));
$read = stream_socket_client("ssl://".$orignal_parse.":443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $get);
$cert = stream_context_get_params($read);
$certinfo = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
// echo "Name: ".$certinfo['name']."<br/>";
// echo "Subject: ".$certinfo['subject']['C']." ".$certinfo['subject']['ST']." ".$certinfo['subject']['CN']."<br/>";
// echo "Issuer: ".$certinfo['issuer']['C']." ".$certinfo['subject']['O']." ".$certinfo['subject']['CN']."<br/>";
// echo "Version: ".$certinfo['version']."<br/>";
// echo "Valid from: ".gmdate("Y-m-d",$certinfo['validFrom'])." to ".gmdate("Y-m-d",$certinfo['validTo'])."<br/>"; 
         // $validFrom = date('Y-m-d H:i:s', $data['validFrom_time_t']);
         // $validTo = date('Y-m-d H:i:s', $data['validTo_time_t']);

          ?>
       
          <tr>
            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editCertificateModal<?php echo $certificate_id; ?>"><?php echo $certinfo['name']; ?></a></td>
            <td><?php echo $certificate_domain; ?></td>
            <td><?php echo $certinfo['issuer']['C']; ?></td>
            <td><?php echo date('d-m-Y H:i:s', $certinfo['validTo_time_t']) ; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editCertificateModal<?php echo $certificate_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="post.php?delete_certificate=<?php echo $certificate_id; ?>">Delete</a>
                </div>
              </div>
            </td>
          </tr>

          <?php

          include("edit_certificate_modal.php");
          }
          
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("add_certificate_modal.php"); ?>
