<?php

/*
 * ITFlow - Database update to version 2.7.0 (from 2.6.9)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // The five built-in ticket statuses now have fixed SLA clock behaviour, matching
    // getTicketStatusSlaLock() in functions/app.php: On Hold, Resolved and Closed
    // pause the resolution clock, New and Open run it. Admin > Ticket Statuses no
    // longer offers the choice on these five - only on custom statuses.
    //
    // On Hold shipped with the column defaulting to 0, so a stock install breached
    // the resolution SLA on tickets parked waiting for a client or a vendor. Every
    // install had to find the toggle themselves; this sets it for them.
    //
    // Resolved and Closed are set for consistency across the SLA surfaces, not to
    // change behaviour - both already stopped the clocks via ticket_resolved_at and
    // ticket_closed_at, which is what the cron and syncTicketSlaClock() actually read.
    mysqli_query($mysqli, "UPDATE ticket_statuses SET ticket_status_pauses_sla = 1 WHERE ticket_status_id IN (3, 4, 5)");
    mysqli_query($mysqli, "UPDATE ticket_statuses SET ticket_status_pauses_sla = 0 WHERE ticket_status_id IN (1, 2)");

    /*
     * Tickets already parked in On Hold are still holding an open sla_history
     * interval, so their paused time would keep counting as consumed. Closing those
     * intervals needs business-hours maths in the app timezone, and this file also
     * runs from scripts/update_cli.php, which sets neither - so the first
     * cron/ticket_sla.php run after the upgrade reconciles them instead, where the
     * timezone is set. Nothing is lost by waiting a minute: the same cron no longer
     * warns or breaches a paused ticket the moment the flag above lands.
     *
     * Breach verdicts already recorded against on-hold tickets are deliberately left
     * alone. They were recorded by a clock that really was running under the old
     * rules, and rewriting a stored miss is exactly what the reopen-integrity rules
     * in functions/sla.php exist to prevent. Re-stamp an individual ticket (change
     * its priority or its SLA) if you want it re-judged.
     */
