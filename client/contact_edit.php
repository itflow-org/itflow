<?php
/*
 * Client Portal
 * A contact is changed on its own page now - old links land there.
 */

require_once '../config.php';
require_once '../includes/load_global_settings.php';
require_once '../functions.php';
require_once 'includes/check_login.php';

redirect('contact.php?id=' . intval($_GET['id'] ?? 0));
