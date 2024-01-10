
<div class="modal" id="addTicketModal">
<div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-file-invoice mr-2"></i>Add Unbilled Ticket to Invoice</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body bg-white">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Ticket Number</th>
                                <th>Scope</th>
                                <th class="text-right">Add to Invoice</th>
                            </tr>
                        </thead>
                        <?php while ($row = mysqli_fetch_array($sql_tickets_billable)) { 
                            $ticket_id = intval($row['ticket_id']);
                            $ticket_subject = nullable_htmlentities($row['ticket_subject']);
                            ?>
                            <tr>
                                <td><?php echo $ticket_id?></td>
                                <td><?php echo $ticket_subject ?></td>
                                <td></td>
                                <td><a href='ticket.php?ticket_id=<?php echo $ticket_id?>&invoice_id=<?php echo $invoice_id?>#addInvoiceFromTicketModal'>
                                    <i class="fas fa-fw fa-plus-circle"></i></td>
                            </tr>
                        <?php } ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

