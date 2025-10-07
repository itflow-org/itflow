<?php

require_once "includes/guest_header.php";

//Initialize the HTML Purifier to prevent XSS
require "../plugins/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

if (!isset($_GET['ticket_id'], $_GET['url_key'])) {
    echo "<br><h2>Oops, something went wrong! Please raise a ticket if you believe this is an error.</h2>";
    require_once "includes/guest_footer.php";
    exit();
}

// Company info
$company_sql_row = mysqli_fetch_array(mysqli_query($mysqli, "
    SELECT 
        company_phone,
        company_phone_country_code,
        company_website 
    FROM 
        companies,
        settings
    WHERE 
        companies.company_id = settings.company_id
        AND companies.company_id = 1"
));

$company_phone_country_code = nullable_htmlentities($company_sql_row['company_phone_country_code']);
$company_phone = nullable_htmlentities(formatPhoneNumber($company_sql_row['company_phone'], $company_phone_country_code));
$company_website = nullable_htmlentities($company_sql_row['company_website']);

$url_key = sanitizeInput($_GET['url_key']);
$ticket_id = intval($_GET['ticket_id']);

$ticket_sql = mysqli_query($mysqli,
    "SELECT * FROM tickets
            LEFT JOIN users on ticket_assigned_to = user_id
            LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
            WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key'"
);

if (mysqli_num_rows($ticket_sql) !== 1) {
    // Invalid invoice/key
    echo "<br><h2>Oops, something went wrong! Please raise a ticket if you believe this is an error.</h2>";
    require_once "includes/guest_footer.php";

    exit();
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

    ?>

    <div class="card mt-3">
        <div class="card-header bg-dark text-center">
            <h4 class="mt-1">
                Ticket <?php echo $ticket_prefix, $ticket_number ?>
            </h4>
        </div>

        <div class="card-body prettyContent">
            <h5><strong>Subject:</strong> <?php echo $ticket_subject ?></h5>
            <hr>
            <p>
                <strong>State:</strong> <?php echo $ticket_status ?>
                <br>
                <strong>Priority:</strong> <?php echo $ticket_priority ?>
                <br>
                <?php if (!empty($ticket_assigned_to) && empty($ticket_closed_at)) { ?>
                    <strong>Assigned to: </strong> <?php echo $ticket_assigned_to ?>
                <?php } ?>
            </p>
            <?php echo $ticket_details ?>
        </div>
    </div>

    <hr>

    <!-- Either show the reply comments box, option to re-open ticket, show ticket smiley feedback or thanks for feedback -->

    <?php if (empty($ticket_resolved_at)) { ?>
        <!-- Reply - guest users should email or login so we know exactly who replied -->
        <h6><i>Please <a href="../client">log in</a> or reply to the ticket via email to respond</i></h6>

    <?php } elseif (empty($ticket_closed_at)) { ?>
        <!-- Re-open -->

        <h4>Your ticket has been resolved</h4>

        <div class="col-4">
            <div class="row">
                <div class="col">
                    <a href="guest_post.php?reopen_ticket&ticket_id=<?php echo $ticket_id; ?>&url_key=<?php echo $url_key ?>" class="btn btn-secondary btn-lg"><i class="fas fa-fw fa-redo text-white"></i> Reopen ticket</a>
                </div>

                <div class="col">
                    <a href="guest_post.php?close_ticket=&ticket_id=<?php echo $ticket_id; ?>&url_key=<?php echo $url_key ?>" class="btn btn-success btn-lg"><i class="fas fa-fw fa-gavel text-white"></i> Close ticket</a>
                </div>
            </div>
        </div>
        <br>

    <?php } elseif (empty($ticket_feedback)) { ?>

        <h4>Ticket closed. Please rate your ticket</h4>

        <div class="col-4">
            <div class="row">
                <div class="col">
                    <a href="guest_post.php?add_ticket_feedback&ticket_id=<?php echo $ticket_id; ?>&url_key=<?php echo $url_key ?>&feedback=Good" class="btn btn-success btn-lg"><i class="fas fa-fw fa-smile text-white"></i> Good</a>
                </div>

                <div class="col">
                    <a href="guest_post.php?add_ticket_feedback&ticket_id=<?php echo $ticket_id; ?>&url_key=<?php echo $url_key ?>&feedback=Bad" class="btn btn-danger btn-lg"><i class="fas fa-fw fa-frown text-white"></i> Bad</a>
                </div>
            </div>
        </div>
        <br>

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
            $avatar_link = "../uploads/clients/$ticket_reply_by/$user_avatar";
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

    <?php } else {
        echo "Ticket ID not found!";
    } ?>

<div class="card-footer">
    <?php echo "<i class='fas fa-phone fa-fw mr-2'></i>$company_phone | <i class='fas fa-globe fa-fw mr-2 ml-2'></i>$company_website"; ?>
</div>

<?php
require_once "includes/guest_footer.php";
