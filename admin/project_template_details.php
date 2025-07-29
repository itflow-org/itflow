<?php

require_once "includes/inc_all_admin.php";


if (isset($_GET['project_template_id'])) {
    $project_template_id = intval($_GET['project_template_id']);

    $sql_project_templates = mysqli_query(
        $mysqli,
        "SELECT * FROM project_templates
        WHERE project_template_id = $project_template_id LIMIT 1"
    );

    if (mysqli_num_rows($sql_project_templates) == 0) {
        echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='admin_project_template.php'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

        include_once "footer.php";
        exit;
    }

    $row = mysqli_fetch_array($sql_project_templates);

    $project_template_name = nullable_htmlentities($row['project_template_name']);
    $project_template_description = nullable_htmlentities($row['project_template_description']);
    $project_template_created_at = date("Y-m-d", strtotime($row['project_template_created_at']));
    $project_template_updated_at = nullable_htmlentities($row['project_template_updated_at']);

    // Get Associated Ticket Templates
    $sql_ticket_templates = mysqli_query($mysqli, "SELECT * FROM ticket_templates, project_template_ticket_templates
        WHERE ticket_templates.ticket_template_id = project_template_ticket_templates.ticket_template_id
        AND project_template_ticket_templates.project_template_id = $project_template_id
        ORDER BY ticket_template_order ASC, ticket_template_name ASC");
    $ticket_template_count = mysqli_num_rows($sql_ticket_templates);

    // Get All Task Templates
    $sql_task_templates = mysqli_query($mysqli,
        "SELECT * FROM ticket_templates, task_templates, project_template_ticket_templates
        WHERE ticket_templates.ticket_template_id = project_template_ticket_templates.ticket_template_id
        AND project_template_ticket_templates.project_template_id = $project_template_id
        AND ticket_templates.ticket_template_id = task_template_ticket_template_id
        ORDER BY task_template_created_at ASC"
    );
    $task_template_count = mysqli_num_rows($sql_task_templates);

?>

<!-- Breadcrumbs-->
<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item">
        <a href="admin_user.php">Admin</a>
    </li>
    <li class="breadcrumb-item">
        <a href="project_template.php">Project Templates</a>
    </li>
    <li class="breadcrumb-item active">Project Template Details</li>
</ol>

<!-- Project Header -->
<div class="card card-body">
    <div class="row">
        <div class="col-sm-4">
            <div class="media">
                <i class="fa fa-fw fa-2x fa-project-diagram text-secondary mr-3"></i>
                <div class="media-body">
                    <h3 class="mb-0"><?php echo $project_template_name; ?><span class='badge badge-pill badge-info ml-2'>Template</span></h3>
                    <div><small class="text-secondary"><?php echo $project_template_description; ?></small></div>
                </div>
            </div>
        </div>

        <div class="col-sm-3">
            <div class="media">
                <i class="fa fa-fw fa-2x fa-life-ring text-secondary mr-3"></i>
                <div class="media-body">
                    <div>Ticket Templates</div>
                    <h3 class="mb-0"><?php echo $ticket_template_count; ?></h3>
                </div>
            </div>
        </div>

        <div class="col-sm-3">
            <div class="media">
                <i class="fa fa-fw fa-2x fa-tasks text-secondary mr-3"></i>
                <div class="media-body">
                    <div>Task Templates</div>
                    <h3 class="mb-0"><?php echo $task_template_count; ?></h3>
                </div>
            </div>
        </div>

        <div class="col-sm-2">
            <div class="btn-group float-right">
                <button type="button" class="btn btn-primary btn-sm" href="#" data-toggle="modal" data-target="#addProjectTemplateTicketTemplateModal">
                    <i class="fas fa-fw fa-plus mr-2"></i>Add Ticket Template
                </button>
                <div class="dropdown dropleft text-center ml-3">
                    <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown">
                        <i class="fas fa-fw fa-ellipsis-v"></i>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editProjectTemplateModal<?php echo $project_template_id; ?>">
                            <i class="fas fa-fw fa-edit mr-2"></i>Edit Template
                        </a>
                        <?php if ($session_user_role == 3) { ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?archive_project_template=<?php echo $project_template_id; ?>">
                                <i class="fas fa-fw fa-archive mr-2"></i>Archive (not yet implemented)
                            </a>
                        <?php } ?>
                        <?php if ($session_user_role == 3) { ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger confirm-link" href="post.php?delete_project_template=<?php echo $project_template_id; ?>">
                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">

        <!-- Tickets card -->
        <?php if (mysqli_num_rows($sql_ticket_templates) > 0) { ?>
            <div class="card card-body card-outline card-dark mb-3">

                <h5 class="text-secondary"><i class="fa fa-fw fa-life-ring mr-2"></i>Project Ticket Templates</h5>
                <div class="table-responsive-sm">
                    <table class="table table-striped table-borderless table-hover">
                        <thead class="text-dark">
                        <tr>
                            <th>Order</th>
                            <th>Template Name</th>
                            <th>Description</th>
                            <th>Ticket Subject</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($row = mysqli_fetch_array($sql_ticket_templates)) {
                            $ticket_template_id = intval($row['ticket_template_id']);
                            $ticket_template_order = intval($row['ticket_template_order']);
                            $ticket_template_name = nullable_htmlentities($row['ticket_template_name']);
                            $ticket_template_description = nullable_htmlentities($row['ticket_template_description']);
                            $ticket_template_subject = nullable_htmlentities($row['ticket_template_subject']);
                            $ticket_template_created_at = nullable_htmlentities($row['ticket_template_created_at']);
                            $ticket_template_updated_at = nullable_htmlentities($row['ticket_template_updated_at']);

                            ?>

                            <tr>
                                <td class="pr-0">
                                    <form action="post.php" method="post" autocomplete="off">
                                        <input type="hidden" name="edit_ticket_template_order">
                                        <input type="hidden" name="project_template_id" value="<?php echo $project_template_id; ?>">
                                        <input type="hidden" name="ticket_template_id" value="<?php echo $ticket_template_id; ?>">
                                        <input type="text" class="form-control pr-0" onchange="this.form.submit()" name="order" value="<?php echo $ticket_template_order; ?>">
                                    </form>
                                </td>
                                <td>
                                    <a href="admin_ticket_template_details.php?ticket_template_id=<?php echo $ticket_template_id; ?>">
                                        <?php echo $ticket_template_name; ?>
                                    </a>
                                </td>
                                <td><?php echo $ticket_template_description; ?></td>
                                <td><?php echo $ticket_template_subject; ?></td>
                                <td>
                                    <form action="post.php" method="post" autocomplete="off">
                                        <input type="hidden" name="project_template_id" value="<?php echo $project_template_id; ?>">
                                        <input type="hidden" name="ticket_template_id" value="<?php echo $ticket_template_id; ?>">
                                        <button type="submit" class="btn btn-default btn-sm confirm-link"
                                            name="remove_ticket_template_from_project_template">
                                            <i class="fa fa-fw fa-times"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>

                        <?php } ?>

                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="col-md-4">

        <!-- Task Templates Card -->
        <?php if (mysqli_num_rows($sql_task_templates) > 0) { ?>
        <div class="card card-body card-outline card-dark">
            <h5 class="text-secondary"><i class="fas fa-fw fa-tasks mr-2"></i>Project Task Templates</h5>
            <table class="table">
                <?php
                while($row = mysqli_fetch_array($sql_task_templates)){
                    $task_template_id = intval($row['task_template_id']);
                    $task_template_name = nullable_htmlentities($row['task_template_name']);
                    $task_template_description = nullable_htmlentities($row['task_template_description']);
                ?>
                    <tr>
                        <td>
                            <i class="far fa-fw fa-check-square text-primary mr-3"></i>
                            <?php echo $task_template_name; ?>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        </div>
        <?php  } ?>
        <!-- End Task TemplatesCard -->

    </div> <!-- End col-3 -->

</div> <!-- End row -->

<?php

require_once "modals/admin_project_template_edit_modal.php";
require_once "modals/admin_project_template_ticket_template_add_modal.php";

}

require_once "../includes/footer.php";

?>

<script src="js/pretty_content.js"></script>
