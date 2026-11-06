<?php

/*
 * Text messaging - texts to and from your one support number, through a texting service
 * (Skyetel today, set up in Admin > Integrations > Text Messaging).
 *
 * Going out: smsQueue() puts a text in sms_messages, smsSendQueued() sends one and the
 * Text Messages job (cron/sms_queue.php, smsQueueRun()) sends the rest, a second apart, as
 * the service allows. A number that replied STOP gets nothing more until it replies START.
 *
 * Coming in: the service posts each text to api/sms/<provider>.php, which hands it to
 * smsInbound(). What happens to it, in order:
 *   STOP, START, HELP - the number is opted out or back in, or told who you are, and answered
 *   a blocked number - Spam, quietly
 *   a number texting on a ticket in the last SMS_CONVERSATION_DAYS days - a reply on it
 *   a contact's mobile (or phone) - a new ticket for them (Settings > Tickets > Text to ticket)
 *   a verification code - Texts > Codes, for those who can view credentials, masked, and
 *     wiped after SMS_CODE_KEEP_HOURS
 *   what looks like spam - Texts > Spam
 *   anyone else - Texts > Unmatched, for an agent to open a ticket from, or dismiss
 *
 * Texts to clients - about their tickets, visits and invoices - go only to contacts who agreed
 * (contact_sms_opt_in: an agent with their say-so, the client portal, or replying YES when asked
 * by text), and wait for the morning when they'd land at night (smsSendAfter()). Each is sent by
 * the Text Messages job, and a reply to one about a ticket goes on that ticket.
 *
 * Numbers are kept as E.164 digits without the + ("14125550100"), short codes as their digits.
 */

const SMS_CONVERSATION_DAYS = 14;      // a text within this long of the last one on a ticket goes on it
const SMS_CODE_MASK_MINUTES = 15;      // a code can be revealed for this long after it arrives
const SMS_CODE_KEEP_HOURS = 24;        // then the code text is wiped, this long after it arrived
const SMS_SPAM_KEEP_DAYS = 30;         // spam and dismissed texts are deleted after this long
const SMS_KEEP_DAYS = 365;             // the rest of the log
const SMS_MAX_ATTEMPTS = 3;            // a text that won't go is tried this many times
const SMS_TEXT_LIMIT = 1000;           // a longer text goes as several - Skyetel takes 1024 characters
const SMS_MEDIA_MAX_BYTES = 5242880;   // a photo or file texted in, at most
const SMS_MEDIA_MAX_FILES = 10;
const SMS_QUIET_FROM = 21;             // texts to clients that ITFlow sends by itself wait from 9pm...
const SMS_QUIET_UNTIL = 8;             // ...until 8am, your time
const SMS_OPT_IN_ASK_DAYS = 7;         // a YES counts as agreeing this long after they were asked

/* A text's words or a reply's HTML for a query - as they are; escapeSql() would take out anything that looks like a tag */
function smsSql($value): string
{
    global $mysqli;
    return mysqli_real_escape_string($mysqli, (string) $value);
}

/* ------------------------------------------------------------------ Providers */

/* The texting services ITFlow works with - what each needs to be set up */
function smsProviderKinds(): array
{
    return [
        'Skyetel' => [
            'icon' => 'fas fa-sms',
            'portal_url' => 'https://portal.skyetel.com',
            'about' => 'Your Skyetel number texts clients, and texts to it come back into ITFlow.',
            'username_label' => 'SID',
            'secret_label' => 'Secret',
            'endpoint' => 'skyetel',
            'send' => 'smsSkyetelSend',
            'per_second' => 1,
            'per_five_minutes' => 300,
        ],
    ];
}

/* One provider by id - raw, or null */
function smsProviderRow($provider_id): ?array
{
    global $mysqli;
    $provider_id = intval($provider_id);
    if (!$provider_id) {
        return null;
    }
    try {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM sms_providers WHERE sms_provider_id = $provider_id LIMIT 1"));
    } catch (Throwable $e) {
        return null;
    }
    return $row ?: null;
}

/* The provider texts go through - the one that is on (the first, if there are more) - or null. Read once a request */
function smsProvider(bool $fresh = false): ?array
{
    global $mysqli;
    static $provider = false;
    if ($provider === false || $fresh) {
        $provider = null;
        try {
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM sms_providers WHERE sms_provider_active = 1 ORDER BY sms_provider_id ASC LIMIT 1"));
            $provider = $row ?: null;
        } catch (Throwable $e) {
            // Before the 3.6.0 database update there is no texting
        }
    }
    return $provider;
}

/* Whether texts can go out - a provider that is on, with its keys and number */
function smsReady(): bool
{
    $provider = smsProvider();
    return $provider && isset(smsProviderKinds()[$provider['sms_provider_name']])
        && trim((string) $provider['sms_provider_username']) !== '' && trim((string) $provider['sms_provider_secret']) !== ''
        && smsNumberIsPhone((string) $provider['sms_provider_number']);
}

/* The address the provider posts texts to - what to paste into its portal */
function smsInboundUrl(array $provider): string
{
    global $config_base_url;
    $kind = smsProviderKinds()[$provider['sms_provider_name']] ?? null;
    $endpoint = $kind['endpoint'] ?? 'skyetel';
    return 'https://' . rtrim((string) $config_base_url, '/') . "/api/sms/$endpoint.php?key=" . rawurlencode((string) $provider['sms_provider_inbound_key']);
}

/*
 * What the Text Messaging window sent, checked - [$after, $errors]: the row to save, and what
 * is wrong by field. A secret left empty keeps the saved one; a new provider gets its key for
 * the inbound address.
 */
function smsProviderCheck(array $in, ?array $before): array
{
    global $mysqli;

    $kinds = smsProviderKinds();
    $errors = [];
    $name = $before ? (string) $before['sms_provider_name'] : (string) ($in['provider'] ?? '');
    if (!isset($kinds[$name])) {
        $errors['provider'] = 'Pick a texting service ITFlow works with.';
        $name = (string) array_key_first($kinds);
    } elseif (!$before && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM sms_providers WHERE sms_provider_name = '" . escapeSql($name) . "'"))) {
        $errors['provider'] = "$name is set up already - edit that one.";
    }
    $kind = $kinds[$name];

    $after = [
        'sms_provider_name' => $name,
        'sms_provider_description' => mb_substr(trim((string) ($in['description'] ?? '')), 0, 250),
        'sms_provider_username' => trim((string) ($in['username'] ?? '')),
        'sms_provider_secret' => trim((string) ($in['secret'] ?? '')),
        'sms_provider_number' => smsNumber((string) ($in['number'] ?? '')),
        'sms_provider_active' => $before ? (empty($in['active']) ? 0 : 1) : 1,
        'sms_provider_inbound_key' => $before ? (string) $before['sms_provider_inbound_key'] : randomString(40),
    ];
    if ($after['sms_provider_secret'] === '' && $before) {
        $after['sms_provider_secret'] = (string) $before['sms_provider_secret'];
    }

    if ($after['sms_provider_username'] === '') {
        $errors['username'] = "Its {$kind['username_label']}, from $name's portal.";
    } elseif (mb_strlen($after['sms_provider_username']) > 250 || preg_match('/\s/', $after['sms_provider_username'])) {
        $errors['username'] = "That isn't a {$kind['username_label']} - it has no spaces.";
    }
    if ($after['sms_provider_secret'] === '') {
        $errors['secret'] = "Its {$kind['secret_label']}, shown when the {$kind['username_label']} is made.";
    } elseif (mb_strlen($after['sms_provider_secret']) > 500) {
        $errors['secret'] = "That is too long to be a {$kind['secret_label']}.";
    }
    if (trim((string) ($in['number'] ?? '')) === '') {
        $errors['number'] = "The $name number texts go from - SMS-enabled in $name's portal.";
    } elseif ($after['sms_provider_number'] === '') {
        $errors['number'] = "That isn't a phone number - with its dial code, like +1 412 555 0100.";
    }
    return [$after, $errors];
}

/* Save a provider - its id */
function smsProviderWrite(array $after, int $provider_id = 0): int
{
    global $mysqli;
    $set = [];
    foreach ($after as $column => $value) {
        $set[] = "`$column` = " . (is_int($value) ? $value : "'" . escapeSql((string) $value) . "'");
    }
    $set = implode(', ', $set);
    if ($provider_id) {
        mysqli_query($mysqli, "UPDATE sms_providers SET $set WHERE sms_provider_id = $provider_id");
        return $provider_id;
    }
    mysqli_query($mysqli, "INSERT INTO sms_providers SET $set");
    return mysqli_insert_id($mysqli);
}

/* What changed on a provider, for the audit log and the message - the secret only as changed */
function smsProviderChangeLines(array $before, array $after): array
{
    $lines = [];
    $labels = ['sms_provider_description' => 'description', 'sms_provider_username' => 'SID', 'sms_provider_number' => 'number'];
    foreach ($labels as $column => $label) {
        if ((string) $before[$column] !== (string) $after[$column]) {
            $from = $column === 'sms_provider_number' ? smsNumberText($before[$column]) : (string) $before[$column];
            $to = $column === 'sms_provider_number' ? smsNumberText($after[$column]) : (string) $after[$column];
            $lines[] = "$label " . ($from !== '' ? $from : '(none)') . " -> " . ($to !== '' ? $to : '(none)');
        }
    }
    if ((string) $before['sms_provider_secret'] !== (string) $after['sms_provider_secret']) {
        $lines[] = 'secret changed';
    }
    if (intval($before['sms_provider_active']) !== intval($after['sms_provider_active'])) {
        $lines[] = intval($after['sms_provider_active']) ? 'turned on' : 'turned off';
    }
    return $lines;
}

/* A provider's texts in the last 30 days - ['sent', 'failed', 'received', 'last_in'] */
function smsProviderActivity(): array
{
    global $mysqli;
    try {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
            COALESCE(SUM(sms_message_direction = 'out' AND sms_message_status = 'sent'), 0) AS sent,
            COALESCE(SUM(sms_message_direction = 'out' AND sms_message_status = 'failed'), 0) AS failed,
            COALESCE(SUM(sms_message_direction = 'in'), 0) AS received
            FROM sms_messages WHERE sms_message_created_at > NOW() - INTERVAL 30 DAY"));
    } catch (Throwable $e) {
        $row = null;
    }
    return ['sent' => intval($row['sent'] ?? 0), 'failed' => intval($row['failed'] ?? 0), 'received' => intval($row['received'] ?? 0)];
}

/* ------------------------------------------------------------------ Numbers */

/* The dial code a number written without one is taken to have - your company's, else North America's */
function smsDefaultCountryCode(): string
{
    global $mysqli;
    static $cc = null;
    if ($cc === null) {
        $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT company_phone_country_code FROM companies WHERE company_id = 1"));
        $cc = preg_replace('/\D/', '', (string) ($row[0] ?? ''));
        if ($cc === '') {
            $cc = '1';
        }
    }
    return $cc;
}

/*
 * A number as a text goes to it - E.164 digits without the + - from a number as ITFlow keeps
 * it (a contact's digits and dial code), or as typed ("+1 412-555-0100", "(412) 555-0100").
 * '' when it can't be a number a text goes to.
 */
function smsNumber($number, $country_code = ''): string
{
    $raw = trim((string) $number);
    $digits = preg_replace('/\D/', '', $raw);
    if ($digits === '') {
        return '';
    }
    if (strpos($raw, '+') === 0) {
        $e164 = $digits;
    } elseif (strpos($digits, '00') === 0 && strlen($digits) > 10) {
        $e164 = substr($digits, 2);
    } else {
        $cc = preg_replace('/\D/', '', (string) $country_code);
        if ($cc === '') {
            $cc = smsDefaultCountryCode();
        }
        $e164 = $cc . phoneParts($digits, $cc)['national'];
    }
    return smsNumberIsPhone($e164) ? $e164 : '';
}

/* A number as a service sends it - its digits, a 10-digit North American number given its 1, a short code as it is */
function smsInboundNumber($number): string
{
    $digits = preg_replace('/\D/', '', (string) $number);
    if (strlen($digits) === 10 && smsDefaultCountryCode() === '1') {
        $digits = '1' . $digits;
    }
    return strlen($digits) >= 3 && strlen($digits) <= 15 ? $digits : '';
}

/* Whether E.164 digits are a phone number (8 to 15 digits) rather than a short code */
function smsNumberIsPhone(string $digits): bool
{
    return preg_match('/^[1-9]\d{7,14}$/', $digits) === 1;
}

/* A number for reading - "+1 (412) 555-0100", "Short code 32665" */
function smsNumberText($digits): string
{
    $digits = preg_replace('/\D/', '', (string) $digits);
    if ($digits === '') {
        return '';
    }
    if (!smsNumberIsPhone($digits)) {
        return "Short code $digits";
    }
    if (strlen($digits) === 11 && $digits[0] === '1') {
        return formatPhoneNumber(substr($digits, 1), '1');
    }
    for ($length = 1; $length <= 3; $length++) {
        $cc = substr($digits, 0, $length);
        if (phoneFormatRules($cc)) {
            return formatPhoneNumber(substr($digits, $length), $cc);
        }
    }
    return "+$digits";
}

/* What ITFlow knows about a number (sms_numbers) - or null */
function smsNumberRow(string $number): ?array
{
    global $mysqli;
    if ($number === '') {
        return null;
    }
    try {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM sms_numbers WHERE sms_number_phone = '" . escapeSql($number) . "' LIMIT 1"));
    } catch (Throwable $e) {
        return null;
    }
    return $row ?: null;
}

/* Set what is known about a number - columns of sms_numbers without the prefix: label, opted_out_at, blocked_at, blocked_by */
function smsNumberSet(string $number, array $set): void
{
    global $mysqli;
    if ($number === '' || !$set) {
        return;
    }
    $pairs = [];
    foreach ($set as $column => $value) {
        $column = 'sms_number_' . preg_replace('/[^a-z_]/', '', (string) $column);
        $pairs[] = "`$column` = " . ($value === null ? 'NULL' : ($value === 'NOW()' ? 'NOW()' : "'" . escapeSql((string) $value) . "'"));
    }
    $pairs = implode(', ', $pairs);
    mysqli_query($mysqli, "INSERT INTO sms_numbers SET sms_number_phone = '" . escapeSql($number) . "', $pairs ON DUPLICATE KEY UPDATE $pairs");
}

function smsOptedOut(string $number): bool
{
    return !empty(smsNumberRow($number)['sms_number_opted_out_at']);
}

function smsBlocked(string $number): bool
{
    return !empty(smsNumberRow($number)['sms_number_blocked_at']);
}

/* A name for a number - what an agent called it (a code sender, say), or '' */
function smsNumberLabel(string $number): string
{
    return trim((string) (smsNumberRow($number)['sms_number_label'] ?? ''));
}

/*
 * The contacts a number belongs to - their mobile first, then their phone, the most recently
 * changed first. Each: contact_id, contact_name, contact_client_id, client_name.
 */
function smsContactsFor(string $number): array
{
    global $mysqli;
    if (!smsNumberIsPhone($number)) {
        return [];
    }
    $tail = escapeSql(substr($number, -7));
    $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_client_id, contact_mobile, contact_mobile_country_code, contact_phone, contact_phone_country_code, client_name
        FROM contacts
        LEFT JOIN clients ON client_id = contact_client_id
        WHERE contact_archived_at IS NULL AND (contact_client_id = 0 OR client_archived_at IS NULL)
        AND (REGEXP_REPLACE(contact_mobile, '[^0-9]', '') LIKE '%$tail' OR REGEXP_REPLACE(contact_phone, '[^0-9]', '') LIKE '%$tail')
        ORDER BY contact_updated_at DESC, contact_id DESC
        LIMIT 50");
    $mobiles = [];
    $phones = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        if (smsNumber($row['contact_mobile'], $row['contact_mobile_country_code']) === $number) {
            $mobiles[] = $row;
        } elseif (smsNumber($row['contact_phone'], $row['contact_phone_country_code']) === $number) {
            $phones[] = $row;
        }
    }
    return array_merge($mobiles, $phones);
}

/* E.164 digits in their parts - [dial code, the number after it] - as a contact's mobile is kept. ['', digits] when the dial code isn't known */
function smsNumberParts(string $digits): array
{
    if (strlen($digits) === 11 && $digits[0] === '1') {
        return ['1', substr($digits, 1)];
    }
    for ($length = 1; $length <= 3; $length++) {
        if (phoneFormatRules(substr($digits, 0, $length))) {
            return [substr($digits, 0, $length), substr($digits, $length)];
        }
    }
    return ['', $digits];
}

/* A contact's number for texts - their mobile, as a text goes to it - or '' */
function smsContactNumber(array $contact): string
{
    return smsNumber($contact['contact_mobile'] ?? '', $contact['contact_mobile_country_code'] ?? '');
}

/* ------------------------------------------------------------------ Text */

/* Plain text from a reply's HTML - lines kept, links written out, formatting dropped */
function smsTextFromHtml(string $html): string
{
    $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
    // A link keeps where it goes, unless its words are its address
    $html = preg_replace_callback('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', function ($m) {
        $words = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $href = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (stripos($href, 'mailto:') === 0) {
            $href = substr($href, 7);
        }
        return $words === '' || $words === $href ? $href : "$words ($href)";
    }, $html);
    $html = preg_replace('#<li\b[^>]*>#i', "\n- ", $html);
    $html = preg_replace('#<br\s*/?>#i', "\n", $html);
    $html = preg_replace('#</(p|div|h[1-6]|tr|blockquote|pre|table|ul|ol)>#i', "\n", $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\u{00A0}", ' ', $text);
    $text = preg_replace("/[ \t]+/", ' ', $text);
    $text = preg_replace("/ *\n */", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

/* A text in parts no longer than the service takes - split at a paragraph, a line, then a word */
function smsSplit(string $text, int $limit = SMS_TEXT_LIMIT): array
{
    $text = trim($text);
    $parts = [];
    while (mb_strlen($text) > $limit) {
        $head = mb_substr($text, 0, $limit);
        $cut = 0;
        foreach (["\n\n", "\n", '. ', ' '] as $break) {
            $at = mb_strrpos($head, $break);
            if ($at !== false && $at > $limit / 2) {
                $cut = $at + mb_strlen($break);
                break;
            }
        }
        if (!$cut) {
            $cut = $limit;
        }
        $parts[] = trim(mb_substr($text, 0, $cut));
        $text = trim(mb_substr($text, $cut));
    }
    if ($text !== '') {
        $parts[] = $text;
    }
    return $parts;
}

/*
 * A keyword the whole text is - 'stop', 'start' or 'help' - or ''. Carriers expect these
 * answered; CANCEL and END stop texts too, as the industry's rules say they must.
 */
function smsKeyword(string $text): string
{
    $word = strtoupper(preg_replace('/[^A-Za-z]/', '', trim($text)));
    if (mb_strlen(trim($text)) > 20) {
        return '';
    }
    if (in_array($word, ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'OPTOUT', 'REVOKE'], true)) {
        return 'stop';
    }
    if (in_array($word, ['START', 'UNSTOP', 'SUBSCRIBE', 'OPTIN'], true)) {
        return 'start';
    }
    if (in_array($word, ['HELP', 'INFO'], true)) {
        return 'help';
    }
    return '';
}

/*
 * The verification code in a text - "Your Microsoft code is 482 913", "G-582014 is your Google
 * verification code" - or ''. It takes the words such texts use and a run of 4 to 8 digits.
 */
function smsVerificationCode(string $text): string
{
    $words = '/\b(codes?|passcodes?|pass code|otp|one[- ]time|verification|verify|security|authenticat\w*|2fa|mfa|two[- ](?:factor|step)|sign[- ]?in|log[- ]?in|pin)\b/i';
    if (!preg_match($words, $text)) {
        return '';
    }
    $patterns = [
        // Right after the word code, passcode, OTP or PIN, with a few words between
        '/\b(?:code|passcode|otp|pin)\b[^0-9\n]{0,24}?\b((?:[A-Z]{1,3}-)?\d{3,4}(?:[ -]?\d{2,4})?)\b(?![\d,:\/%]|\.\d)/i',
        // Before "is your ... code"
        '/\b((?:[A-Z]{1,3}-)?\d{4,8})\b(?=\s+is\s+your\b)/i',
        // Any run of 4 to 8 digits on its own, or 3 and 3 ("123 456")
        '/(?<![\d.,:\/$])\b(\d{4,8}|\d{3}[ -]\d{3})\b(?![\d,:\/%]|\.\d)/',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            $code = strtoupper(trim($m[1]));
            if (strlen(preg_replace('/\D/', '', $code)) >= 4 && strlen(preg_replace('/\D/', '', $code)) <= 8) {
                return $code;
            }
        }
    }
    return '';
}

/* Whether a text from someone you don't know looks like spam - shortened links and the usual bait */
function smsLooksLikeSpam(string $text): bool
{
    $lower = mb_strtolower($text);
    if (preg_match('#\b(bit\.ly|tinyurl\.com|t\.co|is\.gd|cutt\.ly|rb\.gy|shorturl\.at|tiny\.cc|ow\.ly|rebrand\.ly|t\.ly)/#', $lower)) {
        return true;
    }
    $bait = ['you have won', "you've won", 'you won a', 'claim your', 'free gift', 'gift card', 'act now', 'limited time offer', 'pre-approved', 'preapproved',
        'loan', 'crypto', 'bitcoin', 'unpaid toll', 'toll balance', 'toll violation', 'could not be delivered', 'delivery attempt', 'redelivery', 'usps', 'parcel',
        'reply yes to', 'click the link', 'click here', 'verify your account', 'account has been suspended', 'final notice', 'debt relief', 'lower your rate', 'casino', 'promo code'];
    $hits = 0;
    foreach ($bait as $phrase) {
        if (strpos($lower, $phrase) !== false) {
            $hits++;
        }
    }
    $has_link = preg_match('#https?://|www\.|\b[a-z0-9-]+\.(com|net|info|top|xyz|club|site|online|icu|live|vip|shop)/#', $lower) === 1;
    return $hits >= 2 || ($hits >= 1 && $has_link);
}

/* ------------------------------------------------------------------ Sending */

/*
 * Queue a text - ['ids' => [the text's rows], 'error' => why it can't go, or '']. $to in E.164
 * digits - smsNumber() of one typed or kept on a contact; $text plain, split when long. $meta: purpose, contact_id,
 * client_id, ticket_id, ticket_reply_id, user_id, send_after (not before then - smsSendAfter()),
 * and force - to a number that replied STOP, for the reply that says so and nothing else.
 */
function smsQueue($to, string $text, array $meta = []): array
{
    global $mysqli;

    $provider = smsProvider();
    if (!smsReady()) {
        return ['ids' => [], 'error' => "Texting isn't set up - Admin > Text Messaging"];
    }
    $number = preg_replace('/\D/', '', (string) $to);
    if (!smsNumberIsPhone($number)) {
        return ['ids' => [], 'error' => "That isn't a number a text can go to"];
    }
    $parts = smsSplit($text);
    if (!$parts) {
        return ['ids' => [], 'error' => 'There is nothing to text'];
    }

    $blocked = empty($meta['force']) && smsOptedOut($number);
    $columns = "sms_message_direction = 'out', sms_message_number = '" . escapeSql($number) . "', sms_message_our_number = '" . escapeSql($provider['sms_provider_number']) . "',
        sms_message_purpose = '" . escapeSql(substr((string) ($meta['purpose'] ?? ''), 0, 30)) . "',
        sms_message_contact_id = " . intval($meta['contact_id'] ?? 0) . ", sms_message_client_id = " . intval($meta['client_id'] ?? 0) . ",
        sms_message_ticket_id = " . intval($meta['ticket_id'] ?? 0) . ", sms_message_ticket_reply_id = " . intval($meta['ticket_reply_id'] ?? 0) . ",
        sms_message_user_id = " . intval($meta['user_id'] ?? 0)
        . (!empty($meta['send_after']) && smsColumnExists('sms_messages', 'sms_message_send_after') ? ", sms_message_send_after = '" . escapeSql($meta['send_after']) . "'" : '');

    $ids = [];
    foreach ($parts as $part) {
        mysqli_query($mysqli, "INSERT INTO sms_messages SET $columns, sms_message_body = '" . smsSql($part) . "',
            sms_message_status = '" . ($blocked ? 'blocked' : 'queued') . "'" . ($blocked ? ", sms_message_error = 'They replied STOP - they get no texts until they reply START'" : ''));
        $ids[] = mysqli_insert_id($mysqli);
        if ($blocked) {
            break;
        }
    }
    return ['ids' => $ids, 'error' => $blocked ? 'They replied STOP to your texts, so they get none until they reply START' : ''];
}

/* Send queued texts now - the first, and the rest a second apart, as the service allows. ['sent' => n, 'error' => the first reason one didn't go] */
function smsSendNow(array $ids): array
{
    $sent = 0;
    $error = '';
    foreach (array_values($ids) as $i => $id) {
        if ($i) {
            usleep(1100000);
        }
        $result = smsSendQueued(intval($id));
        if ($result['ok']) {
            $sent++;
        } elseif ($error === '') {
            $error = $result['error'];
        }
    }
    return ['sent' => $sent, 'error' => $error];
}

/* Send one queued text - ['ok', 'error']. One that can be tried again goes back in the queue for the job */
function smsSendQueued(int $id): array
{
    global $mysqli;

    $provider = smsProvider();
    $kind = $provider ? (smsProviderKinds()[$provider['sms_provider_name']] ?? null) : null;
    if (!$kind || !smsReady()) {
        return ['ok' => false, 'error' => "Texting isn't set up"];
    }

    // Claimed, so the job and a request can't both send it
    mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_status = 'sending', sms_message_attempts = sms_message_attempts + 1, sms_message_attempted_at = NOW()
        WHERE sms_message_id = $id AND sms_message_direction = 'out' AND sms_message_status = 'queued'");
    if (mysqli_affected_rows($mysqli) !== 1) {
        return ['ok' => false, 'error' => 'It was sent already, or is being sent'];
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT sms_message_number, sms_message_body, sms_message_attempts FROM sms_messages WHERE sms_message_id = $id"));

    $result = call_user_func($kind['send'], $provider, (string) $row['sms_message_number'], (string) $row['sms_message_body'], []);

    if ($result['ok']) {
        mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_status = 'sent', sms_message_sent_at = NOW(), sms_message_error = NULL,
            sms_message_provider_ref = " . ($result['ref'] !== '' ? "'" . escapeSql(substr($result['ref'], 0, 100)) . "'" : 'NULL') . " WHERE sms_message_id = $id");
        return ['ok' => true, 'error' => ''];
    }

    $again = $result['retry'] && intval($row['sms_message_attempts']) < SMS_MAX_ATTEMPTS;
    mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_status = '" . ($again ? 'queued' : 'failed') . "', sms_message_error = '" . escapeSql(mb_substr($result['error'], 0, 500)) . "' WHERE sms_message_id = $id");
    if (!$again) {
        logApp('Text Message', 'warning', "A text to " . smsNumberText($row['sms_message_number']) . " didn't go: " . $result['error']);
    }
    return ['ok' => false, 'error' => $result['error'] . ($again ? ' - it will be tried again in a minute or two' : '')];
}

/*
 * The Text Messages job - texts waiting to go, oldest first, a second apart, until the
 * service's five-minute allowance or $max_seconds runs out. One tried before waits a couple
 * of minutes for each try. ['sent', 'failed', 'waiting'].
 */
function smsQueueRun(int $max_seconds = 45): array
{
    global $mysqli;

    $counts = ['sent' => 0, 'failed' => 0, 'waiting' => 0];
    $provider = smsProvider();
    $kind = $provider ? (smsProviderKinds()[$provider['sms_provider_name']] ?? null) : null;
    if (!$kind || !smsReady()) {
        return $counts;
    }

    // Left sending by a run that stopped part way - back in the queue
    mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_status = 'queued' WHERE sms_message_status = 'sending' AND sms_message_attempted_at < NOW() - INTERVAL 10 MINUTE");

    $wait_column = smsColumnExists('sms_messages', 'sms_message_send_after');
    $started = time();
    while (time() - $started < $max_seconds) {
        $recent = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM sms_messages WHERE sms_message_direction = 'out' AND sms_message_attempted_at > NOW() - INTERVAL 5 MINUTE"))[0]);
        if ($recent >= $kind['per_five_minutes'] - 10) {
            break;
        }
        $next = mysqli_fetch_row(mysqli_query($mysqli, "SELECT sms_message_id FROM sms_messages
            WHERE sms_message_direction = 'out' AND sms_message_status = 'queued'
            AND (sms_message_attempted_at IS NULL OR sms_message_attempted_at < NOW() - INTERVAL (2 * sms_message_attempts) MINUTE)
            " . ($wait_column ? 'AND (sms_message_send_after IS NULL OR sms_message_send_after <= NOW())' : '') . "
            ORDER BY sms_message_id ASC LIMIT 1"));
        if (!$next) {
            break;
        }
        $result = smsSendQueued(intval($next[0]));
        $counts[$result['ok'] ? 'sent' : 'failed']++;
        usleep(1100000);
    }
    $counts['waiting'] = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM sms_messages WHERE sms_message_direction = 'out' AND sms_message_status = 'queued'"))[0]);
    return $counts;
}

/*
 * Skyetel - POST https://sms.skyetel.com/v1/out?from=<number>, its SID and secret as the
 * user and password, {"to", "text", "media"}. ['ok', 'ref', 'error', 'retry'].
 */
function smsSkyetelSend(array $provider, string $to, string $text, array $media = []): array
{
    $body = ['to' => $to, 'text' => $text];
    if ($media) {
        $body['media'] = array_values($media);
    }
    $ch = curl_init('https://sms.skyetel.com/v1/out?from=' . rawurlencode(preg_replace('/\D/', '', (string) $provider['sms_provider_number'])));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $provider['sms_provider_username'] . ':' . $provider['sms_provider_secret'],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'ITFlow',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $raw = curl_exec($ch);
    $curl_error = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'ref' => '', 'error' => "Couldn't reach Skyetel" . ($curl_error !== '' ? " ($curl_error)" : ''), 'retry' => true];
    }
    $json = json_decode((string) $raw, true);
    $said = is_array($json) ? trim((string) ($json['message'] ?? $json['error'] ?? $json['detail'] ?? '')) : trim(strip_tags((string) $raw));
    if ($code >= 200 && $code < 300 && !(is_array($json) && isset($json['success']) && $json['success'] === false)) {
        $ref = is_array($json) ? (string) ($json['id'] ?? $json['message_id'] ?? $json['mid'] ?? '') : '';
        return ['ok' => true, 'ref' => $ref, 'error' => '', 'retry' => false];
    }
    if ($code === 401) {
        return ['ok' => false, 'ref' => '', 'error' => "Skyetel turned it down (401) - check the SID and secret, that the number is SMS-enabled and registered for texting, and that the account has a balance", 'retry' => false];
    }
    if ($code === 403 || $code === 429) {
        return ['ok' => false, 'ref' => '', 'error' => "Skyetel says too many texts went at once ($code)", 'retry' => true];
    }
    $said = $said !== '' ? ' - ' . mb_strimwidth($said, 0, 200, '...') : '';
    return ['ok' => false, 'ref' => '', 'error' => "Skyetel answered $code$said", 'retry' => $code >= 500];
}

/* ------------------------------------------------------------------ Coming in */

/* Settings > Tickets > Text to ticket, and the ticket numbering and billing a ticket opened from a text takes - read where they're used */
function smsTicketSettings(): array
{
    global $mysqli;
    $settings = ['config_ticket_sms_parse' => 1, 'config_ticket_sms_unknown_senders' => 0, 'config_ticket_sms_acknowledge' => 1, 'config_ticket_prefix' => '', 'config_ticket_default_billable' => 0];
    try {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_ticket_sms_parse, config_ticket_sms_unknown_senders, config_ticket_sms_acknowledge, config_ticket_prefix, config_ticket_default_billable FROM settings WHERE company_id = 1"));
        if ($row) {
            $settings = array_merge($settings, $row);
        }
    } catch (Throwable $e) {
        // Before the 3.6.0 database update - the defaults
    }
    return $settings;
}

/*
 * A text that came in, from the provider's endpoint - what became of it:
 * ['status' => ticket, unmatched, code, spam, keyword, duplicate or ignored, 'id' => its row, 'ticket_id'].
 */
function smsInbound(array $provider, $from_raw, $to_raw, string $text, array $media): array
{
    global $mysqli;

    $settings = smsTicketSettings();
    $from = smsInboundNumber($from_raw);
    $to = smsInboundNumber($to_raw);
    $text = trim(mb_substr(str_replace("\r\n", "\n", $text), 0, 5000));
    $media = array_slice(array_values(array_filter(array_map('strval', $media), function ($url) {
        return preg_match('#^https://#i', $url) === 1;
    })), 0, SMS_MEDIA_MAX_FILES);
    if ($from === '' || ($text === '' && !$media)) {
        return ['status' => 'ignored', 'id' => 0, 'ticket_id' => 0];
    }
    $number_esc = escapeSql($from);
    $media_json = $media ? smsSql(json_encode($media)) : '';

    // The same text again within two minutes - a service trying again - is the one already here
    $same = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT sms_message_id, sms_message_status, sms_message_ticket_id FROM sms_messages
        WHERE sms_message_direction = 'in' AND sms_message_number = '$number_esc' AND sms_message_created_at > NOW() - INTERVAL 2 MINUTE
        AND sms_message_body = '" . smsSql($text) . "' AND COALESCE(sms_message_media, '') = '$media_json' LIMIT 1"));
    if ($same) {
        return ['status' => 'duplicate', 'id' => intval($same['sms_message_id']), 'ticket_id' => intval($same['sms_message_ticket_id'])];
    }

    mysqli_query($mysqli, "INSERT INTO sms_messages SET sms_message_direction = 'in', sms_message_status = 'received', sms_message_number = '$number_esc',
        sms_message_our_number = '" . escapeSql($to) . "', sms_message_body = '" . smsSql($text) . "', sms_message_media = " . ($media ? "'$media_json'" : 'NULL'));
    $id = mysqli_insert_id($mysqli);
    mysqli_query($mysqli, "UPDATE sms_providers SET sms_provider_last_inbound_at = NOW() WHERE sms_provider_id = " . intval($provider['sms_provider_id']));

    $done = function ($status, $set = '', $ticket_id = 0) use ($mysqli, $id) {
        mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_status = '$status'" . ($set !== '' ? ", $set" : '') . " WHERE sms_message_id = $id");
        return ['status' => $status, 'id' => $id, 'ticket_id' => intval($ticket_id)];
    };

    $conversation = smsConversationTicket($from);

    // STOP, START, HELP - answered, and nothing else
    $keyword = smsKeyword($text);
    if ($keyword === '' && strtoupper(trim($text, " .!\n")) === 'YES' && smsOptedOut($from)) {
        $keyword = 'start';
    }
    // YES to being asked whether texts are all right - they agreed
    if ($keyword === '' && preg_match('/^\s*(yes|y|yes please|ok|okay|sure)\s*[.!]*\s*$/i', $text) && ($asked = smsOptInPending($from))) {
        smsOptInSet(intval($asked['contact_id']), true, 'them, by replying YES when asked by text');
        $queued = smsQueue($from, smsCompanyName() . ": thanks - you'll get texts about your tickets, visits and invoices. Reply STOP any time to stop them, HELP for help.",
            ['purpose' => 'opt_in', 'contact_id' => intval($asked['contact_id']), 'client_id' => intval($asked['contact_client_id'])]);
        if ($queued['ids']) {
            smsSendNow($queued['ids']);
        }
        logAudit('Text Message', 'Edit', escapeSql($asked['contact_name'] . ' (' . smsNumberText($from) . ') replied YES and agreed to texts'), intval($asked['contact_client_id']));
        return $done('keyword', 'sms_message_contact_id = ' . intval($asked['contact_id']) . ', sms_message_client_id = ' . intval($asked['contact_client_id']));
    }
    if ($keyword !== '') {
        smsKeywordAnswer($keyword, $from, $conversation);
        return $done('keyword', $conversation ? 'sms_message_ticket_id = ' . intval($conversation['ticket_id']) : '', $conversation['ticket_id'] ?? 0);
    }

    if (smsBlocked($from)) {
        return $done('spam');
    }

    $to_tickets = intval($settings['config_ticket_sms_parse']) === 1;

    // Texting on a ticket - a reply on it
    if ($to_tickets && $conversation) {
        $reply_id = smsTicketReply($id, $conversation, $from, $text, $media);
        return $done('ticket', "sms_message_ticket_id = " . intval($conversation['ticket_id']) . ", sms_message_ticket_reply_id = " . intval($reply_id)
            . ", sms_message_client_id = " . intval($conversation['ticket_client_id']), $conversation['ticket_id']);
    }

    // A contact - a new ticket for them
    $contacts = smsContactsFor($from);
    if ($to_tickets && $contacts) {
        $contact = $contacts[0];
        $ticket_id = smsTicketOpen($id, $from, $text, $media, intval($contact['contact_id']), intval($contact['contact_client_id']));
        return $done('ticket', "sms_message_ticket_id = $ticket_id, sms_message_contact_id = " . intval($contact['contact_id']) . ", sms_message_client_id = " . intval($contact['contact_client_id']), $ticket_id);
    }

    // A verification code - kept out of tickets, for those who can see credentials
    $code = smsVerificationCode($text);
    if ($code !== '' && !$contacts) {
        return $done('code', "sms_message_code = '" . escapeSql($code) . "'");
    }

    if (!$contacts && smsLooksLikeSpam($text)) {
        return $done('spam');
    }

    if ($to_tickets && intval($settings['config_ticket_sms_unknown_senders']) === 1 && !$contacts) {
        $ticket_id = smsTicketOpen($id, $from, $text, $media, 0, 0);
        return $done('ticket', "sms_message_ticket_id = $ticket_id", $ticket_id);
    }

    // For an agent to look at - told once an hour a number, not for every text
    $earlier = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM sms_messages WHERE sms_message_direction = 'in' AND sms_message_number = '$number_esc'
        AND sms_message_status = 'unmatched' AND sms_message_created_at > NOW() - INTERVAL 1 HOUR AND sms_message_id <> $id"))[0]);
    $result = $done('unmatched', $contacts ? 'sms_message_contact_id = ' . intval($contacts[0]['contact_id']) . ', sms_message_client_id = ' . intval($contacts[0]['contact_client_id']) : '');
    if (!$earlier) {
        $who = $contacts ? $contacts[0]['contact_name'] : smsNumberText($from);
        appNotify('Text Message', escapeSql("A text from $who is waiting in Texts"), '/agent/texts.php', $contacts ? intval($contacts[0]['contact_client_id']) : 0);
    }
    return $result;
}

/* The ticket a number is texting on - the last one a text to or from it went on, in SMS_CONVERSATION_DAYS, still open - or null */
function smsConversationTicket(string $number): ?array
{
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_id, ticket_client_id, ticket_contact_id, ticket_status, ticket_closed_at
        FROM sms_messages
        JOIN tickets ON ticket_id = sms_message_ticket_id
        WHERE sms_message_number = '" . escapeSql($number) . "' AND sms_message_ticket_id > 0
        AND sms_message_created_at > NOW() - INTERVAL " . SMS_CONVERSATION_DAYS . " DAY
        AND ticket_archived_at IS NULL
        ORDER BY sms_message_id DESC LIMIT 1"));
    return $row && empty($row['ticket_closed_at']) ? $row : null;
}

/* Answer STOP, START or HELP - and say so on the ticket they text on */
function smsKeywordAnswer(string $keyword, string $number, ?array $conversation): void
{
    global $mysqli;

    $company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code, company_email FROM companies WHERE company_id = 1")) ?: [];
    $name = trim((string) ($company['company_name'] ?? '')) ?: 'Support';
    $ticket_id = intval($conversation['ticket_id'] ?? 0);
    $meta = ['purpose' => 'keyword', 'ticket_id' => $ticket_id, 'client_id' => intval($conversation['ticket_client_id'] ?? 0), 'force' => true];
    $contacts = smsContactsFor($number);
    $who = ($contacts ? $contacts[0]['contact_name'] . ' (' . smsNumberText($number) . ')' : smsNumberText($number));

    if ($keyword === 'stop') {
        smsNumberSet($number, ['opted_out_at' => 'NOW()']);
        $answer = "$name: you won't get any more texts from us. Reply START to get them again.";
        $note = "$who replied STOP - they get no more texts until they reply START.";
        logAudit('Text Message', 'Edit', escapeSql("$who replied STOP and was opted out of texts"), intval($conversation['ticket_client_id'] ?? 0), $ticket_id);
    } elseif ($keyword === 'start') {
        smsNumberSet($number, ['opted_out_at' => null]);
        $answer = "$name: texts from us are back on. Reply STOP to stop them, HELP for help.";
        $note = "$who replied START - texts to them are back on.";
        logAudit('Text Message', 'Edit', escapeSql("$who replied START and was opted back in to texts"), intval($conversation['ticket_client_id'] ?? 0), $ticket_id);
    } else {
        $ways = [];
        if (trim((string) ($company['company_phone'] ?? '')) !== '') {
            $ways[] = 'call ' . formatPhoneNumber($company['company_phone'], $company['company_phone_country_code'] ?? '');
        }
        if (filter_var($company['company_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $ways[] = 'email ' . $company['company_email'];
        }
        $answer = "$name support" . ($ways ? ': ' . implode(' or ', $ways) : '') . '. Reply STOP to stop texts.';
        $note = '';
    }

    $queued = smsQueue($number, $answer, $meta);
    if ($queued['ids']) {
        smsSendNow($queued['ids']);
    }
    if ($note !== '' && $ticket_id) {
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '" . smsSql('<p>' . escapeHtml($note) . '</p>') . "', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:00:00', ticket_reply_by = 0, ticket_reply_ticket_id = $ticket_id");
    }
}

/* A text's words as a reply or a ticket's details - its lines kept, nothing in it taken as HTML */
function smsTextHtml(string $text): string
{
    return $text === '' ? '' : '<p>' . nl2br(escapeHtml($text)) . '</p>';
}

/*
 * Open a ticket from a text - for a contact, a client, or no client (from someone you don't
 * know). $by is the agent opening it from Texts (0: it came in), who isn't texted the number;
 * $more_ids, other texts from the number that go on it with this one. Its id.
 */
function smsTicketOpen(int $message_id, string $from, string $text, array $media, int $contact_id, int $client_id, string $subject = '', int $by = 0, array $more_ids = []): int
{
    global $mysqli;

    $settings = smsTicketSettings();
    $config_ticket_prefix = (string) $settings['config_ticket_prefix'];

    mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number), config_ticket_next_number = config_ticket_next_number + 1 WHERE company_id = 1");
    $ticket_number = mysqli_insert_id($mysqli);

    $contact_name = $contact_id ? trim((string) getFieldById('contacts', $contact_id, 'contact_name')) : '';
    $who = $contact_name !== '' ? $contact_name : smsNumberText($from);
    if (trim($subject) === '') {
        $first_line = trim(strtok($text, "\n") ?: '');
        $subject = $first_line !== '' ? mb_strimwidth($first_line, 0, 80, '...') : "Text from $who";
    }
    $prefix = escapeSql($config_ticket_prefix);
    $details = smsSql(smsTextHtml($text !== '' ? $text : '(A photo or file, attached)'));
    $url_key = randomString(32);
    $billable = intval($settings['config_ticket_default_billable']) ? 1 : 0;

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$prefix', ticket_number = $ticket_number, ticket_source = 'Text', ticket_subject = '" . escapeSql(mb_substr($subject, 0, 500)) . "',
        ticket_details = '$details', ticket_priority = 'Medium', ticket_status = 1, ticket_billable = $billable, ticket_created_by = " . intval($by) . ",
        ticket_contact_id = $contact_id, ticket_url_key = '$url_key', ticket_client_id = $client_id");
    $ticket_id = mysqli_insert_id($mysqli);
    applyTicketSla($ticket_id);
    $ids = implode(',', array_unique(array_merge([intval($message_id)], array_map('intval', $more_ids))));
    mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_ticket_id = $ticket_id, sms_message_contact_id = $contact_id, sms_message_client_id = $client_id WHERE sms_message_id IN ($ids)");

    if ($media) {
        smsSaveMedia($media, $ticket_id, null, $message_id);
    }

    $reference = $config_ticket_prefix . $ticket_number;
    logAudit('Ticket', 'Create', escapeSql($by ? "Text: ticket $reference was opened from a text from $who ($subject)" : "Text: $who texted in and ticket $reference was opened ($subject)"), $client_id, $ticket_id);
    logTicketHistory($ticket_id, escapeSql("Opened from a text from $who" . ($contact_name !== '' ? ' (' . smsNumberText($from) . ')' : '')));

    // The new-ticket address and the client's contacts who hear about new tickets - by email, as for any other
    ticketMailNewTicketAddress($ticket_id, 'text');
    ticketMailColleaguesNew($ticket_id);
    triggerCustomAction('ticket_create', $ticket_id);

    // They're texted back the number - so they know it landed, and that replying adds to it
    if (!$by && intval($settings['config_ticket_sms_acknowledge']) === 1 && smsNumberIsPhone($from)) {
        $company = trim((string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT company_name FROM companies WHERE company_id = 1"))[0] ?? ''));
        $queued = smsQueue($from, ($company !== '' ? "$company: " : '') . "thanks, we got your text and opened ticket $reference. Reply here to add to it.",
            ['purpose' => 'acknowledge', 'ticket_id' => $ticket_id, 'contact_id' => $contact_id, 'client_id' => $client_id]);
        if ($queued['ids']) {
            smsSendNow($queued['ids']);
        }
    }
    return $ticket_id;
}

/* A text on the ticket it is texting on - a client reply, its photos with it, the ticket opened again. The reply's id */
function smsTicketReply(int $message_id, array $conversation, string $from, string $text, array $media): int
{
    global $mysqli;

    $ticket_id = intval($conversation['ticket_id']);
    $row = ticketRow($ticket_id);
    $client_id = intval($row['ticket_client_id']);

    // Who it is - the ticket's contact, or another of the client's contacts with the number
    $contact_id = 0;
    foreach (smsContactsFor($from) as $contact) {
        if (intval($contact['contact_id']) === intval($row['ticket_contact_id'])) {
            $contact_id = intval($contact['contact_id']);
            break;
        }
        if (!$contact_id && intval($contact['contact_client_id']) === $client_id) {
            $contact_id = intval($contact['contact_id']);
        }
    }
    $who = $contact_id ? (string) getFieldById('contacts', $contact_id, 'contact_name') : smsNumberText($from);

    $html = smsTextHtml($text !== '' ? $text : '(A photo or file, attached)');
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '" . smsSql($html) . "', ticket_reply_type = 'Client', ticket_reply_time_worked = '00:00:00', ticket_reply_by = $contact_id, ticket_reply_ticket_id = $ticket_id");
    $reply_id = mysqli_insert_id($mysqli);
    mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_contact_id = $contact_id WHERE sms_message_id = " . intval($message_id));
    if ($media) {
        smsSaveMedia($media, $ticket_id, $reply_id, $message_id);
    }

    // Back to Open - unless it is in a status of your own set to stay put when the client replies
    $status = intval($row['ticket_status']);
    $new_status = ticketStatusAfterClientReply($status);
    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = $new_status, ticket_resolved_at = NULL, ticket_updated_at = NOW() WHERE ticket_id = $ticket_id");
    resetTicketResolutionSla($ticket_id);
    logTicketHistory($ticket_id, escapeSql("$who replied by text" . ($new_status !== $status ? ', reopening the ticket' : '')));
    logAudit('Ticket', 'Edit', escapeSql("Text: $who replied by text on ticket " . ticketReference($row)), $client_id, $ticket_id);

    // The agent it is assigned to - in ITFlow and by email, with what they wrote
    $assigned = intval($row['ticket_assigned_to']);
    if ($assigned) {
        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '" . escapeSql("$who replied by text on Ticket " . ticketReference($row) . " - " . $row['ticket_subject']) . "',
            notification_action = '" . escapeSql('/agent/' . ticketUrl($ticket_id, $client_id)) . "', notification_client_id = $client_id, notification_user_id = $assigned");
    }
    ticketMailAssigneeReplied($ticket_id, 'text', $html, $who);
    triggerCustomAction('ticket_reply_client', $ticket_id);
    return $reply_id;
}

/*
 * Fetch the photos and files a text brought and keep them on the ticket - on a reply, or on
 * the ticket itself. Only https, only to a public address, each at most SMS_MEDIA_MAX_BYTES,
 * only the kinds a ticket takes. What couldn't be fetched is noted on the text. How many were kept.
 */
function smsSaveMedia(array $urls, int $ticket_id, ?int $reply_id, int $message_id): int
{
    global $mysqli;

    $types = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'application/pdf' => 'pdf',
        'text/plain' => 'txt', 'text/csv' => 'csv', 'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3',
        'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/ogg' => 'ogg', 'application/zip' => 'zip', 'application/json' => 'json'];
    $dir = dirname(__DIR__) . "/uploads/tickets/$ticket_id/";
    mkdirMissing(dirname(__DIR__) . '/uploads/tickets/');
    mkdirMissing($dir);

    $kept = 0;
    $missed = [];
    foreach (array_slice($urls, 0, SMS_MEDIA_MAX_FILES) as $i => $url) {
        $host = parse_url($url, PHP_URL_HOST);
        $ip = $host ? gethostbyname($host) : '';
        if (!preg_match('#^https://#i', $url) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $missed[] = 'one at an address ITFlow won\'t fetch from';
            continue;
        }
        $data = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'ITFlow',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$data) {
                $data .= $chunk;
                return strlen($data) > SMS_MEDIA_MAX_BYTES ? 0 : strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = strtolower(trim(explode(';', (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE))[0]));
        curl_close($ch);
        if (strlen($data) > SMS_MEDIA_MAX_BYTES) {
            $missed[] = 'one over ' . round(SMS_MEDIA_MAX_BYTES / 1048576) . ' MB';
            continue;
        }
        if (!$ok || $code !== 200 || $data === '') {
            $missed[] = "one that couldn't be fetched" . ($code ? " ($code)" : '');
            continue;
        }
        $extension = $types[$type] ?? strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        if (!in_array($extension, TICKET_ATTACHMENT_EXTENSIONS, true)) {
            $missed[] = 'a kind of file a ticket doesn\'t take' . ($type !== '' ? " ($type)" : '');
            continue;
        }
        $stored = bin2hex(random_bytes(16)) . ".$extension";
        if (file_put_contents($dir . $stored, $data) === false) {
            $missed[] = "one that couldn't be saved";
            continue;
        }
        $name = escapeSql('Texted ' . (str_starts_with($type, 'image/') ? 'photo' : 'file') . ' ' . ($i + 1) . ".$extension");
        mysqli_query($mysqli, "INSERT INTO ticket_attachments SET ticket_attachment_name = '$name', ticket_attachment_reference_name = '$stored',
            ticket_attachment_reply_id = " . ($reply_id === null ? 'NULL' : intval($reply_id)) . ", ticket_attachment_ticket_id = $ticket_id");
        $kept++;
    }
    if ($missed) {
        mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_error = '" . escapeSql(mb_substr('Not kept on the ticket: ' . implode(', ', $missed), 0, 500)) . "' WHERE sms_message_id = " . intval($message_id));
    }
    return $kept;
}

/* ------------------------------------------------------------------ The Texts inbox */

/* Its tabs - label, icon, which texts - and whether the person looking may see each */
function smsInboxTabs(): array
{
    return [
        'unmatched' => ['Unmatched', 'fa-question-circle', "sms_message_status = 'unmatched'", lookupUserPermission('module_support') >= 1],
        'codes' => ['Codes', 'fa-key', "sms_message_status = 'code'", lookupUserPermission('module_credential') >= 1],
        'spam' => ['Spam', 'fa-ban', "sms_message_status = 'spam'", lookupUserPermission('module_support') >= 1],
    ];
}

/* How many texts wait in Unmatched - and codes still fresh, for those who may see them - for the side nav. 0 before texting is set up */
function smsInboxWaiting(): int
{
    global $mysqli;
    if (!smsProvider()) {
        return 0;
    }
    $where = ["sms_message_status = 'unmatched'"];
    if (lookupUserPermission('module_credential') >= 1) {
        $where[] = "(sms_message_status = 'code' AND sms_message_created_at > NOW() - INTERVAL " . SMS_CODE_MASK_MINUTES . " MINUTE)";
    }
    try {
        return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM sms_messages WHERE sms_message_direction = 'in' AND (" . implode(' OR ', $where) . ")"))[0]);
    } catch (Throwable $e) {
        return 0;
    }
}

/* Whether a code can still be revealed - SMS_CODE_MASK_MINUTES after it came, and while it's kept */
function smsCodeFresh(array $row): bool
{
    return ($row['sms_message_status'] ?? '') === 'code' && (string) ($row['sms_message_code'] ?? '') !== ''
        && strtotime($row['sms_message_created_at']) > time() - SMS_CODE_MASK_MINUTES * 60;
}

/* A code masked - its length kept, so it can be told apart from the next */
function smsCodeMasked(string $code): string
{
    return preg_replace('/[0-9A-Za-z]/', '•', $code);
}

/* One inbound text by id - raw, or null */
function smsMessageRow($message_id): ?array
{
    global $mysqli;
    $message_id = intval($message_id);
    try {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM sms_messages WHERE sms_message_id = $message_id LIMIT 1"));
    } catch (Throwable $e) {
        return null;
    }
    return $row ?: null;
}

/*
 * Tidy the log - from the Text Messages job. A code's text is wiped SMS_CODE_KEEP_HOURS after
 * it came (that one came from that sender is kept); spam and dismissed texts go after
 * SMS_SPAM_KEEP_DAYS, everything after SMS_KEEP_DAYS.
 */
function smsTidy(): void
{
    global $mysqli;
    mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_body = NULL, sms_message_code = NULL, sms_message_media = NULL, sms_message_error = 'The code was wiped a day after it came'
        WHERE sms_message_status = 'code' AND sms_message_body IS NOT NULL AND sms_message_created_at < NOW() - INTERVAL " . SMS_CODE_KEEP_HOURS . " HOUR");
    mysqli_query($mysqli, "DELETE FROM sms_messages WHERE sms_message_status IN ('spam', 'dismissed') AND sms_message_created_at < NOW() - INTERVAL " . SMS_SPAM_KEEP_DAYS . " DAY");
    mysqli_query($mysqli, "DELETE FROM sms_messages WHERE sms_message_created_at < NOW() - INTERVAL " . SMS_KEEP_DAYS . " DAY");
}

/* ------------------------------------------------------------------ The log */

/* Texts that didn't go in the last week - for the side nav. 0 before the tables exist */
function smsFailedRecently(): int
{
    global $mysqli;
    try {
        return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM sms_messages WHERE sms_message_direction = 'out' AND sms_message_status = 'failed' AND sms_message_created_at > NOW() - INTERVAL 7 DAY"))[0]);
    } catch (Throwable $e) {
        return 0;
    }
}

/* Maintenance > Text Messages' tabs - label, icon, which texts */
function smsLogTabs(): array
{
    return [
        '' => ['All', 'fa-list', '1'],
        'waiting' => ['Waiting', 'fa-hourglass-half', "sms_message_direction = 'out' AND sms_message_status IN ('queued', 'sending')"],
        'failed' => ['Failed', 'fa-exclamation-triangle', "sms_message_direction = 'out' AND sms_message_status IN ('failed', 'blocked')"],
        'sent' => ['Sent', 'fa-paper-plane', "sms_message_direction = 'out' AND sms_message_status = 'sent'"],
        'received' => ['Received', 'fa-inbox', "sms_message_direction = 'in'"],
    ];
}

/* How a text stands, for the log - key, label, badge, icon, and why when it didn't go */
function smsLogState(array $row): array
{
    $states = [
        'queued' => ['Waiting', 'text-bg-light border', 'fa-hourglass-half'],
        'sending' => ['Sending', 'text-bg-info', 'fa-spinner'],
        'sent' => ['Sent', 'text-bg-success', 'fa-check'],
        'failed' => ['Failed', 'text-bg-danger', 'fa-exclamation-triangle'],
        'blocked' => ['Not sent', 'text-bg-warning', 'fa-hand-paper'],
        'cancelled' => ['Cancelled', 'text-bg-secondary', 'fa-ban'],
        'ticket' => ['On a ticket', 'text-bg-primary', 'fa-life-ring'],
        'unmatched' => ['In Texts', 'text-bg-warning', 'fa-question-circle'],
        'code' => ['Code', 'text-bg-dark', 'fa-key'],
        'spam' => ['Spam', 'text-bg-secondary', 'fa-ban'],
        'keyword' => ['Keyword', 'text-bg-light border', 'fa-hand-paper'],
        'dismissed' => ['Dismissed', 'text-bg-light border', 'fa-check'],
        'received' => ['Received', 'text-bg-light border', 'fa-inbox'],
    ];
    $status = (string) $row['sms_message_status'];
    [$label, $badge, $icon] = $states[$status] ?? [ucfirst($status), 'text-bg-light border', 'fa-circle'];
    $error = trim((string) $row['sms_message_error']);
    // Waiting for the morning - says when
    if ($status === 'queued' && !empty($row['sms_message_send_after']) && strtotime($row['sms_message_send_after']) > time() && $error === '') {
        $error = 'Waiting until ' . date('g:ia', strtotime($row['sms_message_send_after'])) . ' - it would land at night';
    }
    return ['key' => $status, 'label' => $label, 'badge' => $badge, 'icon' => $icon, 'error' => $error];
}

/* A text's words as the log shows them - a code masked, a wiped one said so */
function smsLogBody(array $row): string
{
    $body = (string) $row['sms_message_body'];
    if ($row['sms_message_status'] === 'code') {
        if ($row['sms_message_body'] === null) {
            return '(A verification code - wiped a day after it came)';
        }
        $code = (string) $row['sms_message_code'];
        return $code !== '' ? str_replace($code, smsCodeMasked($code), $body) : $body;
    }
    return $body;
}

/* What a text ITFlow sent by itself was for, in words - '' for a reply an agent wrote */
function smsPurposeText(string $purpose): string
{
    return [
        'acknowledge' => 'The ticket number, for a new ticket', 'keyword' => 'The answer to STOP, START or HELP', 'test' => 'A test',
        'notify_opened' => 'Their ticket was opened', 'notify_resolved' => 'Their ticket was resolved', 'notify_replied' => 'A reply was emailed to them',
        'notify_scheduled' => 'A visit or call was booked', 'notify_unscheduled' => 'A visit or call was cancelled', 'reminder' => 'A reminder of a visit or call',
        'invoice' => 'An invoice, with its link', 'invoice_reminder' => 'A payment reminder, with its link',
        'opt_in_request' => 'Asking whether texts are all right', 'opt_in' => 'Thanks for agreeing to texts',
    ][$purpose] ?? '';
}

/* What can be done with a text in the log - retry a failed one, cancel a waiting one, delete one that isn't being sent */
function smsLogCan(array $row, string $action): bool
{
    $status = (string) $row['sms_message_status'];
    $out = $row['sms_message_direction'] === 'out';
    if ($action === 'retry') {
        return $out && in_array($status, ['failed', 'cancelled'], true);
    }
    if ($action === 'cancel') {
        return $out && $status === 'queued';
    }
    return $status !== 'sending';
}

/* ------------------------------------------------------------------ On tickets */

/*
 * Each reply's texts on a ticket - [reply id => ['direction', 'status', 'number', 'error', 'parts']] -
 * for the conversation: "Texted to ..." and whether it went, or "By text" on one that came in.
 */
function smsRepliesForTicket(int $ticket_id): array
{
    global $mysqli;
    $states = [];
    try {
        $sql = mysqli_query($mysqli, "SELECT sms_message_ticket_reply_id, sms_message_direction, sms_message_status, sms_message_number, sms_message_error
            FROM sms_messages WHERE sms_message_ticket_id = " . intval($ticket_id) . " AND sms_message_ticket_reply_id > 0 ORDER BY sms_message_id ASC");
    } catch (Throwable $e) {
        return [];
    }
    $rank = ['failed' => 5, 'blocked' => 4, 'queued' => 3, 'sending' => 2, 'sent' => 1];
    while ($row = mysqli_fetch_assoc($sql)) {
        $reply_id = intval($row['sms_message_ticket_reply_id']);
        $state = $states[$reply_id] ?? ['direction' => $row['sms_message_direction'], 'status' => $row['sms_message_status'], 'number' => $row['sms_message_number'], 'error' => '', 'parts' => 0];
        $state['parts']++;
        // The worst of its parts is how it stands
        if (($rank[$row['sms_message_status']] ?? 0) > ($rank[$state['status']] ?? 0)) {
            $state['status'] = $row['sms_message_status'];
        }
        if ((string) $row['sms_message_error'] !== '' && $state['error'] === '') {
            $state['error'] = (string) $row['sms_message_error'];
        }
        $states[$reply_id] = $state;
    }
    return $states;
}

/* How a text reply stands, for the line under it - [icon, words, tone] */
function smsReplyStateText(array $state): array
{
    $to = smsNumberText($state['number']);
    $parts = $state['parts'] > 1 ? ' (' . $state['parts'] . ' texts)' : '';
    switch ($state['status']) {
        case 'sent':
            return ['fa-check', "Texted to $to$parts", 'text-body-secondary'];
        case 'queued':
        case 'sending':
            return ['fa-hourglass-half', "Texting $to$parts - waiting to go", 'text-body-secondary'];
        case 'blocked':
            return ['fa-ban', "Not texted to $to - they replied STOP", 'text-danger-emphasis'];
        default:
            return ['fa-exclamation-triangle', "Not texted to $to" . ($state['error'] !== '' ? ' - ' . $state['error'] : ''), 'text-danger-emphasis'];
    }
}

/*
 * Who a text reply on a ticket goes to - ['number', 'name', 'opted_out'] - or null: its
 * contact's mobile, else the number it is texting on (someone you don't know).
 */
function smsTicketRecipient(array $ticket): ?array
{
    global $mysqli;
    if (!smsReady()) {
        return null;
    }
    $number = '';
    $name = '';
    $contact_id = intval($ticket['ticket_contact_id'] ?? 0);
    if ($contact_id) {
        $contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_mobile, contact_mobile_country_code FROM contacts WHERE contact_id = $contact_id AND contact_archived_at IS NULL"));
        if ($contact) {
            $number = smsContactNumber($contact);
            $name = (string) $contact['contact_name'];
        }
    }
    if ($number === '') {
        $last = mysqli_fetch_row(mysqli_query($mysqli, "SELECT sms_message_number FROM sms_messages WHERE sms_message_ticket_id = " . intval($ticket['ticket_id']) . " AND sms_message_direction = 'in' ORDER BY sms_message_id DESC LIMIT 1"));
        $number = smsNumberIsPhone((string) ($last[0] ?? '')) ? (string) $last[0] : '';
    }
    if ($number === '') {
        return null;
    }
    return ['number' => $number, 'name' => $name, 'opted_out' => smsOptedOut($number)];
}

/*
 * Text a reply on a ticket to who it goes to (smsTicketRecipient()) - now, the rest by the job
 * when it is long. ['ok' => went, 'queued' => waiting to go, 'to' => who, for reading, 'error'].
 */
function smsTicketReplySend(array $ticket, int $reply_id, string $html, int $user_id): array
{
    $recipient = smsTicketRecipient($ticket);
    if (!$recipient) {
        return ['ok' => false, 'queued' => false, 'to' => '', 'error' => smsReady() ? "there's no mobile number on its contact to text" : "texting isn't set up"];
    }
    $to = ($recipient['name'] !== '' ? $recipient['name'] . ' at ' : '') . smsNumberText($recipient['number']);
    $text = smsTextFromHtml($html);
    if ($text === '') {
        return ['ok' => false, 'queued' => false, 'to' => $to, 'error' => 'there are no words in it to text'];
    }
    $queued = smsQueue($recipient['number'], $text, ['purpose' => 'reply', 'ticket_id' => intval($ticket['ticket_id']), 'ticket_reply_id' => $reply_id,
        'contact_id' => intval($ticket['ticket_contact_id']), 'client_id' => intval($ticket['ticket_client_id']), 'user_id' => $user_id]);
    if ($queued['error'] !== '') {
        return ['ok' => false, 'queued' => false, 'to' => $to, 'error' => $queued['error']];
    }
    $sent = smsSendNow($queued['ids']);
    $waiting = count($queued['ids']) - $sent['sent'];
    return ['ok' => $waiting === 0, 'queued' => $waiting > 0 && smsQueuedStill($queued['ids']), 'to' => $to, 'error' => $sent['error']];
}

/* Whether any of these texts is still waiting to go - tried, and to be tried again */
function smsQueuedStill(array $ids): bool
{
    global $mysqli;
    $ids = array_filter(array_map('intval', $ids));
    if (!$ids) {
        return false;
    }
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM sms_messages WHERE sms_message_id IN (" . implode(',', $ids) . ") AND sms_message_status = 'queued'"))[0]) > 0;
}

/* ------------------------------------------------------------------ Texts to clients */

/* Whether a table has a column yet - the texts to clients arrive with the 3.6.1 database update. Asked once a request */
function smsColumnExists(string $table, string $column): bool
{
    global $mysqli;
    static $known = [];
    $key = "$table.$column";
    if (!isset($known[$key])) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . escapeSql($table) . "' AND COLUMN_NAME = '" . escapeSql($column) . "' LIMIT 1");
        $known[$key] = $result && mysqli_num_rows($result) > 0;
    }
    return $known[$key];
}

/* Your company's name, as texts start with it */
function smsCompanyName(): string
{
    global $mysqli;
    static $name = null;
    if ($name === null) {
        $name = trim((string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT company_name FROM companies WHERE company_id = 1"))[0] ?? ''));
        if ($name === '') {
            $name = 'Support';
        }
    }
    return $name;
}

/* Settings > Notifications > Texts to clients - read where they're used */
function smsClientSettings(): array
{
    global $mysqli;
    $settings = ['config_sms_client_tickets' => 1, 'config_sms_client_visits' => 1, 'config_sms_visit_reminder_hours' => 24, 'config_sms_client_invoices' => 1];
    if (!smsColumnExists('settings', 'config_sms_client_tickets')) {
        return array_map(function () { return 0; }, $settings);
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_sms_client_tickets, config_sms_client_visits, config_sms_visit_reminder_hours, config_sms_client_invoices FROM settings WHERE company_id = 1"));
    return array_map('intval', array_merge($settings, $row ?: []));
}

/* The reminder choices on Settings > Notifications - hours before a visit or call */
function smsReminderChoices(): array
{
    return [0 => 'No reminder', 1 => 'An hour before', 2 => 'Two hours before', 4 => 'Four hours before', 24 => 'The day before'];
}

/*
 * When a text ITFlow sends a client by itself may go - null for now, or the next morning when it
 * is night (SMS_QUIET_FROM to SMS_QUIET_UNTIL, your time). $before: what it's about starts then
 * (a visit) - when the morning is too late for it, now.
 */
function smsSendAfter(?int $before = null): ?string
{
    $hour = intval(date('G'));
    if ($hour >= SMS_QUIET_UNTIL && $hour < SMS_QUIET_FROM) {
        return null;
    }
    $morning = strtotime(($hour >= SMS_QUIET_FROM ? 'tomorrow' : 'today') . ' ' . sprintf('%02d:00', SMS_QUIET_UNTIL));
    if ($before !== null && $morning >= $before - 15 * 60) {
        return null;
    }
    return date('Y-m-d H:i:s', $morning);
}

/*
 * A contact who can be texted about their work - agreed to texts, has a mobile, didn't reply
 * STOP, isn't archived - with 'number' added. Null otherwise.
 */
function smsContactTextable(int $contact_id): ?array
{
    global $mysqli;
    if (!$contact_id || !smsReady() || !smsColumnExists('contacts', 'contact_sms_opt_in')) {
        return null;
    }
    $contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_client_id, contact_mobile, contact_mobile_country_code, contact_sms_opt_in
        FROM contacts WHERE contact_id = $contact_id AND contact_archived_at IS NULL"));
    if (!$contact || !intval($contact['contact_sms_opt_in'])) {
        return null;
    }
    $contact['number'] = smsContactNumber($contact);
    return $contact['number'] !== '' && !smsOptedOut($contact['number']) ? $contact : null;
}

/* Queue a text to a contact who agreed to texts - at night, for the morning. Whether it was queued */
function smsNotifyContact(int $contact_id, string $text, array $meta, ?int $before = null): bool
{
    $contact = smsContactTextable($contact_id);
    if (!$contact) {
        return false;
    }
    $queued = smsQueue($contact['number'], $text, $meta + ['contact_id' => $contact_id, 'client_id' => intval($contact['contact_client_id']), 'send_after' => smsSendAfter($before)]);
    return (bool) $queued['ids'] && $queued['error'] === '';
}

/* A ticket's subject, short enough for a text */
function smsTicketSubject(array $row): string
{
    return mb_strimwidth(trim((string) $row['ticket_subject']), 0, 60, '...');
}

/* When a visit is, as a text says it - "today at 2:00pm", "tomorrow at 9:30am", "on Tue, Nov 10 at 2:00pm" */
function smsWhenText(string $datetime): string
{
    $at = strtotime($datetime);
    $time = date('g:ia', $at);
    if (date('Y-m-d', $at) === date('Y-m-d')) {
        return "today at $time";
    }
    if (date('Y-m-d', $at) === date('Y-m-d', strtotime('tomorrow'))) {
        return "tomorrow at $time";
    }
    return 'on ' . date('D, M j', $at) . " at $time";
}

/*
 * A ticket's contact told by text, from triggerCustomAction(): ticket_create (opened for them -
 * not one that came in by text, which was answered already), ticket_resolve, ticket_schedule and
 * ticket_unschedule. Each as Settings > Notifications > Texts to clients has it, and only to a
 * contact who agreed. Their reply goes on the ticket.
 */
function smsClientNotify(string $trigger, $ticket_id): void
{
    if (!smsReady()) {
        return;
    }
    $row = ticketRow($ticket_id);
    if (!$row || !intval($row['ticket_contact_id'])) {
        return;
    }
    $settings = smsClientSettings();
    $reference = ticketReference($row);
    $company = smsCompanyName();
    $meta = ['ticket_id' => intval($row['ticket_id'])];
    $text = '';
    $before = null;

    if ($trigger === 'ticket_create' && $settings['config_sms_client_tickets'] && $row['ticket_source'] !== 'Text') {
        $text = "$company: we opened ticket $reference for you - " . smsTicketSubject($row) . ". Reply to this text to add to it.";
        $meta['purpose'] = 'notify_opened';
    } elseif ($trigger === 'ticket_resolve' && $settings['config_sms_client_tickets']) {
        $text = "$company: ticket $reference (" . smsTicketSubject($row) . ") is resolved. If it isn't fixed, reply here and we'll pick it up again.";
        $meta['purpose'] = 'notify_resolved';
    } elseif ($trigger === 'ticket_schedule' && $settings['config_sms_client_visits'] && !empty($row['ticket_schedule'])) {
        $what = intval($row['ticket_onsite']) ? 'A visit' : 'Remote work';
        $who = trim((string) $row['user_name']) !== '' ? ' with ' . strtok(trim((string) $row['user_name']), ' ') : '';
        $text = "$company: $what$who for ticket $reference is booked " . smsWhenText($row['ticket_schedule']) . ". Reply here if that doesn't suit.";
        $meta['purpose'] = 'notify_scheduled';
        $before = strtotime($row['ticket_schedule']);
    } elseif ($trigger === 'ticket_unschedule' && $settings['config_sms_client_visits']) {
        $text = "$company: the time booked for ticket $reference is cancelled - we'll be in touch to book another.";
        $meta['purpose'] = 'notify_unscheduled';
    }
    if ($text !== '') {
        smsNotifyContact(intval($row['ticket_contact_id']), $text, $meta, $before);
    }
}

/* An agent's emailed reply, told by text too - when Texts to clients says each reply. Whether it was */
function smsTicketReplyNotify(array $row): bool
{
    if (!smsReady() || smsClientSettings()['config_sms_client_tickets'] !== 2) {
        return false;
    }
    return smsNotifyContact(intval($row['ticket_contact_id']), smsCompanyName() . " replied on ticket " . ticketReference($row) . " - see your email. Or reply here to answer by text.",
        ['ticket_id' => intval($row['ticket_id']), 'purpose' => 'notify_replied']);
}

/*
 * Reminders of visits and calls booked on tickets - from the Text Messages job, the set hours
 * before, to contacts who agreed. Once a booking: not when one went in the reminder's window
 * already, nor when the booking itself was texted in it. How many were queued.
 */
function smsVisitReminders(): int
{
    global $mysqli;
    $settings = smsClientSettings();
    $hours = $settings['config_sms_visit_reminder_hours'];
    if (!smsReady() || !$settings['config_sms_client_visits'] || $hours <= 0) {
        return 0;
    }
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets
        JOIN contacts ON contact_id = ticket_contact_id AND contact_sms_opt_in = 1 AND contact_archived_at IS NULL
        WHERE ticket_schedule > NOW() AND ticket_schedule <= NOW() + INTERVAL $hours HOUR
        AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL AND ticket_archived_at IS NULL
        AND NOT EXISTS (SELECT 1 FROM sms_messages WHERE sms_message_purpose IN ('reminder', 'notify_scheduled') AND sms_message_ticket_id = ticket_id
            AND sms_message_created_at > NOW() - INTERVAL $hours HOUR)
        LIMIT 50");
    $queued = 0;
    while ($found = mysqli_fetch_row($sql)) {
        $row = ticketRow($found[0]);
        $who = trim((string) $row['user_name']) !== '' ? strtok(trim((string) $row['user_name']), ' ') : 'we';
        $what = intval($row['ticket_onsite']) ? ($who === 'we' ? 'we are visiting' : "$who is visiting") : ($who === 'we' ? 'we are connecting remotely' : "$who is connecting remotely");
        $text = smsCompanyName() . ": a reminder - $what for ticket " . ticketReference($row) . ' ' . smsWhenText($row['ticket_schedule']) . ". Reply here if that doesn't suit.";
        if (smsNotifyContact(intval($row['ticket_contact_id']), $text, ['ticket_id' => intval($row['ticket_id']), 'purpose' => 'reminder'], strtotime($row['ticket_schedule']))) {
            $queued++;
        }
    }
    return $queued;
}

/*
 * An invoice, or a reminder of what's owed, texted with its link to the ones of the people it
 * was emailed to who agreed to texts - when Texts to clients has invoices on. $kind 'invoice' or
 * 'reminder'; $recipients as billingEmailRecipients() gives them. Who it went to, for the
 * invoice's history ("Name (+1 ...)").
 */
function smsInvoiceNotify(array $invoice, string $kind, array $recipients): array
{
    if (!smsReady() || !smsClientSettings()['config_sms_client_invoices']) {
        return [];
    }
    $state = invoiceState($invoice, invoicePaid($invoice['invoice_id']));
    // A draft is being sent as this is asked
    if (!in_array($state['state'], ['open', 'overdue', 'draft'], true) || $state['balance'] <= 0) {
        return [];
    }
    $number = billingNumber('invoice', $invoice);
    $currency = (string) $invoice['invoice_currency_code'];
    $link = billingGuestUrl('invoice', $invoice);
    $pay = billingOnlinePayments() ? 'Pay' : 'View it';
    if ($kind === 'reminder') {
        $when = $state['days'] < 0 ? abs($state['days']) . ' day' . (abs($state['days']) === 1 ? '' : 's') . ' overdue' : 'due ' . date('M j', strtotime($invoice['invoice_due']));
        $text = smsCompanyName() . ": a reminder - invoice $number has " . billingMoney($state['balance'], $currency) . " still to pay, $when. $pay: $link";
    } else {
        $text = smsCompanyName() . ": invoice $number for " . billingMoney($state['balance'], $currency) . ' is due ' . date('M j', strtotime($invoice['invoice_due'])) . ". $pay: $link";
    }
    $texted = [];
    foreach ($recipients as $recipient) {
        $contact = smsContactTextable(intval($recipient['id'] ?? 0));
        if ($contact && smsNotifyContact(intval($contact['contact_id']), $text, ['purpose' => $kind === 'reminder' ? 'invoice_reminder' : 'invoice'])) {
            $texted[] = $contact['contact_name'] . ' (' . smsNumberText($contact['number']) . ')';
        }
    }
    return $texted;
}

/*
 * Turn a contact's texts on or off - $by says how they agreed, for the record ("Alex Rivera,
 * with their say-so", "them, in the client portal"). Turned on by the contact themselves, a STOP
 * they sent before is lifted: this is them asking again. An agent turning it on doesn't lift one.
 */
function smsOptInSet(int $contact_id, bool $on, string $by, bool $by_them = true): void
{
    global $mysqli;
    if (!$contact_id || !smsColumnExists('contacts', 'contact_sms_opt_in')) {
        return;
    }
    if ($on) {
        mysqli_query($mysqli, "UPDATE contacts SET contact_sms_opt_in = 1, contact_sms_opt_in_at = NOW(), contact_sms_opt_in_by = '" . escapeSql(mb_substr($by, 0, 200)) . "' WHERE contact_id = $contact_id");
        if ($by_them) {
            $contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_mobile, contact_mobile_country_code FROM contacts WHERE contact_id = $contact_id"));
            $number = $contact ? smsContactNumber($contact) : '';
            if ($number !== '' && smsOptedOut($number)) {
                smsNumberSet($number, ['opted_out_at' => null]);
            }
        }
    } else {
        mysqli_query($mysqli, "UPDATE contacts SET contact_sms_opt_in = 0, contact_sms_opt_in_at = NULL, contact_sms_opt_in_by = NULL WHERE contact_id = $contact_id");
    }
}

/* Ask a contact by text whether texts are all right - YES agrees (smsInbound()). ['ok', 'error'] */
function smsOptInAsk(int $contact_id, int $user_id): array
{
    global $mysqli;
    if (!smsReady()) {
        return ['ok' => false, 'error' => "texting isn't set up"];
    }
    $contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_client_id, contact_mobile, contact_mobile_country_code FROM contacts WHERE contact_id = $contact_id AND contact_archived_at IS NULL"));
    $number = $contact ? smsContactNumber($contact) : '';
    if ($number === '') {
        return ['ok' => false, 'error' => 'they have no mobile number'];
    }
    if (smsOptedOut($number)) {
        return ['ok' => false, 'error' => 'they replied STOP to your texts - only they can turn texts back on, by texting START'];
    }
    $queued = smsQueue($number, smsCompanyName() . ': can we text you about your tickets, visits and invoices? Reply YES to agree. Reply STOP and you won\'t get texts from us.',
        ['purpose' => 'opt_in_request', 'contact_id' => $contact_id, 'client_id' => intval($contact['contact_client_id']), 'user_id' => $user_id]);
    if ($queued['error'] !== '') {
        return ['ok' => false, 'error' => $queued['error']];
    }
    $sent = smsSendNow($queued['ids']);
    return ['ok' => $sent['sent'] > 0, 'error' => $sent['error']];
}

/* The contact a number was asked about texts for, in the last SMS_OPT_IN_ASK_DAYS days, who hasn't agreed yet - or null */
function smsOptInPending(string $number): ?array
{
    global $mysqli;
    if (!smsColumnExists('contacts', 'contact_sms_opt_in')) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_client_id FROM sms_messages
        JOIN contacts ON contact_id = sms_message_contact_id AND contact_archived_at IS NULL AND contact_sms_opt_in = 0
        WHERE sms_message_direction = 'out' AND sms_message_purpose = 'opt_in_request' AND sms_message_number = '" . escapeSql($number) . "'
        AND sms_message_created_at > NOW() - INTERVAL " . SMS_OPT_IN_ASK_DAYS . " DAY
        ORDER BY sms_message_id DESC LIMIT 1"));
    return $row ?: null;
}

/*
 * Where a contact stands with texts, for their page - ['ready' => texting is set up, 'number',
 * 'on', 'at', 'by', 'stopped' => replied STOP, 'asked_at' => asked by text and not answered yet].
 */
function smsContactTextState(int $contact_id): array
{
    global $mysqli;
    $state = ['ready' => smsReady() && smsColumnExists('contacts', 'contact_sms_opt_in'), 'number' => '', 'on' => false, 'at' => null, 'by' => '', 'stopped' => false, 'asked_at' => null];
    if (!$state['ready']) {
        return $state;
    }
    $contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_mobile, contact_mobile_country_code, contact_sms_opt_in, contact_sms_opt_in_at, contact_sms_opt_in_by FROM contacts WHERE contact_id = $contact_id"));
    if (!$contact) {
        return $state;
    }
    $state['number'] = smsContactNumber($contact);
    $state['on'] = intval($contact['contact_sms_opt_in']) === 1;
    $state['at'] = $contact['contact_sms_opt_in_at'];
    $state['by'] = (string) $contact['contact_sms_opt_in_by'];
    $state['stopped'] = $state['number'] !== '' && smsOptedOut($state['number']);
    if (!$state['on'] && $state['number'] !== '') {
        $asked = mysqli_fetch_row(mysqli_query($mysqli, "SELECT MAX(sms_message_created_at) FROM sms_messages WHERE sms_message_purpose = 'opt_in_request' AND sms_message_contact_id = $contact_id
            AND sms_message_created_at > NOW() - INTERVAL " . SMS_OPT_IN_ASK_DAYS . " DAY"));
        $state['asked_at'] = $asked[0] ?? null;
    }
    return $state;
}

/* The contact window's Texts switch, saved - when it was on the window, and changed. With their say-so, an agent's word for it */
function smsContactOptInFromForm(int $contact_id, array $post, int $client_id): void
{
    global $session_name;
    if (empty($post['sms_opt_in_shown']) || !$contact_id) {
        return;
    }
    $state = smsContactTextState($contact_id);
    $want = !empty($post['contact_sms_opt_in']);
    if (!$state['ready'] || $want === $state['on']) {
        return;
    }
    smsOptInSet($contact_id, $want, stripslashes((string) $session_name) . ', with their say-so', false);
    $name = (string) getFieldById('contacts', $contact_id, 'contact_name');
    logAudit('Contact', 'Edit', "$session_name " . ($want ? 'turned on texts for ' : 'turned off texts for ') . escapeSql($name), $client_id, $contact_id);
}
