<?php $sql = mysqli_query($mysqli,"SELECT * FROM client_domains WHERE client_id = $client_id ORDER BY client_domain_id DESC"); ?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-globe"></i> Domains</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addClientDomainModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">

    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Domain</th>
            <th>Registrar</th>
            <th>Expire</th>
            <th>Server</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $client_domain_id = $row['client_domain_id'];
            $client_domain_name = $row['client_domain_name'];
            $client_domain_registrar = $row['client_domain_registrar'];
            $client_domain_expire = $row['client_domain_expire'];
            $client_domain_server = $row['client_domain_server'];

      
          ?>
          <tr>
            <td><?php echo $client_domain_name; ?></td>
            <td><?php echo $client_domain_registrar; ?></td>
            <td><?php echo $client_domain_expire; ?></td>
            <td><?php echo $client_domain_server; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientDomainModal<?php echo $client_domain_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_client_domain=<?php echo $client_domain_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php
          include("edit_client_domain_modal.php");
          }
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_client_domain_modal.php"); ?>