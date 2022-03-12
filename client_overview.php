<?php

$sql_contacts = mysqli_query($mysqli,"SELECT * FROM contacts LEFT JOIN departments ON contact_department_id = department_id WHERE contact_client_id = $client_id AND contacts.company_id = $session_company_id ORDER BY contact_updated_at DESC LIMIT 5");

$sql_vendors = mysqli_query($mysqli,"SELECT * FROM vendors WHERE (vendor_name LIKE '%$query%' OR vendor_phone LIKE '%$phone_query%') AND company_id = $session_company_id ORDER BY vendor_id DESC LIMIT 5");

$sql_documents = mysqli_query($mysqli, "SELECT * FROM documents LEFT JOIN clients on document_client_id = clients.client_id WHERE document_name LIKE '%$query%' AND documents.company_id = $session_company_id ORDER BY document_id DESC LIMIT 5");
    
$sql_tickets = mysqli_query($mysqli, "SELECT * FROM tickets LEFT JOIN clients on tickets.ticket_client_id = clients.client_id WHERE (ticket_subject LIKE '%$query%' OR ticket_number = '$query') AND tickets.company_id = $session_company_id ORDER BY ticket_id DESC LIMIT 5");

$sql_logins = mysqli_query($mysqli,"SELECT * FROM logins WHERE (login_name LIKE '%$query%' OR login_username LIKE '%$query%') AND company_id = $session_company_id ORDER BY login_id DESC LIMIT 5");

?>

<h4><i class="fas fa-tachometer-alt"></i> Overview</h4>
<hr>
<div class="row">

    <?php if(mysqli_num_rows($sql_contacts) > 0){ ?> 

    <!-- Contacts-->

    <div class="col-6">
        <div class="card mb-3">
            <div class="card-header">
                <h6><i class="fa fa-users"></i> Recent Contacts</h6>
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

                    while($row = mysqli_fetch_array($sql_contacts)){
                        $contact_id = $row['contact_id'];
                        $contact_name = $row['contact_name'];
                        $contact_title = $row['contact_title'];
                        $contact_phone = formatPhoneNumber($row['contact_phone']);
                        $contact_extension = $row['contact_extension'];
                        $contact_mobile = formatPhoneNumber($row['contact_mobile']);
                        $contact_email = $row['contact_email'];
                        $client_id = $row['client_id'];
                        $client_name = $row['client_name'];
                        $department_name = $row['department_name'];

                        ?>
                        <tr>
                            <td><a href="client.php?client_id=<?php echo $client_id; ?>&tab=contacts"><?php echo $contact_name; ?></a>
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

</div>