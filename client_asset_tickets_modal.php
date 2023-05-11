<div class="modal" id="assetTicketsModal<?php echo $asset_id; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-<?php echo $device_icon; ?>"></i> <?php echo $asset_name; ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body bg-white">
                <?php
                // Query is run from client_assets.php
                while ($row = mysqli_fetch_array($sql_tickets)) {
                    $ticket_id = intval($row['ticket_id']);
                    $ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
                    $ticket_number = intval($row['ticket_number']);
                    $ticket_subject = nullable_htmlentities($row['ticket_subject']);
                    $ticket_status = nullable_htmlentities($row['ticket_status']);
                    $ticket_created_at = nullable_htmlentities($row['ticket_created_at']);
                    $ticket_updated_at = nullable_htmlentities($row['ticket_updated_at']);
                    ?>
                    <p>
                        <i class="fas fa-fw fa-ticket-alt"></i>
                        Ticket: <a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>"><?php echo "$ticket_prefix$ticket_number" ?></a> on <?php echo $ticket_created_at; ?> <?php echo $ticket_subject; ?>
                    </p>
                <?php } ?>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
            </div>

        </div>
    </div>
</div>
