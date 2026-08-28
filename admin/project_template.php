<?php

require_once "includes/inc_all_admin.php";


if (isset($_GET['project_template_id'])) {
    $project_template_id = intval($_GET['project_template_id']);

    $sql_project_templates = mysqli_query(
        $mysqli,
        "SELECT project_template_created_at, project_template_description, project_template_name,
            project_template_updated_at FROM project_templates
        WHERE project_template_id = $project_template_id LIMIT 1"
    );

    if (mysqli_num_rows($sql_project_templates) == 0) {
        echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='javascript:history.back()'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

        require_once "../includes/footer.php";
        exit;
    }

    $row = mysqli_fetch_assoc($sql_project_templates);

    $project_template_name = escapeHtml($row['project_template_name']);
    $project_template_description = escapeHtml($row['project_template_description']);
    $project_template_created_at = date("Y-m-d", strtotime($row['project_template_created_at']));
    $project_template_updated_at = escapeHtml($row['project_template_updated_at']);

    // Get Associated Ticket Templates
    $sql_ticket_templates = mysqli_query($mysqli, "SELECT ticket_template_created_at, ticket_template_description,
        project_template_ticket_templates.ticket_template_id, ticket_template_name,
        ticket_template_order, ticket_template_subject, ticket_template_updated_at FROM ticket_templates, project_template_ticket_templates
        WHERE ticket_templates.ticket_template_id = project_template_ticket_templates.ticket_template_id
        AND project_template_ticket_templates.project_template_id = $project_template_id
        ORDER BY ticket_template_order ASC, ticket_template_name ASC");
    $ticket_template_count = mysqli_num_rows($sql_ticket_templates);

    // Get All Task Templates
    $sql_task_templates = mysqli_query($mysqli,
        "SELECT task_template_id, task_template_name FROM ticket_templates, task_templates, project_template_ticket_templates
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
        <a href="project_templates.php">Project Templates</a>
    </li>
    <li class="breadcrumb-item active">Project Template Details</li>
</ol>

<!-- Project Header -->
<div class="card card-body mb-3">
    <div class="row">
        <div class="col-sm-4">
            <div class="d-flex">
                <i class="fa fa-fw fa-2x fa-project-diagram text-secondary me-3"></i>
                <div class="flex-grow-1">
                    <h3 class="mb-0"><?= $project_template_name ?><span class='badge rounded-pill bg-info ms-2'>Template</span></h3>
                    <div><small class="text-secondary"><?= $project_template_description ?></small></div>
                </div>
            </div>
        </div>

        <div class="col-sm-3">
            <div class="d-flex">
                <i class="fa fa-fw fa-2x fa-life-ring text-secondary me-3"></i>
                <div class="flex-grow-1">
                    <div>Ticket Templates</div>
                    <h3 class="mb-0"><?= $ticket_template_count ?></h3>
                </div>
            </div>
        </div>

        <div class="col-sm-3">
            <div class="d-flex">
                <i class="fa fa-fw fa-2x fa-tasks text-secondary me-3"></i>
                <div class="flex-grow-1">
                    <div>Task Templates</div>
                    <h3 class="mb-0"><?= $task_template_count ?></h3>
                </div>
            </div>
        </div>

        <div class="col-sm-2">
            <div class="btn-group float-end">
                <button type="button" class="btn btn-primary btn-sm ajax-modal" href="#" data-modal-url="modals/project_template/project_template_ticket_template_add.php?project_template_id=<?= $project_template_id ?>">
                    <i class="fas fa-fw fa-plus me-2"></i>Add Ticket Template
                </button>
                <div class="dropdown dropstart text-center ms-3">
                    <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown">
                        <i class="fas fa-fw fa-ellipsis-v"></i>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/project_template/project_template_edit.php?project_template_id=<?= $project_template_id ?>">
                            <i class="fas fa-fw fa-edit me-2"></i>Edit Template
                        </a>
                        <?php if ($session_user_role == 3) { ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?archive_project_template=<?= $project_template_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                <i class="fas fa-fw fa-archive me-2"></i>Archive (not yet implemented)
                            </a>
                        <?php } ?>
                        <?php if ($session_user_role == 3) { ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger confirm-link" href="post.php?delete_project_template=<?= $project_template_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                <i class="fas fa-fw fa-trash me-2"></i>Delete
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

                <h5 class="text-secondary"><i class="fa fa-fw fa-life-ring me-2"></i>Project Ticket Templates</h5>
                <div class="table-responsive-sm">
                    <table class="table table-striped table-borderless table-hover" id="ticket_templates">
                        <thead class="text-dark">
                        <tr>
                            <th>Template Name</th>
                            <th>Description</th>
                            <th>Ticket Subject</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($row = mysqli_fetch_assoc($sql_ticket_templates)) {
                            $ticket_template_id = intval($row['ticket_template_id']);
                            $ticket_template_order = intval($row['ticket_template_order']);
                            $ticket_template_name = escapeHtml($row['ticket_template_name']);
                            $ticket_template_description = escapeHtml($row['ticket_template_description']);
                            $ticket_template_subject = escapeHtml($row['ticket_template_subject']);
                            $ticket_template_created_at = escapeHtml($row['ticket_template_created_at']);
                            $ticket_template_updated_at = escapeHtml($row['ticket_template_updated_at']);

                            ?>

                            <tr data-task-id="<?= $ticket_template_id ?>">
                                <td>
                                    <a href="#" class="drag-handle"><i class="fas fa-bars text-muted me-2"></i></a>
                                    <a href="ticket_template.php?ticket_template_id=<?= $ticket_template_id ?>">
                                        <?= $ticket_template_name ?>
                                    </a>
                                </td>
                                <td><?= $ticket_template_description ?></td>
                                <td><?= $ticket_template_subject ?></td>
                                <td>
                                    <form action="post.php" method="post" autocomplete="off">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="project_template_id" value="<?= $project_template_id ?>">
                                        <input type="hidden" name="ticket_template_id" value="<?= $ticket_template_id ?>">
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
            <h5 class="text-secondary"><i class="fas fa-fw fa-tasks me-2"></i>Project Task Templates</h5>
            <table class="table">
                <?php
                while($row = mysqli_fetch_assoc($sql_task_templates)){
                    $task_template_id = intval($row['task_template_id']);
                    $task_template_name = escapeHtml($row['task_template_name']);
                ?>
                    <tr>
                        <td>
                            <i class="far fa-fw fa-check-square text-primary me-3"></i>
                            <?= $task_template_name ?>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        </div>
        <?php  } ?>
        <!-- End Task TemplatesCard -->

    </div> <!-- End col-3 -->

</div> <!-- End row -->

<script src="../libs/SortableJS/Sortable.min.js"></script>
<script>
new Sortable(document.querySelector('table#ticket_templates tbody'), {
    handle: '.drag-handle',
    animation: 150,
    onEnd: function (evt) {
        const rows = document.querySelectorAll('table#ticket_templates tbody tr');
        const positions = Array.from(rows).map((row, index) => ({
            id: row.dataset.taskId,
            order: index
        }));

        itflowPostForm('/agent/ajax.php', {
            update_project_template_ticket_order: true,
            csrf_token: '<?= $_SESSION['csrf_token'] ?>',
            project_template_id: <?= $project_template_id ?>,
            positions: positions
        });
    }
});
</script>

<?php

}

require_once "../includes/footer.php";

?>

<script src="../js/pretty_content.js"></script>
