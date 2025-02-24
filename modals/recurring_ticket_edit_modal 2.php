<div class="modal" id="editRecurringTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-calendar-check mr-2"></i><span id="editHeader"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="scheduled_ticket_id" id="editTicketId">
                <input type="hidden" name="client" id="editClientId">

                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-edit-details"><i class="fa fa-fw fa-life-ring mr-2"></i>Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-edit-contacts"><i class="fa fa-fw fa-users mr-2"></i>Contacts</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-edit-schedule"><i class="fa fa-fw fa-building mr-2"></i>Schedule</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-edit-assets"><i class="fa fa-fw fa-desktop mr-2"></i>Assets</a>
                        </li>
                    </ul>

                    <div class="tab-content" <?php if (lookupUserPermission('module_support') <= 1) { echo 'inert'; } ?>>

                        <div class="tab-pane fade show active" id="pills-edit-details">

                            <div class="form-group">
                                <label>Subject <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="subject" placeholder="Subject" maxlength="500" required id="editTicketSubject">
                                </div>
                            </div>

                            <div class="form-group">
                                <textarea class="form-control tinymce" name="details" id="editTicketDetails"></textarea>
                            </div>

                            <div class="form-group">
                                <label>Priority <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                                    </div>
                                    <!-- Not using select2 as couldn't get this working with Javascript modal population -->
                                    <select class="form-control" name="priority" required id="editTicketPriority">
                                        <option id="Low">Low</option>
                                        <option id="Medium">Medium</option>
                                        <option id="High">High</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Assign To</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                                    </div>
                                    <select class="form-control select2" name="assigned_to" id="editTicketAgent">
                                        <option value="0">- Not Assigned -</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group" <?php if (!$config_module_enable_accounting) { echo 'style="display:none"'; } ?>>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" name="billable" id="editTicketBillable" value="1">
                                    <label class="custom-control-label" for="editTicketBillable">Mark Billable</label>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-edit-contacts">

                            <div class="form-group">
                                <label>Contact</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <select class="form-control select2" name="contact" id="editTicketContact">
                                        <option value="">- Contact -</option>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-edit-schedule">

                            <div class="form-group">
                                <label>Frequency <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-recycle"></i></span>
                                    </div>
                                    <!-- Not using select2 as couldn't get this working with Javascript modal population -->
                                    <select class="form-control" name="frequency" required id="editTicketFrequency">
                                        <option id="Weekly">Weekly</option>
                                        <option id="Monthly">Monthly</option>
                                        <option id="Quarterly">Quarterly</option>
                                        <option id="Biannually">Biannually</option>
                                        <option id="Annually">Annually</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Next run date <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                                    </div>
                                    <input class="form-control" type="date" name="next_date" id="editTicketNextRun" max="2999-12-31">
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-edit-assets">

                            <div class="form-group">
                                <label>Asset</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                                    </div>
                                    <select class="form-control select2" name="asset" id="editTicketAssetId">
                                        <option value="0">- None -</option>
                                    </select>
                                </div>
                            </div>

                        </div>

                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_recurring_ticket" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
