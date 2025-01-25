<?php
/*
 * Client Portal
 * Ticket detail page
 */

require_once "includes/inc_all.php";

//Initialize the HTML Purifier to prevent XSS
require "../plugins/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

$allowed_extensions = array('jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx', 'csv', 'xls', 'xlsx', 'xlsm', 'zip', 'tar', 'gz');

if (isset($_GET['id']) && intval($_GET['id'])) {
    $ticket_id = intval($_GET['id']);

    if ($session_contact_primary == 1 || $session_contact_is_technical_contact) {
        // For a primary / technical contact viewing all tickets
        $ticket_sql = mysqli_query($mysqli,
            "SELECT * FROM tickets
            LEFT JOIN users on ticket_assigned_to = user_id
            LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
            WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id"
        );

    } else {
        // For a user viewing their own ticket
        $ticket_sql = mysqli_query($mysqli,
            "SELECT * FROM tickets
            LEFT JOIN users on ticket_assigned_to = user_id
            LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
            WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id AND ticket_contact_id = $session_contact_id"
        );
    }

    $ticket_row = mysqli_fetch_array($ticket_sql);

    if ($ticket_row) {

        $ticket_prefix = nullable_htmlentities($ticket_row['ticket_prefix']);
        $ticket_number = intval($ticket_row['ticket_number']);
        $ticket_status = nullable_htmlentities($ticket_row['ticket_status_name']);
        $ticket_priority = nullable_htmlentities($ticket_row['ticket_priority']);
        $ticket_subject = nullable_htmlentities($ticket_row['ticket_subject']);
        $ticket_details = $purifier->purify($ticket_row['ticket_details']);
        $ticket_assigned_to = nullable_htmlentities($ticket_row['user_name']);
        $ticket_resolved_at = nullable_htmlentities($ticket_row['ticket_resolved_at']);
        $ticket_closed_at = nullable_htmlentities($ticket_row['ticket_closed_at']);
        $ticket_feedback = nullable_htmlentities($ticket_row['ticket_feedback']);

        // Get Ticket Attachments (not associated with a specific reply)
        $sql_ticket_attachments = mysqli_query(
            $mysqli,
            "SELECT * FROM ticket_attachments
            WHERE ticket_attachment_reply_id IS NULL
            AND ticket_attachment_ticket_id = $ticket_id"
        );

        // Get Tasks
        $sql_tasks = mysqli_query( $mysqli, "SELECT * FROM tasks WHERE task_ticket_id = $ticket_id ORDER BY task_order ASC, task_id ASC");
        $task_count = mysqli_num_rows($sql_tasks);

        // Get Completed Task Count
        $sql_tasks_completed = mysqli_query($mysqli,
            "SELECT * FROM tasks
            WHERE task_ticket_id = $ticket_id
            AND task_completed_at IS NOT NULL"
        );
        $completed_task_count = mysqli_num_rows($sql_tasks_completed);

        ?>

        <ol class="breadcrumb d-print-none">
            <li class="breadcrumb-item">
                <a href="index.php">Home</a>
            </li>
            <li class="breadcrumb-item">
                <a href="tickets.php">Tickets</a>
            </li>
            <li class="breadcrumb-item active">Ticket <?php echo $ticket_prefix . $ticket_number; ?></li>
        </ol>

        <div class="card">
            <div class="card-header bg-dark text-center">
                <h4 class="mt-1">
                    Ticket <?php echo $ticket_prefix, $ticket_number ?>
                    <?php
                    if (empty($ticket_resolved_at) && $task_count == $completed_task_count) { ?>
                        <a href="post.php?resolve_ticket=<?php echo $ticket_id; ?>" class="btn btn-sm btn-outline-success float-right text-white confirm-link"><i class="fas fa-fw fa-check text-success"></i> Resolve ticket</a>
                    <?php } ?>
                </h4>
            </div>

            <div class="card-body prettyContent">
                <h5><strong>Subject:</strong> <?php echo $ticket_subject ?></h5>
                <hr>
                <p>
                    <strong>State:</strong> <?php echo $ticket_status ?><br>
                    <strong>Priority:</strong> <?php echo $ticket_priority ?><br>

                    <?php if (empty($ticket_closed_at)) { ?>

                        <?php if ($task_count) { ?>
                            <strong>Tasks: </strong> <?php echo $completed_task_count . " / " .$task_count ?>
                            <br>
                        <?php } ?>

                        <?php if (!empty($ticket_assigned_to)) { ?>
                            <strong>Assigned to: </strong> <?php echo $ticket_assigned_to ?>
                        <?php } ?>

                    <?php } ?>
                </p>
                <?php echo $ticket_details ?>

                <?php
                while ($ticket_attachment = mysqli_fetch_array($sql_ticket_attachments)) {
                    $name = nullable_htmlentities($ticket_attachment['ticket_attachment_name']);
                    $ref_name = nullable_htmlentities($ticket_attachment['ticket_attachment_reference_name']);
                    echo "<hr class=''><i class='fas fa-fw fa-paperclip text-secondary mr-1'></i>$name | <a target='_blank' href='https://$config_base_url/uploads/tickets/$ticket_id/$ref_name'><i class='fas fa-fw fa-external-link-alt mr-1'></i>View</a>";
                }
                ?>
            </div>
        </div>

        <hr>

        <!-- Either show the reply comments box, option to re-open ticket, show ticket smiley feedback or thanks for feedback -->

        <?php if (empty($ticket_resolved_at)) { ?>
            <!-- Reply -->

            <form action="post.php" enctype="multipart/form-data" method="post">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket_id ?>">
                <div class="form-group">
                    <textarea class="form-control tinymce" name="comment" placeholder="Add comments.."></textarea>
                </div>
                <div class="form-group">
                    <input type="file" class="form-control-file" name="file[]" multiple id="fileInput" accept=".jpg, .jpeg, .gif, .png, .webp, .pdf, .txt, .md, .doc, .docx, .odt, .csv, .xls, .xlsx, .ods, .pptx, .odp, .zip, .tar, .gz, .xml, .msg, .json, .wav, .mp3, .ogg, .mov, .mp4, .av1, .ovpn">
                </div>
                <button type="submit" class="btn btn-primary" name="add_ticket_comment">Reply</button>
            </form>

        <?php } elseif (empty($ticket_closed_at)) { ?>
            <!-- Re-open -->

            <h4>Your ticket has been resolved</h4>

            <div class="col-6">
                <div class="row">
                    <div class="col">
                        <a href="post.php?reopen_ticket=<?php echo $ticket_id; ?>" class="btn btn-secondary btn-lg"><i class="fas fa-fw fa-redo text-white"></i> Reopen ticket</a>
                    </div>

                    <div class="col">
                        <a href="post.php?close_ticket=<?php echo $ticket_id; ?>" class="btn btn-success btn-lg confirm-link"><i class="fas fa-fw fa-gavel text-white"></i> Close ticket</a>
                    </div>
                </div>
            </div>
            <br>

        <?php } elseif (empty($ticket_feedback)) { ?>

            <h4>Ticket closed. Please rate your ticket</h4>

            <form action="post.php" method="post">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket_id ?>">

                <button type="submit" class="btn btn-primary btn-lg" name="add_ticket_feedback" value="Good" onclick="this.form.submit()">
                    <span class="fa fa-smile" aria-hidden="true"></span> Good
                </button>

                <button type="submit" class="btn btn-danger btn-lg" name="add_ticket_feedback" value="Bad" onclick="this.form.submit()">
                    <span class="fa fa-frown" aria-hidden="true"></span> Bad
                </button>
            </form>

        <?php } else { ?>

            <h4>Rated <?php echo $ticket_feedback ?> -- Thanks for your feedback!</h4>

        <?php } ?>

        <!-- End comments/reopen/feedback -->

        <hr>
        <br>

        <?php
        $sql = mysqli_query($mysqli, "SELECT * FROM ticket_replies LEFT JOIN users ON ticket_reply_by = user_id LEFT JOIN contacts ON ticket_reply_by = contact_id WHERE ticket_reply_ticket_id = $ticket_id AND ticket_reply_archived_at IS NULL AND ticket_reply_type != 'Internal' ORDER BY ticket_reply_id DESC");

        while ($row = mysqli_fetch_array($sql)) {
            $ticket_reply_id = intval($row['ticket_reply_id']);
            $ticket_reply = $purifier->purify($row['ticket_reply']);
            $ticket_reply_created_at = nullable_htmlentities($row['ticket_reply_created_at']);
            $ticket_reply_updated_at = nullable_htmlentities($row['ticket_reply_updated_at']);
            $ticket_reply_by = intval($row['ticket_reply_by']);
            $ticket_reply_type = $row['ticket_reply_type'];

            if ($ticket_reply_type == "Client") {
                $ticket_reply_by_display = nullable_htmlentities($row['contact_name']);
                $user_initials = initials($row['contact_name']);
                $user_avatar = $row['contact_photo'];
                $avatar_link = "../uploads/clients/$session_client_id/$user_avatar";
            } else {
                $ticket_reply_by_display = nullable_htmlentities($row['user_name']);
                $user_id = intval($row['user_id']);
                $user_avatar = $row['user_avatar'];
                $user_initials = initials($row['user_name']);
                $avatar_link = "../uploads/users/$user_id/$user_avatar";
            }

            // Get attachments for this reply
            $sql_ticket_reply_attachments = mysqli_query(
                $mysqli,
                "SELECT * FROM ticket_attachments
                        WHERE ticket_attachment_reply_id = $ticket_reply_id
                        AND ticket_attachment_ticket_id = $ticket_id"
            );
            ?>

            <div class="card card-outline <?php if ($ticket_reply_type == 'Client') { echo "card-warning"; } else { echo "card-info"; } ?> mb-3">
                <div class="card-header">
                    <h3 class="card-title">
                        <div class="media">
                            <?php
                            if (!empty($user_avatar)) {
                                ?>
                                <img src="<?php echo $avatar_link ?>" alt="User Avatar" class="img-size-50 mr-3 img-circle">
                                <?php
                            } else {
                                ?>
                                <span class="fa-stack fa-2x">
                                    <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                                    <span class="fa fa-stack-1x text-white"><?php echo $user_initials; ?></span>
                                </span>
                                <?php
                            }
                            ?>

                            <div class="media-body">
                                <?php echo $ticket_reply_by_display; ?>
                                <br>
                                <small class="text-muted"><?php echo $ticket_reply_created_at; ?> <?php if (!empty($ticket_reply_updated_at)) { echo "(edited: $ticket_reply_updated_at)"; } ?></small>
                            </div>
                        </div>
                    </h3>
                </div>

                <div class="card-body prettyContent">
                    <?php echo $ticket_reply; ?>

                    <?php
                    while ($ticket_attachment = mysqli_fetch_array($sql_ticket_reply_attachments)) {
                        $name = nullable_htmlentities($ticket_attachment['ticket_attachment_name']);
                        $ref_name = nullable_htmlentities($ticket_attachment['ticket_attachment_reference_name']);
                        echo "<hr><i class='fas fa-fw fa-paperclip text-secondary mr-1'></i>$name | <a target='_blank' href='https://$config_base_url/uploads/tickets/$ticket_id/$ref_name'><i class='fas fa-fw fa-external-link-alt mr-1'></i>View</a>";
                    }
                    ?>
                </div>
            </div>

            <?php

        }

        ?>

        <script src="../js/pretty_content.js"></script>

        <?php
    } else {
        echo "Ticket ID not found!";
    }

} else {
    header("Location: index.php");
}

require_once "includes/footer.php";


