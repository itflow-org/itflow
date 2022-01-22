<?php
//include("config.php");
include("header.php");

//Paging
if(isset($_GET['p'])){
    $p = intval($_GET['p']);
    $record_from = (($p)-1)*$_SESSION['records_per_page'];
    $record_to = $_SESSION['records_per_page'];
}else{
    $record_from = 0;
    $record_to = $_SESSION['records_per_page'];
    $p = 1;
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS * FROM scheduled_tickets LEFT JOIN clients on scheduled_ticket_client_id = client_id");
$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-sync"></i> Scheduled Tickets</h3>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-borderless table-hover">
                <thead class="<?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
                    <tr>
                        <th><a class="text-dark">Client</a></th>
                        <th><a class="text-dark">Subject</a></th>
                        <th><a class="text-dark">Frequency</a></th>
                        <th><a class="text-dark">Priority</a></th>

                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php

                while($row = mysqli_fetch_array($sql)){
                    $scheduled_ticket_id = $row['scheduled_ticket_id'];
                    $scheduled_ticket_category = $row['scheduled_ticket_category'];
                    $scheduled_ticket_subject = $row['scheduled_ticket_subject'];
                    $scheduled_ticket_details = $row['scheduled_ticket_details'];
                    $scheduled_ticket_priority = $row['scheduled_ticket_priority'];
                    $scheduled_ticket_frequency = $row['scheduled_ticket_frequency'];
                    $scheduled_ticket_start_date = $row['scheduled_ticket_start_date'];
                    $scheduled_ticket_next_run = $row['scheduled_ticket_next_run'];
                    $scheduled_ticket_client_name = $row['client_name'];
                    $scheduled_ticket_contact_id = $row['scheduled_ticket_contact_id'];
                    $scheduled_ticket_asset_id = $row['scheduled_ticket_asset_id'];
                ?>

                <tr>
                    <td><a> <?php echo $scheduled_ticket_client_name ?></a></td>
                    <td><a> <?php echo $scheduled_ticket_subject ?></a></td>
                    <td><a> <?php echo $scheduled_ticket_frequency ?></a></td>
                    <td><a> <?php echo $scheduled_ticket_priority ?></a></td>

                    <td>
                        <div class="dropdown dropleft text-center">
                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <div class="dropdown-menu">
                                <!--<a class="dropdown-item" href="#" data-toggle="modal" data-target="#editScheduledTicketModal<?php echo $scheduled_ticket_id; ?>">Edit</a>-->
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="post.php?delete_scheduled_ticket=<?php echo $scheduled_ticket_id; ?>">Delete</a>
                            </div>
                        </div>
                    </td>
                </tr>

                <?php
                }
                ?>

                </tbody>
            </table>
        </div>
    </div>
</div>


<?php

//include(".php");
include("footer.php");