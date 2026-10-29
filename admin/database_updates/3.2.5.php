<?php

/*
 * ITFlow - Database update to version 3.2.5 (from 3.2.4)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // The document template a document was made from - 0 for none, or made before this
    mysqli_query($mysqli, "ALTER TABLE `documents`
        ADD `document_template_id` int(11) NOT NULL DEFAULT 0 AFTER `document_client_id`,
        ADD KEY `document_template_id` (`document_template_id`)");
