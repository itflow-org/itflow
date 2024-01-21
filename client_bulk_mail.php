<?php
require_once "inc_all_client.php";

$sql = mysqli_query($mysqli, "SELECT * FROM contacts
    WHERE contact_client_id = $client_id
    AND contact_archived_at IS NULL
    AND contact_email != ''
    ORDER BY contact_primary DESC,
    contact_important DESC"
);

?>
    
<form action="post.php" method="post">   

    <div class="card">
        <div class="card-header">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-envelope-open mr-2"></i>Bulk Mail</h3>
            <div class="card-tools">
                <button type="submit" class="btn btn-primary" name="send_bulk_mail_now">
                    <i class="fas fa-paper-plane mr-2"></i>Send Now
                </button>
            </div>
        </div>
        <div class="card-body">

            <div class="row">
                
                <div class="col">

                    <h5>Email Message</h5>

                    <hr>
                    
                    <div class="form-group">
                        <input type="text" class="form-control" name="mail_from" placeholder="Email From" required>
                    </div>

                    <div class="form-group">
                        <input type="text" class="form-control" name="mail_from_name" placeholder="From Name" required>
                    </div>

                    <div class="form-group">
                        <input type="text" class="form-control" name="subject" placeholder="Subject" required>
                    </div>

                    <div class="form-group">
                        <textarea class="form-control tinymce" name="body" placeholder="Type an email in here"></textarea>
                    </div>

                </div>

                <div class="col">

                    <h5>Select Contacts</h5>
                    <hr>
                    <div class="card">
                        <table class="table">
                            <thead>
                                <tr>
                                    <td>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="selectAllCheckbox" onchange="toggleCheckboxes()">
                                        </div>
                                    </td>
                                    <th>Name</th>
                                    <th>Title</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>

                            <?php
                            while ($row = mysqli_fetch_array($sql)) {
                                $contact_id = intval($row['contact_id']);
                                $contact_name = nullable_htmlentities($row['contact_name']);
                                $contact_title = nullable_htmlentities($row['contact_title']);
                                $contact_email = nullable_htmlentities($row['contact_email']);
                                $contact_primary = intval($row['contact_primary']);
                                $contact_important = intval($row['contact_important']);
                                $contact_billing = intval($row['contact_billing']);
                                $contact_technical = intval($row['contact_technical']);
                            ?>
                            <tr>
                                <td>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="contact[]" value="<?php echo $contact_id; ?>">
                                    </div>
                                </td>
                                <td>
                                    <a href="client_contact_details.php?client_id=<?php echo $client_id; ?>&contact_id=<?php echo $contact_id; ?>" target="_blank">
                                        <?php echo $contact_name; ?>
                                    </a>
                                </td>
                                <td><?php echo $contact_title; ?></td>
                                <td><?php echo $contact_email; ?></td>
                            </tr>
                            <?php } ?>
                            </tbody>
                        </table>

                    </div>
                
                </div>

            </div>
        </div>
    </div>

</form>

<script>
function toggleCheckboxes() {
    // Get the state of the 'selectAllCheckbox'
    var selectAllChecked = document.getElementById('selectAllCheckbox').checked;
    
    // Find all checkboxes with the name 'contact[]' and set their state
    var checkboxes = document.querySelectorAll('input[type="checkbox"][name="contact[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = selectAllChecked;
    });
}
</script>

<?php

require_once "footer.php";
