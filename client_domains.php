<?php $sql = mysqli_query($mysqli,"SELECT * FROM domains WHERE client_id = $client_id ORDER BY domain_id DESC"); ?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-globe"></i> Domains</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addDomainModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">

    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Domain</th>
            <th>Registrar</th>
            <th>WebHost</th>
            <th>Expire</th>
            <th class="text-center">Actions</th>
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

            $sql_domain_webhost = mysqli_query($mysqli,"SELECT vendor_name FROM vendors WHERE vendor_id = $domain_webhost");
            $row = mysqli_fetch_array($sql_domain_webhost);
            $domain_webhost_name = $row['vendor_name'];

          ?>
          <tr>
            <td><?php echo $domain_name; ?></td>
            <td><?php echo $domain_registrar_name; ?></td>
            <td><?php echo $domain_webhost_name; ?></td>
            <td><?php echo $domain_expire; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editDomainModal<?php echo $domain_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_domain=<?php echo $domain_id; ?>">Delete</a>
                </div>
              </div>
              <?php include("edit_domain_modal.php"); ?>     
            </td>
          </tr>

          <?php
          
          }
          
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_domain_modal.php"); ?>