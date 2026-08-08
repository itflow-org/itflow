<?php

require_once '../../../includes/modal_header.php';

$calendar_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT calendar_color, calendar_feed_accessed_at, calendar_feed_busy_only,
    calendar_feed_created_at, calendar_feed_key, calendar_name FROM calendars WHERE calendar_id = $calendar_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$calendar_name = escapeHtml($row['calendar_name']);
$calendar_color = escapeHtml($row['calendar_color']);
$calendar_feed_key = escapeHtml($row['calendar_feed_key']);
$calendar_feed_busy_only = intval($row['calendar_feed_busy_only']);
$calendar_feed_created_at = escapeHtml($row['calendar_feed_created_at']);
$calendar_feed_accessed_at = escapeHtml($row['calendar_feed_accessed_at']);

// $config_base_url lives in config.php (written at setup from HTTP_HOST, or from
// the --base_url argument to setup_cli.php) and is expected to be a bare host.
// Tolerate a scheme having been saved into it rather than emitting https://https://
$base_url = rtrim(preg_replace('#^[a-z]+://#i', '', (string) $config_base_url), '/');
$feed_path = "/guest/guest_calendar_feed.php?key=$calendar_feed_key";
$feed_url_https = "https://$base_url$feed_path";
$feed_url_webcal = "webcal://$base_url$feed_path";

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-share-alt mr-2"></i>Share <?= $calendar_name ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<?php if (empty($base_url)) { ?>

    <div class="modal-body">
        <div class="alert alert-danger mb-0">
            <i class="fas fa-fw fa-exclamation-triangle mr-2"></i>
            <strong>$config_base_url is not set in config.php.</strong>
            Without it there is no hostname to build a subscription link from. Set it
            to the hostname this install is reached on and reopen this dialog.
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">
            <i class="fas fa-fw fa-times mr-2"></i>Close
        </button>
    </div>

<?php } elseif (empty($calendar_feed_key)) { ?>

    <form action="post.php" method="post" autocomplete="off">

        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="calendar_id" value="<?= $calendar_id ?>">

        <div class="modal-body">

            <p>
                Publishing <strong><?= $calendar_name ?></strong> creates a secret link that
                Google Calendar, Nextcloud, Apple Calendar or Thunderbird can subscribe to.
            </p>

            <div class="alert alert-warning">
                <i class="fas fa-fw fa-exclamation-triangle mr-2"></i>
                Anyone with the link can read this calendar without logging in. Share it
                like a password, and revoke it here if it gets out.
            </div>

            <div class="form-group">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="feedBusyOnly" name="busy_only" value="1">
                    <label class="custom-control-label" for="feedBusyOnly">
                        Busy only &mdash; publish time blocks without titles, descriptions or locations
                    </label>
                </div>
            </div>

        </div>
        <div class="modal-footer">
            <button type="submit" name="share_calendar" class="btn btn-primary">
                <i class="fas fa-fw fa-link mr-2"></i>Create link
            </button>
            <button type="button" class="btn btn-light" data-dismiss="modal">
                <i class="fas fa-fw fa-times mr-2"></i>Cancel
            </button>
        </div>
    </form>

<?php } else { ?>

    <form action="post.php" method="post" autocomplete="off">

        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="calendar_id" value="<?= $calendar_id ?>">

        <div class="modal-body">

            <div class="form-group">
                <label>Subscription link</label>
                <div class="input-group">
                    <input type="text" class="form-control" value="<?= $feed_url_https ?>" readonly onclick="this.select();">
                    <div class="input-group-append">
                        <button class="btn btn-secondary clipboardjs" type="button" data-clipboard-text="<?= $feed_url_https ?>" title="Copy link">
                            <i class="far fa-fw fa-copy"></i>
                        </button>
                    </div>
                </div>
                <small class="form-text text-muted">
                    Paste this into <strong>Google Calendar</strong> &rarr; Other calendars &rarr; From URL,
                    or <strong>Nextcloud Calendar</strong> &rarr; New calendar &rarr; New subscription.
                </small>
            </div>

            <div class="form-group">
                <label>Or open directly in a desktop calendar app</label>
                <div class="input-group">
                    <input type="text" class="form-control" value="<?= $feed_url_webcal ?>" readonly onclick="this.select();">
                    <div class="input-group-append">
                        <button class="btn btn-secondary clipboardjs" type="button" data-clipboard-text="<?= $feed_url_webcal ?>" title="Copy link">
                            <i class="far fa-fw fa-copy"></i>
                        </button>
                        <a class="btn btn-secondary" href="<?= $feed_url_webcal ?>" title="Open">
                            <i class="fas fa-fw fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>
            </div>

            <hr>

            <div class="form-group">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="feedBusyOnly" name="busy_only" value="1" <?php if ($calendar_feed_busy_only == 1) { echo "checked"; } ?>>
                    <label class="custom-control-label" for="feedBusyOnly">
                        Busy only &mdash; publish time blocks without titles, descriptions or locations
                    </label>
                </div>
            </div>

            <dl class="row mb-0 text-muted">
                <dt class="col-5">Link created</dt>
                <dd class="col-7"><?= $calendar_feed_created_at ?: 'Unknown' ?></dd>
                <dt class="col-5">Last fetched</dt>
                <dd class="col-7"><?= $calendar_feed_accessed_at ?: 'Never' ?></dd>
            </dl>

            <div class="alert alert-secondary mt-3 mb-0">
                <i class="fas fa-fw fa-info-circle mr-2"></i>
                Subscriptions are read-only, and clients decide how often to refresh.
                Google refreshes on its own schedule (often 12&ndash;24 hours) and cannot be
                forced. Nextcloud defaults to once a week unless
                <code>calendarSubscriptionRefreshRate</code> is lowered, and refuses
                subscriptions pointing at a private IP address.
            </div>

        </div>
        <div class="modal-footer justify-content-between">
            <div>
                <button type="submit" name="share_calendar" class="btn btn-primary">
                    <i class="fas fa-fw fa-check mr-2"></i>Save
                </button>
                <button type="button" class="btn btn-light" data-dismiss="modal">
                    <i class="fas fa-fw fa-times mr-2"></i>Close
                </button>
            </div>
            <div class="dropdown dropup">
                <button class="btn btn-light" type="button" data-toggle="dropdown">
                    <i class="fas fa-fw fa-ellipsis-v"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item confirm-link" href="post.php?regenerate_calendar_feed=<?= $calendar_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-fw fa-sync mr-2"></i>Regenerate link
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?unshare_calendar=<?= $calendar_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-fw fa-unlink mr-2"></i>Stop sharing
                    </a>
                </div>
            </div>
        </div>
    </form>

<?php } ?>

<?php
require_once '../../../includes/modal_footer.php';
