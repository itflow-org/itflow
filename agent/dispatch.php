<?php

/*
 * Dispatch (functions/dispatch.php) - each agent's booked work beside the
 * tickets still to give out. Day: a column per agent. Week: a row per agent and
 * a column per day. Drag a ticket from the queue onto an agent and a time, or a
 * booking to another agent or time, and the Schedule window opens; drag a
 * booking back onto the queue to take it off the schedule. Workload: each
 * agent's open tickets, SLAs and booked hours, flagged when they fall behind or
 * carry far more than the team - drop a ticket on an agent to give it to them.
 * This week: tickets in and closed, SLA results and each agent's share.
 * agent/js/dispatch.js draws it from ajax.php ?dispatch_*. ?date=Y-m-d opens on
 * that day.
 */

require_once "includes/inc_all.php";

enforceUserPermission('module_support');
if (intval($config_module_enable_ticketing) !== 1) {
    flashAlert('Ticketing is switched off (Admin > Settings > Modules).', 'error');
    redirect('/agent/calendar.php');
}

$agents = dispatchAgents();
$state = dispatchState();
$can_edit = dispatchCanEdit();
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_config_calendar_first_day FROM user_settings WHERE user_id = $session_user_id"));
$first_day = intval($row['user_config_calendar_first_day'] ?? 0);
$clients = mysqli_fetch_all(mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL" . clientScopeSql('client_id') . " ORDER BY client_name"), MYSQLI_ASSOC);
$tell_client = intval($config_ticket_client_general_notifications) === 1;

?>

<div class="itflow-dispatch" id="itflowDispatch"
    data-csrf="<?= $_SESSION['csrf_token'] ?>"
    data-can-edit="<?= $can_edit ? 1 : 0 ?>"
    data-agents="<?= escapeHtml(json_encode(array_values($agents))) ?>"
    data-state="<?= escapeHtml(json_encode($state)) ?>"
    data-first-day="<?= $first_day ?>"
    data-default-minutes="<?= CALENDAR_TICKET_MINUTES ?>"
    data-lengths="<?= escapeHtml(json_encode(DISPATCH_LENGTHS)) ?>"
    data-business-hours="<?= escapeHtml(json_encode(dispatchBusinessHours())) ?>"
    data-zone="<?= timezoneShifted() ? escapeHtml(timezoneView()) : '' ?>"
    data-initial-date="<?= calendarIsDate($_GET['date'] ?? '') ? escapeHtml($_GET['date']) : substr(timezoneNow(), 0, 10) ?>">

    <div class="card mb-3">
        <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2">
            <h3 class="card-title me-auto my-1">
                <i class="fas fa-fw fa-people-arrows me-2"></i>Dispatch
                <?php if (!$can_edit) { ?>
                    <span class="badge text-bg-secondary ms-2 align-middle" title="Booking and moving tickets takes Support at Modify in your role">View only</span>
                <?php } ?>
                <span class="fw-normal text-body-secondary ms-2 small" data-dispatch-title></span>
            </h3>
            <?php if (timezoneShifted()) { ?>
                <?= timezoneNote() ?>
            <?php } ?>
            <div class="btn-group btn-group-sm" role="group" aria-label="View">
                <button type="button" class="btn btn-outline-secondary" data-dispatch-view="day">Day</button>
                <button type="button" class="btn btn-outline-secondary" data-dispatch-view="week">Week</button>
                <button type="button" class="btn btn-outline-secondary" data-dispatch-view="workload">Workload</button>
            </div>
            <div class="btn-group btn-group-sm" role="group" aria-label="Move">
                <button type="button" class="btn btn-outline-secondary" data-dispatch-nav="-1" title="Back"><i class="fas fa-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-dispatch-nav="0">Today</button>
                <button type="button" class="btn btn-outline-secondary" data-dispatch-nav="1" title="Forward"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="input-group input-group-sm w-auto">
                <span class="input-group-text"><i class="fas fa-fw fa-calendar-day"></i></span>
                <input type="date" class="form-control" data-dispatch-date aria-label="Go to a day">
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"><i class="fas fa-users me-2"></i>Agents <span data-dispatch-agent-count></span></button>
                <div class="dropdown-menu dropdown-menu-end p-2 itflow-dispatch-agents">
                    <?php foreach ($agents as $agent) { ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="<?= intval($agent['id']) ?>" id="dispatchAgent<?= intval($agent['id']) ?>" data-dispatch-agent <?= in_array($agent['id'], $state['hidden'], true) ? '' : 'checked' ?>>
                            <label class="form-check-label" for="dispatchAgent<?= intval($agent['id']) ?>"><span class="itflow-dispatch-dot me-1" style="background: <?= escapeHtml($agent['color']) ?>"></span><?= escapeHtml($agent['name']) ?></label>
                        </div>
                    <?php } ?>
                    <hr class="my-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="dispatchWeekends" data-dispatch-weekends <?= $state['weekends'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="dispatchWeekends">Weekends in Week</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card mb-0 itflow-dispatch-pane">
                <div class="card-header p-2">
                    <ul class="nav nav-pills nav-fill small flex-nowrap" role="tablist">
                        <li class="nav-item"><a class="nav-link py-1 px-1" href="#" data-dispatch-tab="unassigned">No agent <span class="badge text-bg-danger" data-dispatch-count="unassigned"></span></a></li>
                        <li class="nav-item"><a class="nav-link py-1 px-1" href="#" data-dispatch-tab="unbooked">Not booked <span class="badge text-bg-warning" data-dispatch-count="unbooked"></span></a></li>
                        <li class="nav-item"><a class="nav-link py-1 px-1" href="#" data-dispatch-tab="left_behind" title="Open tickets booked on a day that has gone">Left behind <span class="badge text-bg-danger" data-dispatch-count="left_behind"></span></a></li>
                    </ul>
                    <div class="mt-2 d-flex flex-column gap-1">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
                            <input type="search" class="form-control" placeholder="Search tickets" data-dispatch-filter="q" aria-label="Search tickets">
                        </div>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                            <select class="form-select select2" data-dispatch-filter="client_id" aria-label="Client">
                                <option value="">All clients</option>
                                <?php foreach ($clients as $client) { ?>
                                    <option value="<?= intval($client['client_id']) ?>"><?= escapeHtml($client['client_name']) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-fw fa-thermometer-half"></i></span>
                            <select class="form-select select2" data-dispatch-filter="priority" aria-label="Priority">
                                <option value="">Any priority</option>
                                <?php foreach (TICKET_PRIORITIES as $priority) { ?>
                                    <option><?= escapeHtml($priority) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body p-2 itflow-dispatch-queue" data-dispatch-queue></div>
                <div class="card-footer small text-body-secondary py-2"><?= $can_edit ? 'Drag a ticket onto an agent and a time. Drag booked work back here to take it off the schedule. On Day, drag over an agent\'s empty time to raise a new ticket there.' : 'Your role can look at Dispatch, not book from it.' ?></div>
            </div>
        </div>
        <div class="col-lg-9">
            <div class="card mb-0 itflow-dispatch-pane">
                <div class="card-body p-0" data-dispatch-board></div>
            </div>
        </div>
    </div>

    <!-- This week (agent/js/dispatch.js loadStats) -->
    <div class="card mt-3">
        <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2">
            <h3 class="card-title my-1 me-auto"><i class="fas fa-fw fa-chart-bar me-2"></i>This week <span class="fw-normal text-body-secondary small ms-1" data-dispatch-week></span></h3>
            <span class="small text-body-secondary">Every ticket you can see - the table has the agents ticked under Agents</span>
        </div>
        <div class="card-body" data-dispatch-stats>
            <div class="text-body-secondary small">Loading...</div>
        </div>
    </div>
</div>

<!-- The Schedule window: who, when, how long, onsite or remote -->
<div class="modal fade" id="dispatchScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" autocomplete="off" data-dispatch-schedule>
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-calendar-check me-2"></i>Schedule <small class="ms-1 opacity-75" data-dispatch-schedule-ref></small></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="border rounded p-2 mb-3 small" data-dispatch-schedule-ticket></div>
                <!-- The contact's other open tickets - a duplicate can be merged before it is booked -->
                <div class="border rounded p-2 mb-3 small overflow-auto" style="max-height: 18rem;" data-dispatch-schedule-others hidden></div>
                <div class="mb-3">
                    <label>Agent</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                        <select class="form-select select2" name="agent_id" required>
                            <?php foreach ($agents as $agent) { ?>
                                <option value="<?= intval($agent['id']) ?>"<?= selectOptionColor($agent['color']) ?>><?= escapeHtml($agent['name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-7">
                        <label>Date and time</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                            <input type="datetime-local" class="form-control" name="start" required>
                        </div>
                    </div>
                    <div class="col-sm-5">
                        <label>How long</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-hourglass-half"></i></span>
                            <select class="form-select select2" name="minutes"></select>
                        </div>
                    </div>
                </div>
                <?= timezoneFormHint() ?>
                <div class="mb-3">
                    <label>Visit</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        <select class="form-select select2" name="onsite">
                            <option value="0">Remote</option>
                            <option value="1">Onsite</option>
                        </select>
                    </div>
                </div>
                <?php if ($tell_client) { ?>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" name="tell_client" value="1" id="dispatchTellClient" checked>
                        <label class="form-check-label" for="dispatchTellClient">Email the contact and watchers the time</label>
                    </div>
                <?php } ?>
                <div class="form-text"><?= $tell_client ? 'The agent is always emailed it, with a calendar file.' : 'The agent is emailed it, with a calendar file.' ?></div>
                <div class="alert alert-danger small mt-3 mb-0" data-dispatch-schedule-error hidden></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Schedule it</button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Asking before something is changed - taking a booking off -->
<div class="modal fade" id="dispatchConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" data-dispatch-confirm-title></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body small" data-dispatch-confirm-text></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-dispatch-confirm-ok>Take it off</button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>

<link href="<?= staticFileUrl('/libs/fullcalendar/skeleton.css') ?>" rel="stylesheet">
<link href="<?= staticFileUrl('/libs/fullcalendar/themes/classic/theme.css') ?>" rel="stylesheet">
<link href="<?= staticFileUrl('/libs/fullcalendar/themes/classic/palette.css') ?>" rel="stylesheet">
<script src="<?= staticFileUrl('/libs/fullcalendar/fullcalendar.global.js') ?>"></script>
<script src="<?= staticFileUrl('/libs/fullcalendar/themes/classic/global.js') ?>"></script>
<script src="<?= staticFileUrl('/libs/SortableJS/Sortable.min.js') ?>"></script>
<script src="<?= staticFileUrl('/agent/js/dispatch.js') ?>"></script>
