<?php

require_once '../includes/ajax_header.php';

$ticket_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM tickets
    LEFT JOIN clients ON client_id = ticket_client_id
    WHERE ticket_id = $ticket_id
    LIMIT 1"
);

$row = mysqli_fetch_array($sql);
$ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$contact_id = intval($row['ticket_contact_id']);
$client_id = intval($row['ticket_client_id']);
$client_name = nullable_htmlentities($row['client_name']);

// Generate the HTML form content using output buffering.
ob_start();

?>

<div class="modal-header">
    <h5 class="modal-title"><i class="fa fa-fw fa-user mr-2"></i>Changing contact: <strong><?php echo "$ticket_prefix$ticket_number"; ?></strong> - <?php echo $client_name; ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
    <div class="modal-body bg-white">

        <div class="form-group">
            <label>Contact</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                </div>
                <select class="form-control select2" name="contact">
                    <option value="">No One</option>
                    <?php
                    $sql_client_contacts_select = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_primary, contact_technical FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_primary DESC, contact_technical DESC, contact_name ASC");
                    while ($row = mysqli_fetch_array($sql_client_contacts_select)) {
                        $contact_id_select = intval($row['contact_id']);
                        $contact_name_select = nullable_htmlentities($row['contact_name']);
                        $contact_primary_select = intval($row['contact_primary']);
                        if($contact_primary_select == 1) {
                            $contact_primary_display_select = " (Primary)";
                        } else {
                            $contact_primary_display_select = "";
                        }
                        $contact_technical_select = intval($row['contact_technical']);
                        if($contact_technical_select == 1) {
                            $contact_technical_display_select = " (Technical)";
                        } else {
                            $contact_technical_display_select = "";
                        }
                        $contact_title_select = nullable_htmlentities($row['contact_title']);
                        if(!empty($contact_title_select)) {
                            $contact_title_display_select = " - $contact_title_select";
                        } else {
                            $contact_title_display_select = "";
                        }

                        ?>
                        <option 
                            value="<?php echo $contact_id_select; ?>"
                            <?php if ($contact_id_select  == $contact_id) { echo "selected"; } ?>
                            >
                            <?php echo "$contact_name_select$contact_title_display_select$contact_primary_display_select$contact_technical_display_select"; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <?php if (!empty($config_smtp_host)) { ?>
            <div class="form-group">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="contact_notify" value="1" id="checkNotifyContact" <?php if ($config_ticket_client_general_notifications) { echo "checked"; } ?>>
                    <label class="form-check-label" for="checkNotifyContact">
                        Send email notification
                    </label>
                </div>
            </div>
        <?php } ?>

    </div>

    <div class="modal-footer bg-white">
        <button type="submit" name="edit_ticket_contact" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>

</form>

<?php

require_once "../includes/ajax_footer.php";
