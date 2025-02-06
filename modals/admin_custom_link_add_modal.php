<div class="modal" id="addLinkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-external-link-alt mr-2"></i>New Custom Link</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">

                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-list-ul"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" placeholder="Link name" maxlength="200" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Order</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-sort-numeric-down"></i></span>
                            </div>
                            <input type="number" class="form-control" name="order" placeholder="Leave blank for no order">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>URI <strong class="text-danger">*</strong></label> / <span class="text-secondary">Open New Tab</span>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-external-link-alt"></i></span>
                            </div>
                            <input type="text" class="form-control" name="uri" placeholder="Enter Link" maxlength="500" required>
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <input type="checkbox" name="new_tab" value="1">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Icon</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-image"></i></span>
                            </div>
                            <input type="text" class="form-control" name="icon" placeholder="Icon ex handshake" maxlength="200">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Location <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-home"></i></span>
                            </div>
                            <select class="form-control select2" name="location" required>
                                <option value="1">Main Side Nav</option>
                                <option value="2">Top Nav (Icon Required)</option>
                                <option value="3">Client Portal Nav</option>
                            </select>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_custom_link" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
