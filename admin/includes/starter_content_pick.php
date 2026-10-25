<?php

/*
 * Pick which - choosing a starter content pack's items one by one, as
 * Maintenance > Starter Content (admin/starter_content.php) and the Starter
 * content step of Get started (admin/getting_started/content.php) both offer
 * it. Each item is shown as it will look - a tag as its pill, a category as
 * its chip, a product with its price - with what it is for, and a template or
 * canned response has Preview, the whole of it in a window
 * (admin/starter_content_preview.php answers it). js/starter_content.js keeps
 * the ticks and counts in step and opens the previews.
 *
 * The form sends picked[pack] (the list was there) and items[pack][] (what is
 * ticked, by starterItemKey()); the item lists come from
 * starterContentItems() in post/starter_content_model.php, which has to be
 * loaded first.
 */

defined('FROM_STARTER_CONTENT') || die("Direct file access is not allowed");

/* What an item is for, in a line - '' when the library says nothing. */
function starterItemAbout($pack, $row) {
    switch ($pack) {
        case 'categories':
            return (string) ($row['description'] ?? '');
        case 'tags':
            return '';
        case 'products':
            return (string) ($row[5] ?? '');
    }
    return (string) ($row['description'] ?? '');
}

/*
 * An item as it will look - a tag as its pill, a category as its chip, a
 * product with its price and how it is billed, anything else its name.
 */
function starterItemLook($pack, $row) {
    switch ($pack) {
        case 'tags':
            return tagPill(['tag_name' => $row[1], 'tag_color' => $row[2], 'tag_icon' => $row[3]]);
        case 'categories':
            return categoryChip(['category_name' => $row['name'], 'category_type' => $row['type'], 'category_color' => $row['color'], 'category_icon' => $row['icon']]);
        case 'products':
            [$frequency, $seat] = array_pad(explode(':', (string) ($row[6] ?? '')), 2, '');
            return '<span class="fw-semibold">' . escapeHtml($row[0]) . '</span> <span class="text-body-secondary text-nowrap">'
                . escapeHtml(billingMoney(floatval($row[3])) . ' · ' . productBilledText($frequency, $seat)) . '</span>';
    }
    return '<span class="fw-semibold">' . escapeHtml(starterItemName($pack, $row)) . '</span>';
}

/* The packs whose items have more to them than a line - shown whole by Preview. */
function starterPreviewPacks() {
    return ['ticket_templates', 'project_templates', 'canned_responses', 'vendor_templates', 'document_templates', 'license_templates', 'products'];
}

/* [Words in brackets] in a template, marked as the bits filled in as it is used. */
function starterPreviewFills($html) {
    return preg_replace('/\[([A-Za-z][A-Za-z0-9 ,.\'\/&;-]{0,40})\]/', '<span class="itflow-starter-fill">[$1]</span>', (string) $html);
}

/* A fact of a preview - its label and the value (html), or nothing when there isn't one. */
function starterPreviewFact($label, $html) {
    return trim((string) $html) === '' ? '' : '<dt>' . escapeHtml($label) . '</dt><dd>' . $html . '</dd>';
}

/* What a template asks for, as a list - each with its kind, choices and whether it has to be answered. */
function starterPreviewFillIns(array $fill_ins) {
    if (!$fill_ins) {
        return '';
    }
    $kinds = ['text' => 'text', 'textarea' => 'longer text', 'date' => 'a date', 'number' => 'a number', 'yesno' => 'yes or no', 'list' => 'one of', 'email' => 'an email address'];
    $html = '<h3 class="itflow-starter-preview-head">What it asks for</h3><ul class="itflow-starter-preview-list">';
    foreach ($fill_ins as $fill_in) {
        $type = (string) ($fill_in['type'] ?? 'text');
        $options = (array) ($fill_in['options'] ?? []);
        $kind = $kinds[$type] ?? $type;
        if ($type === 'list' && $options) {
            $kind .= ' ' . implode(', ', array_map('strval', $options));
        }
        $html .= '<li><span class="fw-semibold">' . escapeHtml((string) $fill_in['name']) . '</span> <span class="text-body-secondary">- ' . escapeHtml($kind)
            . (!empty($fill_in['required']) ? ', needed' : '') . '</span>'
            . (!empty($fill_in['help']) ? '<div class="small text-body-secondary">' . escapeHtml((string) $fill_in['help']) . '</div>' : '') . '</li>';
    }
    return $html . '</ul>';
}

/* A web address as a link out - its host and path as given, https:// in front. */
function starterPreviewLink($address) {
    $address = trim((string) $address);
    if ($address === '') {
        return '';
    }
    $url = preg_match('#^https?://#i', $address) ? $address : "https://$address";
    return '<a href="' . escapeHtml($url) . '" target="_blank" rel="noopener noreferrer">' . escapeHtml($address) . '<i class="fas fa-external-link-alt small ms-1"></i></a>';
}

/*
 * An item whole, for Preview - what it says and sets up, as it will come in.
 * '' for a pack whose items are all there is to them (starterItemLook()).
 */
function starterItemPreview($pack, $row) {
    $about = starterItemAbout($pack, $row);
    $html = $about !== '' && $pack !== 'products' ? '<p class="text-body-secondary">' . escapeHtml($about) . '</p>' : '';
    switch ($pack) {
        case 'ticket_templates':
            $category = !empty($row['category']) ? categoryChip(['category_name' => $row['category']['name'], 'category_type' => 'Ticket', 'category_color' => $row['category']['color'] ?? '', 'category_icon' => $row['category']['icon'] ?? '']) : '';
            $tags = implode(' ', array_map(function ($tag) {
                return tagPill(['tag_name' => $tag['name'], 'tag_color' => $tag['color'] ?? '', 'tag_icon' => $tag['icon'] ?? '']);
            }, (array) ($row['tags'] ?? [])));
            $due = intval($row['due_hours'] ?? 0);
            $portal = !empty($row['portal']) ? '<span class="fw-semibold">' . escapeHtml((string) $row['portal']['title']) . '</span> - ' . escapeHtml((string) ($row['portal']['description'] ?? ''))
                . '<div class="small text-body-secondary">A request form in the client portal - it comes in turned off.</div>' : '';
            $html .= '<dl class="itflow-starter-preview-facts">'
                . starterPreviewFact('Subject', starterPreviewFills(escapeHtml((string) $row['subject'])))
                . starterPreviewFact('Category', $category)
                . starterPreviewFact('Priority', escapeHtml((string) ($row['priority'] ?? '')))
                . starterPreviewFact('Due', $due ? ($due % 24 === 0 ? ($due / 24) . ' day' . ($due === 24 ? '' : 's') : "$due hour" . ($due === 1 ? '' : 's')) . ' after it is raised' : '')
                . starterPreviewFact('Tags', $tags)
                . starterPreviewFact('Clients ask with', $portal)
                . '</dl>';
            $html .= '<h3 class="itflow-starter-preview-head">What it says</h3><div class="itflow-starter-preview-doc">' . starterPreviewFills($row['details'] ?? '') . '</div>';
            $html .= starterPreviewFillIns((array) ($row['fill_ins'] ?? []));
            if (!empty($row['tasks'])) {
                $minutes = array_sum(array_map(function ($task) {
                    return intval($task['estimate'] ?? 0);
                }, $row['tasks']));
                $html .= '<h3 class="itflow-starter-preview-head">Tasks <span class="fw-normal text-body-secondary">- ' . count($row['tasks']) . ', about ' . escapeHtml(starterMinutesText($minutes)) . '</span></h3><ol class="itflow-starter-preview-list">';
                foreach ($row['tasks'] as $task) {
                    $when = !empty($task['when']) ? ' <span class="badge rounded-pill bg-body-tertiary text-body border fw-normal">when ' . escapeHtml($task['when']['fill_in'] . ' is ' . $task['when']['value']) . '</span>' : '';
                    $html .= '<li>' . starterPreviewFills(escapeHtml((string) $task['name'])) . $when
                        . (!empty($task['estimate']) ? ' <span class="text-body-secondary small">' . intval($task['estimate']) . ' min</span>' : '') . '</li>';
                }
                $html .= '</ol>';
            }
            return $html;

        case 'canned_responses':
            $types = ['internal' => 'An internal note', 'public' => 'A public note', 'email' => 'An email reply'];
            $categories = implode(' ', array_map(function ($category) {
                return categoryChip(['category_name' => $category['name'], 'category_type' => 'Ticket', 'category_color' => $category['color'] ?? '', 'category_icon' => $category['icon'] ?? '']);
            }, (array) ($row['categories'] ?? [])));
            $html .= '<dl class="itflow-starter-preview-facts">'
                . starterPreviewFact('Goes in as', escapeHtml($types[$row['reply_type'] ?? ''] ?? ucfirst((string) ($row['reply_type'] ?? ''))))
                . starterPreviewFact('Offered on', $categories !== '' ? $categories : '<span class="text-body-secondary">Every ticket</span>')
                . starterPreviewFact('Sets the status to', escapeHtml((string) ($row['status'] ?? '')))
                . starterPreviewFact('Adds time worked', !empty($row['minutes']) ? intval($row['minutes']) . ' minutes' : '')
                . '</dl>';
            return $html . '<h3 class="itflow-starter-preview-head">What it says</h3><div class="itflow-starter-preview-doc">' . starterPreviewFills($row['body'] ?? '') . '</div>';

        case 'project_templates':
            $html .= '<h3 class="itflow-starter-preview-head">Its tickets, in order</h3><ol class="itflow-starter-preview-list">';
            foreach ((array) ($row['ticket_templates'] ?? []) as $ticket_template) {
                $html .= '<li>' . escapeHtml((string) $ticket_template) . '</li>';
            }
            return $html . '</ol><p class="small text-body-secondary mb-0">Each from the ticket template of that name - one that isn\'t here is left out.</p>';

        case 'vendor_templates':
            $html .= '<dl class="itflow-starter-preview-facts">'
                . starterPreviewFact('Support site', starterPreviewLink($row['website'] ?? ''))
                . starterPreviewFact('Status page', starterPreviewLink($row['status_page'] ?? ''))
                . starterPreviewFact('Cases opened at', starterPreviewLink($row['portal'] ?? ''))
                . '</dl>';
            return $html . (!empty($row['notes']) ? '<h3 class="itflow-starter-preview-head">What support asks for</h3><div class="itflow-starter-preview-notes">' . nl2br(escapeHtml((string) $row['notes'])) . '</div>' : '');

        case 'document_templates':
            $review = intval($row['review_months'] ?? 0);
            $html .= '<dl class="itflow-starter-preview-facts">'
                . starterPreviewFact('Reviewed', $review ? 'Every ' . ($review === 1 ? 'month' : "$review months") : '')
                . '</dl>';
            $html .= starterPreviewFillIns((array) ($row['fill_ins'] ?? []));
            return $html . '<h3 class="itflow-starter-preview-head">What it says</h3><div class="itflow-starter-preview-doc">' . starterPreviewFills($row['content'] ?? '') . '</div>';

        case 'license_templates':
            $html .= '<dl class="itflow-starter-preview-facts">'
                . starterPreviewFact('Type', escapeHtml((string) ($row['type'] ?? '')))
                . starterPreviewFact('Licensed per', escapeHtml((string) ($row['license_type'] ?? '')))
                . starterPreviewFact('Renews every', escapeHtml(SOFTWARE_RENEWAL_TERMS[(string) ($row['term'] ?? '')] ?? ''))
                . starterPreviewFact('Vendor', escapeHtml((string) ($row['vendor']['name'] ?? '')))
                . starterPreviewFact('Sold as', escapeHtml((string) ($row['product'] ?? '')))
                . '</dl>';
            return $html . (!empty($row['notes']) ? '<h3 class="itflow-starter-preview-head">What to know</h3><div class="itflow-starter-preview-notes">' . nl2br(escapeHtml((string) $row['notes'])) . '</div>' : '');

        case 'products':
            [$frequency, $seat] = array_pad(explode(':', (string) ($row[6] ?? '')), 2, '');
            $types = productTypes();
            return '<p class="text-body-secondary">' . escapeHtml((string) $row[5]) . '</p><dl class="itflow-starter-preview-facts">'
                . starterPreviewFact('Type', escapeHtml($types[$row[1]]['label'] ?? ucfirst((string) $row[1])))
                . starterPreviewFact('Code', escapeHtml((string) $row[2]))
                . starterPreviewFact('Price', escapeHtml(billingMoney(floatval($row[3]))) . (floatval($row[3]) == 0 ? ' <span class="text-body-secondary">- priced on each quote</span>' : ''))
                . starterPreviewFact('Billed', escapeHtml(productBilledText($frequency, $seat)))
                . starterPreviewFact('Income category', escapeHtml((string) $row[4]))
                . starterPreviewFact('Becomes', !empty($row[7]) ? escapeHtml('A client asset (' . $row[7] . ') when sold' . (!empty($row[8]) ? ', with ' . intval($row[8]) . ' months of warranty' : '')) : '')
                . '</dl><p class="small text-body-secondary mb-0">Prices are starting points - change them once it is here.</p>';
    }
    return '';
}

/* Minutes as words without the business-hours qualifier - "3 hours 15 minutes". */
function starterMinutesText($minutes) {
    $minutes = intval($minutes);
    if ($minutes < 60) {
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }
    $hours = intdiv($minutes, 60);
    $rest = $minutes % 60;
    return $hours . ' hour' . ($hours === 1 ? '' : 's') . ($rest ? " $rest minutes" : '');
}

/*
 * A pack's Pick which - its items not here yet, each ticked, shown as they
 * will look with what they are for, and Preview where there is more to see.
 * $form is the id of the form the ticks go with, when they sit outside it (a
 * table row's Add). $items from starterContentItems().
 */
function starterPickHtml($pack, array $items, $form = '') {
    if (!$items) {
        return '';
    }
    $form_attr = $form !== '' ? ' form="' . escapeHtml($form) . '"' : '';
    $previews = in_array($pack, starterPreviewPacks(), true);
    $kind = $pack === 'tags' ? ' is-pills' : '';
    ob_start(); ?>
    <details class="itflow-starter-pick mt-1" data-starter-pick-list>
        <summary class="small">Pick which</summary>
        <input type="hidden" name="picked[<?= escapeHtml($pack) ?>]" value="1"<?= $form_attr ?>>
        <div class="itflow-starter-pick-tools small mt-2">
            <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-starter-all>All</button>
            <span class="text-body-secondary" aria-hidden="true">·</span>
            <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-starter-none>None</button>
        </div>
        <div class="itflow-starter-pick-box mt-2">
            <div class="itflow-starter-pick-items<?= $kind ?>">
                <?php $group = null;
                foreach ($items as $n => $item) {
                    if ($item['group'] !== '' && $item['group'] !== $group) {
                        $group = $item['group']; ?>
                        <div class="itflow-starter-pick-group"><?= escapeHtml($group) ?></div>
                    <?php }
                    $id = 'starterItem_' . $pack . '_' . $n;
                    $about = $pack === 'products' ? '' : starterItemAbout($pack, $item['row']); ?>
                    <div class="itflow-starter-item" data-starter-item-row>
                        <input class="form-check-input flex-shrink-0" type="checkbox" name="items[<?= escapeHtml($pack) ?>][]" value="<?= escapeHtml($item['key']) ?>" id="<?= escapeHtml($id) ?>" checked data-starter-item<?= $form_attr ?>>
                        <label class="itflow-starter-item-label min-w-0" for="<?= escapeHtml($id) ?>">
                            <?= starterItemLook($pack, $item['row']) ?>
                            <?php if ($about !== '') { ?>
                                <span class="d-block small text-body-secondary itflow-starter-item-about"><?= escapeHtml($about) ?></span>
                            <?php } ?>
                        </label>
                        <?php if ($previews) { ?>
                            <button type="button" class="btn btn-link btn-sm text-decoration-none flex-shrink-0 ms-auto py-0" data-starter-preview="<?= escapeHtml($pack) ?>" data-starter-key="<?= escapeHtml($item['key']) ?>"
                                title="See all of <?= escapeHtml($item['name']) ?>"><i class="far fa-eye me-1"></i>Preview</button>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </details>
    <?php return ob_get_clean();
}

/* The window Preview opens - once on a page. */
function starterPreviewModalHtml() {
    ob_start(); ?>
    <div class="modal fade" id="starterPreview" tabindex="-1" aria-labelledby="starterPreviewTitle" aria-hidden="true" data-starter-preview-url="/admin/starter_content_preview.php">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="starterPreviewTitle">Preview</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body itflow-starter-preview" data-starter-preview-body></div>
                <div class="modal-footer">
                    <span class="small text-body-secondary me-auto">As it comes in - yours to change once it is here.</span>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}
