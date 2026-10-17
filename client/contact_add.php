<?php
/*
 * Client Portal
 * Adding a contact is on the contact page now - old links land there.
 */

require_once '../config.php';
require_once '../includes/load_global_settings.php';
require_once '../functions.php';
require_once 'includes/check_login.php';

redirect('contact.php?new=1');
