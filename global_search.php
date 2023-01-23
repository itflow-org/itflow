<?php include("inc_all.php"); ?>

<?php

if (isset($_GET['query'])) {

    $query = trim(strip_tags(mysqli_real_escape_string($mysqli,$_GET['query'])));

    $phone_query = preg_replace("/[^0-9]/", '',$query);
    if (empty($phone_query)) {
        $phone_query = $query;
    }

    $ticket_num_query = str_replace("$config_ticket_prefix", "", "$query");

    $sql_clients = mysqli_query($mysqli,"SELECT * FROM clients LEFT JOIN locations ON clients.primary_location = locations.location_id WHERE client_name LIKE '%$query%' AND clients.company_id = $session_company_id ORDER BY client_id DESC LIMIT 5");
    $sql_contacts = mysqli_query($mysqli,"SELECT * FROM contacts LEFT JOIN clients ON client_id = contact_client_id WHERE (contact_name LIKE '%$query%' OR contact_title LIKE '%$query%' OR contact_email LIKE '%$query%' OR contact_phone LIKE '%$phone_query%' OR contact_mobile LIKE '%$phone_query%') AND contacts.company_id = $session_company_id ORDER BY contact_id DESC LIMIT 5");
    $sql_vendors = mysqli_query($mysqli,"SELECT * FROM vendors WHERE (vendor_name LIKE '%$query%' OR vendor_phone LIKE '%$phone_query%') AND company_id = $session_company_id ORDER BY vendor_id DESC LIMIT 5");
    $sql_products = mysqli_query($mysqli,"SELECT * FROM products WHERE product_name LIKE '%$query%' AND company_id = $session_company_id ORDER BY product_id DESC LIMIT 5");
    $sql_documents = mysqli_query($mysqli, "SELECT * FROM documents LEFT JOIN clients on document_client_id = clients.client_id WHERE MATCH(document_content_raw) AGAINST ('$query') AND documents.company_id = $session_company_id ORDER BY document_id DESC LIMIT 5");
    $sql_tickets = mysqli_query($mysqli, "SELECT * FROM tickets LEFT JOIN clients on tickets.ticket_client_id = clients.client_id WHERE (ticket_subject LIKE '%$query%' OR ticket_number = '$ticket_num_query') AND tickets.company_id = $session_company_id ORDER BY ticket_id DESC LIMIT 5");
    $sql_logins = mysqli_query($mysqli,"SELECT * FROM logins WHERE (login_name LIKE '%$query%' OR login_username LIKE '%$query%') AND company_id = $session_company_id ORDER BY login_id DESC LIMIT 5");

    $q = htmlentities($_GET['query']);
    ?>

    <h4 class="text-center"><i class="fa fa-search"></i> Search all things</h4>
    <hr>
    <div class="row">

        <?php if (mysqli_num_rows($sql_clients) > 0) { ?>

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
                                <th>Phone</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_clients)) {
                                $client_id = $row['client_id'];
                                $client_name = htmlentities($row['client_name']);
                                $location_phone = formatPhoneNumber($row['location_phone']);
                                $client_website = htmlentities($row['client_website']);

                                ?>
                                <tr>
                                    <td><a href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
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

        <?php } ?>

        <?php if (mysqli_num_rows($sql_contacts) > 0) { ?>

            <!-- Contacts-->

            <div class="col-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mt-1"><i class="fa fa-users"></i> Contacts</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-borderless">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Cell</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_contacts)) {
                                $contact_id = $row['contact_id'];
                                $contact_name = htmlentities($row['contact_name']);
                                $contact_title = htmlentities($row['contact_title']);
                                $contact_phone = formatPhoneNumber($row['contact_phone']);
                                $contact_extension = htmlentities($row['contact_extension']);
                                $contact_mobile = formatPhoneNumber($row['contact_mobile']);
                                $contact_email = htmlentities($row['contact_email']);
                                $client_id = $row['client_id'];
                                $client_name = htmlentities($row['client_name']);
                                $contact_department = htmlentities($row['contact_department']);

                                ?>
                                <tr>
                                    <td><a href="client_contact_details.php?client_id=<?php echo $client_id; ?>&contact_id=<?php echo $contact_id; ?>"><?php echo $contact_name; ?></a>
                                        <br><small class="text-secondary"><?php echo $contact_title; ?></small>
                                    </td>
                                    <td><?php echo $contact_email; ?></td>
                                    <td><?php echo "$contact_phone $contact_extension"; ?></td>
                                    <td><?php echo $contact_mobile; ?></td>
                                </tr>

                                <?php
                            }
                            ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php } ?>

        <?php if (mysqli_num_rows($sql_vendors) > 0) { ?>

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

                            while ($row = mysqli_fetch_array($sql_vendors)) {
                                $vendor_name = htmlentities($row['vendor_name']);
                                $vendor_description = htmlentities($row['vendor_description']);
                                $vendor_phone = formatPhoneNumber($row['vendor_phone']);
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

        <?php } ?>

        <?php if (mysqli_num_rows($sql_products) > 0) { ?>

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

                            while ($row = mysqli_fetch_array($sql_products)) {
                                $product_name = htmlentities($row['product_name']);
                                $product_description = htmlentities($row['product_description']);
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

        <?php } ?>

        <?php if (mysqli_num_rows($sql_documents) > 0) { ?>

            <!-- Documents -->
            <div class="col-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mt-1"><i class="fa fa-file-alt"></i> Documents</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-borderless">
                            <thead>
                            <tr>
                                <th>Document</th>
                                <th>Client</th>
                                <th>Updated</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_documents)) {
                                $document_name = htmlentities($row['document_name']);
                                $document_client_id = $row['document_client_id'];
                                $document_client = htmlentities($row['client_name']);
                                $document_updated = $row['document_updated_at'];

                                ?>
                                <tr>
                                    <td><a href="client_documents.php?client_id=<?php echo $document_client_id ?>&q=<?php echo $q ?>"><?php echo $document_name; ?></a></td>
                                    <td><?php echo $document_client ?></td>
                                    <td><?php echo $document_updated ?></td>
                                </tr>

                                <?php
                            }
                            ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php } ?>

        <?php if (mysqli_num_rows($sql_tickets) > 0) { ?>

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
                                <th>Ticket ID</th>
                                <th>Description</th>
                                <th>Client</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_tickets)) {
                                $ticket_id = $row['ticket_id'];
                                $ticket_prefix = $row['ticket_prefix'];
                                $ticket_number = $row['ticket_number'];
                                $ticket_subject = htmlentities($row['ticket_subject']);
                                $ticket_client = htmlentities($row['client_name']);
                                $ticket_status = htmlentities($row['ticket_status']);

                                ?>
                                <tr>
                                    <td><a href="ticket.php?ticket_id=<?php echo $ticket_id ?>"><?php echo $ticket_prefix . $ticket_number; ?></a></td>
                                    <td><?php echo $ticket_subject; ?></td>
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

        <?php } ?>

        <?php if (mysqli_num_rows($sql_logins) > 0) { ?>

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

                            while ($row = mysqli_fetch_array($sql_logins)) {
                                $login_name = htmlentities($row['login_name']);
                                $login_client_id = $row['login_client_id'];
                                $login_username = htmlentities($row['login_username']);
                                $login_password = htmlentities(decryptLoginEntry($row['login_password']));

                                ?>
                                <tr>
                                    <td><a href="client_logins.php?client_id=<?php echo $login_client_id ?>&q=<?php echo $q ?>"><?php echo $login_name; ?></a></td>
                                    <td><?php echo $login_username; ?></td>
                                    <td><a tabindex="0" class="btn btn-sm" data-toggle="popover" data-trigger="focus" data-placement="left" data-content="<?php echo $login_password; ?>"><i class="far fa-eye text-secondary"></i></a><button class="btn btn-sm clipboardjs" data-clipboard-text="<?php echo $login_password; ?>"><i class="far fa-copy text-secondary"></i></button></td>


                                </tr>

                                <?php
                            }
                            ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php } ?>

    </div>


<?php } ?>

<?php include("footer.php");