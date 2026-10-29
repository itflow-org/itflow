<?php

/*
 * Share a calendar - publish it as a read-only subscription link for Google,
 * Outlook, Apple, Nextcloud or Thunderbird (a client's maintenance windows, a
 * team's onsite calendar on a wall screen). Admins only: the link needs no
 * login. Busy only publishes the times without the details.
 */

require_once '../../../includes/modal_header.php';

if (!calendarCanManage()) {
    echo json_encode(['error' => 'Only an admin can share a calendar.']);
    exit;
}

$calendar_id = intval($_GET['id'] ?? 0);
$calendar = calendarList()[$calendar_id] ?? null;
if (!$calendar) {
    echo json_encode(['error' => 'That calendar no longer exists.']);
    exit;
}
$feed_key = (string) $calendar['calendar_feed_key'];
$urls = $feed_key !== '' ? calendarFeedUrls($feed_key) : null;
$csrf = $_SESSION['csrf_token'];

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-share-alt me-2"></i>Share <?= escapeHtml($calendar['calendar_name']) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="calendar_id" value="<?= $calendar_id ?>">

    <div class="modal-body">

        <?php if (empty($config_base_url)) { ?>
            <div class="alert alert-danger">
                <i class="fas fa-fw fa-exclamation-triangle me-2"></i><strong>$config_base_url is not set in config.php.</strong>
                Without it there is no address to build a subscription link from. Set it to the hostname this install is reached on and reopen this.
            </div>
        <?php } ?>

        <?php if (!$urls) { ?>
            <p>Sharing <strong><?= escapeHtml($calendar['calendar_name']) ?></strong> makes a secret link that Google Calendar, Outlook, an iPhone, Nextcloud or Thunderbird can subscribe to - a client's maintenance windows in their own calendar, or the team's onsite visits on a wall screen.</p>
            <div class="alert alert-warning small">
                <i class="fas fa-fw fa-exclamation-triangle me-2"></i>Anyone with the link can read this calendar without signing in. Share it like a password; a new link can be made here if it gets out.
            </div>
        <?php } else { ?>
            <div class="mb-3">
                <label class="form-label">Subscription link</label>
                <div class="input-group">
                    <input type="text" class="form-control font-monospace small" value="<?= escapeHtml($urls['https']) ?>" readonly onclick="this.select();">
                    <button class="btn btn-outline-secondary clipboardjs" type="button" data-clipboard-text="<?= escapeHtml($urls['https']) ?>" title="Copy link"><i class="far fa-fw fa-copy"></i></button>
                    <a class="btn btn-outline-secondary" href="<?= escapeHtml($urls['webcal']) ?>" title="Open in the calendar app on this device"><i class="fas fa-fw fa-external-link-alt"></i></a>
                </div>
                <div class="form-text">
                    Made <?= $calendar['calendar_feed_created_at'] ? escapeHtml(date('M j, Y', strtotime($calendar['calendar_feed_created_at']))) : '' ?> &middot;
                    <?= $calendar['calendar_feed_accessed_at'] ? 'last fetched ' . escapeHtml(timeAgo($calendar['calendar_feed_accessed_at'])) : 'not fetched by a calendar yet' ?>
                </div>
            </div>
        <?php } ?>

        <div class="form-check form-switch mb-3">
            <input type="checkbox" class="form-check-input" id="feedBusyOnly" name="busy_only" value="1" <?= intval($calendar['calendar_feed_busy_only']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="feedBusyOnly">Busy only <span class="small text-body-secondary">- just the times, with no titles, descriptions or places</span></label>
        </div>

        <?php if ($urls) {
            $help_url_https = $urls['https'];
            $help_url_webcal = $urls['webcal'];
            $help_id = 'shareFeedHelp' . $calendar_id;
            ?>
            <div class="fw-semibold mb-2">Subscribing</div>
            <?php require 'calendar_subscribe_help.php'; ?>
        <?php } ?>

    </div>
    <div class="modal-footer">
        <?php if ($urls) { ?>
            <div class="dropdown dropup me-auto">
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="dropdown" title="More actions"><i class="fas fa-ellipsis-h"></i></button>
                <div class="dropdown-menu">
                    <a class="dropdown-item confirm-link" href="post.php?regenerate_calendar_feed=<?= $calendar_id ?>&csrf_token=<?= $csrf ?>"
                        data-confirm-title="Make a new link?" data-confirm-button="New link"
                        data-confirm-text="The current link stops working - everyone subscribed needs the new one."><i class="fas fa-fw fa-sync me-2"></i>Make a new link</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger confirm-link" href="post.php?unshare_calendar=<?= $calendar_id ?>&csrf_token=<?= $csrf ?>"
                        data-confirm-title="Stop sharing?" data-confirm-button="Stop sharing"
                        data-confirm-text="Calendars subscribed to it stop updating."><i class="fas fa-fw fa-unlink me-2"></i>Stop sharing</a>
                </div>
            </div>
            <button type="submit" name="share_calendar" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <?php } else { ?>
            <button type="submit" name="share_calendar" class="btn btn-primary text-bold"><i class="fas fa-link me-2"></i>Make the link</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
