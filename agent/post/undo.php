<?php

/*
 * ITFlow - Undo on the message after an archive, a bulk change or a move (functions/undo.php)
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_GET['undo'])) {

    validateCSRFToken();

    [$ok, $message] = undoApply((string) $_GET['undo']);

    flashAlert($message, $ok ? 'success' : 'error');

    redirect();

}
