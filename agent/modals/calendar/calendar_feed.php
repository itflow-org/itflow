<?php

/*
 * My calendar link - a private subscription link to this agent's own
 * schedule (their scheduled tickets, the events they are on, the projects
 * they manage, or the whole team's) for the calendar on their phone, Outlook,
 * Google or Nextcloud. It shows only what they can see in ITFlow and stops
 * working when their account does.
 */

require_once '../../../includes/modal_header.php';

$feed = calendarUserFeed($session_user_id);
$values = $feed ?: ['feed_scope' => 'mine', 'feed_tickets' => 1, 'feed_events' => 1, 'feed_projects' => 1, 'feed_busy_only' => 0];
$urls = $feed ? calendarFeedUrls($feed['feed_key']) : null;
$csrf = $_SESSION['csrf_token'];
$can_support = lookupUserPermission('module_support') >= 1;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-mobile-alt me-2"></i>My calendar link</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <div class="modal-body">

        <?php if (empty($config_base_url)) { ?>
            <div class="alert alert-danger"><i class="fas fa-fw fa-exclamation-triangle me-2"></i><strong>$config_base_url is not set in config.php</strong>, so there is no address to build the link from.</div>
        <?php } ?>

        <p class="mb-3">Your schedule in the calendar you already use - on your phone, in Outlook, Google or Nextcloud - kept up to date as work is booked. It is a private link: anyone who has it can read what it shows, so keep it to yourself.</p>

        <?php if ($urls) { ?>
            <div class="mb-3">
                <label class="form-label">Subscription link</label>
                <div class="input-group">
                    <input type="text" class="form-control font-monospace small" value="<?= escapeHtml($urls['https']) ?>" readonly onclick="this.select();" id="myCalendarLink">
                    <button class="btn btn-outline-secondary clipboardjs" type="button" data-clipboard-text="<?= escapeHtml($urls['https']) ?>" title="Copy link"><i class="far fa-fw fa-copy"></i></button>
                    <a class="btn btn-outline-secondary" href="<?= escapeHtml($urls['webcal']) ?>" title="Open in the calendar app on this device"><i class="fas fa-fw fa-external-link-alt"></i></a>
                </div>
                <div class="form-text">
                    Made <?= escapeHtml(date('M j, Y', strtotime($feed['feed_created_at']))) ?> &middot;
                    <?= $feed['feed_accessed_at'] ? 'last fetched ' . escapeHtml(timeAgo($feed['feed_accessed_at'])) : 'not fetched by a calendar yet' ?>
                </div>
            </div>
        <?php } ?>

        <div class="border rounded p-3 mb-3">
            <div class="fw-semibold mb-2">What it shows</div>
            <div class="btn-group w-100 mb-3" role="group">
                <input type="radio" class="btn-check" name="scope" id="feedScopeMine" value="mine" <?= $values['feed_scope'] !== 'everyone' ? 'checked' : '' ?> autocomplete="off">
                <label class="btn btn-outline-secondary" for="feedScopeMine"><i class="fas fa-user me-1"></i>Just mine</label>
                <input type="radio" class="btn-check" name="scope" id="feedScopeEveryone" value="everyone" <?= $values['feed_scope'] === 'everyone' ? 'checked' : '' ?> autocomplete="off">
                <label class="btn btn-outline-secondary" for="feedScopeEveryone"><i class="fas fa-users me-1"></i>The whole team</label>
            </div>
            <?php if ($can_support) { ?>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="feed_tickets" value="1" id="feedTickets" <?= $values['feed_tickets'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="feedTickets">Scheduled tickets <span class="small text-body-secondary">- with the site's address, who to see and their number</span></label>
                </div>
            <?php } ?>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="feed_events" value="1" id="feedEvents" <?= $values['feed_events'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="feedEvents">Events <span class="small text-body-secondary">- the ones you are on or added</span></label>
            </div>
            <?php if ($can_support) { ?>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="feed_projects" value="1" id="feedProjects" <?= $values['feed_projects'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="feedProjects">Projects <span class="small text-body-secondary">- when the ones you manage start and are due</span></label>
                </div>
            <?php } ?>
            <hr class="my-2">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" name="busy_only" value="1" id="feedBusyOnly" <?= $values['feed_busy_only'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="feedBusyOnly">Busy only <span class="small text-body-secondary">- just the times, with no titles, clients or addresses</span></label>
            </div>
        </div>

        <?php if ($urls) {
            $help_url_https = $urls['https'];
            $help_url_webcal = $urls['webcal'];
            $help_id = 'myFeedHelp';
            ?>
            <div class="fw-semibold mb-2">Adding it to your calendar</div>
            <?php require 'calendar_subscribe_help.php'; ?>
        <?php } ?>

    </div>
    <div class="modal-footer">
        <?php if ($feed) { ?>
            <div class="dropdown dropup me-auto">
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="dropdown" title="More actions"><i class="fas fa-ellipsis-h"></i></button>
                <div class="dropdown-menu">
                    <a class="dropdown-item confirm-link" href="post.php?calendar_feed_regenerate=1&csrf_token=<?= $csrf ?>"
                        data-confirm-title="Make a new link?" data-confirm-button="New link"
                        data-confirm-text="The link you have now stops working - anywhere it is subscribed needs the new one."><i class="fas fa-fw fa-sync me-2"></i>Make a new link</a>
                    <a class="dropdown-item text-danger confirm-link" href="post.php?calendar_feed_stop=1&csrf_token=<?= $csrf ?>"
                        data-confirm-title="Stop the link?" data-confirm-button="Stop it"
                        data-confirm-text="Calendars subscribed to it stop updating."><i class="fas fa-fw fa-unlink me-2"></i>Stop the link</a>
                </div>
            </div>
            <button type="submit" name="calendar_feed_save" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <?php } else { ?>
            <button type="submit" name="calendar_feed_save" class="btn btn-primary text-bold"><i class="fas fa-link me-2"></i>Make my link</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
