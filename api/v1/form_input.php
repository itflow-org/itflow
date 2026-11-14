<?php

/*
 * API - the save functions behind ITFlow's forms take the form's fields ($in); an
 * API call sends them by their column names. apiFormInput() builds $in from what was sent,
 * and on an update from the record's own values ($row) for what wasn't. $map is
 * API field => [$in key, $row column].
 */

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

if (!function_exists('apiFormInput')) {
    function apiFormInput(array $map, $row = null) {
        $in = [];
        foreach ($map as $field => [$key, $column]) {
            if (array_key_exists($field, $_POST)) {
                $in[$key] = is_scalar($_POST[$field]) ? (string) $_POST[$field] : '';
            } elseif ($row && $column !== null && array_key_exists($column, $row)) {
                $in[$key] = (string) $row[$column];
            }
        }
        return $in;
    }

    /** 400 with the save function's words, as a sentence. */
    function apiFormRefuse($error) {
        global $return_arr;
        http_response_code(400);
        $error = rtrim(html_entity_decode(strip_tags((string) $error), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $return_arr['success'] = "False";
        $return_arr['message'] = preg_match('/[.!?]$/', $error) ? $error : "$error.";
        echo json_encode($return_arr);
        exit();
    }
}
