<?php

/*
 * Get started - Support: business hours (what response times count), the
 * standard response times as the default SLAs (GETTING_STARTED_SLAS), how
 * tickets are numbered and whether clients have the portal
 * (getting_started_support). The same settings as Admin > SLAs, Tickets and
 * Modules.
 */

defined('FROM_GETTING_STARTED') || die("Direct file access is not allowed");

$gs_support = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_business_days, config_business_hours_start, config_business_hours_end, config_ticket_prefix, config_ticket_next_number,
    config_client_portal_enable FROM settings WHERE company_id = 1")) ?: [];
$gs_days = array_map('intval', explode(',', (string) ($gs_support['config_business_days'] ?? '1,2,3,4,5')));
if (isset($settings_kept['values']['business_days_kept'])) {
    // The days ticked, when a save came back - they are sent as a list, which settingsRefuse() doesn't keep
    $gs_days = array_map('intval', array_filter(explode(',', (string) $settings_kept['values']['business_days_kept'])));
}
$gs_day_names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
$gs_slas = [];
$gs_sql = mysqli_query($mysqli, "SELECT sla_assignment_priority, sla_name, sla_response_minutes, sla_resolution_minutes FROM sla_assignments
    JOIN slas ON sla_id = sla_assignment_sla_id WHERE sla_assignment_client_id = 0 AND sla_archived_at IS NULL");
while ($gs_row = mysqli_fetch_assoc($gs_sql)) {
    $gs_slas[$gs_row['sla_assignment_priority']] = $gs_row;
}
$gs_use_standard = settingsChecked('standard_slas', true);

?>
<form action="post.php" method="post" autocomplete="off" data-gs-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <h2 class="h6 fw-semibold">Business hours</h2>
    <p class="small text-body-secondary">Response times count only these - an urgent ticket on Friday evening is due Monday morning. Holidays are added in Admin &gt; SLAs.</p>
    <?= settingsField('business_days', 'Days', '<div class="d-flex flex-wrap gap-2" role="group" aria-label="Business days" data-gs-days>' . implode('', array_map(function ($day) use ($gs_day_names, $gs_days) {
        return '<input type="checkbox" class="btn-check" name="business_days[]" value="' . $day . '" id="gsDay' . $day . '" autocomplete="off"' . (in_array($day, $gs_days, true) ? ' checked' : '') . '>'
            . '<label class="btn btn-outline-primary btn-sm itflow-gs-day" for="gsDay' . $day . '">' . $gs_day_names[$day] . '</label>';
    }, array_keys($gs_day_names))) . '</div>', '', 'gsDay1') ?>
    <div class="row g-3 mt-0 mb-4">
        <div class="col-sm-6">
            <?= settingsInput('business_hours_start', substr((string) ($gs_support['config_business_hours_start'] ?? '09:00'), 0, 5), ['icon' => 'fa-sun', 'type' => 'time', 'label' => 'From', 'required' => true]) ?>
        </div>
        <div class="col-sm-6">
            <?= settingsInput('business_hours_end', substr((string) ($gs_support['config_business_hours_end'] ?? '17:00'), 0, 5), ['icon' => 'fa-moon', 'type' => 'time', 'label' => 'To', 'required' => true]) ?>
        </div>
    </div>

    <h2 class="h6 fw-semibold">Response times</h2>
    <?php if ($gs_slas) { ?>
        <p class="small text-body-secondary">The response times a ticket gets unless its client has their own - in Admin &gt; SLAs.</p>
    <?php } else { ?>
        <p class="small text-body-secondary">How soon a ticket is answered and put right, by its priority - a client can have their own. Change them, or add more, in Admin &gt; SLAs.</p>
    <?php } ?>
    <div class="table-responsive mb-3">
        <table class="table table-sm align-middle mb-0 itflow-gs-slas" data-gs-slas>
            <thead>
                <tr><th scope="col">Priority</th><th scope="col">Answered within</th><th scope="col">Put right within</th></tr>
            </thead>
            <tbody>
                <?php foreach (array_keys(GETTING_STARTED_SLAS) as $gs_priority) {
                    [$gs_response, $gs_resolution] = isset($gs_slas[$gs_priority])
                        ? [intval($gs_slas[$gs_priority]['sla_response_minutes']), intval($gs_slas[$gs_priority]['sla_resolution_minutes'])]
                        : GETTING_STARTED_SLAS[$gs_priority]; ?>
                    <tr data-gs-sla="<?= $gs_priority ?>">
                        <th scope="row" class="fw-normal"><?= $gs_priority ?><?= isset($gs_slas[$gs_priority]) ? ' <span class="small text-body-secondary">- ' . escapeHtml($gs_slas[$gs_priority]['sla_name']) . '</span>' : '' ?></th>
                        <td><?= escapeHtml(formatSlaMinutes($gs_response)) ?></td>
                        <td><?= escapeHtml(formatSlaMinutes($gs_resolution)) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php if (count($gs_slas) < count(GETTING_STARTED_SLAS)) { ?>
        <div class="mb-4"><?= settingsSwitch('standard_slas', $gs_use_standard, ['id' => 'gsStandardSlas', 'label' => $gs_slas ? 'Use these for the priorities that have none' : 'Use these',
            'help' => 'Open tickets get them too.']) ?></div>
    <?php } ?>

    <h2 class="h6 fw-semibold<?= count($gs_slas) < count(GETTING_STARTED_SLAS) ? '' : ' mt-4' ?>">Ticket numbers</h2>
    <div class="mb-4"><?= settingsNumberingFields('ticket', (string) ($gs_support['config_ticket_prefix'] ?? ''), intval($gs_support['config_ticket_next_number'] ?? 1)) ?></div>

    <h2 class="h6 fw-semibold">Client portal</h2>
    <div class="mb-4">
        <input type="hidden" name="config_client_portal_enable" value="0">
        <?= settingsSwitch('config_client_portal_enable', intval($gs_support['config_client_portal_enable'] ?? 1) === 1, ['id' => 'gsPortal', 'label' => 'Clients can sign in to raise and follow tickets',
            'help' => 'And to pay invoices, see quotes and documents - what each contact sees is set on the contact. Invite them from their client\'s Contacts.']) ?>
    </div>

    <button type="submit" name="getting_started_support" value="1" class="btn btn-primary" data-gs-save>Save and continue<i class="fas fa-arrow-right ms-2"></i></button>
</form>
