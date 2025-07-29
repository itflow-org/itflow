<?php

require_once '../../includes/modal_header.php';

$ticket_reply_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM ticket_replies
    LEFT JOIN tickets ON ticket_id = ticket_reply_ticket_id
    WHERE ticket_reply_id = $ticket_reply_id
    LIMIT 1"
);

$row = mysqli_fetch_array($sql);
$ticket_reply = nullable_htmlentities($row['ticket_reply']);
$client_id = intval($row['ticket_client_id']);

// Generate the HTML form content using output buffering.
ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-edit mr-2"></i>Redacting Ticket Reply</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="ticket_reply_id" value="<?php echo $ticket_reply_id; ?>">
    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">

    <div class="modal-body">

        <div class="form-group">
            <textarea class="form-control tinymceRedact" name="ticket_reply"><?php echo $ticket_reply; ?></textarea>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="redact_ticket_reply" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../includes/modal_footer.php';
