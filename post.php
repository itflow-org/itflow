<?php

/*
 * ITFlow - Main GET/POST request handler
 */

require_once("config.php");
require_once("functions.php");
require_once("check_login.php");

// Load specific module logic
require_once("post/account.php");
require_once("post/account_type.php");
require_once("post/api.php");
require_once("post/asset.php");
require_once("post/category.php");
require_once("post/certificate.php");
require_once("post/client.php");
require_once("post/contact.php");
require_once("post/custom_field.php");
require_once("post/document.php");
require_once("post/folder.php");
require_once("post/domain.php");
require_once("post/event.php");
require_once("post/expense.php");
require_once("post/file.php");
require_once("post/invoice.php");
require_once("post/location.php");
require_once("post/login.php");
require_once("post/network.php");
require_once("post/product.php");
require_once("post/profile.php");
require_once("post/quote.php");
require_once("post/revenue.php");
require_once("post/service.php");
require_once("post/service_template.php");
require_once("post/setting.php");
require_once("post/software.php");
require_once("post/tag.php");
require_once("post/tax.php");
require_once("post/ticket.php");
require_once("post/transfer.php");
require_once("post/trip.php");
require_once("post/user.php");
require_once("post/vendor.php");
require_once("post/budget.php");
require_once("post/misc.php");

?>

