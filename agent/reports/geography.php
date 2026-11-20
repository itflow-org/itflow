<?php

/*
 * Geography - where clients and their locations are. It opens on the
 * company's own country when there are locations in it, otherwise on the
 * world, and drills in a step at a time: country, then state or province,
 * then city. Each place counts its active locations and the clients with at
 * least one of them, so a client with sites in two states counts in both.
 * The map is the world by country, or the states of the US and the provinces
 * of Canada; another country's regions are a chart instead.
 */

require_once "includes/inc_report.php";
reportEnforce('geography');

$leads = !empty($_GET['leads']);
$by = ($_GET['by'] ?? '') === 'locations' ? 'locations' : 'clients';

$all = reportGeoLocations($leads);
$client_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM clients WHERE client_archived_at IS NULL"
    . ($leads ? '' : ' AND client_lead = 0') . reportClientSql('client_id', 0)))[0]);

/** The locations in one place at a level. */
function geographyIn(array $locations, $level, $key) {
    return array_values(array_filter($locations, function ($location) use ($level, $key) {
        return $location[$level] === $key;
    }));
}

/** "1 client", "12 clients". */
function geographyNoun($n, $word) {
    return reportCount($n) . ' ' . $word . ($n == 1 ? '' : 's');
}

// Drill in a step at a time - a place picked that has no locations falls back a step. The
// filters carry where they were drawn (at), so picking another country starts it afresh
// rather than looking for the old state in it.
$at = explode('|', (string) ($_GET['at'] ?? ''), 2) + [1 => ''];
$home = reportGeoPlace([])['country'];
$countries = reportGeoGroup($all, 'country');
$country = (string) ($_GET['country'] ?? ($home !== '-' && geographyIn($all, 'country', $home) ? $home : 'all'));
$shown = $country === 'all' ? [] : geographyIn($all, 'country', $country);
if (!$shown) {
    $country = 'all';
    $shown = $all;
}
$regions = $country === 'all' ? [] : reportGeoGroup($shown, 'region');
$region = $country === 'all' || (isset($_GET['at']) && $at[0] !== $country) ? '' : (string) ($_GET['region'] ?? '');
if ($region !== '') {
    $in_region = geographyIn($shown, 'region', $region);
    $region = $in_region ? $region : '';
    $shown = $in_region ?: $shown;
}
$cities = reportGeoGroup($shown, 'city');
$city = $region === '' || (isset($_GET['at']) && $at[1] !== $region) ? '' : (string) ($_GET['city'] ?? '');
if ($city !== '') {
    $in_city = geographyIn($shown, 'city', $city);
    $city = $in_city ? $city : '';
    $shown = $in_city ?: $shown;
}
$level = $city !== '' ? 3 : ($region !== '' ? 2 : ($country !== 'all' ? 1 : 0));

$country_name = $country === 'all' ? 'All countries' : $shown[0]['country_name'];
$term = reportGeoRegionTerm($country);
$terms = reportGeoRegionTerm($country, true);
$path = [$country_name];
if ($level >= 2) {
    $path[] = $shown[0]['region_name'];
}
if ($level === 3) {
    $path[] = $shown[0]['city_name'];
}

$drill = function ($country, $region = '', $city = '') {
    return reportUrl(['country' => $country, 'region' => $region !== '' ? $region : null, 'city' => $city !== '' ? $city : null, 'at' => null, 'export' => null, 'table' => null]);
};
$place_link = function ($url, $row) {
    return '<a class="fw-semibold ' . ($row['key'] === '-' ? 'fst-italic text-body-secondary' : 'text-body') . '" href="' . escapeHtml($url) . '">' . escapeHtml($row['name']) . '</a>';
};
$share_meter = function ($row) {
    return '<div class="d-flex align-items-center justify-content-end gap-2"><div class="itflow-report-meter d-none d-lg-flex" style="width: 5rem"><span style="width: ' . min(100, floatval($row['share'])) . '%"></span></div>'
        . '<span class="small text-body-secondary">' . reportPercent($row['share']) . '</span></div>';
};

// The places a step down from here: countries, or this country's states
$areas = $level === 0 ? $countries : ($level === 1 ? $regions : []);
foreach ($areas as &$row) {
    $row['share'] = reportShare($row['locations'], count($shown), 1);
}
unset($row);
$area_columns = [
    'name' => ['label' => $level === 0 ? 'Country' : $term, 'html' => function ($row) use ($drill, $place_link, $level) {
        return $place_link($level === 0 ? $drill($row['key']) : $drill($row['country'], $row['key']), $row);
    }],
    'clients' => ['label' => 'Clients', 'type' => 'count', 'class' => 'fw-semibold'],
    'locations' => ['label' => 'Locations', 'type' => 'count'],
    'cities' => ['label' => 'Cities', 'type' => 'count'],
    'share' => ['label' => 'Share', 'type' => 'percent', 'title' => 'Share of the locations', 'html' => $share_meter],
];

$city_columns = [
    'name' => ['label' => 'City', 'html' => function ($row) use ($drill, $place_link) {
        return $place_link($drill($row['country'], $row['region'], $row['key']), $row);
    }],
    'region_name' => ['label' => $level === 0 ? 'State or region' : $term, 'screen' => $level < 2],
    'country_name' => ['label' => 'Country', 'screen' => $level === 0],
    'clients' => ['label' => 'Clients', 'type' => 'count', 'class' => 'fw-semibold'],
    'locations' => ['label' => 'Locations', 'type' => 'count'],
];

foreach ($shown as &$row) {
    $row['address'] = locationAddressText($row);
}
unset($row);
$location_columns = [
    'client_name' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['client_id'], $row['client_name'], $row['client_abbreviation']);
    }],
    'location_name' => ['label' => 'Location', 'html' => function ($row) {
        return '<a class="text-body" href="/agent/location.php?client_id=' . intval($row['client_id']) . '&location_id=' . intval($row['location_id']) . '">' . escapeHtml($row['location_name']) . '</a>'
            . (intval($row['location_primary']) ? ' <span class="badge rounded-pill text-bg-primary fw-normal ms-1">Primary</span>' : '');
    }],
    'address' => ['label' => 'Address', 'class' => 'itflow-report-text', 'csv' => false],
    'location_address' => ['label' => 'Street', 'screen' => false],
    'location_city' => ['label' => 'City', 'screen' => false],
    'location_state' => ['label' => 'State', 'screen' => false],
    'location_zip' => ['label' => 'Postal code', 'screen' => false],
    'location_country' => ['label' => 'Country', 'screen' => false],
    'location_primary' => ['label' => 'Primary', 'screen' => false, 'csv' => function ($row) {
        return intval($row['location_primary']) ? 'Yes' : 'No';
    }],
];

$csv_name = 'Geography ' . implode(' ', $path);
if (reportExporting('areas') && $level <= 1) {
    reportCsv($csv_name . ($level === 0 ? ' by country' : ' by ' . strtolower($term)), $area_columns, $areas);
}
if (reportExporting('cities') && $level <= 2) {
    reportCsv($csv_name . ' by city', $city_columns, $cities);
}
if (reportExporting($level === 3 ? null : 'locations') && $level >= 2) {
    reportCsv($csv_name . ' locations', $location_columns, $shown);
}

require_once "includes/inc_report_layout.php";

// The figures
$placed_clients = count(array_unique(array_column($all, 'client_id')));
$shown_clients = count(array_unique(array_column($shown, 'client_id')));
$next = [0 => $countries, 1 => $regions, 2 => $cities][$level] ?? [];
$next_named = array_values(array_filter($next, function ($row) { return $row['key'] !== '-'; }));
$next_label = [0 => 'Countries', 1 => $terms, 2 => 'Cities'][$level] ?? '';
// No city, a country ITFlow does not know, or a US state or Canadian province it cannot match
$country_names = reportGeoCountries()['names'];
$gaps = count(array_filter($shown, function ($location) use ($country_names) {
    $known = reportGeoRegions($location['country'])['names'];
    return $location['city'] === '-' || !isset($country_names[$location['country']]) || ($known && !isset($known[$location['region']]));
}));

$stats = [
    ['label' => 'Clients', 'value' => reportCount($shown_clients),
        'sub' => $level === 0
            ? ($client_count > $placed_clients ? reportCount($client_count - $placed_clients) . ' <span class="text-body-tertiary">with no location</span>' : '<span class="text-body-tertiary">All have a location</span>')
            : reportPercent(reportShare($shown_clients, $placed_clients, 0)) . ' <span class="text-body-tertiary">of clients</span>'],
    ['label' => 'Locations', 'value' => reportCount(count($shown)),
        'sub' => $shown_clients ? number_format(count($shown) / $shown_clients, 1) . ' <span class="text-body-tertiary">per client</span>' : ''],
];
if ($next_label !== '') {
    $stats[] = ['label' => $next_label, 'value' => reportCount(count($next_named)),
        'sub' => $next_named ? '<span class="text-body-tertiary">Most:</span> ' . escapeHtml($next_named[0]['name']) . ' <span class="text-body-tertiary">(' . geographyNoun($next_named[0]['clients'], 'client') . ')</span>' : ''];
}
$stats[] = ['label' => 'Incomplete addresses', 'value' => reportCount($gaps), 'tone' => $gaps ? 'warning' : 'success',
    'sub' => '<span class="text-body-tertiary">' . ($gaps ? 'No city, or a country or state not recognized' : 'Every location placed') . '</span>'];

// The map: the world by country, or this country's states - shaded by clients or locations
$map_name = $level === 0 ? 'world' : (REPORT_GEO_MAPS[$country] ?? '');
$map_rows = $level === 0 ? $countries : $regions;
$values = $titles = $links = [];
$off_map = 0;
if ($map_name !== '') {
    $on_map = reportGeoMap($map_name)['regions'] ?? [];
    foreach ($map_rows as $row) {
        if (!isset($on_map[$row['key']])) {
            $off_map += $row['locations'];
            continue;
        }
        $values[$row['key']] = $row[$by];
        $titles[$row['key']] = $row['name'] . ': ' . geographyNoun($row['clients'], 'client') . ', ' . geographyNoun($row['locations'], 'location');
        $links[$row['key']] = $level === 0 ? $drill($row['key']) : $drill($country, $row['key']);
    }
}
$shade = '<span class="btn-group btn-group-sm d-print-none" role="group">';
foreach (['clients' => 'Clients', 'locations' => 'Locations'] as $key => $text) {
    $shade .= '<a class="btn btn-outline-secondary' . ($by === $key ? ' active' : '') . '"' . ($by === $key ? ' aria-current="true"' : '')
        . ' href="' . escapeHtml(reportUrl(['by' => $key === 'clients' ? null : $key, 'export' => null, 'table' => null])) . '">' . $text . '</a>';
}
$shade .= '</span>';

// Without a map, the places a step down as a chart
$chart_rows = $map_name === '' && $level <= 2 ? array_slice($level === 1 ? $regions : $cities, 0, 12) : [];

$export = [];
if ($level <= 1) {
    $export['areas'] = $level === 0 ? 'By country' : 'By ' . strtolower($term);
}
if ($level <= 2) {
    $export['cities'] = 'By city';
}
if ($level === 2) {
    $export['locations'] = 'Locations';
}

$controls = reportSelectControl('country', $country, ['all' => 'All countries'] + array_column($countries, 'name', 'key'), 'fa-globe-americas', '', 'col-sm-6 col-lg-auto');
if ($level >= 1) {
    $controls .= reportSelectControl('region', $region, ['' => 'All ' . strtolower($terms)] + array_column($regions, 'name', 'key'), 'fa-map', '', 'col-sm-6 col-lg-auto');
}
if ($level >= 2) {
    $controls .= reportSelectControl('city', $city, ['' => 'All cities'] + array_column($cities, 'name', 'key'), 'fa-city', '', 'col-sm-6 col-lg-auto');
}
$controls .= reportSwitchControl('leads', $leads, 'Include leads');

?>

<div class="itflow-report">

    <?php reportHeader('geography', [
        'subtitle' => implode(' · ', $path),
        'controls' => $controls,
        'export' => $level === 3 ? true : $export,
        'keep' => ['at' => "$country|$region"] + ($by === 'locations' ? ['by' => 'locations'] : []),
        'print_meta' => implode(' · ', $path),
    ]); ?>

    <?= reportStats($stats) ?>

    <?php if ($map_name !== '' && $all) { ?>
        <?= reportSectionOpen($level === 0 ? 'Countries' : $country_name, $shade) ?>
            <div class="card-body">
                <?= reportGeoMapHtml($map_name, $values, $titles, $links, $level >= 2 ? $region : '', ($level === 0 ? 'Countries' : $terms) . ' shaded by ' . $by) ?>
                <?= reportGeoLegendHtml($values ? max($values) : 0, $by === 'clients' ? 'clients' : 'locations') ?>
            </div>
            <?php if ($off_map) { ?>
                <div class="card-footer small text-body-secondary">
                    <?= geographyNoun($off_map, 'location') ?> could not be placed on the map - the <?= $level === 0 ? 'country' : strtolower($term) ?> is not filled in or not recognized. They are in the table below.
                </div>
            <?php } ?>
        </div>
    <?php } elseif ($chart_rows) { ?>
        <?= reportSectionOpen($level === 1 ? $country_name : $path[1], $shade) ?>
            <div class="card-body">
                <?= reportChart([
                    'type' => 'bar',
                    'horizontal' => true,
                    'labels' => array_column($chart_rows, 'name'),
                    'series' => [['label' => $by === 'clients' ? 'Clients' : 'Locations', 'data' => array_column($chart_rows, $by), 'tone' => 'accent']],
                    'format' => 'count',
                    'legend' => false,
                ], '', 'How many ' . $by . ' are in each place') ?>
            </div>
        </div>
    <?php } ?>

    <?php if ($level <= 1) { ?>
        <?= reportSectionOpen($level === 0 ? 'By country' : 'By ' . strtolower($term), 'Most clients first') ?>
            <?= reportTable($area_columns, $areas, ['empty' => reportEmpty('No locations yet - add them on each client\'s Locations page.', 'fa-map-marker-alt', 'secondary')]) ?>
            <div class="card-footer small text-body-secondary">
                Active locations of active clients<?= $leads ? ' and leads' : '' ?>. A client with locations in more than one place counts in each, so the clients do not add up to the total.
                <?php if ($home !== '-') { ?>A location with no country is counted in <?= escapeHtml($country_names[$home] ?? $home) ?>.<?php } ?>
            </div>
        </div>
    <?php } ?>

    <?php if ($level <= 2 && $cities) { ?>
        <?= reportSectionOpen('By city', 'Most clients first') ?>
            <?= reportTable($city_columns, $cities, ['limit' => 25, 'export_table' => 'cities']) ?>
        </div>
    <?php } ?>

    <?php if ($level >= 2) { ?>
        <?= reportSectionOpen('Locations', geographyNoun(count($shown), 'location')) ?>
            <?= reportTable($location_columns, $shown, ['limit' => 100, 'export_table' => 'locations']) ?>
        </div>
    <?php } ?>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="<?= staticFileUrl('/js/reports.js') ?>"></script>
