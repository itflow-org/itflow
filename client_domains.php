<?php include("inc_all_client.php"); ?>

<?php 

if(!empty($_GET['sb'])){
  $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
}else{
  $sb = "domain_name";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM domains LEFT JOIN vendors ON domain_registrar = vendor_id
  WHERE domain_client_id = $client_id AND (domain_name LIKE '%$q%' OR vendor_name LIKE '%$q%') 
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
      <div class="row">
        
        <div class="col-md-4">
          <div class="input-group mb-3 mb-md-0">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Domains">
            <div class="input-group-append">
              <button class="btn btn-dark"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>

        <div class="col-md-8">
          <div class="float-right">
            <a href="post.php?export_client_domains_csv=<?php echo $client_id; ?>" class="btn btn-default"><i class="fa fa-fw fa-download"></i> Export</a>
          </div>
        </div>

      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=domain_name&o=<?php echo $disp; ?>">Domain</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=vendor_name&o=<?php echo $disp; ?>">Registrar</a></th>
            <th>Web Host</th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=domain_expire&o=<?php echo $disp; ?>">Expires</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $domain_id = $row['domain_id'];
            $domain_name = htmlentities($row['domain_name']);
            $domain_registrar = htmlentities($row['domain_registrar']);
            $domain_webhost = htmlentities($row['domain_webhost']);
            $domain_expire = htmlentities($row['domain_expire']);
            $domain_registrar_name = htmlentities($row['vendor_name']);
            if(empty($domain_registrar_name)){
              $domain_registrar_name = "-";
            }

            $sql_domain_webhost = mysqli_query($mysqli,"SELECT vendor_name FROM vendors WHERE vendor_id = $domain_webhost");
            $row = mysqli_fetch_array($sql_domain_webhost);
            $domain_webhost_name = htmlentities($row['vendor_name']);
            if(empty($domain_webhost_name)){
              $domain_webhost_name = "-";
            }

          ?>
          <tr>
            <td><a class="text-dark" href="#" data-toggle="modal" onclick="populateDomainEditModal(<?php echo $client_id, ",", $domain_id ?>)" data-target="#editDomainModal"><?php echo $domain_name; ?></a></td>
            <td><?php echo $domain_registrar_name; ?></td>
            <td><?php echo $domain_webhost_name; ?></td>
            <td><?php echo $domain_expire; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" onclick="populateDomainEditModal(<?php echo $client_id, ",", $domain_id ?>)" data-target="#editDomainModal">Edit</a>
                  <?php if($session_user_role == 3) { ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="post.php?delete_domain=<?php echo $domain_id; ?>">Delete</a>
                  <?php } ?>
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
include("client_domain_edit_modal.php");
include("client_domain_add_modal.php");
?>

<script>
    function populateDomainEditModal(client_id, domain_id) {

        // Send a GET request to post.php as post.php?domain_get_json_details=true&client_id=NUM&domain_id=NUM
        jQuery.get(
            "ajax.php",
            {domain_get_json_details: 'true', client_id: client_id, domain_id: domain_id},
            function(data){

                // If we get a response from post.php, parse it as JSON
                const response = JSON.parse(data);

                // Access the domain info (one), registrars (multiple) and webhosts (multiple)
                const domain = response.domain[0];
                const vendors = response.vendors;

                // Populate the domain modal fields
                document.getElementById("editHeader").innerText = " " + domain.domain_name;
                document.getElementById("editDomainId").value = domain_id;
                document.getElementById("editDomainName").value = domain.domain_name;
                document.getElementById("editExpire").value = domain.domain_expire;
                document.getElementById("editDomainIP").value = domain.domain_ip;
                document.getElementById("editNameServers").value = domain.domain_name_servers;
                document.getElementById("editMailServers").value = domain.domain_mail_servers;
                document.getElementById("editTxtRecords").value = domain.domain_txt;
                document.getElementById("editRawWhois").value = domain.domain_raw_whois;

                /* DROPDOWNS */

                // Registrar dropdown
                var registrarDropdown = document.getElementById("editRegistrarId");

                // Clear registrar dropdown
                var i, L = registrarDropdown.options.length -1;
                for(i = L; i >= 0; i--) {
                    registrarDropdown.remove(i);
                }
                registrarDropdown[registrarDropdown.length] = new Option('- Vendor -', '0');

                // Populate dropdown
                vendors.forEach(vendor => {
                    if(parseInt(vendor.vendor_id) == parseInt(domain.domain_registrar)){
                        // Selected domain
                        registrarDropdown[registrarDropdown.length] = new Option(vendor.vendor_name, vendor.vendor_id, true, true);
                    }
                    else{
                        registrarDropdown[registrarDropdown.length] = new Option(vendor.vendor_name, vendor.vendor_id);
                    }
                });

                // Webhost dropdown
                var webhostDropdown = document.getElementById("editWebhostId");

                // Clear registrar dropdown
                var i, L = webhostDropdown.options.length -1;
                for(i = L; i >= 0; i--) {
                    webhostDropdown.remove(i);
                }
                webhostDropdown[webhostDropdown.length] = new Option('- Vendor -', '0');

                // Populate dropdown
                vendors.forEach(vendor => {
                    if(parseInt(vendor.vendor_id) == parseInt(domain.domain_webhost)){
                        // Selected domain
                        webhostDropdown[webhostDropdown.length] = new Option(vendor.vendor_name, vendor.vendor_id, true, true);
                    }
                    else{
                        webhostDropdown[webhostDropdown.length] = new Option(vendor.vendor_name, vendor.vendor_id);
                    }
                });


            }
        );
    }
</script>

<?php include("footer.php"); ?>