<?php

/*
 * ITFlow - quote line pricing. A quote line can carry what it costs you (each)
 * and how its price comes from that cost: a markup %, a margin %, or a price set
 * by hand. Markup 25% on a cost of 80 is 100; margin 25% on 80 is 106.67.
 *
 * Cost, method and % are for agents only - shown in the Edit Line window, under
 * the line on the quote page while it can be changed, and in the quote's Margin.
 * Never on the PDF, the guest link, the client portal or an invoice made from
 * the quote: invoices are made from the price, as before.
 *
 * A line added from a product takes the product's cost (products.product_cost)
 * and keeps the product's price - a set price. Admin > Settings > Quotes has what
 * a line is priced by when a cost is typed, its % and the % buttons offered.
 *
 * Columns (database 3.8.7): quote_items.item_cost, item_price_method and
 * item_price_percent - the names and meanings MountaineerIT's build uses - and
 * settings.config_quote_price_method, _percent and _presets.
 */

/** How a line's price comes from its cost. */
const QUOTE_PRICE_METHODS = ['markup' => 'Markup', 'margin' => 'Margin', 'price' => 'Set price'];

/** Whether the 3.8.7 update has run. */
function quotePricingReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.8.7', '>=');
    }
    return $ready;
}

/** A method as stored - markup, margin or price - or '' for anything else. */
function quotePriceMethod($method) {
    $method = strtolower(trim((string) $method));
    return isset(QUOTE_PRICE_METHODS[$method]) ? $method : '';
}

/** A % as it is read - 25, 12.5 - without trailing zeros. */
function quotePricePercentText($percent) {
    return rtrim(rtrim(number_format(floatval($percent), 2, '.', ''), '0'), '.');
}

/** The presets a comma list holds - each above 0 and under 1000, in the order typed, at most eight. */
function quotePricePresets($text) {
    $presets = [];
    foreach (explode(',', (string) $text) as $part) {
        $part = trim(str_replace('%', '', $part));
        if (is_numeric($part) && floatval($part) > 0 && floatval($part) < 1000) {
            $presets[quotePricePercentText($part)] = round(floatval($part), 2);
        }
    }
    return array_slice(array_values($presets), 0, 8);
}

/** Admin > Settings > Quotes' pricing: method (markup or margin), percent, presets. Read once a request. */
function quotePricingSettings() {
    global $mysqli;
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }
    $row = quotePricingReady() ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_quote_price_method, config_quote_price_percent, config_quote_price_presets FROM settings WHERE company_id = 1")) : null;
    $method = quotePriceMethod($row['config_quote_price_method'] ?? '');
    $presets = quotePricePresets($row['config_quote_price_presets'] ?? '');
    $settings = [
        'method' => in_array($method, ['markup', 'margin'], true) ? $method : 'markup',
        'percent' => isset($row['config_quote_price_percent']) && is_numeric($row['config_quote_price_percent']) ? floatval($row['config_quote_price_percent']) : 20.0,
        'presets' => $presets ?: [15, 20, 25, 35],
    ];
    return $settings;
}

/** The price a cost comes to - markup 25% on 80 is 100, margin 25% on 80 is 106.67. Null when it can't be worked out. */
function quotePriceFromCost($cost, $method, $percent) {
    $cost = floatval($cost);
    $percent = floatval($percent);
    if ($cost <= 0 || $percent < 0) {
        return null;
    }
    if ($method === 'markup') {
        return round($cost * (1 + $percent / 100), 2);
    }
    if ($method === 'margin' && $percent < 100) {
        return round($cost / (1 - $percent / 100), 2);
    }
    return null;
}

/** A typed amount or %, or null when left empty or not a number. */
function quotePricingNumber($value) {
    $value = trim(str_replace([',', '%', '$'], '', (string) $value));
    return $value === '' || !is_numeric($value) ? null : round(floatval($value), 2);
}

/*
 * What a form sent - cost, price_method and price_percent - made sense of:
 * a negative cost or % is dropped, a margin of 100% or more (no price gives
 * it) is dropped, and a set price has no %. [cost|null, method|'', percent|null].
 */
function quotePricingFromInput(array $in) {
    $cost = quotePricingNumber($in['cost'] ?? '');
    $method = quotePriceMethod($in['price_method'] ?? '');
    $percent = quotePricingNumber($in['price_percent'] ?? '');
    if ($cost !== null && ($cost < 0 || $cost >= 1e11)) {
        $cost = null;
    }
    if ($method === 'price' || ($percent !== null && ($percent < 0 || $percent >= 100000)) || ($method === 'margin' && $percent !== null && $percent >= 100)) {
        $percent = null;
    }
    if ($cost === null && $method !== 'price') {
        // Nothing to price from
        $method = '';
        $percent = null;
    }
    return [$cost, $method, $percent];
}

/*
 * The SET for a quote line's pricing, for billingItemSave(): what the Edit
 * Line window sent (pricing = 1 with cost, price_method, price_percent), or -
 * for a new line from a product with a cost - that cost and the product's
 * price as a set price. '' leaves the line's pricing as it is (a change made
 * where the line sits on the quote).
 */
function quotePricingSetSql(array $in, $is_new, $product_id) {
    global $mysqli;
    if (!quotePricingReady()) {
        return '';
    }
    if (!empty($in['pricing'])) {
        [$cost, $method, $percent] = quotePricingFromInput($in);
    } elseif ($is_new && intval($product_id)) {
        $product_cost = floatval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT product_cost FROM products WHERE product_id = " . intval($product_id)))[0] ?? 0);
        if ($product_cost <= 0) {
            return '';
        }
        [$cost, $method, $percent] = [round($product_cost, 2), 'price', null];
    } else {
        return '';
    }
    return ", item_cost = " . ($cost === null ? 'NULL' : $cost)
        . ", item_price_method = " . ($method === '' ? 'NULL' : "'$method'")
        . ", item_price_percent = " . ($percent === null ? 'NULL' : $percent);
}

/** The SET that gives a copied quote line the pricing of the one it came from ($item a billingItems() row). '' before 3.8.7. */
function quotePricingCopySql(array $item) {
    if (!quotePricingReady() || !array_key_exists('item_cost', $item)) {
        return '';
    }
    $method = quotePriceMethod($item['item_price_method'] ?? '');
    return ", item_cost = " . ($item['item_cost'] === null ? 'NULL' : floatval($item['item_cost']))
        . ", item_price_method = " . ($method === '' ? 'NULL' : "'$method'")
        . ", item_price_percent = " . ($item['item_price_percent'] === null ? 'NULL' : floatval($item['item_price_percent']));
}

/*
 * How a line is priced now: its markup or margin while its price still comes
 * from its cost; a price changed by hand since then is a set price.
 */
function quotePricingMethodNow($cost, $method, $percent, $price) {
    $method = quotePriceMethod($method);
    if (in_array($method, ['markup', 'margin'], true)) {
        $worked = quotePriceFromCost($cost, $method, $percent);
        if ($worked === null || abs($worked - floatval($price)) >= 0.01) {
            return 'price';
        }
    }
    return $method;
}

/*
 * Under a line on the quote page, while it can be changed - its cost and how
 * it is priced: "Cost $80.00 · Markup 25% · 20% margin". Agents only (never
 * printed). '' for a line with no cost.
 */
function quotePricingLineHtml(array $item, $currency) {
    if (!array_key_exists('item_cost', $item) || $item['item_cost'] === null || $item['item_cost'] === '') {
        return '';
    }
    $cost = floatval($item['item_cost']);
    $price = floatval($item['item_price']);
    $method = quotePricingMethodNow($cost, $item['item_price_method'] ?? '', $item['item_price_percent'] ?? 0, $price);
    $parts = ['Cost ' . escapeHtml(billingMoney($cost, $currency))];
    if (in_array($method, ['markup', 'margin'], true)) {
        $parts[] = QUOTE_PRICE_METHODS[$method] . ' ' . escapeHtml(quotePricePercentText($item['item_price_percent'])) . '%';
    }
    $tone = 'bg-body-secondary text-body-secondary';
    if ($cost > 0 && $price > 0) {
        $profit = $price - $cost;
        $parts[] = $method === 'margin'
            ? escapeHtml(number_format($profit / $cost * 100, 1)) . '% markup'
            : escapeHtml(number_format($profit / $price * 100, 1)) . '% margin';
        if ($profit < 0) {
            $tone = 'bg-danger-subtle text-danger-emphasis';
        }
    }
    return '<div class="small mt-1 d-print-none" data-line-pricing><span class="badge rounded-pill ' . $tone . ' fw-normal text-wrap text-start" title="What it costs you - only agents see this">'
        . '<i class="fas fa-tag me-1"></i>' . implode(' &middot; ', $parts) . '</span></div>';
}

/*
 * The Edit Line window's pricing, on a quote ($item a billingItems() row):
 * your cost, Price by, the %, the % buttons and what the price comes to -
 * itflowBillingPricingForm() (js/billing_items.js) works the Price out as
 * they change.
 */
function quotePricingFields(array $item, $currency) {
    if (!quotePricingReady()) {
        return '';
    }
    $settings = quotePricingSettings();
    $cost = $item['item_cost'] ?? null;
    $has_cost = $cost !== null && $cost !== '';
    $method = $has_cost ? quotePricingMethodNow($cost, $item['item_price_method'] ?? '', $item['item_price_percent'] ?? 0, $item['item_price']) : '';
    if ($method === '') {
        $method = $has_cost ? 'price' : $settings['method'];
    }
    $percent = in_array($method, ['markup', 'margin'], true) && $has_cost && $item['item_price_percent'] !== null
        ? quotePricePercentText($item['item_price_percent']) : quotePricePercentText($settings['percent']);
    ob_start(); ?>
    <div class="col-12">
        <div class="border rounded p-3 itflow-quote-pricing" data-quote-pricing data-currency="<?= escapeHtml($currency) ?>">
            <input type="hidden" name="pricing" value="1">
            <div class="d-flex flex-wrap align-items-baseline column-gap-2 mb-2">
                <span class="fw-semibold"><i class="fas fa-fw fa-tag text-secondary me-1"></i>Your cost</span>
                <span class="small text-body-secondary">Only agents see this - never the PDF, the guest link or the portal.</span>
            </div>
            <div class="row g-2">
                <div class="col-sm-4">
                    <label class="form-label">Cost each</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-receipt"></i></span>
                        <input type="text" class="form-control text-end" name="cost" inputmode="decimal" placeholder="0.00"
                            value="<?= $has_cost ? number_format(floatval($cost), 2, '.', '') : '' ?>" data-pricing-cost>
                    </div>
                </div>
                <div class="col-sm-4">
                    <label class="form-label">Price by</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calculator"></i></span>
                        <select class="form-select select2" name="price_method" data-pricing-method>
                            <?php foreach (QUOTE_PRICE_METHODS as $value => $label) { ?>
                                <option value="<?= $value ?>"<?= $value === $method ? ' selected' : '' ?>><?= $label ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="col-sm-4" data-pricing-percent-wrap<?= $method === 'price' ? ' hidden' : '' ?>>
                    <label class="form-label">Percent</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                        <input type="text" class="form-control text-end" name="price_percent" inputmode="decimal" value="<?= escapeHtml($percent) ?>" data-pricing-percent>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-1 mt-2" data-pricing-presets<?= $method === 'price' ? ' hidden' : '' ?>>
                <?php foreach ($settings['presets'] as $preset) { ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-pricing-preset="<?= escapeHtml(quotePricePercentText($preset)) ?>"><?= escapeHtml(quotePricePercentText($preset)) ?>%</button>
                <?php } ?>
            </div>
            <div class="small text-body-secondary mt-2" data-pricing-hint></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
