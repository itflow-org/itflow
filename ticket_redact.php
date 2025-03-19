<?php

require_once "includes/inc_all.php";

// Perms - Admins only
if (!isset($session_is_admin) || !$session_is_admin) {
    exit(WORDING_ROLECHECK_FAILED . "<br>Tell your admin: Your role does not have admin access.");
}

//Initialize the HTML Purifier to prevent XSS
require "plugins/htmlpurifier/HTMLPurifier.standalone.php";
$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);


if (isset($_GET['ticket_id'])) {
    $ticket_id = intval($_GET['ticket_id']);

    $ticket_sql = mysqli_query(
        $mysqli,
        "SELECT ticket_prefix, ticket_number, ticket_subject, ticket_details FROM tickets
        WHERE ticket_id = $ticket_id AND ticket_closed_at IS NOT NULL
        LIMIT 1"
    );

    if (mysqli_num_rows($ticket_sql) == 0) {

        echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='tickets.php'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

    } else {

        $ticket_row = mysqli_fetch_array($ticket_sql);
        $ticket_prefix = nullable_htmlentities($ticket_row['ticket_prefix']);
        $ticket_number = intval($ticket_row['ticket_number']);
        $ticket_subject = nullable_htmlentities($ticket_row['ticket_subject']);
        $ticket_details = $purifier->purify($ticket_row['ticket_details']);

        // Get ticket replies
        $sql_ticket_replies = mysqli_query(
            $mysqli,
            "SELECT * FROM ticket_replies 
            LEFT JOIN users ON ticket_reply_by = user_id
            LEFT JOIN contacts ON ticket_reply_by = contact_id
            WHERE ticket_reply_ticket_id = $ticket_id
            AND ticket_reply_archived_at IS NULL
            ORDER BY ticket_reply_id DESC"
        );

        ?>

        <!-- Breadcrumbs-->
        <ol class="breadcrumb d-print-none">
            <li class="breadcrumb-item">
                <a href="tickets.php">Tickets</a>
            </li>
            <li class="breadcrumb-item active"><i class="fas fa-life-ring mr-1"></i><?php echo "$ticket_prefix$ticket_number";?></li>
        </ol>

        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa fa-2x fa-fw fa fa-life-ring text-secondary mr-2"></i>
                    <span class="h3"><?php echo "$ticket_prefix$ticket_number - $ticket_subject"; ?></span>
                </div>
            </div>
        </div>

        <!-- Ticket details -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    Ticket Details
                </div>
            </div>
            <div class="card-body prettyContent">
                <?php echo $ticket_details ?>
            </div>
        </div>
        <!-- End Ticket details -->

        <hr>

        <?php
        // Cycle though all ticket replies
        while ($row = mysqli_fetch_array($sql_ticket_replies)) {
            $ticket_reply_id = intval($row['ticket_reply_id']);
            $ticket_reply = $purifier->purify($row['ticket_reply']);
            $ticket_reply_type = nullable_htmlentities($row['ticket_reply_type']);
            if ($ticket_reply_type == "Client") {
                $ticket_reply_by_display = nullable_htmlentities($row['contact_name']);
            } else {
                $ticket_reply_by_display = nullable_htmlentities($row['user_name']);
            } ?>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <?php echo ucfirst($ticket_reply_type) ?> ticket reply by <?php echo $ticket_reply_by_display ?>
                    </div>
                    <div class="float-right">
                        <a href="ticket_redact_details.php?ticket_id=<?php echo $ticket_id; ?>&ticket_reply_id=<?php echo $ticket_reply_id?>" class="btn btn-danger btn-sm ml-3">
                            <i class="fas fa-fw fa-marker mr-2"></i>Redact
                        </a>
                    </div>
                </div>
                <div class="card-body prettyContent">
                    <?php echo $ticket_reply ?>
                </div>
            </div>

        <?php }
        // End ticket replies


    } // End ticket row SQL


} else {
    echo "No ticket ID specified";
}

require_once "includes/footer.php";

?>

