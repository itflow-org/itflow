<link rel="stylesheet" href="css/ticket_kanban.css">

<?php
isset($mysqli) || die("Direct file access is not allowed");

// Fetch statuses
$status_sql = mysqli_query($mysqli, "SELECT * FROM ticket_statuses WHERE ticket_status_active = 1 AND ticket_status_id != 5 ORDER BY ticket_status_order");

$statuses = array();

while ($row = mysqli_fetch_assoc($status_sql)) {

    $status_id = intval($row['ticket_status_id']);
    $status_name = escapeHtml($row['ticket_status_name']);
    $kanban_order = intval($row['ticket_status_order']);

    $statuses[$status_id] = array(
        'id'      => $status_id,
        'name'    => $status_name,
        'order'   => $kanban_order,
        'tickets' => array()
    );
}

$ordering_snippet = "ORDER BY
    CASE
        WHEN ticket_priority = 'High' THEN 1
        WHEN ticket_priority = 'Medium' THEN 2
        WHEN ticket_priority = 'Low' THEN 3
        ELSE 4
    END,
    ticket_id DESC";

if ($config_ticket_ordering == 1) {
    $ordering_snippet = "ORDER BY ticket_order ASC";
}

// Fetch tickets
$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM tickets
    LEFT JOIN clients ON ticket_client_id = client_id
    LEFT JOIN contacts ON ticket_contact_id = contact_id
    LEFT JOIN users ON ticket_assigned_to = user_id
    LEFT JOIN assets ON ticket_asset_id = asset_id
    LEFT JOIN locations ON ticket_location_id = location_id
    LEFT JOIN vendors ON ticket_vendor_id = vendor_id
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    LEFT JOIN categories ON ticket_category = category_id
    WHERE $ticket_status_snippet $ticket_assigned_query
    $category_query
    $client_query
    AND DATE(ticket_created_at) BETWEEN '$dtf' AND '$dtt'
    AND (
        CONCAT(ticket_prefix,ticket_number) LIKE '%$q%' OR
        client_name LIKE '%$q%' OR
        ticket_subject LIKE '%$q%' OR
        ticket_status_name LIKE '%$q%' OR
        ticket_priority LIKE '%$q%' OR
        user_name LIKE '%$q%' OR
        contact_name LIKE '%$q%' OR
        asset_name LIKE '%$q%' OR
        vendor_name LIKE '%$q%' OR
        ticket_vendor_ticket_number LIKE '%$q%'
    )
    $ticket_project_snippet
    $access_permission_query_overide
    $ordering_snippet"
);

while ($row = mysqli_fetch_assoc($sql)) {

    $status_id = $row['ticket_status_id'];

    foreach ($row as $key => $value) {
        if (is_string($value)) {
            $row[$key] = escapeHtml($value);
        }
    }

    if (isset($statuses[$status_id])) {
        $statuses[$status_id]['tickets'][] = $row;
    }
}

// Convert associative array to indexed array
$kanban = array_values($statuses);
?>

<div class="container-fluid" id="kanban-board">

    <?php foreach ($kanban as $column) { ?>

        <div class="kanban-column card card-dark" data-status-id="<?php echo $column['id']; ?>">
            <h6 class="panel-title"><?php echo $column['name']; ?></h6>

            <div class="kanban-status" data-column-name="<?php echo $column['name']; ?>" data-status-id="<?php echo $column['id']; ?>">

                <?php foreach ($column['tickets'] as $item) {

                    if ($item['ticket_priority'] == "High") {
                        $ticket_priority_color = "danger";
                    } elseif ($item['ticket_priority'] == "Medium") {
                        $ticket_priority_color = "warning";
                    } else {
                        $ticket_priority_color = "info";
                    }
                    ?>

                    <div class="task grab-cursor"
                         data-ticket-id="<?php echo $item['ticket_id']; ?>"
                         data-ticket-status-id="<?php echo $item['ticket_status_id']; ?>"
                         ondblclick="window.location.href='ticket.php?ticket_id=<?php echo $item['ticket_id']; ?>'">

                <span class="badge badge-<?php echo $ticket_priority_color; ?>">
                    <?php echo $item['ticket_priority']; ?>
                </span>

                        <span class="badge badge-secondary">
                    <?php echo $item['category_name']; ?>
                </span>

                        <div class="btn btn-light drag-handle-class" style="display:none;">
                            <i class="drag-handle-class fas fa-bars"></i>
                        </div>

                        <br>

                        <b>
                            <?php
                            if (!$client_url) {
                                if ($item['contact_name'] != "") {
                                    echo $item['client_name'] . ' - ' . $item['contact_name'];
                                } else {
                                    echo $item['client_name'];
                                }
                            } else {
                                echo $item['contact_name'];
                            }
                            ?>
                        </b>

                        <br>

                        <?php if ($item['asset_name'] != "") { ?>
                            <i class="fa fa-fw fa-desktop text-secondary mr-2"></i><?php echo $item['asset_name']; ?><br>
                        <?php } ?>

                        <i class="fa fa-fw fa-life-ring text-secondary mr-2"></i><?php echo $item['ticket_subject']; ?><br>

                        <i class="fas fa-fw fa-user mr-2 text-secondary"></i><?php echo $item['user_name']; ?><br>

                        <?php if ($item['ticket_schedule'] != "") { ?>
                            <i class="fa fa-fw fa-calendar-check text-secondary mr-2"></i>
                            <span class="badge badge-warning"><?php echo $item['ticket_schedule']; ?></span>
                        <?php } ?>

                    </div>

                <?php } ?>

            </div>
        </div>

    <?php } ?>

</div>

<?php
echo "<script>";
echo "const CONFIG_TICKET_MOVING_COLUMNS = " . json_encode($config_ticket_moving_columns) . ";";
echo "const CONFIG_TICKET_ORDERING = " . json_encode($config_ticket_ordering) . ";";
echo "</script>";
?>

<script src="../libs/SortableJS/Sortable.min.js"></script>
<script src="js/tickets_kanban.js"></script>
