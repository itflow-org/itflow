<?php

// Default Column Sortby Filter
$sort = "ticket_template_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";


//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM ticket_templates
    WHERE (ticket_template_name LIKE '%$q%' OR ticket_template_description LIKE '%$q%')
    AND ticket_template_archived_at IS NULL
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-life-ring mr-2"></i>Ticket Templates</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTicketTemplateModal"><i class="fas fa-plus mr-2"></i>New Ticket Template</button>
        </div>
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if(isset($q)){ echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Ticket Templates">
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
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_template_name&order=<?php echo $disp; ?>">
                            Template <?php if ($sort == 'ticket_template_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>Tasks</th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while($row = mysqli_fetch_array($sql)){
                    $ticket_template_id = intval($row['ticket_template_id']);
                    $ticket_template_name = nullable_htmlentities($row['ticket_template_name']);
                    $ticket_template_description = nullable_htmlentities($row['ticket_template_description']);
                    $ticket_template_subject = nullable_htmlentities($row['ticket_template_subject']);
                    $ticket_template_created_at = nullable_htmlentities($row['ticket_template_created_at']);

                    ?>
                    <tr>
                        <td>
                            <a class="text-dark">
                                <div class="media">
                                    <i class="fa fa-fw fa-2x fa-life-ring mr-3"></i>
                                    <div class="media-body">
                                        <div>
                                            <a href="admin_ticket_template_details.php?ticket_template_id=<?php echo $ticket_template_id; ?>">
                                                <?php echo $ticket_template_name; ?>
                                            </a>
                                        </div>
                                        <div><small class="text-secondary"><?php echo $ticket_template_description; ?></small></div>
                                    </div>
                                </div>
                            </a>
                        </td>
                        <td>0</td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_ticket_template=<?php echo $ticket_template_id; ?>">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>

                <?php } ?>

                </tbody>
            </table>
        </div>
        <?php require_once "includes/filter_footer.php";
 ?>
    </div>
</div>

<?php
require_once "admin_ticket_template_add_modal.php";
require_once "includes/footer.php";

