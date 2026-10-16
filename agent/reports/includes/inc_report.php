<?php

/*
 * A report page's start, before anything is drawn: signed in, and allowed to
 * use reports at all. The page then checks its own report (reportEnforce()),
 * works out its numbers, sends a CSV if one was asked for, and only then
 * draws the page with inc_report_layout.php - so an export is never preceded
 * by HTML.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/check_login.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/page_title.php';

enforceUserPermission('module_reporting');
