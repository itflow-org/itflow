<?php

/*
 * ITFlow - Database update to version 2.6.3 (from 2.6.2)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Add Indexes to Ticket Replies foreign key to ticket_id and same with Attachment Fixes a slow down issue with many Replies with large bodies.

    mysqli_query($mysqli, "ALTER TABLE ticket_replies ADD INDEX (ticket_reply_ticket_id, ticket_reply_archived_at)");
    mysqli_query($mysqli, "ALTER TABLE ticket_attachments ADD INDEX (ticket_attachment_reply_id)");
