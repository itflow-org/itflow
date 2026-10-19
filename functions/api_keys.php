<?php

/*
 * ITFlow - API keys (Admin > API Keys): each acts as an agent, with that
 * agent's role and client access, until the day it expires. A key is only
 * as good as its agent - a disabled, archived or role-less one turns every
 * call away (api/v1/enforce_api_rbac.php).
 *
 * Shared by admin/api_keys.php, admin/modals/api/* and admin/post/api_keys.php.
 */

/* One key - the row with its agent's name, picture, type, status, archived and role - or null. Admin > API Keys lists them the same way. */
function apiKeyRow($api_key_id) {
    global $mysqli;
    $api_key_id = intval($api_key_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT api_key_id, api_key_name, api_key_secret, api_key_created_at, api_key_expire, api_key_user_id, api_key_last_used_at,
            user_name, user_avatar, user_type, user_status, user_archived_at, user_role_id
        FROM api_keys LEFT JOIN users ON user_id = api_key_user_id WHERE api_key_id = $api_key_id"));
    return $row ?: null;
}

/* Has this key expired? It works until the start of its expiry date. */
function apiKeyExpired(array $row) {
    return (string) $row['api_key_expire'] <= date('Y-m-d');
}

/*
 * Why a key's agent turns every call away - '' when they don't: no agent,
 * one who is disabled or archived, or one with no role. $role is their
 * roleRow() (or null).
 */
function apiKeyAgentProblem(array $row, $role) {
    $name = trim(str_replace(' (archived)', '', (string) ($row['user_name'] ?? '')));
    if (!intval($row['api_key_user_id']) || $row['user_name'] === null || intval($row['user_type']) !== 1) {
        return 'it acts as nobody - pick an agent';
    }
    if ($row['user_archived_at']) {
        return "$name is archived";
    }
    if (intval($row['user_status']) !== 1) {
        return "$name is disabled";
    }
    if (!$role) {
        return "$name has no role";
    }
    return '';
}

/*
 * A key's state as a pill - whether it works, and until when. Expired;
 * won't work because of its agent; expiring within two weeks; or working.
 * HTML.
 */
function apiKeyStatePill(array $row, $role) {
    $expire = date('M j, Y', strtotime($row['api_key_expire']));
    if (apiKeyExpired($row)) {
        return '<span class="badge rounded-pill text-bg-secondary fw-normal" title="Expired ' . $expire . '"><i class="fas fa-ban me-1"></i>Expired</span>';
    }
    $problem = apiKeyAgentProblem($row, $role);
    if ($problem !== '') {
        return '<span class="badge rounded-pill bg-danger-subtle text-danger-emphasis fw-normal" title="Every call is turned away until it acts as an active agent with a role"><i class="fas fa-exclamation-triangle me-1"></i>Won\'t work - ' . escapeHtml($problem) . '</span>';
    }
    $days = (int) floor((strtotime($row['api_key_expire']) - strtotime(date('Y-m-d'))) / 86400);
    if ($days <= 14) {
        return '<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fw-normal" title="It stops working on ' . $expire . '"><i class="fas fa-hourglass-half me-1"></i>Expires in ' . $days . ' day' . ($days === 1 ? '' : 's') . '</span>';
    }
    return '<span class="badge rounded-pill bg-success-subtle text-success-emphasis fw-normal" title="It stops working on ' . $expire . '"><i class="fas fa-check-circle me-1"></i>Works until ' . $expire . '</span>';
}

/* The agents a key can act as - active ones, by name: [user_id => row with role_id]. */
function apiKeyAgents() {
    global $mysqli;
    $agents = [];
    $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar, user_role_id FROM users
        WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $agents[intval($row['user_id'])] = $row;
    }
    return $agents;
}

/*
 * The New Key / Edit Key window's fields, checked - [fields, error].
 * fields: name, expire (Y-m-d, after today - or the day it already has, so
 * an expired key can be renamed and stay expired) and user_id (an active
 * agent).
 */
function apiKeyFromInput(array $in, $current_expire = '') {
    $name = trim(mb_substr(strip_tags((string) ($in['name'] ?? '')), 0, 255));
    if ($name === '') {
        return [null, 'A key needs a name.'];
    }
    $expire = (string) ($in['expire'] ?? '');
    $date = DateTime::createFromFormat('!Y-m-d', $expire);
    if (!$date || $date->format('Y-m-d') !== $expire || $expire > '2999-12-31' || ($expire <= date('Y-m-d') && $expire !== (string) $current_expire)) {
        return [null, 'Pick the day it stops working - after today.'];
    }
    $user_id = intval($in['run_as_user'] ?? 0);
    if (!isset(apiKeyAgents()[$user_id])) {
        return [null, 'Pick an active agent for it to act as.'];
    }
    return [['name' => $name, 'expire' => $expire, 'user_id' => $user_id], ''];
}

/*
 * The "Act as" picker of the key windows, with what each agent can open
 * shown under it for the one picked (js in api_key_form). $selected: the
 * user_id, 0 for none.
 */
function apiKeyAgentFieldHtml($selected = 0) {
    $selected = intval($selected);
    $agents = apiKeyAgents();
    $roles = roleRows();
    $access = userClientAccess(array_keys($agents));
    ob_start(); ?>
    <div class="mb-3" data-api-key-agent>
        <label>Acts as <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
            <select class="form-select select2" name="run_as_user" required>
                <option value="">- Pick an agent -</option>
                <?php foreach ($agents as $user_id => $agent) { ?>
                    <option value="<?= $user_id ?>" <?= $user_id === $selected ? 'selected' : '' ?><?= selectOptionUser($user_id, $agent['user_name'], $agent['user_avatar'] ?? '') ?>><?= escapeHtml($agent['user_name']) ?></option>
                <?php } ?>
            </select>
        </div>
        <?php foreach ($agents as $user_id => $agent) {
            $role = $roles[intval($agent['user_role_id'])] ?? null; ?>
            <div class="mt-2 small" data-api-key-access="<?= $user_id ?>"<?= $user_id === $selected ? '' : ' hidden' ?>>
                <?php if ($role) { ?>
                    <div class="mb-1"><?= roleAccessHtml($role) ?></div>
                    <div class="text-body-secondary"><i class="fas fa-fw fa-user-tag me-1"></i>Their role, <?= escapeHtml($role['role_name']) ?> · <i class="fas fa-fw fa-building me-1"></i><?= escapeHtml(userClientAccessText($access[$user_id] ?? ['allow' => [], 'deny' => []], intval($role['role_is_admin']))) ?></div>
                <?php } else { ?>
                    <span class="text-danger-emphasis"><i class="fas fa-exclamation-triangle me-1"></i>They have no role, so the key would be turned away from everything.</span>
                <?php } ?>
            </div>
        <?php } ?>
        <div class="form-text">The key can do what they can - their role and the clients they see - and calls show in the audit log as them.</div>
    </div>
    <script>
        (function () {
            const field = document.currentScript.closest('[data-api-key-agent]') || document.querySelector('[data-api-key-agent]:not([data-wired])');
            if (!field || field.dataset.wired) {
                return;
            }
            field.dataset.wired = '1';
            const picker = field.querySelector('select[name="run_as_user"]');
            picker.addEventListener('change', function () {
                field.querySelectorAll('[data-api-key-access]').forEach(function (access) {
                    access.hidden = access.dataset.apiKeyAccess !== picker.value;
                });
            });
        })();
    </script>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------- What each key is used for */

/** The endpoint a call went to, from its address - "/api/v1/clients/read.php" is "clients/read". */
function apiKeyUsageEndpoint($uri) {
    $path = (string) parse_url((string) $uri, PHP_URL_PATH);
    $path = preg_replace('#^.*?/api/v1/#', '', $path);
    $path = preg_replace('/\.php$/', '', $path);
    return substr(preg_replace('#[^a-z0-9_/-]#', '', strtolower($path)), 0, 100);
}

/*
 * Count a call with a key - to its day and endpoint, with when and from
 * where (api/v1/validate_api_key.php). Never in the way of the call: before
 * the update that adds the table, nothing is counted.
 */
function apiKeyUsageRecord($api_key_id, $endpoint, $ip) {
    global $mysqli;
    $api_key_id = intval($api_key_id);
    $endpoint = escapeSql(substr((string) $endpoint, 0, 100));
    $ip = escapeSql(substr((string) $ip, 0, 200));
    try {
        mysqli_query($mysqli, "INSERT INTO api_key_usage SET api_key_usage_key_id = $api_key_id, api_key_usage_date = CURDATE(), api_key_usage_endpoint = '$endpoint',
                api_key_usage_calls = 1, api_key_usage_last_at = NOW(), api_key_usage_last_ip = '$ip'
            ON DUPLICATE KEY UPDATE api_key_usage_calls = api_key_usage_calls + 1, api_key_usage_last_at = NOW(), api_key_usage_last_ip = '$ip'");
    } catch (Throwable $e) {
        error_log('ITFlow: API key use not counted - ' . $e->getMessage());
    }
}

/*
 * Each key's use over the last $days days - [api_key_id => ['calls',
 * 'endpoints', 'last_at', 'last_ip']], nothing for a key not used.
 */
function apiKeyUsageSummary(array $api_key_ids, $days = 30) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $api_key_ids)));
    $usage = [];
    if (!$ids) {
        return $usage;
    }
    $days = max(1, intval($days));
    $sql = mysqli_query($mysqli, "SELECT api_key_usage_key_id, SUM(api_key_usage_calls) AS calls, COUNT(DISTINCT api_key_usage_endpoint) AS endpoints, MAX(api_key_usage_last_at) AS last_at
        FROM api_key_usage WHERE api_key_usage_key_id IN (" . implode(',', $ids) . ") AND api_key_usage_date > CURDATE() - INTERVAL $days DAY
        GROUP BY api_key_usage_key_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $usage[intval($row['api_key_usage_key_id'])] = ['calls' => intval($row['calls']), 'endpoints' => intval($row['endpoints']), 'last_at' => $row['last_at'], 'last_ip' => ''];
    }
    // Where the last call came from - the row it was counted on
    $sql = mysqli_query($mysqli, "SELECT u.api_key_usage_key_id, u.api_key_usage_last_ip FROM api_key_usage u
        JOIN (SELECT api_key_usage_key_id, MAX(api_key_usage_last_at) AS last_at FROM api_key_usage WHERE api_key_usage_key_id IN (" . implode(',', $ids) . ") GROUP BY api_key_usage_key_id) m
            ON m.api_key_usage_key_id = u.api_key_usage_key_id AND m.last_at = u.api_key_usage_last_at");
    while ($row = mysqli_fetch_row($sql)) {
        if (isset($usage[intval($row[0])])) {
            $usage[intval($row[0])]['last_ip'] = (string) $row[1];
        }
    }
    return $usage;
}

/* A key's calls each day over the last $days days, oldest first - [Y-m-d => calls], every day there. */
function apiKeyUsageDaily($api_key_id, $days = 30) {
    global $mysqli;
    $days = max(1, intval($days));
    $daily = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $daily[date('Y-m-d', strtotime("-$i days"))] = 0;
    }
    $sql = mysqli_query($mysqli, "SELECT api_key_usage_date, SUM(api_key_usage_calls) FROM api_key_usage
        WHERE api_key_usage_key_id = " . intval($api_key_id) . " AND api_key_usage_date > CURDATE() - INTERVAL $days DAY GROUP BY api_key_usage_date");
    while ($row = mysqli_fetch_row($sql)) {
        if (isset($daily[$row[0]])) {
            $daily[$row[0]] = intval($row[1]);
        }
    }
    return $daily;
}

/* The endpoints a key called over the last $days days, busiest first - rows of endpoint, calls, last_at. */
function apiKeyUsageEndpoints($api_key_id, $days = 30) {
    global $mysqli;
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT api_key_usage_endpoint AS endpoint, SUM(api_key_usage_calls) AS calls, MAX(api_key_usage_last_at) AS last_at FROM api_key_usage
        WHERE api_key_usage_key_id = " . intval($api_key_id) . " AND api_key_usage_date > CURDATE() - INTERVAL " . max(1, intval($days)) . " DAY
        GROUP BY api_key_usage_endpoint ORDER BY calls DESC, endpoint ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/* The IP addresses a key was last called from on each day, over the last $days days - rows of ip and last_at, latest first. */
function apiKeyUsageIps($api_key_id, $days = 30) {
    global $mysqli;
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT api_key_usage_last_ip AS ip, MAX(api_key_usage_last_at) AS last_at FROM api_key_usage
        WHERE api_key_usage_key_id = " . intval($api_key_id) . " AND api_key_usage_date > CURDATE() - INTERVAL " . max(1, intval($days)) . " DAY AND api_key_usage_last_ip <> ''
        GROUP BY api_key_usage_last_ip ORDER BY last_at DESC LIMIT 10");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/* Calls turned away with a key after it expired, over the last $days days - ['count', 'last_at']. */
function apiKeyTurnedAway($api_key_id, $days = 30) {
    global $mysqli;
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), MAX(log_created_at) FROM logs
        WHERE log_type = 'API' AND log_action = 'Failed' AND log_entity_id = " . intval($api_key_id) . " AND log_created_at > NOW() - INTERVAL " . max(1, intval($days)) . " DAY"));
    return ['count' => intval($row[0]), 'last_at' => $row[1]];
}

/*
 * After an API call is turned away: when this IP address has just reached
 * the admins' number of them in SIGN_IN_WINDOW_MINUTES, tell them
 * (signInAlertAdmins()).
 */
function apiKeyWatch() {
    global $mysqli, $session_ip;
    $limit = signInAlertLimit();
    $ip = escapeSql((string) $session_ip);
    if ($limit <= 0 || $ip === '') {
        return;
    }
    $count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM logs WHERE log_type = 'API' AND log_action = 'Failed' AND log_ip = '$ip'
        AND log_created_at > NOW() - INTERVAL " . SIGN_IN_WINDOW_MINUTES . " MINUTE"))[0]);
    if ($count === $limit) {
        signInAlertAdmins(
            "$limit API calls turned away from $session_ip",
            "$limit API calls in " . SIGN_IN_WINDOW_MINUTES . " minutes from $session_ip were turned away - with a key that is wrong or has expired.",
            ['IP address' => (string) $session_ip],
            '/admin/audit_logs.php?view=security&q=' . rawurlencode((string) $session_ip)
        );
    }
}
