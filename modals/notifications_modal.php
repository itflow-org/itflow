<!-- Notifications Modal -->
<div class="modal" id="notificationsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <?php if ($num_notifications) { ?>
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class='fas fa-bell mr-2'></i>Notifications<span class="badge badge-secondary badge-pill px-3 ml-3"><?php echo $num_notifications; ?><span> 
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <?php } ?>
            <div class="modal-body">
                <?php if ($num_notifications) { ?>

                <?php

			    while ($row = mysqli_fetch_array($sql_notifications)) {
			        $notification_id = intval($row['notification_id']);
			        $notification_type = nullable_htmlentities($row['notification_type']);
			        $notification_details = nullable_htmlentities($row['notification']);
			        $notification_action = nullable_htmlentities($row['notification_action']);
			        $notification_created_at_formated = date('M d g:ia',strtotime($row['notification_created_at']));
                    $notification_client_id = intval($row['notification_client_id']);
			        if(empty($notification_action)) { $notification_action = "#"; }
				?>

               
                <a class="text-dark dropdown-item px-1" href="<?php echo $notification_action; ?>">
                    <div>
                        <span class="text-bold">
                        	<i class="fas fa-bullhorn mr-2"></i><?php echo $notification_type; ?>
                        </span>
                        <small class="text-muted float-right">
                        	<?php echo $notification_created_at_formated; ?>
                        </small>
                    </div>
                    <small class="text-secondary"><?php echo $notification_details; ?></small>
                </a>
            
            	<?php 
        		}

                } else {
                ?>
                <div class="text-center text-secondary py-5">
                    <i class='far fa-6x fa-bell-slash'></i>
                    <h3 class="mt-3">No Notifications</h3>
                </div>
                <?php } ?>
            </div>
            <div class="modal-footer justify-content-end">
            	<?php if ($num_notifications) { ?>
                <a href="post.php?dismiss_all_notifications&csrf_token=<?php echo $_SESSION['csrf_token'] ?>" class="btn btn-dark">
            		<i class="fas fa-check mr-2"></i>Dismiss all
                </a>
                <?php } else { ?>
                <a href="notifications_dismissed.php" class="btn btn-dark">
                    See Dismissed Notifications
                </a>
                <?php } ?>
                <button type="button" class="btn btn-light" data-dismiss="modal">
                    <i class="fas fa-times mr-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>