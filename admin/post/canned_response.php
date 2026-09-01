<?php

// Canned Responses

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_canned_response'])) {

    validateCSRFToken();

    $name = escapeSql($_POST['name']);

    // The body is TinyMCE HTML, so it goes in the way ticket replies and ticket template
    // details do - escaped for the query only, and purified where it is rendered
    $body = mysqli_real_escape_string($mysqli, $_POST['body']);

    // 0 is the general case: offered on every ticket whatever its category
    $category_id = intval($_POST['category']);

    mysqli_query($mysqli, "INSERT INTO canned_responses SET canned_response_name = '$name', canned_response_body = '$body', canned_response_category_id = $category_id");

    $canned_response_id = mysqli_insert_id($mysqli);

    logAudit("Canned Response", "Create", "$session_name created canned response $name", 0, $canned_response_id);

    flashAlert("Canned Response <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_canned_response'])) {

    validateCSRFToken();

    $canned_response_id = intval($_POST['canned_response_id']);
    $name = escapeSql($_POST['name']);
    $body = mysqli_real_escape_string($mysqli, $_POST['body']);
    $category_id = intval($_POST['category']);

    mysqli_query($mysqli, "UPDATE canned_responses SET canned_response_name = '$name', canned_response_body = '$body', canned_response_category_id = $category_id WHERE canned_response_id = $canned_response_id");

    logAudit("Canned Response", "Edit", "$session_name edited canned response $name", 0, $canned_response_id);

    flashAlert("Canned Response <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['delete_canned_response'])) {

    validateCSRFToken();

    $canned_response_id = intval($_GET['delete_canned_response']);

    $name = escapeSql(getFieldById('canned_responses', $canned_response_id, 'canned_response_name'));

    mysqli_query($mysqli, "DELETE FROM canned_responses WHERE canned_response_id = $canned_response_id");

    logAudit("Canned Response", "Delete", "$session_name deleted canned response $name", 0, $canned_response_id);

    flashAlert("Canned Response <strong>$name</strong> deleted", 'error');

    redirect();

}
