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

function formatPhoneNumber($phoneNumber, $country_code = '', $show_country_code = false) {
    // Remove all non-digit characters
    $digits = preg_replace('/\D/', '', $phoneNumber ?? '');
    $formatted = '';

    // If no digits at all, fallback early
    if (strlen($digits) === 0) {
        return $phoneNumber;
    }

    // Helper function to safely check the first digit
    $startsWith = function($str, $char) {
        return isset($str[0]) && $str[0] === $char;
    };

    switch ($country_code) {
        case '1': // USA/Canada
            if (strlen($digits) === 10) {
                $formatted = '(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6);
            }
            break;

        case '44': // UK
            if ($startsWith($digits, '0')) {
                $digits = substr($digits, 1);
            }
            if (strlen($digits) === 10) {
                $formatted = '0' . substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7);
            }
            break;

        case '61': // Australia
            if ($startsWith($digits, '0')) {
                $digits = substr($digits, 1);
            }
            if (strlen($digits) === 9) {
                $formatted = '0' . substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7);
            }
            break;

        case '91': // India
            if (strlen($digits) === 10) {
                $formatted = substr($digits, 0, 5) . ' ' . substr($digits, 5);
            }
            break;

        case '81': // Japan
            if ($startsWith($digits, '0')) {
                $digits = substr($digits, 1);
            }
            if (strlen($digits) >= 9 && strlen($digits) <= 10) {
                $formatted = '0' . substr($digits, 0, 2) . '-' . substr($digits, 2, 4) . '-' . substr($digits, 6);
            }
            break;

        case '49': // Germany
            if ($startsWith($digits, '0')) {
                $digits = substr($digits, 1);
            }
            if (strlen($digits) >= 10) {
                $formatted = '0' . substr($digits, 0, 3) . ' ' . substr($digits, 3);
            }
            break;

        case '33': // France
            if ($startsWith($digits, '0')) {
                $digits = substr($digits, 1);
            }
            if (strlen($digits) === 9) {
                $formatted = '0' . implode(' ', str_split($digits, 2));
            }
            break;

        case '34': // Spain
            if (strlen($digits) === 9) {
                $formatted = substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6);
            }
            break;

        case '39': // Italy
            if ($startsWith($digits, '0')) {
                $digits = substr($digits, 1);
            }
            $formatted = '0' . implode(' ', str_split($digits, 3));
            break;

        case '55': // Brazil
            if (strlen($digits) === 11) {
                $formatted = '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 5) . '-' . substr($digits, 7);
            }
            break;

        case '7': // Russia
            if ($startsWith($digits, '8')) {
                $digits = substr($digits, 1);
            }
            if (strlen($digits) === 10) {
                $formatted = '8 (' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6, 2) . '-' . substr($digits, 8);
            }
            break;

        case '86': // China
            if (strlen($digits) === 11) {
                $formatted = substr($digits, 0, 3) . ' ' . substr($digits, 3, 4) . ' ' . substr($digits, 7);
            }
            break;

        case '82': // South Korea
            if (strlen($digits) === 11) {
                $formatted = substr($digits, 0, 3) . '-' . substr($digits, 3, 4) . '-' . substr($digits, 7);
            }
            break;

        case '62': // Indonesia
            if (!$startsWith($digits, '0')) {
                $digits = '0' . $digits;
            }
            if (strlen($digits) === 12) {
                $formatted = substr($digits, 0, 4) . ' ' . substr($digits, 4, 4) . ' ' . substr($digits, 8);
            }
            break;

        case '63': // Philippines
            if (strlen($digits) === 11) {
                $formatted = substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7);
            }
            break;

        case '234': // Nigeria
            if (!$startsWith($digits, '0')) {
                $digits = '0' . $digits;
            }
            if (strlen($digits) === 11) {
                $formatted = substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7);
            }
            break;

        case '27': // South Africa
            if (strlen($digits) >= 9 && strlen($digits) <= 10) {
                $formatted = substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6);
            }
            break;

        case '971': // UAE
            if (strlen($digits) === 9) {
                $formatted = substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6);
            }
            break;

        default:
            // fallback — do nothing, use raw digits later
            break;
    }

    if (!$formatted) {
        $formatted = $digits ?: $phoneNumber;
    }

    return $show_country_code && $country_code ? "+$country_code $formatted" : $formatted;
}

function timeAgo($datetime)
{
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

function shortenClientName($client)
{
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

function roundUpToNearestMultiple($n, $increment = 1000)
{
    return (int) ($increment * ceil($n / $increment));
}

function roundToNearest15Min($time)
{
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
    // expects "HH:MM:SS"
    [$h, $m, $s] = array_map('intval', explode(':', $time));

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
