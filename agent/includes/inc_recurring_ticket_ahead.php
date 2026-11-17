<?php

/*
 * The Schedule tab's booking and making ahead, in New and Edit Recurring
 * Ticket (agent/modals/recurring_ticket/) - the time and visit each ticket is
 * booked for, and how many days ahead it is made (functions/ticket.php). Set
 * $ahead_row to the recurring ticket when editing, null for a new one. Only
 * after the 3.7.7 update. Saved by recurringTicketFromPost()
 * (agent/post/recurring_ticket.php).
 */

isset($mysqli) || die("Direct file access is not allowed");

if (recurringAheadReady()) {
    $ahead_time = substr((string) ($ahead_row['recurring_ticket_schedule_time'] ?? ''), 0, 5);
    $ahead_onsite = intval($ahead_row['recurring_ticket_schedule_onsite'] ?? 0);
    $ahead_days = intval($ahead_row['recurring_ticket_lead_days'] ?? 0);
    $ahead_mode = ($ahead_row['recurring_ticket_lead_mode'] ?? '') === 'month' ? 'month' : 'days';
    $ahead_next = $ahead_row ? substr((string) $ahead_row['recurring_ticket_next_run'], 0, 10) : '';
    ?>
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label>Book the work for</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                    <input type="time" class="form-control" name="schedule_time" step="900" value="<?= escapeHtml($ahead_time) ?>">
                    <select class="form-select select2" name="schedule_onsite">
                        <option value="0" <?= $ahead_onsite ? '' : 'selected' ?>>Remote</option>
                        <option value="1" <?= $ahead_onsite ? 'selected' : '' ?>>Onsite</option>
                    </select>
                </div>
                <div class="form-text">Each ticket is booked for its run day at this time, with the emails and calendar invite booking it on the ticket sends. Leave it empty to book nothing.</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label>Make it ahead</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar-minus"></i></span>
                    <input type="number" class="form-control" name="lead_days" min="0" max="90" value="<?= $ahead_days ?>" style="max-width: 5.5rem">
                    <select class="form-select select2" name="lead_mode">
                        <option value="days" <?= $ahead_mode === 'days' ? 'selected' : '' ?>>days before its run day</option>
                        <option value="month" <?= $ahead_mode === 'month' ? 'selected' : '' ?>>days before the 1st of its month</option>
                    </select>
                </div>
                <div class="form-text">0 makes it on its run day, which stays the day the work is for. Before the 1st of its month makes a month's runs on one day - 3 makes all of December's on November 28 - for monthly and longer repeats.</div>
                <?php if ($ahead_next !== '' && $ahead_days > 0) { ?>
                    <div class="small mt-1"><i class="far fa-fw fa-calendar-plus me-1"></i>The next one is made <?= escapeHtml(date('M j, Y', strtotime(max(recurringTicketMadeOn($ahead_row, $ahead_next), date('Y-m-d'))))) ?>, for <?= escapeHtml(date('M j, Y', strtotime($ahead_next))) ?>.</div>
                <?php } ?>
            </div>
        </div>
    </div>
<?php }
