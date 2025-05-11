<?php

require_once "../includes/ajax_header.php";

$sql = mysqli_query(
    $mysqli,
    "SELECT * FROM notifications
    WHERE notification_user_id = $session_user_id
    AND notification_dismissed_at IS NULL
    ORDER BY notification_id DESC"
);

$num_notifications = mysqli_num_rows($sql);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title"><i class='fas fa-bell mr-2'></i>Notifications<span class='badge badge-secondary badge-pill px-3 ml-3'><?php echo $num_notifications; ?><span></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<div class="modal-body bg-white">
    <table class="table table-sm table-hover table-borderless">
    <?php if ($num_notifications) { ?>

        <?php while ($row = mysqli_fetch_array($sql)) {

            $notification_id = intval($row["notification_id"]);
            $notification_type = nullable_htmlentities($row["notification_type"]);
            $notification_details = nullable_htmlentities($row["notification"]);
            $notification_action = nullable_htmlentities(
                $row["notification_action"]
            );
            $notification_timestamp_formated = date(
                "M d g:ia",
                strtotime($row["notification_timestamp"])
            );
            $notification_client_id = intval($row["notification_client_id"]);
            if (empty($notification_action)) {
                $notification_action = "#";
            }
            ?>
        
        <tr class="notification-item">
            <th>
                <a class="text-dark" href="<?php echo $notification_action; ?>">
                    <i class="fas fa-bullhorn mr-2"></i><?php echo $notification_type; ?>
                    <small class="text-muted float-right">
                        <?php echo $notification_timestamp_formated; ?>
                    </small>
                    <br>
                    <small class="text-secondary text-wrap"><?php echo $notification_details; ?></small>
                 </a>
            </th>
        </tr>

        <?php
        }
        ?>
        </table>
        <div class="text-center mt-2">
            <button id="prev-btn" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-caret-left"></i></button>
            <button id="next-btn" class="btn btn-sm btn-outline-secondary"><i class="fas fa-caret-right"></i></button>
        </div>
    <?php } else { ?>
    <div class="text-center text-secondary py-5">
        <i class='far fa-6x fa-bell-slash'></i>
        <h3 class="mt-3">No Notifications</h3>
    </div>
    <?php } ?>
</div>
<div class="modal-footer bg-white justify-content-end">
    <?php if ($num_notifications) { ?>
    
    <a href="post.php?dismiss_all_notifications&csrf_token=<?php echo $_SESSION[
        "csrf_token"
    ]; ?>" class="btn btn-primary">
        <span class="text-white text-bold"><i class="fas fa-check mr-2"></i>Dismiss all</span>
    </a>
    <a href="notifications.php" class="btn btn-secondary">
        <span class="text-white">See all Notifications</span>
    </a>
    <?php } else { ?>
    <a href="notifications.php?dismissed" class="btn btn-dark">
        <span class="text-white text-bold">See Dismissed Notifications</span>
    </a>
    <?php } ?>
    <button type="button" class="btn btn-light" data-dismiss="modal">
        <i class="fas fa-times mr-2"></i>Close
    </button>
</div>

<script>
$(document).ready(function () {
    var perPage = 5;
    var $items = $(".notification-item");
    var totalItems = $items.length;
    var totalPages = Math.ceil(totalItems / perPage);
    var currentPage = 0;

    function showPage(page) {
        $items.hide().slice(page * perPage, (page + 1) * perPage).show();
        $("#prev-btn").prop("disabled", page === 0);
        $("#next-btn").prop("disabled", page >= totalPages - 1);
        $("#page-indicator").text(`Page ${page + 1} of ${totalPages} (${totalItems} total)`);
    }

    $("#prev-btn").on("click", function () {
        if (currentPage > 0) {
            currentPage--;
            showPage(currentPage);
        }
    });

    $("#next-btn").on("click", function () {
        if (currentPage < totalPages - 1) {
            currentPage++;
            showPage(currentPage);
        }
    });

    if (totalItems <= perPage) {
        $("#prev-btn, #next-btn, #page-indicator").hide();
    }

    showPage(currentPage);
});
</script>

<?php require_once "../includes/ajax_footer.php";
