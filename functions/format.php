<?php

// String, number, date & time formatting helpers
// Split from the former monolithic functions.php


function initials($string) {
    if (!empty($string)) {
        $return = '';
        foreach (explode(' ', $string) as $word) {
            $return .= mb_strtoupper($word[0], 'UTF-8'); // Use mb_strtoupper for UTF-8 support
        }
        $return = substr($return, 0, 2);
        return $return;
    }
}


function truncate($text, $chars) {
    if (strlen($text) <= $chars) {
        return $text;
    }
    $text = $text . " ";
    $text = substr($text, 0, $chars);
    $lastSpacePos = strrpos($text, ' ');
    if ($lastSpacePos !== false) {
        $text = substr($text, 0, $lastSpacePos);
    }
    return $text . "...";
}


/**
 * A number for reading.
 *
 * $show_country_code defaults to TRUE: a phone number on screen, in a PDF, in
 * an email or in an export is being read by a human who may need to dial it,
 * and a bare national number is ambiguous the moment a client is abroad. The
 * dial code is only prepended when one is actually stored, so records without
 * one look exactly as they did.
 *
 * Pass false where the output feeds a form INPUT - intl-tel-input runs in
 * separateDialCode mode, so its dropdown owns the code and the field must hold
 * the national number alone. Those are the only call sites that should.
 *
 * Written the way the country writes it, from libphonenumber's rules
 * (functions/phone_formats.php) - the ones intl-tel-input formats the field
 * with. With the dial code, the prefix dialled at home goes: a German mobile
 * stored as 01721234567 is "+49 172 1234567", and "0172 1234567" without it.
 * Italy keeps its 0, as Italians dial it from abroad too. North America keeps
 * the look it has always had here, "+1 (412) 555-0100". A number its country
 * doesn't write that way - too short, too long - is left as its digits.
 */
function formatPhoneNumber($phoneNumber, $country_code = '', $show_country_code = true) {
    $parts = phoneParts($phoneNumber, $country_code);
    if ($parts['national'] === '') {
        return $phoneNumber;
    }
    $cc = $parts['cc'];
    if ($cc === '') {
        return $parts['national'];
    }
    if (!$parts['rules']) {
        // A dial code with no rules - shown as stored
        return $show_country_code ? "+$cc " . $parts['national'] : $parts['national'];
    }

    if (!$show_country_code) {
        return phoneFormatNational($parts) ?? $parts['as_stored'];
    }
    if ($cc === '1') {
        $national = phoneFormatNational($parts);
        return '+1 ' . ($national ?? $parts['national']);
    }
    return "+$cc " . (phoneFormatWith($parts['national'], $parts['rules']['formats'], 'international') ?? $parts['national']);
}

/**
 * A number to dial - +, the dial code and the number without the prefix
 * dialled at home ("+491721234567"), for a tel: link. Just its digits when
 * it has no dial code, and '' when there is no number.
 */
function phoneDialNumber($phoneNumber, $country_code = '') {
    $parts = phoneParts($phoneNumber, $country_code);
    if ($parts['national'] === '') {
        return '';
    }
    return $parts['cc'] !== '' ? '+' . $parts['cc'] . $parts['national'] : $parts['national'];
}

/** libphonenumber's rules for a dial code (functions/phone_formats.php, read once) - null for a code it doesn't know. */
function phoneFormatRules($country_code) {
    static $rules = null;
    if ($rules === null) {
        $rules = require __DIR__ . '/phone_formats.php';
    }
    return $rules[(string) $country_code] ?? null;
}

/**
 * A stored number in its parts - cc, the dial code's digits; national, the
 * number dialled after the dial code; as_stored, its digits as they were; and
 * rules, phoneFormatRules(). The prefix dialled at home comes off (the 0 in
 * Germany, the 1 in North America, the 8 in Russia), and so does the dial
 * code when it was typed in with the number, as libphonenumber takes them
 * off: only where what is left still looks like a number of that country.
 */
function phoneParts($phoneNumber, $country_code = '') {
    $digits = preg_replace('/\D/', '', (string) $phoneNumber);
    $cc = preg_replace('/\D/', '', (string) $country_code);
    $parts = ['cc' => $cc, 'national' => $digits, 'as_stored' => $digits, 'rules' => null];
    if ($cc === '' || $digits === '' || !($rules = phoneFormatRules($cc))) {
        return $parts;
    }
    $parts['rules'] = $rules;
    $looks_right = function ($number) use ($rules) {
        return $rules['valid'] === '' || preg_match('/^(?:' . $rules['valid'] . ')$/', $number) === 1;
    };

    // The dial code typed in front of the number - "+49 172..." or "0049 172..."
    foreach (["00$cc", $cc] as $lead) {
        if (strpos($digits, $lead) === 0 && !$looks_right($digits) && $looks_right(substr($digits, strlen($lead)))) {
            $digits = substr($digits, strlen($lead));
            break;
        }
    }

    // The prefix dialled at home - kept when taking it off leaves something that isn't a number
    $prefix = $rules['npfp'] !== '' ? $rules['npfp'] : ($rules['np'] !== '' ? preg_quote($rules['np'], '/') : '');
    if ($prefix !== '' && preg_match('/^(?:' . $prefix . ')/', $digits, $match, PREG_UNMATCHED_AS_NULL)) {
        // A transform rule rewrites the number only when the prefix's last group matched
        $last_group = count($match) - 1;
        $rest = $rules['nptr'] !== '' && $last_group > 0 && $match[$last_group] !== null
            ? preg_replace('/^(?:' . $prefix . ')/', $rules['nptr'], $digits, 1)
            : substr($digits, strlen($match[0]));
        if ($rest !== '' && !($looks_right($digits) && !$looks_right($rest))) {
            $digits = $rest;
        }
    }
    $parts['national'] = $digits;
    return $parts;
}

/** A number as it is written at home - "0172 1234567" - or null when its country doesn't write one like it. */
function phoneFormatNational(array $parts) {
    return phoneFormatWith($parts['national'], $parts['rules']['formats'] ?? [], 'national');
}

/**
 * A national number in the first of its country's formats that fits it
 * (libphonenumber's way: the leading digits, then the whole number), as
 * written at home - with the prefix dialled there - or after the dial code.
 * Null when none fits.
 */
function phoneFormatWith($national, array $formats, $style) {
    foreach ($formats as [$pattern, $format, $leading, $prefix_rule, $international]) {
        if ($style === 'international') {
            if ($international === 'NA') {
                continue;
            }
            $format = $international !== '' ? $international : $format;
        } elseif ($prefix_rule !== '') {
            // The prefix rule ("0$1", "($1)") stands in for the format's first group, whichever group that is
            $format = preg_replace_callback('/\$\d/', function ($group) use ($prefix_rule) {
                return str_replace('$1', $group[0], $prefix_rule);
            }, $format, 1);
        }
        if ($leading !== '' && !preg_match('/^(?:' . $leading . ')/', $national)) {
            continue;
        }
        if (!preg_match('/^(?:' . $pattern . ')$/', $national)) {
            continue;
        }
        return preg_replace('/^(?:' . $pattern . ')$/', $format, $national);
    }
    return null;
}


function formatAddress($address, $city, $state, $zip, $country = '', $separator = "\n") {
    $address = trim((string)$address);
    $city    = trim((string)$city);
    $state   = trim((string)$state);
    $zip     = trim((string)$zip);
    $country = trim((string)$country);

    // "City, ST 15212" - the comma only appears when something follows the city
    $region = trim(implode(' ', array_filter([$state, $zip], 'strlen')));
    if ($city !== '' && $region !== '') {
        $region = "$city, $region";
    } elseif ($city !== '') {
        $region = $city;
    }

    // Empty parts drop out entirely rather than leaving stray separators
    return implode($separator, array_filter([$address, $region, $country], 'strlen'));
}


/**
 * The agent a ticket is assigned to, as a pill: their avatar (or their initials
 * on a grey circle) then their name. Unassigned keeps the same shape with a
 * user-slash circle - red while the ticket still needs someone, muted once it
 * doesn't. Takes the RAW user columns and escapes them itself.
 * Bootstrap 5.3 utilities and Font Awesome only - no custom CSS.
 */
function formatAssignee($user_id, $user_name, $user_avatar, $highlight_unassigned = true, $none_label = 'Unassigned') {
    $pill = "badge rounded-pill d-inline-flex align-items-center align-middle gap-1 p-1 pe-2";
    $user_id = intval($user_id);

    if (!$user_id) {
        $tone = $highlight_unassigned ? 'danger' : 'secondary';
        $colors = $highlight_unassigned ? 'text-danger-emphasis bg-danger-subtle' : 'text-body-secondary bg-body-secondary';
        return "<span class='$pill $colors'><span class='position-relative d-inline-flex' aria-hidden='true'><i class='fas fa-circle fs-5 text-$tone'></i><i class='fas fa-user-slash position-absolute top-50 start-50 translate-middle small text-white'></i></span>" . escapeHtml($none_label) . "</span>";
    }

    $user_avatar = escapeHtml($user_avatar);
    if ($user_avatar) {
        $avatar = "<img src='/uploads/users/$user_id/$user_avatar' alt='' width='20' height='20' class='rounded-circle object-fit-cover'>";
    } else {
        $avatar = "<span class='position-relative d-inline-flex' aria-hidden='true'><i class='fas fa-circle fs-5 text-secondary'></i><span class='position-absolute top-50 start-50 translate-middle small text-white'><small>" . escapeHtml(initials($user_name)) . "</small></span></span>";
    }

    return "<span class='$pill text-body-emphasis bg-body-secondary'>$avatar" . escapeHtml($user_name) . "</span>";
}


function timeAgo($datetime) {
    if (is_null($datetime)) {
        return "-";
    }

    $time = strtotime($datetime);
    $difference = $time - time(); // Changed to handle future dates

    if ($difference == 0) {
        return 'right now';
    }

    $isFuture = $difference > 0; // Check if the date is in the future
    $difference = abs($difference); // Absolute value for calculation

    $timeRules = array(
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    );

    foreach ($timeRules as $secs => $str) {
        $div = $difference / $secs;
        if ($div >= 1) {
            $t = round($div);
            $timeStr = $t . ' ' . $str . ($t > 1 ? 's' : '');
            return $isFuture ? 'in ' . $timeStr : $timeStr . ' ago';
        }
    }
}


function shortenClientName($client) {
    // Pre-process by removing any non-alphanumeric characters except for certain punctuations.
    $client = html_entity_decode($client); // Decode any HTML entities
    $client = str_replace("'", "", $client); // Removing all occurrences of '
    $cleaned = preg_replace('/[^a-zA-Z0-9&]+/', ' ', $client);

    // Break into words.
    $words = explode(' ', trim($cleaned));

    $shortened = '';

    // If there's only one word.
    if (count($words) == 1) {
        $word = $words[0];

        if (strlen($word) <= 3) {
            return strtoupper($word);
        }

        // Prefer starting and ending characters.
        $shortened = $word[0] . substr($word, -2);
    } else {
        // Less weightage to common words.
        $commonWords = ['the', 'of', 'and'];

        foreach ($words as $word) {
            if (!in_array(strtolower($word), $commonWords) || strlen($shortened) < 2) {
                $shortened .= $word[0];
            }
        }

        // If there are still not enough characters, take from the last word.
        while (strlen($shortened) < 3 && !empty($word)) {
            $shortened .= substr($word, 1, 1);
            $word = substr($word, 1);
        }
    }

    return strtoupper(substr($shortened, 0, 3));
}

/**
 * Up to three letters for a client's initials tile - their abbreviation when
 * they have one, otherwise one made from the name. Raw: escape at output.
 */
function clientInitials($abbreviation, $name) {
    $source = trim((string) $abbreviation) ?: shortenClientName((string) $name);
    return mb_strtoupper(mb_substr($source, 0, 3));
}

/** The colour of a client's initials tile, picked by id so it is the same on every page. */
function clientInitialsTone($client_id) {
    $tones = ['primary', 'success', 'info', 'warning', 'secondary'];
    return $tones[intval($client_id) % count($tones)];
}


/*
 * Option extras for the select picker (js/itflow_select.js): data-*
 * attributes that put a picture, initials, a colour or an icon in front of
 * an <option>, and an optional second line under it.
 *
 *     <option value="<?= $id ?>"<?= selectOptionClient($id, $name, $abbr) ?>><?= $name ?></option>
 *
 * Takes values raw or already escaped - they are decoded and escaped again
 * here, so either way the attribute comes out right.
 */
function selectOptionData(array $data) {
    $html = '';
    foreach ($data as $name => $value) {
        $value = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
        if ($value !== '') {
            $html .= ' data-' . $name . '="' . escapeHtml($value) . '"';
        }
    }
    return $html;
}

/** Up to two initials for a person's name - "Marla Whitfield" is MW. */
function personInitials($name) {
    $words = preg_split('/\s+/u', trim(html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8')), -1, PREG_SPLIT_NO_EMPTY);
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= mb_strtoupper(mb_substr($word, 0, 1));
    }
    return $initials;
}

/** A client - its coloured initials tile, the same as the client list and side nav. */
function selectOptionClient($client_id, $client_name, $client_abbreviation = '') {
    return selectOptionData([
        'initials' => clientInitials(html_entity_decode((string) $client_abbreviation, ENT_QUOTES, 'UTF-8'), html_entity_decode((string) $client_name, ENT_QUOTES, 'UTF-8')),
        'tone' => clientInitialsTone($client_id)
    ]);
}

/** An agent - their avatar, or their initials in a circle. */
function selectOptionUser($user_id, $user_name, $user_avatar = '') {
    $user_avatar = html_entity_decode((string) $user_avatar, ENT_QUOTES, 'UTF-8');
    if ($user_avatar !== '') {
        return selectOptionData(['image' => '/uploads/users/' . intval($user_id) . '/' . rawurlencode($user_avatar)]);
    }
    return selectOptionData(['initials' => personInitials($user_name), 'shape' => 'circle', 'tone' => 'secondary']);
}

/**
 * A contact - their photo, or their initials on the same soft colour as their
 * avatar everywhere else (contactAvatar() in functions/contact.php), with
 * their title underneath. Without $contact_id the initials are grey.
 */
function selectOptionContact($client_id, $contact_name, $contact_photo = '', $contact_title = '', $contact_id = 0) {
    $contact_photo = html_entity_decode((string) $contact_photo, ENT_QUOTES, 'UTF-8');
    $data = $contact_photo !== ''
        ? ['image' => '/uploads/clients/' . intval($client_id) . '/' . rawurlencode($contact_photo)]
        : ['initials' => personInitials($contact_name), 'shape' => 'circle', 'tone' => intval($contact_id) ? contactAvatarTone($contact_id) : 'secondary', 'soft' => '1'];
    $data['description'] = $contact_title;
    return selectOptionData($data);
}

/** A tag, category or status - its colour, and its icon if it has one. */
function selectOptionColor($color, $icon = '') {
    // A hex value, or a CSS colour name
    $color = preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,24})$/i', trim((string) $color)) ? trim($color) : '';
    // "star", "fa-star" (as categories keep them) or "fas fa-star"
    $icon = trim(preg_replace('/[^a-z0-9 -]/', '', strtolower((string) $icon)));
    if ($icon !== '' && strpos($icon, 'fa-') === false) {
        $icon = "fas fa-$icon";
    } elseif ($icon !== '' && !preg_match('/(^| )fa[srbl]?( |$)/', $icon)) {
        $icon = "fas $icon";
    }
    return selectOptionData(['color' => $color, 'icon' => $icon]);
}

/** A ticket priority - the colour its badge has in the ticket lists. */
function selectOptionPriority($priority) {
    $colors = ['Urgent' => 'var(--bs-emphasis-color)', 'High' => 'var(--bs-danger)', 'Medium' => 'var(--bs-warning)', 'Low' => 'var(--bs-info)'];
    return selectOptionData(['color' => $colors[html_entity_decode((string) $priority)] ?? '']);
}

/** An asset - the icon for its type, with make and model underneath. */
function selectOptionAsset($asset_type, $description = '') {
    return selectOptionData(['icon' => 'fas fa-' . getAssetIcon(html_entity_decode((string) $asset_type, ENT_QUOTES, 'UTF-8')), 'description' => $description]);
}

function roundUpToNearestMultiple($n, $increment = 1000) {
    return (int) ($increment * ceil($n / $increment));
}


function roundToNearest15Min($time) {
    // Validate the input time format
    if (!preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $time, $matches)) {
        return false; // or throw an exception
    }

    // Extract hours, minutes, and seconds from the matched time string
    list(, $hours, $minutes, $seconds) = $matches;

    // Convert everything to seconds for easier calculation
    $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds;

    // Calculate the remainder when divided by 900 seconds (15 minutes)
    $remainder = $totalSeconds % 900;

    if ($remainder > 450) {  // If remainder is more than 7.5 minutes (450 seconds), round up
        $totalSeconds += (900 - $remainder);
    } else {  // Else round down
        $totalSeconds -= $remainder;
    }

    // Convert total seconds to decimal hours
    $decimalHours = $totalSeconds / 3600;

    // Return the decimal hours
    return number_format($decimalHours, 2);
}


function formatDuration($time) {
    // expects "HH:MM:SS" - nothing logged (NULL, '') is 0s
    [$h, $m, $s] = array_map('intval', array_pad(explode(':', (string) $time), 3, 0));

    $parts = [];

    if ($h > 0) $parts[] = $h . 'h';
    if ($m > 0) $parts[] = $m . 'm';

    // show seconds only if under 1 minute total OR if nothing else exists
    if ($h == 0 && $m == 0) {
        $parts[] = $s . 's';
    }

    return implode(' ', $parts);
}


function validateDate($date) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }
    return date('Y-m-d'); // Fallback
}

/*
 * Validates a recurring invoice frequency against the values the UI offers -
 * month, quarter and year, each also a MySQL interval unit. A whitelist is the
 * only safe handling for a value that could be spliced into SQL unquoted
 * (INTERVAL 1 <frequency>). Apply it at every write path AND at the read side
 * before use, so a row planted before this fix can't fire.
 */
function validateRecurringFrequency($frequency) {
    if (in_array($frequency, ['month', 'quarter', 'year'], true)) {
        return $frequency;
    }
    return 'month'; // Fallback
}

/*
 * Formats bytes into human readable file sizes
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}


function secondsToTime($inputSeconds) {
    $inputSeconds = floor($inputSeconds);

    $secondsInAMinute = 60;
    $secondsInAnHour = 60 * $secondsInAMinute;
    $secondsInADay = 24 * $secondsInAnHour;

    // Extract days
    $days = floor($inputSeconds / $secondsInADay);

    // Extract hours
    $hourSeconds = $inputSeconds % $secondsInADay;
    $hours = floor($hourSeconds / $secondsInAnHour);

    // Extract minutes
    $minuteSeconds = $hourSeconds % $secondsInAnHour;
    $minutes = floor($minuteSeconds / $secondsInAMinute);

    // Extract the remaining seconds
    $remainingSeconds = $minuteSeconds % $secondsInAMinute;
    $seconds = ceil($remainingSeconds);

    // Format and return
    $timeParts = [];
    $sections = [
        'day' => (int)$days,
        'hour' => (int)$hours,
        'minute' => (int)$minutes,
        'second' => (int)$seconds,
    ];

    foreach ($sections as $name => $value) {
        if ($value > 0) {
            $timeParts[] = $value. ' '.$name.($value == 1 ? '' : 's');
        }
    }

    return implode(', ', $timeParts);
}

/*
 * The empty state of a list - one look on every list page. A page sets
 * $empty_state and includes/inc_empty_state.php (from filter_footer.php)
 * draws it when the list has nothing; a page with a layout of its own calls
 * emptyStateHtml() where it wants it. Three kinds:
 *
 *   - nothing there yet: the list's icon, "No vendors yet", a line on what
 *     goes there, and the page's own New buttons
 *   - a search or filters left nothing: "No vendors match" and Clear filters
 *   - a view that is empty for a good reason (nothing overdue, all caught
 *     up): its own title, with a tick
 *
 * Archived, with nothing in it, says so and offers nothing to add.
 */

/**
 * Whether a search or filter is on the request: any GET value but paging,
 * sorting, the client, Archived and the page's views ($views - the GET keys
 * that pick a view, such as a pill, rather than filter it).
 */
function emptyStateFiltered(array $views = []) {
    $ignored = array_merge(['page', 'sort', 'order', 'canned_date', 'show_column', 'client_id', 'archived'], $views);
    foreach ($_GET as $key => $value) {
        if (in_array($key, $ignored, true)) {
            continue;
        }
        if (is_array($value) ? count(array_filter($value, function ($v) { return is_scalar($v) && trim((string) $v) !== ''; })) : trim((string) $value) !== '') {
            return true;
        }
    }
    return false;
}

/** This page with its search and filters gone - the client, Archived and the page's views ($views) kept. */
function emptyStateClearUrl(array $views = []) {
    $keep = [];
    foreach (array_merge(['client_id', 'archived'], $views) as $key) {
        if (isset($_GET[$key]) && is_scalar($_GET[$key]) && trim((string) $_GET[$key]) !== '') {
            $keep[$key] = (string) $_GET[$key];
        }
    }
    return strtok($_SERVER['REQUEST_URI'] ?? '', '?') . ($keep ? '?' . http_build_query($keep) : '');
}

/**
 * The empty state of the pill picked ($key, from a page's $pills - label,
 * icon, title, alarm): "Nothing in Overdue" with the pill's own line under
 * it - with a tick for a pill that flags trouble (alarm, or one of $good),
 * as nothing there is good news. Null for the page's usual pill
 * ($default), All and Archived, which have their own.
 */
function emptyStatePill(array $pills, $key, $default = '', array $good = []) {
    $key = (string) $key;
    if ($key === (string) $default || $key === 'all' || $key === 'archived' || !isset($pills[$key])) {
        return null;
    }
    $pill = $pills[$key];
    $view = ['title' => 'Nothing in ' . escapeHtml($pill['label'] ?? $key), 'text' => escapeHtml($pill['title'] ?? '')];
    if (!empty($pill['alarm']) || in_array($key, array_map('strval', $good), true)) {
        return $view + ['icon' => 'fa-check'];
    }
    return $view + ['icon' => $pill['icon'] ?? 'fa-filter'];
}

/**
 * A list's empty state. $o:
 *   thing   - what the list holds, plural ('vendors')
 *   icon    - the list's icon ('fa-building', or full classes 'fab fa-...')
 *   title   - HTML; "No {thing} yet" when left out
 *   text    - HTML; a line on what goes there
 *   actions - when nothing is there and nothing filters it: buttons, the first
 *             the main one - each [label, modal (an ajax-modal URL) or href,
 *             size (the modal's), icon]
 *   archived - the Archived view: "No archived {thing}", nothing to add
 *   view    - the pill picked, when it has a title of its own
 *             (emptyStatePill()): title, text, icon and actions over the
 *             ones above - a tick for good news
 *   filtered - a search or filters left nothing; emptyStateFiltered($views)
 *             when left out
 *   views   - GET keys that pick a view rather than filter - not counted as
 *             filters, and kept by Clear filters
 *   match_title, match_text - HTML, for when filters left nothing
 *   clear_url - where Clear filters goes, for a page that builds its own
 */
function emptyStateHtml(array $o) {
    $thing = (string) ($o['thing'] ?? 'records');
    $views = (array) ($o['views'] ?? []);
    $filtered = array_key_exists('filtered', $o) ? (bool) $o['filtered'] : emptyStateFiltered($views);
    $buttons = '';
    if (!$filtered && empty($o['archived']) && !empty($o['view'])) {
        $o = (array) $o['view'] + ['text' => ''] + $o;
    }

    if ($filtered) {
        $icon = 'fas fa-filter';
        $title = $o['match_title'] ?? 'No ' . escapeHtml($thing) . ' match';
        $text = $o['match_text'] ?? 'Try a different search, or clear the filters to see everything.';
        $buttons = '<a class="btn btn-outline-secondary" href="' . escapeHtml($o['clear_url'] ?? emptyStateClearUrl($views)) . '"><i class="fas fa-times me-2"></i>Clear filters</a>';
    } elseif (!empty($o['archived'])) {
        $icon = 'fas fa-archive';
        $title = $o['archived_title'] ?? 'No archived ' . escapeHtml($thing);
        $text = '';
    } else {
        $icon = (string) ($o['icon'] ?? 'fa-inbox');
        $icon = strpos($icon, ' ') === false ? "fas $icon" : $icon;
        $title = $o['title'] ?? 'No ' . escapeHtml($thing) . ' yet';
        $text = (string) ($o['text'] ?? '');
        foreach (array_values((array) ($o['actions'] ?? [])) as $i => $action) {
            $class = 'btn ' . ($i ? 'btn-outline-primary' : 'btn-primary');
            $inner = '<i class="fas ' . escapeHtml($action['icon'] ?? 'fa-plus') . ' me-2"></i>' . escapeHtml($action['label']);
            if (isset($action['modal'])) {
                $buttons .= '<button type="button" class="' . $class . ' ajax-modal" data-modal-url="' . escapeHtml($action['modal']) . '"'
                    . (!empty($action['size']) ? ' data-modal-size="' . escapeHtml($action['size']) . '"' : '') . '>' . $inner . '</button>';
            } else {
                $buttons .= '<a class="' . $class . '" href="' . escapeHtml($action['href'] ?? '#') . '">' . $inner . '</a>';
            }
        }
    }

    return '<div class="itflow-empty-state">'
        . '<div class="itflow-empty-state-icon"><i class="' . escapeHtml($icon) . ' fa-fw" aria-hidden="true"></i></div>'
        . '<div class="itflow-empty-state-title">' . $title . '</div>'
        . ($text !== '' ? '<div class="itflow-empty-state-text">' . $text . '</div>' : '')
        . ($buttons !== '' ? '<div class="itflow-empty-state-actions">' . $buttons . '</div>' : '')
        . '</div>';
}
