
<div class="modal" id="addTicketModal">
<div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fas fa-fw fa-file-invoice me-2"></i>Add Unbilled Ticket to Invoice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Ticket Number</th>
                                <th>Scope</th>
                                <th>Add to Invoice</th>
                            </tr>
                        </thead>
                        <?php while ($row = mysqli_fetch_assoc($sql_tickets_billable)) {
                            $ticket_id = intval($row['ticket_id']);
                            $ticket_subject = escapeHtml($row['ticket_subject']);
                            $ticket_number = intval($row['ticket_number']);
                            $ticket_prefix = escapeHtml($row['ticket_prefix']);
                            $ticket_status = escapeHtml($row['ticket_status']);

                            switch ($ticket_status) {
                                case 'Closed':
                                    $ticket_status_class = 'bg-dark';
                                    break;
                                case 'Auto Close':
                                    $ticket_status_class = 'bg-warning';
                                    break;
                                default:
                                    $ticket_status_class = 'bg-secondary';
                                    break;
                            }


                            ?>
                            <tr>
                                <td>
                                    <a href="ticket.php?ticket_id=<?= $ticket_id ?>">
                                        <span class="badge rounded-pill <?= $ticket_status_class ?> p-3"><?= "$ticket_prefix$ticket_number" ?></span>
                                    </a>
                                </td>
                                <td><?= $ticket_subject ?></td>
                                <td><a href='ticket.php?ticket_id=<?= $ticket_id ?>&invoice_id=<?= $invoice_id ?>#addInvoiceFromTicketModal'>
                                    <i class="fas fa-fw fa-plus-circle"></i></td>
                            </tr>
                        <?php } ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
