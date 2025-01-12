<div class="modal" id="editProjectTemplateModal<?php echo $project_template_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-project-diagram mr-2"></i>Editing Project Template: <strong><?php echo $project_template_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="project_template_id" value="<?php echo $project_template_id; ?>">

                <div class="modal-body bg-white">
                    <div class="form-group">
                        <label>Project Template Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" placeholder="Project Template Name" value="<?php echo $project_template_name; ?>" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                            </div>
                            <input type="text" class="form-control" name="description" placeholder="Description" value="<?php echo $project_template_description; ?>">
                        </div>
                    </div>

                </div>
                
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_project_template" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            
            </form>
        </div>
    </div>
</div>
