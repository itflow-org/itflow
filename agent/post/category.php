<?php

/*
 * ITFlow - a new category from the + beside a category picker in a form on
 * an agent page (admin/modals/category/category_add.php posts to the page's
 * own post.php). Admins only, as the window is. The work is in
 * functions/categories.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_category'])) {

    validateCSRFToken();

    if (empty($session_is_admin)) {
        flashAlert("Only an admin can add categories", 'error');
        redirect();
    }

    [$fields, $error] = categoryFromInput($_POST);
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $category_id = categoryCreate($fields);
    $kind = categoryKind($fields['type'])['one'] ?? 'category';

    logAudit("Category", "Create", "$session_name created $kind " . escapeSql($fields['name']), 0, $category_id);

    flashAlert(ucfirst($kind) . " <strong>" . escapeHtml($fields['name']) . "</strong> created");

    redirect();

}
