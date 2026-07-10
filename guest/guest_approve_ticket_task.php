<?php

require_once "includes/inc_all_guest.php";

//Initialize the HTML Purifier to prevent XSS
require_once "../libs/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

if (!isset($_GET['task_approval_id'], $_GET['url_key'])) {
    echo "<br><h2>Oops, something went wrong! Please raise a ticket if you believe this is an error.</h2>";
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
    exit();
}

// Company info
$company_sql_row = mysqli_fetch_assoc(mysqli_query($mysqli, "
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

$approval_id = intval($_GET['task_approval_id']);
$url_key = sanitizeInput($_GET['url_key']);

$task_row = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT * FROM task_approvals
        LEFT JOIN tasks ON approval_task_id = task_id
        LEFT JOIN tickets on task_ticket_id = ticket_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    WHERE approval_id = $approval_id AND approval_url_key = '$url_key'
    LIMIT 1"
));

if (!$task_row) {
    // Invalid
    echo "<br><h2>Oops, something went wrong! Please raise a ticket if you believe this is an error.</h2>";
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
    exit();
}


$task_id = intval($task_row['task_id']);
$task_name = nullable_htmlentities($task_row['task_name']);
$approval_scope = nullable_htmlentities($task_row['approval_scope']);
$approval_type = nullable_htmlentities($task_row['approval_type']);
$approval_status = nullable_htmlentities($task_row['approval_status']);

$ticket_prefix = nullable_htmlentities($task_row['ticket_prefix']);
$ticket_number = intval($task_row['ticket_number']);
$ticket_status = nullable_htmlentities($task_row['ticket_status_name']);
$ticket_priority = nullable_htmlentities($task_row['ticket_priority']);
$ticket_subject = nullable_htmlentities($task_row['ticket_subject']);
$ticket_details = $purifier->purify($task_row['ticket_details']);

?>

    <div class="card mt-3">
        <div class="card-header bg-dark text-center">
            <h4 class="mt-1">
                Task Approval for Ticket <?php echo $ticket_prefix, $ticket_number ?>
            </h4>
        </div>

        <div class="card-body prettyContent">
            <h5><strong>Subject:</strong> <?php echo $ticket_subject ?></h5>
            <p>
                <strong>State:</strong> <?php echo $ticket_status ?>
                <br>
                <strong>Priority:</strong> <?php echo $ticket_priority ?>
                <br>
            </p>
            <?php echo $ticket_details ?>
            <hr>
            <h5>Task Approval</h5>
            <p>
                <strong>Task Name: </strong><?= ucfirst($task_name); ?>
                <br>
                <strong>Scope/Type:</strong> <?= ucfirst($approval_scope) . " - " . ucfirst($approval_type)?>
                <br>
                <strong>Status:</strong> <?= ucfirst($approval_status)?>
                <br>
                <?php
                if ($approval_status == 'pending') { ?>
                    <strong>Action: </strong><a href="guest_post.php?approve_ticket_task=<?= $task_id ?>&approval_id=<?= $approval_id ?>&approval_url_key=<?= $url_key ?>" class="confirm-link text-bold">Approve Task</a>
                <?php } ?>
            </p>

        </div>
    </div>

    <hr>

    <div class="card-footer">
        <?php echo "<i class='fas fa-phone fa-fw mr-2'></i>$company_phone | <i class='fas fa-globe fa-fw mr-2 ml-2'></i>$company_website"; ?>
    </div>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
