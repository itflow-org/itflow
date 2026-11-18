<?php

/*
 * Admin > Settings > Projects - how projects are numbered, and whether their
 * tickets are kept apart from the service desk (functions/project_tickets.php).
 * Saved by edit_project_settings (admin/post/settings_project.php).
 */

require_once "includes/inc_all_admin.php";

echo settingsPageStart('project');

?>

<form action="post.php" method="post" autocomplete="off" data-settings-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('numbering', 'Numbering', 'Each project gets the next number, after its prefix.') ?>
                <?= settingsNumberingFields('project', $config_project_prefix, $config_project_next_number) ?>
            <?= settingsSectionEnd() ?>

            <?php if (projectTicketsReady()) {
                $project_tickets = projectTicketsSettings(); ?>
                <?= settingsSectionStart('tickets', 'Project tickets', 'A project\'s tickets can be kept apart from the service desk - its dates count, not an SLA.') ?>
                    <?= settingsSwitch('config_project_tickets_apart', $project_tickets['apart'] ? 1 : 0, ['label' => 'Keep project tickets apart',
                        'help' => 'A ticket in a project is left off the Tickets list\'s usual views - it has a Projects view of its own - the dashboard\'s unassigned count and the Dispatch queue, and has no SLA. Agents still see their own under Mine, in Dispatch\'s Workload and on the calendar. A ticket taken out of its project gets its SLA back, counted from then - and so do they all if this is switched off.']) ?>
                    <?= settingsInput('config_project_ticket_color', $project_tickets['color'], ['type' => 'color', 'icon' => 'fa-palette', 'class' => 'form-control-color',
                        'label' => 'Calendar color', 'help' => 'Their bookings on the calendar and Dispatch while they are kept apart - on the calendar, with a dot in the agent\'s own color beside their name.']) ?>
                <?= settingsSectionEnd() ?>
            <?php } ?>

            <?= settingsSaveBar('edit_project_settings') ?>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
