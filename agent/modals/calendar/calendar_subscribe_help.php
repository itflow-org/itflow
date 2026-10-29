<?php

/*
 * How to subscribe to a calendar link in each calendar app - included by the
 * Share modal (a published calendar) and My calendar link (an agent's own
 * schedule), which set $help_url_https, $help_url_webcal and $help_id (to keep
 * the tab ids apart).
 */

isset($mysqli) || die("Direct file access is not allowed");

$help_apps = [
    'google' => ['Google', 'fab fa-google', [
        'In Google Calendar on the web, click <strong>+</strong> next to <em>Other calendars</em>, then <strong>From URL</strong>.',
        'Paste the link and click <strong>Add calendar</strong>. It shows on your phone once it is in your Google account.',
        '<span class="text-body-secondary">Google refreshes on its own schedule - often every 12 to 24 hours, sometimes longer - and cannot be made to go sooner. For a schedule that changes during the day, an app below that refreshes more often is the better fit.</span>',
    ]],
    'outlook' => ['Outlook', 'fab fa-microsoft', [
        'In Outlook on the web or the new Outlook, open the calendar and choose <strong>Add calendar</strong> &rarr; <strong>Subscribe from web</strong>.',
        'Paste the link, give it a name and a color, and <strong>Import</strong>. Classic Outlook: <em>Account settings</em> &rarr; <em>Internet Calendars</em> &rarr; <em>New</em>.',
        '<span class="text-body-secondary">Outlook refreshes subscribed calendars on its own schedule, usually every few hours.</span>',
    ]],
    'apple' => ['iPhone & Mac', 'fab fa-apple', [
        'On an iPhone or iPad: <em>Settings</em> &rarr; <em>Calendar</em> &rarr; <em>Accounts</em> &rarr; <em>Add Account</em> &rarr; <em>Other</em> &rarr; <strong>Add Subscribed Calendar</strong>, and paste the link. Or open the <strong>webcal://</strong> link on the device.',
        'On a Mac: Calendar &rarr; <em>File</em> &rarr; <strong>New Calendar Subscription</strong>, paste the link, and set <em>Auto-refresh</em> to every 15 minutes.',
    ]],
    'nextcloud' => ['Nextcloud', 'fas fa-cloud', [
        'In the Nextcloud Calendar app: <strong>+ New calendar</strong> &rarr; <strong>New subscription from link (read-only)</strong>, and paste the link. Phones syncing Nextcloud through DAVx&#8325; get it too.',
        '<span class="text-body-secondary">Nextcloud refreshes subscriptions once a day unless its admin lowers it:</span><br><code class="user-select-all">occ config:app:set dav calendarSubscriptionRefreshRate --value "PT15M"</code>',
        '<span class="text-body-secondary">If ITFlow is on the same private network, Nextcloud refuses the link until local addresses are allowed:</span><br><code class="user-select-all">occ config:app:set dav webcalAllowLocalAccess --value yes</code><br><span class="text-body-secondary small">Before Nextcloud 32: <code class="user-select-all">occ config:system:set allow_local_remote_servers --value true --type bool</code></span>',
    ]],
    'thunderbird' => ['Thunderbird', 'fas fa-envelope-open-text', [
        '<em>New Calendar</em> &rarr; <strong>On the Network</strong>, paste the link, and pick the calendar it finds.',
    ]],
];

?>
<ul class="nav nav-pills nav-fill small mb-2 itflow-cal-help-tabs" role="tablist">
    <?php $first = true; foreach ($help_apps as $key => [$label, $icon]) { ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link py-1 px-2<?= $first ? ' active' : '' ?>" type="button" data-bs-toggle="pill" data-bs-target="#<?= $help_id ?>-<?= $key ?>" role="tab"><i class="<?= $icon ?> me-1"></i><?= $label ?></button>
        </li>
    <?php $first = false; } ?>
</ul>
<div class="tab-content border rounded p-3 small">
    <?php $first = true; foreach ($help_apps as $key => [$label, $icon, $steps]) { ?>
        <div class="tab-pane fade<?= $first ? ' show active' : '' ?>" id="<?= $help_id ?>-<?= $key ?>" role="tabpanel">
            <ol class="mb-0 ps-3">
                <?php foreach ($steps as $step) { ?>
                    <li class="mb-1"><?= $step ?></li>
                <?php } ?>
            </ol>
        </div>
    <?php $first = false; } ?>
</div>
