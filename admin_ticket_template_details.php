<?php

require_once "inc_all_admin.php";


//Initialize the HTML Purifier to prevent XSS
require "plugins/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
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
$sql_task_templates = mysqli_query($mysqli, "SELECT * FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id");

?>

<ol class="breadcrumb d-print-none">
  <li class="breadcrumb-item">
    <a href="clients.php">Home</a>
  </li>
  <li class="breadcrumb-item">
    <a href="admin_users.php">Admin</a>
  </li>
  <li class="breadcrumb-item">
    <a href="admin_ticket_templates.php">Ticket Templates</a>
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
          <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#editTicketTemplateModal<?php echo $template_id; ?>">
            <i class="fas fa-edit"></i>
          </button>
        </div>
      </div>
      <h5><?php echo $ticket_subject; ?></h5>
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
                  <input type="text" class="form-control" name="task_name" placeholder="Task name">
                  <div class="input-group-append">
                      <button type="submit" name="add_ticket_template_task" class="btn btn-primary"><i class="fas fa-fw fa-check mr-2"></i>Create</button>
                  </div>
              </div>
          </div>
        </form>
        <table class="table table-striped table-sm">
          <?php
          while($row = mysqli_fetch_array($sql_task_templates)){
            $task_template_id = intval($row['task_template_id']);
            $task_template_name = nullable_htmlentities($row['task_template_name']);
            $task_template_description = nullable_htmlentities($row['task_template_description']);
          ?>
          <tr>
            <td><i class="far fa-fw fa-square text-secondary"></i></td>
            <td><?php echo $task_template_name; ?></td>
            <td class="text-right">
              <a href="post.php?delete_task_template=<?php echo $task_template_id; ?>" class="btn btn-link btn-sm text-secondary">
                <i class="fa fa-fw fa-trash-alt"></i>
              </a>
            </td>
          </tr>
          <?php } ?>
        </table>
      </div>
    </div>

  </div>

</div>

<script src="js/pretty_content.js"></script>

<?php

require_once "admin_ticket_template_edit_modal.php";
require_once "footer.php";
