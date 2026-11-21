<?php

/*
 * ITFlow - Health address for an uptime monitor (functions/cron_health.php)
 *
 * GET /health.php?key=<key> answers 200 "OK" while cron is running and every
 * job that's on is keeping up, and 503 with a line for each problem otherwise
 * (no error text). Point a monitor (Uptime Kuma, UptimeRobot...) at it every
 * 5 minutes: it's the only thing that can tell anyone cron itself has stopped.
 * The key can also come in an X-Health-Key header, which keeps it out of the
 * web server's log. The address is on Maintenance > Cron, where it can be
 * renewed; a wrong key, or none made yet, is a 403.
 */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if (!file_exists(__DIR__ . '/config.php')) {
    http_response_code(503);
    exit("PROBLEM\n- ITFlow is not set up\n");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/inc_set_timezone.php';
require_once __DIR__ . '/functions.php';

try {
    $health_key = cronHealthReady() ? (string) (cronHealthSettings()['config_cron_health_key'] ?? '') : '';
} catch (Throwable $e) {
    http_response_code(503);
    exit("PROBLEM\n- The health check could not read the database\n");
}

$health_given = (string) ($_SERVER['HTTP_X_HEALTH_KEY'] ?? ($_GET['key'] ?? ''));
if ($health_key === '' || !hash_equals($health_key, $health_given)) {
    http_response_code(403);
    exit("Forbidden\n");
}

try {
    $health_problems = cronHealthProblems();
} catch (Throwable $e) {
    http_response_code(503);
    exit("PROBLEM\n- The health check could not read the database\n");
}

if (!$health_problems) {
    exit("OK\n");
}

http_response_code(503);
echo "PROBLEM\n";
foreach ($health_problems as $health_problem) {
    echo "- {$health_problem['label']}: {$health_problem['kind']}\n";
}
