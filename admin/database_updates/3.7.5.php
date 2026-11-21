<?php

/*
 * ITFlow - Database update to version 3.7.5 (from 3.7.4)
 * Included by admin/database_updates.php - do not access directly
 *
 * The To line on a ticket's email replies (functions/ticket_reply_recipients.php):
 * who was put in or taken out of it, kept for the ticket so its next email reply
 * starts from the same people. A row only where the choice differs from the usual
 * - the contact, watchers and colleagues in, everyone else out - so an address
 * typed in is kept while it stays in. They go with the ticket, and when it moves
 * to another client.
 *
 *   mail_choice_ticket_id   the ticket
 *   mail_choice_email       the address, lower case
 *   mail_choice_send        1 in To, 0 taken out
 *   mail_choice_by          the agent who sent the reply
 *   mail_choice_updated_at  when
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `ticket_mail_choices` (
        `mail_choice_ticket_id` int(11) NOT NULL,
        `mail_choice_email` varchar(255) NOT NULL,
        `mail_choice_send` tinyint(1) NOT NULL DEFAULT 0,
        `mail_choice_by` int(11) NOT NULL DEFAULT 0,
        `mail_choice_updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`mail_choice_ticket_id`,`mail_choice_email`),
        CONSTRAINT `ticket_mail_choices_ibfk_1` FOREIGN KEY (`mail_choice_ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
