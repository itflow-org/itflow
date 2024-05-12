<div class="modal" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-list-ul mr-2"></i>New <?php echo nullable_htmlentities($category); ?> Category</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="type" value="<?php echo nullable_htmlentities($category); ?>">

                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-list-ul"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" placeholder="Category name" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Color <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-paint-brush"></i></span>
                            </div>
                            <input type="color" class="form-control col-3" name="color" required>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_category" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
