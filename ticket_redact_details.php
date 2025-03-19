<?php

require_once "includes/inc_all.php";

// Perms - Admins only
if (!isset($session_is_admin) || !$session_is_admin) {
    exit(WORDING_ROLECHECK_FAILED . "<br>Tell your admin: Your role does not have admin access.");
}

//Initialize the HTML Purifier to prevent XSS
require_once "plugins/htmlpurifier/HTMLPurifier.standalone.php";
$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

if (isset($_GET['ticket_id']) && isset($_GET['ticket_reply_id'])) {
    $ticket_id = intval($_GET['ticket_id']);
    $ticket_reply_id = intval($_GET['ticket_reply_id']);

    $ticket_sql = mysqli_query(
        $mysqli,
        "SELECT ticket_prefix, ticket_number, ticket_subject, ticket_client_id FROM tickets
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
        $client_id = intval($ticket_row['ticket_client_id']);

        // Get ticket reply
        $sql_ticket_reply = mysqli_query(
            $mysqli,
            "SELECT * FROM ticket_replies
            LEFT JOIN users ON ticket_reply_by = user_id
            LEFT JOIN contacts ON ticket_reply_by = contact_id
            WHERE ticket_reply_id = $ticket_reply_id AND ticket_reply_ticket_id = $ticket_id
            AND ticket_reply_archived_at IS NULL
            LIMIT 1"
        );

        if (mysqli_num_rows($ticket_sql) == 0) {

            echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='tickets.php'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

        } else {

            $reply_row = mysqli_fetch_array($sql_ticket_reply);

            $ticket_reply = $purifier->purify($reply_row['ticket_reply']);
            $ticket_reply_type = nullable_htmlentities($reply_row['ticket_reply_type']);
            if ($ticket_reply_type == "Client") {
                $ticket_reply_by_display = nullable_htmlentities($reply_row['contact_name']);
            } else {
                $ticket_reply_by_display = nullable_htmlentities($reply_row['user_name']);
            } ?>

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
                        <span class="h3"><?php echo "$ticket_prefix$ticket_number - $ticket_subject: " . ucfirst($ticket_reply_type) . " ticket reply by $ticket_reply_by_display" ?></span>
                    </div>
                </div>
            </div>

            <div class="card card-body d-print-none pb-0">

                <form action="post.php" enctype="multipart/form-data" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id ?>">
                    <input type="hidden" name="ticket_reply_id" value="<?php echo $ticket_reply_id ?>">
                    <input type="hidden" name="client_id" value="<?php echo $client_id ?>">
                    <div class="form-group">
                        <textarea id="tinymceTicketRedact" name="ticket_reply" class="form-control tinymceTicketRedact"><?php echo $ticket_reply?></textarea>
                    </div>

                    <div class="form-group">
                        <button onclick="redactSelectedText()" class="btn btn-secondary" type="button">Redact Selected Text</button>
                    </div>

                    <div class="form-group float-right">
                        <button type="submit" id="redact_ticket_reply" name="redact_ticket_reply" class="btn btn-success ml-3"><i class="fas fa-check mr-2"></i>Save</button>
                    </div>

                </form>

            </div>

            <!-- Javascript for the redaction text editor -->
            <script src="js/ticket_redact.js"></script>

        <?php }
        // End ticket replies


    } // End ticket row SQL


} else {
    echo "No ticket ID specified";
}

require_once "includes/footer.php";

