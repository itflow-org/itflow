<?php

/*
 * The Import window (agent/modals/import/import.php) asks here, in JSON.
 * import_check reads the file and says what importing it would do - its
 * columns, the field each is taken as, a few of each column's values, and what
 * each row would do, found by importing it and rolling that back
 * (functions/import.php). import_run imports it. import_template is a kind's
 * template.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['import_check']) || isset($_POST['import_run'])) {

    validateCSRFToken();

    $answer = function (array $answer) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($answer);
        exit;
    };

    $def = importKind((string) ($_POST['kind'] ?? ''));
    if (!$def) {
        $answer(['ok' => false, 'error' => "That isn't something ITFlow imports."]);
    }
    $client_id = !empty($def['client']) ? intval($_POST['client_id'] ?? 0) : 0;
    $refusal = importRefusal($def, $client_id);
    if ($refusal !== '') {
        $answer(['ok' => false, 'error' => $refusal]);
    }

    [$headings, $rows, $error] = importFile();
    if ($error !== '') {
        $answer(['ok' => false, 'error' => $error]);
    }
    $map = importMapFrom($def, $headings, (string) ($_POST['map'] ?? ''));
    $mode = ($_POST['mode'] ?? '') === 'update' ? 'update' : 'skip';
    $run = isset($_POST['import_run']);
    if ($run && !$map) {
        $answer(['ok' => false, 'error' => 'None of the columns is set to be imported - pick what each one is.']);
    }
    set_time_limit(600);

    $result = importProcess($def, $client_id, $rows, $map, $mode, $run);

    // A few of each column's values, to tell what it is
    $samples = [];
    foreach (array_keys($headings) as $index) {
        $values = [];
        foreach ($rows as [, $cells]) {
            $value = mb_substr((string) ($cells[$index] ?? ''), 0, 60);
            if ($value !== '' && !in_array($value, $values, true)) {
                $values[] = $value;
            }
            if (count($values) >= 3) {
                break;
            }
        }
        $samples[] = $values;
    }

    $reply = [
        'ok' => true,
        'rows' => count($rows),
        'headings' => $headings,
        'samples' => $samples,
        'map' => (object) $map,
        'missing' => importMissing($def, $map),
        'added' => $result['added'],
        'updated' => $result['updated'],
        'skipped' => $result['skipped'],
        'failed' => $result['failed'],
        'list' => array_slice($result['rows'], 0, 500),
    ];

    if ($run) {
        $client_name = $client_id ? ' for ' . escapeSql((string) getFieldById('clients', $client_id, 'client_name')) : '';
        logAudit(ucfirst($def['one']), "Import", "$session_name imported " . strtolower($def['title']) . $client_name . " from a CSV file - {$result['added']} added, {$result['updated']} updated, {$result['skipped']} skipped, {$result['failed']} not imported", $client_id);
        $reply['failed_rows'] = $result['failed_rows'];
        $reply['finish'] = isset($def['finish']) ? (string) call_user_func($def['finish'], $result) : '';
    }

    $answer($reply);

}

if (isset($_GET['import_template'])) {

    $def = importKind((string) $_GET['import_template']);
    if (!$def) {
        flashAlert("That isn't something ITFlow imports.", 'error');
        redirect();
    }

    enforceUserPermission($def['module']);

    // Named for the client only when it's one this agent can reach - any id would otherwise say its client's name back
    $client_id = intval($_GET['client_id'] ?? 0);
    $client_name = !empty($def['client']) && $client_id && hasClientAccess($client_id) ? (string) getFieldById('clients', $client_id, 'client_name') : '';

    importTemplateDownload($def, $client_name !== '' ? toAlphanumeric($client_name) . '-' : '');

}
