<?php include("header.php"); ?>

<?php 

if(isset($_GET['query'])){

  $query = mysqli_real_escape_string($mysqli,$_GET['query']);

  $sql_clients = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_name LIKE '%$query%' AND company_id = $session_company_id ORDER BY client_id DESC LIMIT 5");
  $sql_vendors = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_name LIKE '%$query%' AND company_id = $session_company_id ORDER BY vendor_id DESC LIMIT 5");
  $sql_products = mysqli_query($mysqli,"SELECT * FROM products WHERE product_name LIKE '%$query%' AND company_id = $session_company_id ORDER BY product_id DESC LIMIT 5");
  $sql_logins = mysqli_query($mysqli,"SELECT * FROM logins WHERE login_description LIKE '%$query%' AND company_id = $session_company_id ORDER BY login_id DESC LIMIT 5");

?>

<h3><i class="fa fa-search"></i> Welcome to Global Search...</h3>
<hr>
<div class="row">
  
  <div class="col-6">
    <div class="card mb-3">
      <div class="card-header">
        <h6 class="mt-1"><i class="fa fa-users"></i> Clients</h6>
      </div>
      <div class="card-body">
        <table class="table table-striped table-borderless">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
            </tr>
          </thead>
          <tbody>
            <?php
        
            while($row = mysqli_fetch_array($sql_clients)){
              $client_id = $row['client_id'];
              $client_name = $row['client_name'];
              $client_phone = $row['client_phone'];
              if(strlen($client_phone)>2){ 
                $client_phone = substr($row['client_phone'],0,3)."-".substr($row['client_phone'],3,3)."-".substr($row['client_phone'],6,4);
              }
              $client_email = $row['client_email'];
              $client_website = $row['client_website'];

            ?>
            <tr>
              <td><a href="client.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
              <td><a href="mailto:<?php echo$email; ?>"><?php echo $client_email; ?></a></td>
              <td><?php echo $client_phone; ?></td>
            </tr>

            <?php
            }
            ?>

          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-6">
    <div class="card mb-3">
      <div class="card-header">
        <h6 class="mt-1"><i class="fa fa-building"></i> Vendors</h6>
      </div>
      <div class="card-body">
        <table class="table table-striped table-borderless">
          <thead>
            <tr>
              <th>Name</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            <?php
        
            while($row = mysqli_fetch_array($sql_vendors)){
              $vendor_name = $row['vendor_name'];
              $vendor_description = $row['vendor_description'];
            ?>
            <tr>
              <td><?php echo $vendor_name; ?></td>
              <td><?php echo $vendor_description; ?></td>
            </tr>

            <?php
            }
            ?>

          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-6">
    <div class="card mb-3">
      <div class="card-header">
        <h6 class="mt-1"><i class="fa fa-box"></i> Products</h6>
      </div>
      <div class="card-body">
        <table class="table table-striped table-borderless">
          <thead>
            <tr>
              <th>Name</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            <?php
        
            while($row = mysqli_fetch_array($sql_products)){
              $product_name = $row['product_name'];
              $product_description = $row['product_description'];
            ?>
            <tr>
              <td><?php echo $product_name; ?></td>
              <td><?php echo $product_description; ?></td>
            </tr>

            <?php
            }
            ?>

          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-6">
    <div class="card mb-3">
      <div class="card-header">
        <h6 class="mt-1"><i class="fa fa-key"></i> Logins</h6>
      </div>
      <div class="card-body">
        <table class="table table-striped table-borderless">
          <thead>
            <tr>
              <th>Description</th>
              <th>Username</th>
              <th>Password</th>
            </tr>
          </thead>
          <tbody>
            <?php
        
            while($row = mysqli_fetch_array($sql_logins)){
              $login_description = $row['$login_description'];
              $login_username = $row['$login_username'];
              $login_password = $row['$login_password'];
            ?>
            <tr>
              <td><?php echo $login_description; ?></td>
              <td><?php echo $login_username; ?></td>
              <td><?php echo $login_password; ?></td>

            </tr>

            <?php
            }
            ?>

          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>


<?php } ?>

<?php include("footer.php");