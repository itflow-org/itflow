<?php

/*
 * ITFlow - POST handler for the audit log: exporting it to CSV or PDF, with
 * the filters and view the export window posts - the same ones the list
 * uses (auditLogFiltersSql(), functions/audit_log.php). The export is itself
 * logged, as data taken out.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isExportRequest('export_audit_logs')) {

    validateCSRFToken();

    enforceAdminPermission();

    $format = resolveExportFormat($_POST['export_audit_logs']);

    $filter_summary = [];
    $where = auditLogFiltersSql($_POST, $filter_summary);

    $joins = auditLogJoinsSql();
    $num_rows = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM logs $joins WHERE $where"))[0]);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('audit_logs', $format, "$session_company_name-Audit-Logs", 'Audit Logs', summarizeExportFilters($filter_summary));

        // Written 500 at a time - each lot's records looked up together, one query per kind
        $sql = mysqli_query($mysqli, "SELECT log_id, log_type, log_action, log_description, log_ip, log_user_agent, log_created_at, log_client_id, log_user_id, log_entity_id,
                user_name, user_email, user_avatar, user_type, client_name
            FROM logs $joins
            WHERE $where
            ORDER BY log_created_at DESC, log_id DESC");
        $write = function (array $chunk) use (&$export) {
            $records = auditLogRecords($chunk);
            foreach ($chunk as $row) {
                $who = auditLogWho($row);
                $row['audit_who'] = $who['name'] . ($who['kind'] === 'portal' ? ' (portal)' : '');
                $row['audit_record'] = $records[$row['log_type'] . '|' . intval($row['log_entity_id'])]['name'] ?? '';
                $row['audit_device'] = auditLogDevice($row['log_user_agent']);
                addExportRow($export, $row);
            }
        };
        $chunk = [];
        while ($row = mysqli_fetch_assoc($sql)) {
            $chunk[] = $row;
            if (count($chunk) === 500) {
                $write($chunk);
                $chunk = [];
            }
        }
        if ($chunk) {
            $write($chunk);
        }

        finishExport($export);

        logAudit("Audit Log", "Export", "$session_name exported $num_rows audit log " . ($num_rows === 1 ? 'entry' : 'entries') . " to a " . strtoupper($format) . " file");

        exit;
    }

    flashAlert('No audit log entries match - nothing to export.', 'error');

    redirect();

}
