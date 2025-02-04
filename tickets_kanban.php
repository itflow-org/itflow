<link rel="stylesheet" href="/plugins/dragula/dragula.min.css">
<link rel="stylesheet" href="/css/tickets_kanban.css">

<?php
$kanban = [];


// Fetch all statuses
$status_sql = mysqli_query($mysqli, "SELECT * FROM ticket_statuses where ticket_status_active = 1 AND  ticket_status_id != 5 ORDER BY ticket_status_order");
$statuses = [];
while ($status_row = mysqli_fetch_array($status_sql)) {
    $id = $status_row['ticket_status_id'];
    $name = $status_row['ticket_status_name'];
    $kanban_order = $status_row['ticket_status_order'];

    $statuses[$id] = new stdClass();
    $statuses[$id]->id = $id;
    $statuses[$id]->name = $name;
    $statuses[$id]->tickets = [];
    $statuses[$id]->order = $kanban_order; // Store the order
}

// Fetch tickets and merge into statuses
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
    WHERE $ticket_status_snippet " . $ticket_assigned_query . "
    $category_snippet
    AND DATE(ticket_created_at) BETWEEN '$dtf' AND '$dtt'
    AND (CONCAT(ticket_prefix,ticket_number) LIKE '%$q%' OR client_name LIKE '%$q%' OR ticket_subject LIKE '%$q%' OR ticket_status_name LIKE '%$q%' OR ticket_priority LIKE '%$q%' OR user_name LIKE '%$q%' OR contact_name LIKE '%$q%' OR asset_name LIKE '%$q%' OR vendor_name LIKE '%$q%' OR ticket_vendor_ticket_number LIKE '%q%')
    $ticket_permission_snippet
    ORDER BY $sort $order"
);

while ($row = mysqli_fetch_array($sql)) {
    $id = $row['ticket_status_id'];
    $ticket_order = $row['ticket_kanban'];
    $row['ticket_order'] = $ticket_order; // Store the ticket order

    if (isset($statuses[$id])) {
        $statuses[$id]->tickets[] = $row;
    }
}

// Convert associative array to indexed array for sorting
$kanban_array = array_values($statuses);

// Sort the array by the 'order' field, moving null values to the end
usort($kanban_array, function($a, $b) {
    if ($a->order === null) {
        return 1;
    }
    if ($b->order === null) {
        return -1;
    }
    return $a->order - $b->order;
});

// Sort tickets within each column by 'ticket_kanban'
foreach ($kanban_array as $kanban_column) {
    usort($kanban_column->tickets, function($a, $b) {
        return $a['ticket_order'] - $b['ticket_order'];
    });
}

// Re-index the sorted array back to associative array if needed
$ordered_kanban = [];
foreach ($kanban_array as $item) {
    $ordered_kanban[$item->id] = $item;
}

$kanban = $ordered_kanban;






?>

<div class="container-fluid" id="kanban-board">
    <?php
    foreach($kanban as $kanban_column){
        
    ?>
    <div class="kanban-column card card-dark" data-status-id="<?=htmlspecialchars($kanban_column->id); ?>">
        <h6 class="panel-title"><?=htmlspecialchars($kanban_column->name); ?></h6>
        <div id="status" data-column-name="<?=$kanban_column->name?>" data-status-id="<?=htmlspecialchars($kanban_column->id); ?>" style="height: 100%;" >
            <?php
            foreach($kanban_column->tickets as $item){
                if ($item['ticket_priority'] == "High") {
                    $ticket_priority_color = "danger";
                } elseif ($item['ticket_priority'] == "Medium") {
                    $ticket_priority_color = "warning";
                } else {
                    $ticket_priority_color = "info";
                }
            ?>
            
            <div
                class="task"
                data-ticket-id= "<?=$item['ticket_id']?>"
                data-ticket-status-id= "<?=$item['ticket_status_id']?>"
                ondblclick="window.location.href='ticket.php?ticket_id=<?php echo $item['ticket_id']; ?>'"
                style="cursor: grabbing;"
            >
                <span class='badge badge-<?php echo $ticket_priority_color; ?>'>
                    <?php echo $item['ticket_priority']; ?>
                </span>
                <span class='badge badge-secondary'>
                    <?php echo $item['category_name']; ?>
                </span>
                <br>
                
                <b>
                    <?php
                        if ($item['contact_name'] != ""){
                            echo $item['client_name'] . ' - ' . $item['contact_name'];
                        } else {
                            echo $item['client_name'];
                        }
                    ?>
                </b>
                <br>
                <?php if ($item['asset_name'] != "") {?>
                <i class="fa fa-fw fa-desktop text-secondary mr-2"></i><?=$item['asset_name']?>
                <br>
                <?php } ?>
                <i class="fa fa-fw fa fa-life-ring text-secondary mr-2"></i><?=$item['ticket_subject']?>
                <br>
                <i class="fas fa-fw fa-user mr-2 text-secondary"></i><?=$item['user_name']?>
                <br>
                <?php if ($item['ticket_schedule'] != "") {?>
                <i class="fa fa-fw fa-calendar-check text-secondary mr-2"></i><span class="badge badge-warning"><?=$item['ticket_schedule']?></span>
                <?php } ?>
            </div>
            
            <?php
            }
            ?>
        </div>
    </div>
    <?php
    }
    ?>
</div>


    
<script src="/plugins/dragula/dragula.min.js"></script>
<script src="/js/tickets_kanban.js"></script>