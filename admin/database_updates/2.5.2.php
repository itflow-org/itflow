<?php

/*
 * ITFlow - Database update to version 2.5.2 (from 2.5.1)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Resolving a ticket from the kanban board or the client portal recorded
    // the resolution timestamp but never judged the resolution SLA, so those
    // tickets carry a target, a resolution time and no verdict. They read as
    // still-in-flight and are left out of SLA reporting entirely.
    //
    // Both timestamps were stored, so the verdict is simply recomputed from
    // them. Only rows with no verdict at all are touched - anything already
    // judged keeps the answer it was given at the time.
    mysqli_query($mysqli, "UPDATE tickets
        SET ticket_resolution_sla_met = (ticket_resolved_at <= ticket_resolution_due_at)
        WHERE ticket_sla_id > 0
        AND ticket_resolution_due_at IS NOT NULL
        AND ticket_resolved_at IS NOT NULL
        AND ticket_resolution_sla_met IS NULL");

    // Same repair on the response track, for any ticket whose first response
    // was recorded without the verdict being written alongside it
    mysqli_query($mysqli, "UPDATE tickets
        SET ticket_response_sla_met = (ticket_first_response_at <= ticket_response_due_at)
        WHERE ticket_sla_id > 0
        AND ticket_response_due_at IS NOT NULL
        AND ticket_first_response_at IS NOT NULL
        AND ticket_response_sla_met IS NULL");
