<?php

// The service's fields from POST as serviceFormSet() takes them - on an update,
// what isn't sent stays as the service has it ($service_row)

$service_row = $service_row ?? null;

$service_post = [];
foreach (['name', 'description', 'category', 'importance', 'backup', 'notes', 'review_interval', 'review_due'] as $field) {
    if (array_key_exists("service_$field", $_POST)) {
        $service_post[$field] = is_scalar($_POST["service_$field"]) ? (string) $_POST["service_$field"] : '';
    } elseif ($service_row) {
        $service_post[$field] = (string) ($service_row["service_$field"] ?? '');
    }
}

// A new schedule with no date sent works its date out afresh, as the form does
if ($service_row && array_key_exists('service_review_interval', $_POST) && !array_key_exists('service_review_due', $_POST)) {
    $service_post['review_due'] = '';
}
