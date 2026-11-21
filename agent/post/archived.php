<?php

/*
 * ITFlow - POST handlers for Archived (agent/archived.php, functions/archived.php)
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// The ticked rows restored together - each kind:id as its own Restore would, skipping any not the agent's to restore
if (isset($_POST['bulk_restore_archived'])) {

    validateCSRFToken();

    $count = 0;
    $skipped = 0;
    foreach (array_unique(array_map('strval', (array) ($_POST['archived_items'] ?? []))) as $item) {
        if (!preg_match('/^([a-z_]+):(\d+)$/', $item, $match)) {
            continue;
        }
        if (archivedRestore($match[1], $match[2]) === null) {
            $skipped++;
            continue;
        }
        $count++;
    }

    flashAlert("Restored <strong>$count</strong> record" . ($count === 1 ? '' : 's') . ($skipped ? " - $skipped couldn't be" : ''), $skipped && !$count ? 'error' : 'success');

    redirect();

}
