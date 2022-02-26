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

          ?>
          <tr>
            <td><a class="text-dark" href="#" data-toggle="modal" onclick="populateCertificateEditModal(<?php echo $client_id, ",", $certificate_id ?>)" data-target="#editCertificateModal"><?php echo $certificate_name; ?></a></td>
            <td><?php echo $certificate_domain; ?></td>
            <td><?php echo $certificate_issued_by; ?></td>
            <td><?php echo $certificate_expire; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" onclick="populateCertificateEditModal(<?php echo $client_id, ",", $certificate_id ?>)" data-target="#editCertificateModal">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="post.php?delete_certificate=<?php echo $certificate_id; ?>">Delete</a>
                </div>
              </div>
            </td>
          </tr>

          <?php
          }
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php
include("client_certificate_edit_modal.php");
include("client_certificate_add_modal.php");
?>

<script>
    function populateCertificateEditModal(client_id, certificate_id) {

        // Send a GET request to post.php as post.php?certificate_get_json_details=true&client_id=NUM&certificate_id=NUM
        jQuery.get(
            "ajax.php",
            {certificate_get_json_details: 'true', client_id: client_id, certificate_id: certificate_id},
            function(data){

                // If we get a response from post.php, parse it as JSON
                const response = JSON.parse(data);

                // Access the certificate (one) and domains (multiple)
                const certificate = response.certificate[0];
                const domains = response.domains;

                // Populate the cert modal fields
                document.getElementById("editHeader").innerText = " " + certificate.certificate_name;
                document.getElementById("editCertificateId").value = certificate_id;
                document.getElementById("editCertificateName").value = certificate.certificate_name;
                document.getElementById("editDomain").value = certificate.certificate_domain;
                document.getElementById("editIssuedBy").value = certificate.certificate_issued_by;
                document.getElementById("editExpire").value = certificate.certificate_expire;
                document.getElementById("editPublicKey").value = certificate.certificate_public_key;

                // Select the domain dropdown
                var domainDropdown = document.getElementById("editDomainId");

                // Clear domain dropdown
                var i, L = domainDropdown.options.length -1;
                for(i = L; i >= 0; i--) {
                    domainDropdown.remove(i);
                }
                domainDropdown[domainDropdown.length] = new Option('- Domain -', '0');

                // Populate domain dropdown
                domains.forEach(domain => {
                    if(parseInt(domain.domain_id) == parseInt(certificate.certificate_domain_id)){
                        // Selected domain
                        domainDropdown[domainDropdown.length] = new Option(domain.domain_name, domain.domain_id, true, true);
                    }
                    else{
                        domainDropdown[domainDropdown.length] = new Option(domain.domain_name, domain.domain_id);
                    }
                });
            }
        );
    }
</script>

<script type="text/javascript">
    function fetchSSL(type)
    {
        // Get the domain name input & issued/expire/key fields, based on whether this is a new cert or updating an existing
        if(type == 'new'){
            var domain = document.getElementById("domain").value;
            var issuedBy = document.getElementById("issuedBy");
            var expire = document.getElementById("expire");
            var publicKey = document.getElementById("publicKey");

        }
        if(type == 'edit'){
            var domain = document.getElementById("editDomain").value;
            var issuedBy = document.getElementById("editIssuedBy");
            var expire = document.getElementById("editExpire");
            var publicKey = document.getElementById("editPublicKey");
        }

        //Send a GET request to post.php as post.php?certificate_fetch_parse_json_details=TRUE&domain=DOMAIN
        jQuery.get(
            "ajax.php",
            {certificate_fetch_parse_json_details: 'TRUE', domain: domain},
            function(data){
                //If we get a response from post.php, parse it as JSON
                const ssl_data = JSON.parse(data);

                if(ssl_data.success == "TRUE"){
                    // Fill the form fields with the cert data
                    issuedBy.value = ssl_data.issued_by;
                    expire.value = ssl_data.expire;
                    publicKey.value = ssl_data.public_key;
                }
                else{
                    alert("Error whilst parsing/retrieving details for domain")
                }
            }
        );
    }
</script>
