<?php

// Default Column Sortby Filter
$sort = "project_template_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM project_templates
    WHERE (project_template_name LIKE '%$q%' OR project_template_description LIKE '%$q%')
    AND project_template_archived_at IS NULL
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-project-diagram mr-2"></i>Project Templates</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addProjectTemplateModal"><i class="fas fa-plus mr-2"></i>New Project Template</button>
        </div>
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if(isset($q)){ echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Project Templates">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                </div>

            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
                <tr>
                    <th>
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=project_template_name&order=<?php echo $disp; ?>">
                            Template <?php if ($sort == 'project_template_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>Tickets</th>
                    <th>Tasks</th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while($row = mysqli_fetch_array($sql)){
                    $project_template_id = intval($row['project_template_id']);
                    $project_template_name = nullable_htmlentities($row['project_template_name']);
                    $project_template_description = nullable_htmlentities($row['project_template_description']);
                    $project_template_created_at = nullable_htmlentities($row['project_template_created_at']);

                    // Get Ticket Template Count
                    $sql_ticket_templates = mysqli_query($mysqli, "SELECT * FROM ticket_templates, project_template_ticket_templates
                        WHERE ticket_templates.ticket_template_id = project_template_ticket_templates.ticket_template_id
                        AND project_template_ticket_templates.project_template_id = $project_template_id
                        ORDER BY ticket_template_order ASC, ticket_template_name ASC");
                    $ticket_template_count = mysqli_num_rows($sql_ticket_templates);

                    // Get Tasks Template Count
                    $sql_task_templates = mysqli_query($mysqli,
                        "SELECT * FROM ticket_templates, task_templates, project_template_ticket_templates
                        WHERE ticket_templates.ticket_template_id = project_template_ticket_templates.ticket_template_id
                        AND project_template_ticket_templates.project_template_id = $project_template_id
                        AND ticket_templates.ticket_template_id = task_template_ticket_template_id
                        ORDER BY task_template_created_at ASC"
                    );
                    $task_template_count = mysqli_num_rows($sql_task_templates);

                    ?>
                    <tr>
                        <td>
                            <a class="text-dark" href="#" data-toggle="modal" data-target="#editProjectTemplateModal<?php echo $project_template_id; ?>">
                                <div class="media">
                                    <i class="fa fa-fw fa-2x fa-project-diagram mr-3"></i>
                                    <div class="media-body">
                                        <div>
                                            <a href="admin_project_template_details.php?project_template_id=<?php echo $project_template_id; ?>">
                                                <?php echo $project_template_name; ?>
                                            </a>
                                        </div>
                                        <div><small class="text-secondary"><?php echo $project_template_description; ?></small></div>
                                    </div>
                                </div>
                            </a>
                        </td>
                        <td><?php echo $ticket_template_count; ?></td>
                        <td><?php echo $task_template_count; ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editProjectTemplateModal<?php echo $project_template_id; ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <?php if($session_user_role == 3) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_project_template=<?php echo $project_template_id; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php

                    require "modals/admin_project_template_edit_modal.php";

                }

                ?>

                </tbody>
            </table>
        </div>
        <?php require_once "includes/filter_footer.php";
 ?>
    </div>
</div>

<?php
require_once "modals/admin_project_template_add_modal.php";

require_once "includes/footer.php";
