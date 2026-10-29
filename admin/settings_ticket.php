<?php

/*
 * Admin > Settings > Tickets - how tickets are numbered, made from email
 * (cron/ticket_email_parser.php, reading the mailbox in Mail > Receiving),
 * timed and billed, who hears about new ones, when resolved ones close, and
 * how the tickets page opens, and whether AI triages new tickets
 * (functions/ai_ticket.php). Saved by edit_ticket_settings
 * (admin/post/settings_ticket.php).
 */

require_once "includes/inc_all_admin.php";

echo settingsPageStart('ticket');

?>

<form action="post.php" method="post" autocomplete="off" data-settings-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('numbering', 'Numbering', 'Each ticket gets the next number, after its prefix. A reply by email finds its ticket by it.') ?>
                <?= settingsNumberingFields('ticket', $config_ticket_prefix, $config_ticket_next_number) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('email', 'Email to ticket', 'An email to your support mailbox becomes a ticket, and a reply to one goes on it - read from the mailbox in <a href="settings_mail.php?tab=receiving">Mail &gt; Receiving</a> each time the Ticket Email Parser job in <a href="cron.php">Maintenance &gt; Cron</a> runs.') ?>
                <?php if ($config_ticket_email_parse && empty($config_imap_provider)) { ?>
                    <div class="alert alert-warning py-2 small" data-ticket-no-mailbox><i class="fas fa-exclamation-triangle me-2"></i>No mailbox is set up in Mail &gt; Receiving, so there is nothing to read.</div>
                <?php } ?>
                <?= settingsSwitch('config_ticket_email_parse', $config_ticket_email_parse) ?>
                <div data-settings-depends-on="config_ticket_email_parse">
                    <?= settingsSwitch('config_ticket_email_parse_unknown_senders', $config_ticket_email_parse_unknown_senders, ['help' => 'From someone who isn\'t a contact, at a domain no client has - otherwise those are left in the mailbox, flagged and unread.']) ?>
                </div>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('new', 'New tickets', 'Who hears about a ticket as it is raised - in the portal, by email or on a schedule - and whether AI sorts it.') ?>
                <?= settingsInput('config_ticket_new_ticket_notification_email', $config_ticket_new_ticket_notification_email, ['icon' => 'fa-bell', 'type' => 'email', 'maxlength' => 200,
                    'placeholder' => 'support@company.com', 'help' => 'A shared mailbox or list works best. Empty, nobody is emailed.']) ?>
                <?php if (aiTriageReady()) { ?>
                    <?php if (!getAiModel('Tickets')) { ?>
                        <div class="alert alert-warning py-2 small" data-ticket-ai-no-model><i class="fas fa-exclamation-triangle me-2"></i>AI triage needs an AI model for Tickets, or one for everything - <a href="ai_providers.php">AI Providers</a>.</div>
                    <?php } ?>
                    <?= settingsSelect('config_ticket_ai_triage', aiTriageMode(), aiTriageModes(), ['icon' => 'fa-robot',
                        'help' => 'A ticket from email, the portal or the API is given a category and a priority by AI as it comes in, with a line on what the client needs - within a minute, by the AI Triage job in <a href="cron.php">Maintenance &gt; Cron</a>. Fill in sets the category when the ticket has none, and the priority of an emailed one, since nobody picked it; the rest is suggested. Any ticket can be triaged from its menu too.']) ?>
                <?php } ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('time', 'Time and billing', 'How time on a ticket starts, and whether it bills.') ?>
                <?= settingsSwitch('config_ticket_timer_autostart', $config_ticket_timer_autostart, ['help' => 'The timer runs from opening a ticket, rather than from pressing start.']) ?>
                <?php if ($config_module_enable_accounting) { ?>
                    <?= settingsSwitch('config_ticket_default_billable', $config_ticket_default_billable, ['help' => 'A new ticket starts billable - each can be changed. How the time is billed is in <a href="settings_invoice.php#billing-time">Invoices and billing</a>.']) ?>
                <?php } ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('closing', 'Closing', 'A resolved ticket waits for the client to say it isn\'t, then closes by itself, each night.') ?>
                <?= settingsInput('config_ticket_autoclose_hours', intval($config_ticket_autoclose_hours), ['icon' => 'fa-clock', 'type' => 'number', 'min' => 24, 'max' => 8760, 'required' => true,
                    'suffix' => 'hours', 'help' => 'From when it was last changed. 72 is three days.']) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('page', 'The tickets page', 'How it opens for everyone, and what the board lets them do.') ?>
                <?= settingsSelect('config_ticket_default_view', intval($config_ticket_default_view), [0 => 'A list', 1 => 'A compact list', 2 => 'A board (kanban)'], ['icon' => 'fa-eye']) ?>
                <?= settingsSwitch('config_ticket_ordering', $config_ticket_ordering, ['help' => 'Tickets are dragged into order in a column. Off, a column is in order of priority, then number.']) ?>
                <?= settingsSwitch('config_ticket_moving_columns', $config_ticket_moving_columns, ['help' => 'The board\'s columns are dragged into another order.']) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSaveBar('edit_ticket_settings') ?>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
