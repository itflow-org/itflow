<?php

// Search query
if(isset($_GET['q'])){
    $q = mysqli_real_escape_string($mysqli,$_GET['q']);
}else{
    $q = "";
}

// Current tab
$tab = htmlentities($_GET['tab']);

// Overview SQL query
$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS * FROM services WHERE service_client_id = '$client_id' AND (service_name LIKE '%$q%' OR service_description LIKE '%$q%')");
$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>
<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-stream"></i> Services</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addServiceModal"><i class="fas fa-fw fa-plus"></i> New Service</button>
        </div>
    </div>

    <div class="card-body">

        <form autocomplete="off">
            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <input type="hidden" name="tab" value="<?php echo $tab; ?>">
            <div class="input-group">
                <input type="search" class="form-control " name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search <?php echo ucwords($tab); ?>">
                <div class="input-group-append">
                    <button class="btn btn-secondary"><i class="fa fa-search"></i></button>
                </div>
            </div>
        </form>
        <hr>

        <div class="table-responsive">
            <table class="table table-striped table-borderless table-hover">
                <thead class="<?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
                <tr>
                    <th><a class="text-dark">Name</a></th>
                    <th><a class="text-dark">Category</a></th>
                    <th><a class="text-dark">Updated</a></th>
                    <th><a class="text-dark">Importance</a></th>

                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while($row = mysqli_fetch_array($sql)){
                $service_id = $row['service_id'];
                $service_name = $row['service_name'];
                $service_description= $row['service_description'];
                $service_category = $row['service_category'];
                $service_importance = $row['service_importance'];
                $service_notes = $row['service_notes'];
                $service_updated_at = $row['service_updated_at'];
                $service_review_due = $row['service_review_due'];

                // Service Importance
                if($service_importance == "High"){
                    $service_importance_display = "<span class='p-2 badge badge-danger'>$service_importance</span>";
                }elseif($service_importance == "Medium"){
                    $service_importance_display = "<span class='p-2 badge badge-warning'>$service_importance</span>";
                }elseif($service_importance == "Low"){
                    $service_importance_display = "<span class='p-2 badge badge-info'>$service_importance</span>";
                }else{
                    $service_importance_display = "-";
                }


                ?>

                <tr>
                    <!-- Name/Category/Updated/Importance from DB -->
                    <td><a href="#" data-toggle="modal" data-target="#viewServiceModal<?php echo $service_id; ?>"> <?php echo $service_name ?></a></td>
                    <td><a> <?php echo $service_category ?></a></td>
                    <td><a> <?php echo $service_updated_at ?></a></td>
                    <td><a> <?php echo $service_importance ?></a></td>

                    <!-- Action -->
                    <td>
                        <div class="dropdown dropleft text-center">
                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editServiceModal<?php//echo $service_id; ?>">Edit</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="post.php?delete_service=<?php //echo $service_id; ?>">Delete</a>
                            </div>
                        </div>
                    </td>
                </tr>

                <?php

                    // Associated Assets (and their logins/networks/locations)
                    $sql_assets = mysqli_query($mysqli, "SELECT * FROM service_assets 
                                                                LEFT JOIN assets 
                                                                  ON service_assets.asset_id = assets.asset_id
                                                                LEFT JOIN logins
                                                                  ON service_assets.asset_id = logins.login_asset_id
                                                                LEFT JOIN networks
                                                                  ON assets.asset_network_id = networks.network_id
                                                                LEFT JOIN locations
                                                                  ON assets.asset_location_id = locations.location_id
                                                                WHERE service_id = '$service_id'");

                    // Associated logins
                    $sql_logins = mysqli_query($mysqli, "SELECT * FROM service_logins
                                                                LEFT JOIN logins
                                                                  ON service_logins.login_id = logins.login_id
                                                                WHERE service_id = '$service_id'");

                    // Associated Domains
                    $sql_domains = mysqli_query($mysqli, "SELECT * FROM service_domains
                                                              LEFT JOIN domains
                                                                ON service_domains.domain_id = domains.domain_id
                                                              WHERE service_id = '$service_id'");

                    // Associated URLs
                    $sql_urls = mysqli_query($mysqli, "SELECT * FROM service_urls
                                                                WHERE service_id = '$service_id'");

                    // Associated Vendors
                    $sql_vendors = mysqli_query($mysqli, "SELECT * FROM service_vendors
                                                              LEFT JOIN vendors
                                                                ON service_vendors.vendor_id = vendors.vendor_id
                                                              WHERE service_id = '$service_id'");

                    // Associated Contacts
                    $sql_contacts = mysqli_query($mysqli, "SELECT * FROM service_contacts
                                                              LEFT JOIN contacts
                                                                ON service_contacts.contact_id = contacts.contact_id
                                                              WHERE service_id = '$service_id'");

                    // Associated Documents
                    $sql_docs = mysqli_query($mysqli, "SELECT * FROM service_documents
                                                              LEFT JOIN documents
                                                                ON service_documents.document_id = documents.document_id
                                                              WHERE service_id = '$service_id'");



                    include("service_view_modal.php");
                }
                ?>

                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include("service_add_modal.php"); ?>