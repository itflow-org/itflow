<?php

require_once "includes/inc_all_admin.php";


//Initialize the HTML Purifier to prevent XSS
require "plugins/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

if (isset($_GET['ticket_template_id'])) {
    $ticket_template_id = intval($_GET['ticket_template_id']);
}

$sql_ticket_templates = mysqli_query($mysqli, "SELECT * FROM ticket_templates WHERE ticket_template_id = $ticket_template_id");

$row = mysqli_fetch_array($sql_ticket_templates);

$ticket_template_name = nullable_htmlentities($row['ticket_template_name']);
$ticket_template_description = nullable_htmlentities($row['ticket_template_description']);
$ticket_template_subject = nullable_htmlentities($row['ticket_template_subject']);
$ticket_template_details = $purifier->purify($row['ticket_template_details']);
$ticket_template_created_at = nullable_htmlentities($row['ticket_template_created_at']);
$ticket_template_updated_at = nullable_htmlentities($row['ticket_template_updated_at']);

// Get Task Templates
$sql_task_templates = mysqli_query($mysqli, "SELECT * FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id ORDER BY task_template_order ASC, task_template_id ASC");

?>
<link rel="stylesheet" href="/plugins/dragula/dragula.min.css">

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="clients.php">Home</a>
        </li>
        <li class="breadcrumb-item">
            <a href="admin_user.php">Admin</a>
        </li>
        <li class="breadcrumb-item">
            <a href="admin_ticket_template.php">Ticket Templates</a>
        </li>
        <li class="breadcrumb-item active"><i class="fas fa-life-ring mr-2"></i><?php echo $ticket_template_name; ?></li>
    </ol>

    <div class="row">
        <div class="col-8">

            <div class="card card-dark">
                <div class="card-header">
                    <h3 class="card-title mt-2">
                        <div class="media">
                            <i class="fa fa-fw fa-2x fa-life-ring mr-3"></i>
                            <div class="media-body">
                                <h3 class="mb-0"><?php echo $ticket_template_name; ?></h3>
                                <div><small class="text-secondary"><?php echo $ticket_template_description; ?></small></div>
                            </div>
                        </div>
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#editTicketTemplateModal">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
                <h5><?php echo $ticket_template_subject; ?></h5>
                <div class="card-body prettyContent">
                    <?php echo $ticket_template_details; ?>
                </div>
            </div>

        </div>

        <div class="col-4">

            <div class="card card-dark">
                <div class="card-header">
                    <h5 class="card-title"><i class="fa fa-fw fa-tasks mr-2"></i>Tasks</h5>
                </div>
                <div class="card-body">
                    <form action="post.php" method="post" autocomplete="off">
                        <input type="hidden" name="ticket_template_id" value="<?php echo $ticket_template_id; ?>">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-tasks"></i></span>
                                </div>
                                <input type="text" class="form-control" name="task_name" placeholder="Create a task" required>
                                <div class="input-group-append">
                                    <button type="submit" name="add_ticket_template_task" class="btn btn-primary"><i class="fas fa-fw fa-check"></i></button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <table class="table table-striped table-sm">
                        <?php
                        while($row = mysqli_fetch_array($sql_task_templates)){
                            $task_id = intval($row['task_template_id']);
                            $task_name = nullable_htmlentities($row['task_template_name']);
                            $task_completion_estimate = intval($row['task_template_completion_estimate']);
                            $task_description = nullable_htmlentities($row['task_template_description']);
                            ?>
                            <tr data-task-id="<?php echo $task_id; ?>">
                                <td><i class="far fa-fw fa-square text-secondary"></i></td>
                                <td>
                                    <a href="#" class="grab-cursor">
                                        <span class="text-secondary"><?php echo $task_completion_estimate; ?>m</span>
                                        <span class="text-dark"> - <?php echo $task_name; ?></span>
                                    </a>
                                </td>
                                <td class="text-right">
                                    <div class="float-right">
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-link text-secondary btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fas fa-fw fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#"
                                                    data-toggle = "ajax-modal"
                                                    data-ajax-url = "ajax/ajax_ticket_template_task_edit.php"
                                                    data-ajax-id = "<?php echo $task_id; ?>"
                                                    >
                                                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_task_template=<?php echo $task_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                                    <i class="fas fa-fw fa-trash-alt mr-2"></i>Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </table>
                </div>
            </div>

        </div>

    </div>

    <script src="js/pretty_content.js"></script>
    <script src="plugins/dragula/dragula.min.js"></script>
    <script>
    $(document).ready(function() {
        var container = $('.table tbody')[0];

        dragula([container])
            .on('drop', function (el, target, source, sibling) {
                // Handle the drop event to update the order in the database
                var rows = $(container).children();
                var positions = rows.map(function(index, row) {
                    return {
                        id: $(row).data('taskId'),
                        order: index
                    };
                }).get();

                // Send the new order to the server
                $.ajax({
                    url: 'ajax.php',
                    method: 'POST',
                    data: {
                        update_task_templates_order: true, // Adjust the parameter name if needed
                        ticket_template_id: <?php echo $ticket_template_id; ?>,
                        positions: positions
                    },
                    success: function(data) {
                        // Handle success
                    },
                    error: function(error) {
                        console.error('Error updating order:', error);
                    }
                });
            });
    });
    </script>

<?php

require_once "modals/admin_ticket_template_edit_modal.php";
require_once "includes/footer.php";
