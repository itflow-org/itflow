<?php

/*
 * ITFlow - Development mode
 *
 * For a copy of your ITFlow to try things on - a test server you restore production's backup
 * onto. Turned on in config.php, so restoring a backup over it can't turn it off:
 *
 *   $config_dev_mode = true;
 *   $config_dev_mail_to = 'dev@example.com';
 *
 * Admin > Backup sets both, when config.php can be written (devModeConfigSet()).
 *
 * With it on, nothing reaches your clients, your team or the systems production talks to:
 *   - every email goes to the test inbox, its real recipient at the front of its subject
 *     (cron/mail_queue.php); with no test inbox, email is cancelled, as in the demo
 *   - texts aren't sent - they wait in the queue (functions/sms.php, cron/sms_queue.php)
 *   - the ticket mailbox isn't read - it would take production's email (cron/ticket_email_parser.php)
 *   - AI Triage doesn't run (cron/ai_triage.php)
 *   - Stripe refuses every call, so no card is charged and nothing is made there
 *     (includes/stripe_init.php); autopay and Payment Provider Fees don't run
 *   - log entries aren't sent to a syslog server (functions/log_forward.php)
 *   - a managed instance doesn't talk to its manager, and a manager doesn't watch its
 *     instances (managedHttpJson(), cron/manager_checkin.php, cron/managed_instances.php)
 *   - telemetry isn't sent (cron/nightly_tasks.php)
 * A red DEVELOPMENT badge shows in the top bar of every agent page.
 *
 * Off - the default - none of this exists.
 */

/** Is this ITFlow in development mode? */
function devModeOn(): bool
{
    global $config_dev_mode;
    return !empty($config_dev_mode);
}

/** Where development mode sends every email - '' when no good address is set. */
function devModeMailTo(): string
{
    global $config_dev_mail_to;
    $to = trim((string) ($config_dev_mail_to ?? ''));
    return filter_var($to, FILTER_VALIDATE_EMAIL) ? $to : '';
}

/** What development mode switches off, in a line - for the badge and Admin > Backup. */
function devModeSummary(): string
{
    $to = devModeMailTo();
    return 'Development mode: ' . ($to !== '' ? "every email goes to $to" : 'no email is sent')
        . '; texts, the ticket mailbox, AI Triage, Stripe, log forwarding and managed check-ins are off.';
}

/** includes/stripe_init.php: in development mode every Stripe call is refused - nothing is charged or made. */
function devModeBlockStripe(): void
{
    if (!devModeOn() || !class_exists('\Stripe\ApiRequestor')) {
        return;
    }
    \Stripe\ApiRequestor::setHttpClient(new class implements \Stripe\HttpClient\ClientInterface {
        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
        {
            throw new \Stripe\Exception\ApiConnectionException('Development mode - Stripe and card payments are off on this server.');
        }
    });
}

/** Whether config.php can be changed from Admin > Backup. */
function devModeConfigWritable(): bool
{
    return is_writable(dirname(__DIR__) . '/config.php');
}

/*
 * Set development mode's two lines in config.php - replaced where they are, added at the end
 * where they aren't. Returns '' or why it couldn't.
 */
function devModeConfigSet(bool $on, string $mail_to): string
{
    $file = dirname(__DIR__) . '/config.php';
    if ($mail_to !== '' && !filter_var($mail_to, FILTER_VALIDATE_EMAIL)) {
        return 'The test inbox isn\'t an email address.';
    }
    if (!is_writable($file)) {
        return 'config.php can\'t be written - add the lines by hand.';
    }
    $text = @file_get_contents($file);
    if ($text === false) {
        return 'config.php can\'t be read.';
    }
    $lines = ['config_dev_mode' => $on, 'config_dev_mail_to' => $mail_to];
    $added = '';
    foreach ($lines as $name => $value) {
        $line = "\$$name = " . var_export($value, true) . ';';
        $pattern = '/^\$' . $name . '\s*=.*;[ \t]*$/m';
        if (preg_match($pattern, $text)) {
            $text = preg_replace_callback($pattern, function () use ($line) {
                return $line;
            }, $text);
        } else {
            $added .= "$line\n";
        }
    }
    if ($added !== '') {
        $text = rtrim($text) . "\n\n// Development mode - for a test copy: email goes to the test inbox, and nothing reaches clients or other systems (Admin > Backup)\n" . $added;
    }
    if (@file_put_contents($file, $text, LOCK_EX) === false) {
        return 'config.php couldn\'t be written.';
    }
    // PHP's code cache would keep the old config.php for a while
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($file, true);
    }
    foreach ($lines as $name => $value) {
        $GLOBALS[$name] = $value;
    }
    return '';
}
