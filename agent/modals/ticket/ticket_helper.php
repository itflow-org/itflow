<?php

/*
 * Add an agent to help on a ticket, or change or take off one who is
 * (functions/ticket_helpers.php). GET: ticket_id, and user_id to change that
 * agent's booking. Posts save_ticket_helper (agent/post/ticket_helper.php).
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$row = ticketForModal($_GET['ticket_id'] ?? 0, 2);
$ticket_id = intval($row['ticket_id']);
$lead_id = intval($row['ticket_assigned_to']);
if (!ticketHelpersReady()) {
    echo json_encode(['error' => 'The database update for additional agents has not run yet.']);
    exit;
}
$helpers = ticketHelpers($ticket_id);
$edit_user_id = intval($_GET['user_id'] ?? 0);
$helper = $helpers[$edit_user_id] ?? null;
$is_open = ticketIsOpen($row);

// Booked with the lead, at a time of their own, or not at all
$mode = $helper ? (intval($helper['follows_lead']) === 1 ? 'lead' : ($helper['start'] !== '' ? 'own' : 'none')) : 'lead';
$own_start = $mode === 'own' ? $helper['start'] : (string) ($row['ticket_schedule'] ?? '');
$own_minutes = $mode === 'own' ? ticketScheduleMinutes($helper['start'], $helper['end'])
    : ticketScheduleMinutes($row['ticket_schedule'], $row['ticket_schedule_end'] ?? null);
$lead_when = $row['ticket_schedule'] ? ticketScheduleWhenText($row['ticket_schedule'], $row['ticket_schedule_end'] ?? null) : '';

// Who can be added: the agents who can open the ticket, but the lead and those on it already
$agents = [];
if (!$helper) {
    foreach (ticketMentionAgents($row['ticket_client_id']) as $user_id => $agent) {
        if ($user_id !== $lead_id && !isset($helpers[$user_id])) {
            $agents[$user_id] = $agent;
        }
    }
}
$day_user = $helper ? $edit_user_id : intval(array_key_first($agents) ?? 0);
$day_name = $helper ? $helper['user_name'] : ($agents[$day_user]['name'] ?? '');

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <i class="fa fa-fw fa-user-plus me-2"></i><?= $helper ? escapeHtml($helper['user_name']) . ' on' : 'Add an agent to' ?> <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(ticketReference($row)) ?></span>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">

        <?php if ($helper) { ?>
            <input type="hidden" name="user_id" value="<?= $edit_user_id ?>">
        <?php } elseif (!$agents) { ?>
            <div class="alert alert-info mb-0">Every agent who can open this ticket is on it already.</div>
        <?php } else { ?>
            <div class="mb-3">
                <label class="form-label">Agent</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                    <select class="form-select select2" name="user_id" id="scheduleTech" required>
                        <?php foreach ($agents as $user_id => $agent) { ?>
                            <option value="<?= $user_id ?>" data-name="<?= escapeHtml($agent['name']) ?>"<?= selectOptionData($agent['avatar'] !== '' ? ['image' => $agent['avatar']] : ['initials' => $agent['initials'], 'shape' => 'circle', 'tone' => 'secondary']) ?>><?= escapeHtml($agent['name']) ?><?= $user_id === intval($session_user_id) ? ' (me)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">It stays assigned to <?= $lead_id ? '<strong>' . escapeHtml((string) $row['user_name']) . '</strong>' : 'nobody' ?> - the lead.</div>
            </div>
        <?php } ?>

        <?php if ($helper || $agents) { ?>
            <label class="d-block mb-1">When</label>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="helper_mode" value="lead" id="helperModeLead" <?= $mode === 'lead' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="helperModeLead">Same time as the lead
                        <span class="d-block small text-body-secondary"><?= $lead_when !== '' ? escapeHtml(timezoneWhenFor($row['ticket_schedule'], $row['ticket_schedule_end'] ?? null, $session_user_id)) . ' - moves with it' : 'The lead isn\'t booked yet - this follows once they are' ?></span>
                    </label>
                </div>
                <div class="form-check mt-1">
                    <input class="form-check-input" type="radio" name="helper_mode" value="own" id="helperModeOwn" <?= $mode === 'own' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="helperModeOwn">A time of their own</label>
                </div>
                <div class="form-check mt-1">
                    <input class="form-check-input" type="radio" name="helper_mode" value="none" id="helperModeNone" <?= $mode === 'none' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="helperModeNone">Not booked <span class="small text-body-secondary">- on the ticket, nothing on their calendar</span></label>
                </div>
            </div>

            <div data-helper-own <?= $mode === 'own' ? '' : 'hidden' ?>>
                <div class="row g-2 mb-3">
                    <div class="col-sm-7">
                        <label class="form-label">Date and time</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                            <input type="datetime-local" class="form-control" name="scheduled_date_time" id="scheduleDateTime" value="<?= $own_start ? date('Y-m-d\TH:i', strtotime(timezoneToView($own_start))) : '' ?>">
                        </div>
                    </div>
                    <div class="col-sm-5">
                        <label class="form-label">How long</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-hourglass-half"></i></span>
                            <select class="form-select select2" name="schedule_minutes">
                                <?php foreach (ticketScheduleLengths($own_minutes) as $minutes => $label) { ?>
                                    <option value="<?= intval($minutes) ?>" <?= intval($minutes) === $own_minutes ? 'selected' : '' ?>><?= escapeHtml($label) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>

                <?= timezoneFormHint() ?>

                <!-- What else they have that day (agent/js/ticket_schedule_modal.js) -->
                <div class="border rounded p-2 small mb-3" id="scheduleAgentDay" data-user="<?= $day_user ?>" data-ticket="<?= $ticket_id ?>" data-name="<?= escapeHtml($day_name) ?>" hidden>
                    <div class="d-flex align-items-center mb-1">
                        <span class="fw-semibold"><i class="far fa-calendar-alt fa-fw text-secondary me-1"></i><span class="js-day-title"></span></span>
                        <a class="ms-auto js-day-open" href="calendar.php"<?= moduleOn('calendar') ? '' : ' hidden' ?>>Calendar</a>
                    </div>
                    <div class="js-day-items"></div>
                </div>
            </div>

            <div class="form-text">They find the ticket under Mine, get the client's replies by email, and are sent their booking with a calendar file.</div>
        <?php } ?>
    </div>

    <div class="modal-footer">
        <?php if ($helper) { ?>
            <a href="post.php?remove_ticket_helper=<?= $ticket_id ?>&user_id=<?= $edit_user_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-outline-danger confirm-link me-auto"
                data-confirm-title="Take <?= escapeHtml($helper['user_name']) ?> off this ticket?" data-confirm-button="Take off"
                data-confirm-text="<?= $helper['start'] !== '' ? 'Their booking is cancelled, and they are emailed the cancellation.' : 'It goes from their lists.' ?>">
                <i class="fa fa-user-minus me-2"></i>Take Off
            </a>
        <?php } ?>
        <?php if (($helper || $agents) && $is_open) { ?>
            <button type="submit" name="save_ticket_helper" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i><?= $helper ? 'Save' : 'Add' ?></button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    // A time of their own shows its fields - one listener, however often the window opens
    if (!window.itflowHelperModeBound) {
        window.itflowHelperModeBound = true;
        document.addEventListener('change', function (e) {
            if (e.target.name !== 'helper_mode' || !e.target.form) {
                return;
            }
            const own = e.target.form.querySelector('[data-helper-own]');
            own.hidden = e.target.value !== 'own';
            const field = e.target.form.querySelector('#scheduleDateTime');
            if (!own.hidden && field) {
                field.dispatchEvent(new Event('change'));
            }
        });
    }
</script>
<script src="<?= staticFileUrl('/agent/js/ticket_schedule_modal.js') ?>"></script>

<?php
require_once '../../../includes/modal_footer.php';
