<div class="modal" id="editScheduledTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-calendar-check mr-2"></i><span id="editHeader"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">
                    <input type="hidden" name="scheduled_ticket_id" id="editTicketId">
                    <input type="hidden" name="client" id="editClientId">

                    <div class="form-group">
                        <label>Contact <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            </div>
                            <select class="form-control select2" name="contact" id="editTicketContact" required>
                                <option value="">- Contact -</option>
                            </select>
                        </div>
                    </div>

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
                        <label>Subject <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                            </div>
                            <input type="text" class="form-control" name="subject" placeholder="Subject" required id="editTicketSubject">
                        </div>
                    </div>

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

                    <div class="form-group">
                        <textarea class="form-control tinymce" name="details" id="editTicketDetails"></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_scheduled_ticket" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>