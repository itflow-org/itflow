<?php
/*
 * Client Portal
 * Ticket detail page
 */

require_once "inc_portal.php";


//Initialize the HTML Purifier to prevent XSS
require "../plugins/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

if (isset($_GET['id']) && intval($_GET['id'])) {
    $ticket_id = intval($_GET['id']);

    if ($session_contact_primary == 1 || $session_contact_is_technical_contact) {
        $ticket_sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id");
    } else {
        $ticket_sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id AND ticket_contact_id = $session_contact_id");
    }

    $ticket_row = mysqli_fetch_array($ticket_sql);

    if ($ticket_row) {

        $ticket_prefix = nullable_htmlentities($ticket_row['ticket_prefix']);
        $ticket_number = intval($ticket_row['ticket_number']);
        $ticket_status = nullable_htmlentities($ticket_row['ticket_status']);
        $ticket_priority = nullable_htmlentities($ticket_row['ticket_priority']);
        $ticket_subject = nullable_htmlentities($ticket_row['ticket_subject']);
        $ticket_details = $purifier->purify($ticket_row['ticket_details']);
        $ticket_feedback = nullable_htmlentities($ticket_row['ticket_feedback']);

        ?>

        <ol class="breadcrumb d-print-none">
            <li class="breadcrumb-item">
                <a href="index.php">Home</a>
            </li>
            <li class="breadcrumb-item">
                <a href="tickets.php">Tickets</a>
            </li>
            <li class="breadcrumb-item active">Ticket <?php echo $ticket_number; ?></li>
        </ol>

        <div class="card">
            <div class="card-header bg-dark text-center">
                <h4 class="mt-1">
                    Ticket <?php echo $ticket_prefix, $ticket_number ?>
                    <?php
                    if ($ticket_status !== "Closed") { ?>
                        <a href="portal_post.php?close_ticket=<?php echo $ticket_id; ?>" class="btn btn-sm btn-outline-success float-right text-white"><i class="fas fa-fw fa-check text-success"></i> Close ticket</a>
                    <?php } ?>
                </h4>
            </div>

            <div class="card-body prettyContent">
                <h5><strong>Subject:</strong> <?php echo $ticket_subject ?></h5>
                <hr>
                <p>
                    <strong>State:</strong> <?php echo $ticket_status ?>
                    <br>
                    <strong>Priority:</strong> <?php echo $ticket_priority ?>
                </p>
                <?php echo $ticket_details ?>
            </div>
        </div>


        <!-- Either show the reply comments box, ticket smiley feedback, or thanks for feedback -->

        <?php if ($ticket_status !== "Closed") { ?>

            <form action="portal_post.php" method="post">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket_id ?>">
                <div class="form-group">
                    <textarea class="form-control tinymce" name="comment" placeholder="Add comments.."></textarea>
                </div>
                <button type="submit" class="btn btn-primary" name="add_ticket_comment">Reply</button>
            </form>

        <?php } elseif (empty($ticket_feedback)) { ?>

            <h4>Rate your ticket</h4>

            <form action="portal_post.php" method="post">
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

        <!-- End comments/feedback -->

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

require_once "portal_footer.php";


