<?php

/*
 * ITFlow - Database update to version 2.7.7 (from 2.7.6)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // history_description was varchar(200). That was enough while every entry
    // was a fixed sentence ("Invoice created by <user>"), but the send-email
    // and mark-sent flows now record who the document actually went to - a
    // "Name <email>" pair per recipient - and the mark-sent note the agent
    // types. Five billing contacts alone blows past 200 characters, and under
    // strict mode an over-long value is an error, not a truncation, so the
    // history row would be lost and the send would 500.
    //
    // text rather than a bigger varchar: there is no index on this column and
    // no length worth defending, so a cap would only be a future bug.
    mysqli_query($mysqli, "ALTER TABLE `history` MODIFY `history_description` text NOT NULL");
