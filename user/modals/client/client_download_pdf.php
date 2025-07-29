<div class="modal" id="exportClientPDFModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-file-pdf mr-2"></i>Export PDF</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off" target="_blank">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="modal-body bg-white">
                    <ul class="list-group">
                        <div class="row">
                            <div class="col-sm-6">

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="contacts" name="export_contacts" value="1" checked>
                                        <label for="contacts" class="custom-control-label">
                                            <i class='fas fa-fw fa-users mr-2'></i>Contacts
                                        </label>
                                    </div>
                                </li>

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="locations" name="export_locations" value="1" checked>
                                        <label for="locations" class="custom-control-label">
                                            <i class='fas fa-fw fa-map-marker-alt mr-2'></i>Locations
                                        </label>
                                    </div>
                                </li>

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="assets" name="export_assets" value="1" checked>
                                        <label for="assets" class="custom-control-label">
                                            <i class='fas fa-fw fa-desktop mr-2'></i>Assets
                                        </label>
                                    </div>
                                </li>

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="software" name="export_software" value="1" checked>
                                        <label for="software" class="custom-control-label">
                                            <i class='fas fa-fw fa-cube mr-2'></i>Software / Licenses
                                        </label>
                                    </div>
                                </li>

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="credentials" name="export_credentials" value="1">
                                        <label for="credentials" class="custom-control-label">
                                            <i class='fas fa-fw fa-key mr-2'></i>Credentials
                                        </label>
                                    </div>
                                </li>

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="networks" name="export_networks" value="1" checked>
                                        <label for="networks" class="custom-control-label">
                                            <i class='fas fa-fw fa-network-wired mr-2'></i>Networks
                                        </label>
                                    </div>
                                </li>

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="certificates" name="export_certificates" value="1" checked>
                                        <label for="certificates" class="custom-control-label">
                                            <i class='fas fa-fw fa-lock mr-2'></i>Certificates
                                        </label>
                                    </div>
                                </li>

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="domains" name="export_domains" value="1" checked>
                                        <label for="domains" class="custom-control-label">
                                            <i class='fas fa-fw fa-globe mr-2'></i>Domains
                                        </label>
                                    </div>
                                </li>

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="tickets" name="export_tickets" value="1" checked>
                                        <label for="tickets" class="custom-control-label">
                                            <i class='fas fa-fw fa-life-ring mr-2'></i>Tickets
                                        </label>
                                    </div>
                                </li>

                            </div>

                            <div class="col-sm-6">

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="recurring_tickets" name="export_recurring_tickets" value="1" checked>
                                        <label for="recurring_tickets" class="custom-control-label">
                                            <i class='fas fa-fw fa-clock mr-2'></i>Recurring Tickets
                                        </label>
                                    </div>
                                </li>

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="vendors" name="export_vendors" value="1" checked>
                                        <label for="vendors" class="custom-control-label">
                                            <i class='fas fa-fw fa-building mr-2'></i>Vendors
                                        </label>
                                    </div>
                                </li>

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="invoices" name="export_invoices" value="1" checked>
                                        <label for="invoices" class="custom-control-label">
                                            <i class='fas fa-fw fa-file-invoice mr-2'></i>Invoices
                                        </label>
                                    </div>
                                </li>

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="recurring_invoices" name="export_recurring_invoices" value="1" checked>
                                        <label for="recurring_invoices" class="custom-control-label">
                                            <i class='fas fa-fw fa-sync mr-2'></i>Recurring Invoices
                                        </label>
                                    </div>
                                </li>

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="quotes" name="export_quotes" value="1" checked>
                                        <label for="quotes" class="custom-control-label">
                                            <i class='fas fa-fw fa-file mr-2'></i>Quotes
                                        </label>
                                    </div>
                                </li>

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="payments" name="export_payments" value="1" checked>
                                        <label for="payments" class="custom-control-label">
                                            <i class='fas fa-fw fa-credit-card mr-2'></i>Payments
                                        </label>
                                    </div>
                                </li>

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="trips" name="export_trips" value="1" checked>
                                        <label for="trips" class="custom-control-label">
                                            <i class='fas fa-fw fa-route mr-2'></i>Trips
                                        </label>
                                    </div>
                                </li>

                                <li class="list-group-item">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="logs" name="export_logs" value="1" checked>
                                        <label for="logs" class="custom-control-label">
                                            <i class='fas fa-fw fa-eye mr-2'></i>Audit Log
                                        </label>
                                    </div>
                                </li>

                            </div>
                        </div>
                    </ul>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="export_client_pdf" class="btn btn-primary text-bold"><i class="fa fa-fw fa-download mr-2"></i>Export</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
