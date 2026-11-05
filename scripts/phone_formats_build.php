<?php

/*
 * ITFlow - build functions/phone_formats.php, how phone numbers are written
 * in each country, from Google's libphonenumber metadata.
 *
 *   curl -LO https://raw.githubusercontent.com/google/libphonenumber/v9.0.43/resources/PhoneNumberMetadata.xml
 *   php scripts/phone_formats_build.php PhoneNumberMetadata.xml v9.0.43
 *
 * Run it again with a newer release when a country changes how its numbers
 * are written. Each calling code takes its main country's rules - ITFlow keeps
 * the calling code, not the country (+1 is the US, Canada and more, written
 * the same way).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$source = $argv[1] ?? '';
$version = $argv[2] ?? '';
if ($source === '' || $version === '' || !is_readable($source)) {
    fwrite(STDERR, "Usage: php phone_formats_build.php PhoneNumberMetadata.xml <libphonenumber version, as v9.0.43>\n");
    exit(1);
}

$xml = simplexml_load_file($source);
if (!$xml) {
    fwrite(STDERR, "That isn't libphonenumber's PhoneNumberMetadata.xml\n");
    exit(1);
}

$clean = function ($text) {
    return preg_replace('/\s+/', '', (string) $text);
};
$quote = function ($text) {
    return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $text) . "'";
};

// The main country for each calling code
$by_code = [];
foreach ($xml->territories->territory as $territory) {
    $code = (string) $territory['countryCode'];
    if (!isset($by_code[$code]) || (string) $territory['mainCountryForCode'] === 'true') {
        $by_code[$code] = $territory;
    }
}
uksort($by_code, function ($a, $b) {
    return [strlen($a), $a] <=> [strlen($b), $b];
});

$lines = [];
foreach ($by_code as $code => $territory) {
    $prefix = (string) $territory['nationalPrefix'];
    $default_rule = (string) $territory['nationalPrefixFormattingRule'];
    $formats = [];
    foreach ($territory->availableFormats->numberFormat ?? [] as $format) {
        $leading = '';
        foreach ($format->leadingDigits as $digits) {
            $leading = $clean($digits);   // the last is the most precise
        }
        $rule = isset($format['nationalPrefixFormattingRule']) ? (string) $format['nationalPrefixFormattingRule'] : $default_rule;
        $rule = str_replace(['$NP', '$FG'], [$prefix, '$1'], $rule);
        $parts = [(string) $format['pattern'], (string) $format->format, $leading, $rule, isset($format->intlFormat) ? (string) $format->intlFormat : ''];
        $formats[] = '[' . implode(', ', array_map($quote, $parts)) . ']';
    }
    $lines[] = '    ' . $quote($code) . " => ['np' => " . $quote($prefix)
        . ", 'npfp' => " . $quote($clean($territory->nationalPrefixForParsing))
        . ", 'nptr' => " . $quote($clean($territory->nationalPrefixTransformRule))
        . ", 'valid' => " . $quote($clean($territory->generalDesc->nationalNumberPattern))
        . ", 'formats' => [" . implode(', ', $formats) . ']],';
}

$out = <<<PHP
<?php

/*
 * ITFlow - how phone numbers are written, by calling code (formatPhoneNumber()
 * in functions/format.php). Built by scripts/phone_formats_build.php from
 * Google's libphonenumber metadata, $version - the same data the phone fields'
 * intl-tel-input formats with, so a number reads the same on the page as in
 * its field. Licensed under the Apache License 2.0, Copyright (C) 2009 The
 * Libphonenumber Authors. Don't edit it by hand - build it again.
 *
 * Each calling code, for its main country: np, the prefix dialled before a
 * number at home (0 in most countries, 1 in North America, none in Italy);
 * npfp and nptr, how that prefix is found and taken off when that isn't
 * simply np; valid, what a number looks like without it; and formats, each
 * [pattern, format, leading digits, national prefix rule, international
 * format - '' for the same, NA for none].
 */

return [

PHP;
file_put_contents(__DIR__ . '/../functions/phone_formats.php', $out . implode("\n", $lines) . "\n];\n");
echo count($by_code) . " calling codes written to functions/phone_formats.php\n";
