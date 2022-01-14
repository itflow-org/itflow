<?php include("header.php"); ?>

<?php

if(isset($_GET['query'])){

    $query = mysqli_real_escape_string($mysqli,$_GET['query']);

    $sql_clients = mysqli_query($mysqli,"SELECT * FROM clients LEFT JOIN locations ON clients.client_id = locations.location_client_id WHERE client_name LIKE '%$query%' AND clients.company_id = $session_company_id ORDER BY client_id DESC LIMIT 5");
    $sql_vendors = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_name LIKE '%$query%' AND company_id = $session_company_id ORDER BY vendor_id DESC LIMIT 5");
    $sql_products = mysqli_query($mysqli,"SELECT * FROM products WHERE product_name LIKE '%$query%' AND company_id = $session_company_id ORDER BY product_id DESC LIMIT 5");
    $sql_logins = mysqli_query($mysqli,"SELECT * FROM logins WHERE (login_name LIKE '%$query%' OR login_username LIKE '%$query%') AND company_id = $session_company_id ORDER BY login_id DESC LIMIT 5");
    $sql_tickets = mysqli_query($mysqli, "SELECT * FROM tickets LEFT JOIN clients on tickets.ticket_client_id = clients.client_id WHERE (ticket_subject LIKE '%$query%' OR ticket_number = '$query') AND tickets.company_id = $session_company_id ORDER BY ticket_id DESC LIMIT 5");

    $q = htmlentities($_GET['query']);
    ?>

    <h3><i class="fa fa-search"></i> Welcome to Global Search...</h3>
    <hr>
    <div class="row">

        <!-- Clients-->

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
                            $location_phone = $row['location_phone'];
                            if(strlen($location_phone)>2){
                                $location_phone = substr($row['location_phone'],0,3)."-".substr($row['location_phone'],3,3)."-".substr($row['location_phone'],6,4);
                            }
                            //$client_email = $row['client_email'];
                            $client_website = $row['client_website'];

                            ?>
                            <tr>
                                <td><a href="client.php?client_id=<?php echo $client_id; ?>&tab=contacts"><?php echo $client_name; ?></a></td>
                                <td><a href="mailto:<?php //echo $email; ?>"><?php //echo $client_email; ?></a></td>
                                <td><?php echo $location_phone; ?></td>
                            </tr>

                            <?php
                        }
                        ?>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Vendors -->
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
                            <th>Phone</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while($row = mysqli_fetch_array($sql_vendors)){
                            $vendor_name = $row['vendor_name'];
                            $vendor_description = $row['vendor_description'];
                            $vendor_phone = $row['vendor_phone'];
                            ?>
                            <tr>
                                <td><a href="vendors.php?q=<?php echo $q ?>"><?php echo $vendor_name; ?></a></td>
                                <td><?php echo $vendor_description; ?></td>
                                <td><?php echo $vendor_phone; ?></td>
                            </tr>

                            <?php
                        }
                        ?>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Products -->
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
                                <td><a href="products.php?q=<?php echo $q ?>"><?php echo $product_name; ?></a></td>
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

        <!-- Logins -->
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
                            $login_name = $row['login_name'];
                            $login_client_id = $row['login_client_id'];
                            $login_username = $row['login_username'];
                            $login_password = decryptLoginEntry($row['login_password']);

                            ?>
                            <tr>
                                <td><a href="client.php?client_id=<?php echo $login_client_id ?>&tab=logins&q=<?php echo $q ?>"><?php echo $login_name; ?></a></td>
                                <td><?php echo $login_username; ?></td>
                                <td><a tabindex="0" class="btn btn-sm" data-toggle="popover" data-trigger="focus" data-placement="left" data-content="<?php echo $login_password; ?>"><i class="far fa-eye text-secondary"></i></a><button class="btn btn-sm" data-clipboard-text="<?php echo $login_password; ?>"><i class="far fa-copy text-secondary"></i></button></td>


                            </tr>

                            <?php
                        }
                        ?>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tickets -->
        <div class="col-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mt-1"><i class="fa fa-tags"></i> Tickets</h6>
                </div>
                <div class="card-body">
                    <table class="table table-striped table-borderless">
                        <thead>
                        <tr>
                            <th>Description</th>
                            <th>Client</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while($row = mysqli_fetch_array($sql_tickets)){
                            $ticket_id = $row['ticket_id'];
                            $ticket_subject = $row['ticket_subject'];
                            $ticket_client = $row['client_name'];
                            $ticket_status = $row['ticket_status'];

                            ?>
                            <tr>
                                <td><a href="ticket.php?ticket_id=<?php echo $ticket_id ?>"><?php echo $ticket_subject; ?></a></td>
                                <td><?php echo $ticket_client; ?></td>
                                <td><?php echo $ticket_status; ?></td>

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