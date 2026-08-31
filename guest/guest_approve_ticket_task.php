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

$company_phone_country_code = escapeHtml($company_sql_row['company_phone_country_code']);
$company_phone = escapeHtml(formatPhoneNumber($company_sql_row['company_phone'], $company_phone_country_code));
$company_website = escapeHtml($company_sql_row['company_website']);

$approval_id = intval($_GET['task_approval_id']);
$url_key = escapeSql($_GET['url_key']);

$task_row = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT approval_status, task_id, task_name, ticket_details,
        ticket_number, ticket_prefix, ticket_priority, ticket_status_name, ticket_subject FROM task_approvals
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
$task_name = escapeHtml($task_row['task_name']);
$approval_status = escapeHtml($task_row['approval_status']);

$ticket_prefix = escapeHtml($task_row['ticket_prefix']);
$ticket_number = intval($task_row['ticket_number']);
$ticket_status = escapeHtml($task_row['ticket_status_name']);
$ticket_priority = escapeHtml($task_row['ticket_priority']);
$ticket_subject = escapeHtml($task_row['ticket_subject']);
$ticket_details = $purifier->purify($task_row['ticket_details']);

// Same priority colours the agent ticket list uses, so a ticket reads the same
// to the client as it does to the tech looking at it
if ($ticket_priority == "Urgent") {
    $ticket_priority_color = "dark";
} elseif ($ticket_priority == "High") {
    $ticket_priority_color = "danger";
} elseif ($ticket_priority == "Medium") {
    $ticket_priority_color = "warning";
} else {
    $ticket_priority_color = "info";
}

$approve_link = "guest_post.php?approve_ticket_task=$task_id&approval_id=$approval_id&approval_url_key=$url_key";

?>

    <?php /* The ask comes first. A guest arrives here from a mail link with no
             context and exactly one thing to do, so the task being approved and
             the button that approves it sit above the fold; the ticket itself is
             supporting detail and follows underneath. */ ?>
    <div class="card mt-3 mb-3">
        <div class="card-header bg-dark text-center py-3">
            <h4 class="mb-0"><i class="fas fa-fw fa-clipboard-check me-2"></i>Task Approval</h4>
        </div>

        <div class="card-body text-center py-4">

            <?php if ($approval_status == 'pending') { ?>
                <p class="text-muted mb-2">You have been asked to approve the following task</p>
            <?php } ?>

            <h4 class="mb-3"><?= ucfirst($task_name) ?></h4>

            <p class="text-muted mb-4">
                Ticket <span class="text-bold"><?= $ticket_prefix, $ticket_number ?></span> &mdash; <?= $ticket_subject ?>
            </p>

            <?php if ($approval_status == 'pending') { ?>

                <?php /* d-grid below sm so the button spans the width on a phone,
                         which is where a mailed approval link is usually opened */ ?>
                <div class="d-grid gap-2 d-sm-block">
                    <a href="<?= $approve_link ?>" class="btn btn-success btn-lg confirm-link"
                       data-confirm-title="Approve this task?"
                       data-confirm-text="<?= ucfirst($task_name) ?>"
                       data-confirm-button="Yes, approve">
                        <i class="fas fa-fw fa-check me-2"></i>Approve task
                    </a>
                </div>

                <small class="text-muted d-block mt-3">Not expecting this? Get in touch using the details below.</small>

            <?php } elseif ($approval_status == 'approved') { ?>

                <?php /* guest_post.php redirects back here after approving, so this
                         is the confirmation screen for every successful approval -
                         not just an already-done state */ ?>
                <div class="alert alert-success d-inline-block mb-0">
                    <i class="fas fa-fw fa-check-circle me-2"></i><span class="text-bold">Approved</span> &mdash; nothing further is needed.
                </div>

            <?php } else { ?>

                <div class="alert alert-danger d-inline-block mb-0">
                    <i class="fas fa-fw fa-times-circle me-2"></i><span class="text-bold">Declined</span> &mdash; this task was not approved.
                </div>

            <?php } ?>

        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mt-2">Ticket details</h5>
            <div class="card-tools">
                <span class="p-2 badge rounded-pill text-bg-secondary"><?= $ticket_status ?></span>
                <span class="p-2 badge rounded-pill text-bg-<?= $ticket_priority_color ?>"><?= $ticket_priority ?></span>
            </div>
        </div>

        <div class="card-body prettyContent">
            <?= $ticket_details ?>
        </div>
    </div>

    <p class="text-center text-muted my-3">
        <i class="fas fa-phone fa-fw me-2"></i><?= $company_phone ?>
        <span class="mx-2">|</span>
        <i class="fas fa-globe fa-fw me-2"></i><?= $company_website ?>
    </p>

    <?php /* prettyContent above is inert without this - it is what constrains a
             pasted screenshot to the column and styles tables in the ticket body */ ?>
    <script src="/js/pretty_content.js"></script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
