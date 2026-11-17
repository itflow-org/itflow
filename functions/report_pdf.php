<?php

/*
 * A report as a PDF - loaded by reportPdfFinish() once TCPDF itself is, as a
 * class has to follow the one it extends.
 *
 * The page is drawn as usual, then read back here and laid out again for
 * paper, so the PDF follows the page whatever the report puts on it: the dark
 * bar with the report's name and dates, the company and who printed it, the
 * figures along the top, then each section - its heading, its tables with the
 * page's colours, bold and badges, its notes - and the map. The charts are
 * drawn only in the browser, so js/reports.js sends them with the request as
 * pictures, in the order they are on the page. What a printed page leaves
 * out (the filters, the buttons) is left out here too, and so are icons,
 * initials and the small bars beside figures. The page's light colours are
 * used whatever the agent's theme, as on paper.
 */

class ReportPdf extends TCPDF {

    public $footer_label = '';

    public function Footer() {
        $margins = $this->getMargins();
        $width = $this->getPageWidth() - $margins['left'] - $margins['right'];
        $this->SetY(-12);
        $this->SetDrawColor(222, 226, 230);
        $this->SetLineWidth(0.2);
        $this->Line($margins['left'], $this->GetY() - 1.5, $this->getPageWidth() - $margins['right'], $this->GetY() - 1.5);
        $this->SetFont('dejavusans', '', 7);
        $this->SetTextColor(108, 117, 125);
        $this->Cell($width * 0.75, 4, $this->footer_label, 0, 0, 'L');
        $this->Cell($width * 0.25, 4, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

class ReportPdfWriter {

    // The page's text colours (Bootstrap's light theme), by class
    const COLORS = [
        'text-success-emphasis' => '#0a3622', 'text-danger-emphasis' => '#58151c', 'text-warning-emphasis' => '#664d03',
        'text-info-emphasis' => '#055160', 'text-primary-emphasis' => '#052c65', 'text-secondary-emphasis' => '#2b2f32',
        'text-success' => '#198754', 'text-danger' => '#dc3545', 'text-warning' => '#cc9a06', 'text-info' => '#0aa2c0',
        'text-primary' => '#0d6efd', 'text-body-secondary' => '#6c757d', 'text-secondary' => '#6c757d', 'text-muted' => '#6c757d',
        'text-body-tertiary' => '#999da1', 'itflow-report-zero' => '#999da1', 'text-body-emphasis' => '#000000',
    ];

    // Badges: [background, text]
    const BADGES = [
        'primary' => ['#0d6efd', '#ffffff'], 'secondary' => ['#6c757d', '#ffffff'], 'success' => ['#198754', '#ffffff'],
        'danger' => ['#dc3545', '#ffffff'], 'warning' => ['#ffc107', '#000000'], 'info' => ['#0dcaf0', '#000000'],
        'light' => ['#f8f9fa', '#000000'], 'dark' => ['#212529', '#ffffff'],
    ];
    const SUBTLE = [
        'primary' => '#cfe2ff', 'secondary' => '#e2e3e5', 'success' => '#d1e7dd', 'danger' => '#f8d7da',
        'warning' => '#fff3cd', 'info' => '#cff4fc', 'light' => '#fcfcfd', 'dark' => '#ced4da',
    ];

    // reportChart() tones, for the swatches beside a doughnut
    const TONES = [
        'success' => '#198754', 'danger' => '#dc3545', 'warning' => '#ffc107', 'info' => '#0dcaf0', 'muted' => '#6c757d',
        'faint' => '#adb5bd', 'purple' => '#6f42c1', 'teal' => '#20c997', 'orange' => '#fd7e14', 'indigo' => '#6610f2',
        'pink' => '#d63384', 'dark' => '#212529',
    ];

    const BLOCK_TAGS = ['div', 'p', 'ul', 'ol', 'li', 'table', 'canvas', 'svg', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'section', 'header', 'footer', 'hr', 'dl'];
    const SKIP_TAGS = ['script', 'style', 'i', 'img', 'form', 'button', 'input', 'select', 'textarea', 'template', 'noscript', 'title'];
    const SKIP_CLASSES = ['d-print-none', 'visually-hidden', 'itflow-client-avatar', 'itflow-asset-icon', 'itflow-report-meter', 'progress', 'itflow-dash-bar-track', 'itflow-dash-legend-swatch'];

    const GREY = '#6c757d';
    const LINE = '#dee2e6';
    const NONE = '#e9ecef';

    public $pdf;
    public $title = '';
    public $subtitle = '';

    private $company = '';
    private $charts;
    private $canvases;
    private $accent;
    private $width;
    private $size = 8.5;

    /** $charts: PNG data, in the order of the page's charts. $accent: '#rrggbb'. */
    public function __construct(array $charts, $accent) {
        $this->charts = $charts;
        $this->accent = $accent;
        $this->canvases = new SplObjectStorage();
    }

    /** Lay out the report (the .itflow-report element) - false when the page has none. */
    public function render($html, $company, $country) {
        $this->company = (string) $company;
        $doc = new DOMDocument();
        $errors = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($errors);
        $xpath = new DOMXPath($doc);
        $report = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' itflow-report ')]")->item(0);
        if (!$report) {
            return false;
        }
        $i = 0;
        foreach ($xpath->query(".//canvas[@data-report-chart]", $report) as $canvas) {
            $this->canvases[$canvas] = $i++;
        }

        // A table of ten columns or more (a year of months) wants the long edge
        $wide = false;
        foreach ($xpath->query(".//table[not(contains(@class, 'itflow-report-heat'))]//tr", $report) as $tr) {
            if ($xpath->query('./th|./td', $tr)->length > 9) {
                $wide = true;
                break;
            }
        }
        $format = in_array($country, ['United States', 'USA', 'US', 'Canada'], true) ? 'LETTER' : 'A4';
        $this->pdf = new ReportPdf($wide ? 'L' : 'P', 'mm', $format, true, 'UTF-8', false);
        $this->pdf->SetCreator('ITFlow');
        $this->pdf->SetAuthor((string) $company);
        $this->pdf->SetPrintHeader(false);
        $this->pdf->SetMargins(12, 12, 12);
        $this->pdf->SetFooterMargin(10);
        $this->pdf->SetAutoPageBreak(true, 16);
        $this->pdf->setFontSubsetting(true);
        $this->pdf->setCellHeightRatio(1.3);
        $this->pdf->SetFont('dejavusans', '', $this->size);
        $this->pdf->SetTextColor(33, 37, 41);
        $this->pdf->AddPage();
        $margins = $this->pdf->getMargins();
        $this->width = $this->pdf->getPageWidth() - $margins['left'] - $margins['right'];

        foreach ($this->children($report) as $child) {
            $this->block($child);
        }
        $this->pdf->SetTitle(trim($this->title . ' ' . $this->subtitle));
        return true;
    }

    /* ---------- reading the page ---------- */

    private static function classes(DOMElement $el) {
        return preg_split('/\s+/', trim($el->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY);
    }

    private static function has(DOMElement $el, $class) {
        return in_array($class, self::classes($el), true);
    }

    /** Is it something a printed page leaves out, or that has no place on paper? */
    private static function skipped(DOMElement $el) {
        $tag = strtolower($el->tagName);
        if (in_array($tag, self::SKIP_TAGS, true) || $el->hasAttribute('hidden') || $el->getAttribute('aria-hidden') === 'true') {
            return true;
        }
        $classes = self::classes($el);
        if (array_intersect($classes, self::SKIP_CLASSES)) {
            return true;
        }
        // Hidden on screen and not shown in print either
        return in_array('d-none', $classes, true) && !preg_grep('/^d-print-/', $classes);
    }

    private static function text(DOMNode $node) {
        return trim(preg_replace('/\s+/u', ' ', $node->textContent));
    }

    /** An element's child elements and text worth keeping. */
    private function children(DOMNode $node) {
        $out = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement || ($child instanceof DOMText && trim($child->nodeValue) !== '')) {
                $out[] = $child;
            }
        }
        return $out;
    }

    private function find(DOMElement $el, $class) {
        $xpath = new DOMXPath($el->ownerDocument);
        return $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' $class ')]", $el);
    }

    /** Does it hold anything laid out as a block of its own (a table, a chart, a paragraph)? */
    private function hasBlocks(DOMElement $el) {
        foreach ($el->childNodes as $child) {
            if ($child instanceof DOMElement && !self::skipped($child)
                && (in_array(strtolower($child->tagName), self::BLOCK_TAGS, true) || self::has($child, 'card') || self::has($child, 'row'))) {
                return true;
            }
        }
        return false;
    }

    /* ---------- colours ---------- */

    private static function rgb($hex) {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** $share of the accent over the plain grey - the map's and the heat grid's shading. */
    private function heat($share) {
        $a = self::rgb($this->accent);
        $b = self::rgb(self::NONE);
        $share = max(0, min(1, floatval($share)));
        return sprintf('#%02x%02x%02x', round($a[0] * $share + $b[0] * (1 - $share)), round($a[1] * $share + $b[1] * (1 - $share)), round($a[2] * $share + $b[2] * (1 - $share)));
    }

    private static function heatOf(DOMElement $el) {
        return preg_match('/--heat:\s*([0-9.]+)/', $el->getAttribute('style'), $m) ? floatval($m[1]) : 0;
    }

    /* ---------- inline content, as TCPDF reads HTML ---------- */

    /** A run of text and inline elements, keeping the page's colours, bold, italics and badges. */
    private function inline(DOMNode $node) {
        // What a flex box, grid or list item lays out apart stays apart
        $apart = $node instanceof DOMElement && (strtolower($node->tagName) === 'li'
            || preg_grep('/^(d-flex|d-inline-flex|d-grid|gap-\d)$/', self::classes($node)));
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $out .= escapeHtml(preg_replace('/\s+/u', ' ', $child->nodeValue));
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if ($tag === 'br') {
                $out .= '<br>';
                continue;
            }
            if ($child->hasAttribute('data-report-swatch')) {
                $out .= '<span style="color:' . (self::TONES[$child->getAttribute('data-report-swatch')] ?? $this->accent) . ';">■</span> ';
                continue;
            }
            if (self::has($child, 'itflow-geo-swatch')) {
                $out .= '<span style="color:' . self::NONE . ';">■</span> ';
                continue;
            }
            if (self::has($child, 'itflow-geo-scale')) {
                $out .= ' <span style="color:' . $this->heat(0.25) . ';">■</span><span style="color:' . $this->heat(0.6) . ';">■</span><span style="color:' . $this->accent . ';">■</span> ';
                continue;
            }
            if (self::skipped($child)) {
                continue;
            }
            $inner = $this->inline($child);
            if (trim(strip_tags($inner, '<br>')) === '') {
                continue;
            }
            $classes = self::classes($child);
            $style = [];
            foreach ($classes as $class) {
                if (isset(self::COLORS[$class])) {
                    $style['color'] = self::COLORS[$class];
                    break;
                }
            }
            if (in_array($tag, ['b', 'strong'], true) || array_intersect($classes, ['fw-semibold', 'fw-bold', 'fw-medium'])) {
                $style['font-weight'] = 'bold';
            }
            if ($tag === 'em' || in_array('fst-italic', $classes, true)) {
                $style['font-style'] = 'italic';
            }
            if ($tag === 'small' || in_array('small', $classes, true)) {
                $style['font-size'] = round($this->size * 0.88, 1) . 'pt';
            }
            if (in_array('font-monospace', $classes, true) || $tag === 'code') {
                $style['font-family'] = 'dejavusansmono';
            }
            // A badge, or a pill on a pale background
            $badge = null;
            foreach ($classes as $class) {
                if (preg_match('/^text-bg-(\w+)$/', $class, $m) && isset(self::BADGES[$m[1]])) {
                    $badge = self::BADGES[$m[1]];
                } elseif (preg_match('/^bg-(\w+)-subtle$/', $class, $m) && isset(self::SUBTLE[$m[1]])) {
                    $badge = [self::SUBTLE[$m[1]], $style['color'] ?? '#212529'];
                } elseif (preg_match('/^bg-(\w+)$/', $class, $m) && isset(self::BADGES[$m[1]]) && in_array('badge', $classes, true)) {
                    $badge = self::BADGES[$m[1]];
                }
            }
            if ($badge || in_array('badge', $classes, true)) {
                $badge = $badge ?: [self::NONE, '#212529'];
                $style['background-color'] = $badge[0];
                $style['color'] = $badge[1];
                $style['font-size'] = round($this->size * 0.82, 1) . 'pt';
                $inner = '&nbsp;' . trim($inner) . '&nbsp;';
            }
            // Blocks inside a run (a cell's flex box, a list item) sit side by side, and so does what the page spaces apart
            $gap = $apart || in_array($tag, self::BLOCK_TAGS, true) || preg_grep('/^(ms|me|mx|ps|pe|px)-[1-5]$/', $classes) ? ' ' : '';
            if ($style) {
                $css = '';
                foreach ($style as $property => $value) {
                    $css .= "$property:$value;";
                }
                $out .= $gap . '<span style="' . $css . '">' . $inner . '</span>' . $gap;
            } else {
                $out .= $gap . $inner . $gap;
            }
        }
        return preg_replace('/ {2,}/', ' ', $out);
    }

    /* ---------- blocks ---------- */

    private function block(DOMNode $node) {
        if ($node instanceof DOMText) {
            $this->paragraph(escapeHtml(self::text($node)));
            return;
        }
        if (!$node instanceof DOMElement || self::skipped($node)) {
            return;
        }
        $tag = strtolower($node->tagName);
        if (self::has($node, 'itflow-report-head')) {
            $this->header($node);
        } elseif (self::has($node, 'itflow-report-stats')) {
            $this->stats($node);
        } elseif ($tag === 'table') {
            self::has($node, 'itflow-report-heat') ? $this->heatGrid($node) : $this->table($node);
        } elseif ($tag === 'canvas') {
            $this->chart($node);
        } elseif ($tag === 'svg') {
            if (self::has($node, 'itflow-geo-map')) {
                $this->map($node);
            }
        } elseif (self::has($node, 'itflow-dash-bars')) {
            $this->bars($node);
        } elseif (self::has($node, 'itflow-geo-legend')) {
            $this->paragraph($this->inline($node), self::GREY, $this->size * 0.88, 'C');
        } elseif (self::has($node, 'card')) {
            $this->section($node);
        } elseif (self::has($node, 'card-footer')) {
            $this->paragraph($this->inline($node), self::GREY, $this->size * 0.88);
        } elseif ($tag === 'hr') {
            $this->space(2);
        } elseif ($this->hasBlocks($node)) {
            // A container: its inline runs become paragraphs between its blocks
            $run = '';
            foreach ($this->children($node) as $child) {
                $is_block = $child instanceof DOMElement && !self::skipped($child)
                    && (in_array(strtolower($child->tagName), self::BLOCK_TAGS, true) || self::has($child, 'card') || self::has($child, 'row'));
                if (!$is_block) {
                    $holder = $node->ownerDocument->createElement('span');
                    $holder->appendChild($child->cloneNode(true));
                    $run .= $this->inline($holder);
                    continue;
                }
                $this->paragraph($run);
                $run = '';
                $this->block($child);
            }
            $this->paragraph($run);
        } else {
            $text = $this->inline($node);
            $list = $tag === 'li' && $node->parentNode instanceof DOMElement && !self::has($node->parentNode, 'list-unstyled')
                && !preg_grep('/^itflow-/', self::classes($node->parentNode));
            if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) || self::has($node, 'itflow-report-group-title') || self::has($node, 'card-title')) {
                $this->paragraph('<b>' . $text . '</b>', '#212529', $this->size + 1.5);
                return;
            }
            $this->paragraph(($list ? '• ' : '') . $text, null, self::has($node, 'small') ? $this->size * 0.88 : null, self::has($node, 'text-center') ? 'C' : '');
        }
    }

    private function space($mm) {
        $this->pdf->SetY($this->pdf->GetY() + $mm);
    }

    /** Start a new page when less than $mm is left on this one. */
    private function room($mm) {
        if ($this->pdf->GetY() + $mm > $this->pdf->getPageHeight() - $this->pdf->getBreakMargin()) {
            $this->pdf->AddPage();
        }
    }

    private function paragraph($html, $color = null, $size = null, $align = '') {
        if (trim(strip_tags($html)) === '') {
            return;
        }
        $style = 'font-size:' . round($size ?: $this->size, 1) . 'pt;' . ($color ? "color:$color;" : '');
        $this->pdf->writeHTML('<div style="' . $style . '">' . trim($html) . '</div>', true, false, true, false, $align);
        $this->space(1);
    }

    /** The dark bar: the report's name and dates, then the company and who printed it. */
    private function header(DOMElement $el) {
        $title = $this->find($el, 'card-title')->item(0);
        $subtitle = $this->find($el, 'itflow-report-subtitle')->item(0);
        $this->subtitle = $subtitle ? self::text($subtitle) : '';
        if ($title) {
            if ($subtitle) {
                $subtitle->parentNode->removeChild($subtitle);
            }
            $this->title = self::text($title);
        }
        // Each page's footer is drawn as the next one starts, so it is set before any of them
        $this->pdf->footer_label = trim($this->company . ' · ' . $this->title . ($this->subtitle !== '' ? ' · ' . $this->subtitle : ''), ' ·');
        $x = $this->pdf->GetX();
        $y = $this->pdf->GetY();
        $this->pdf->SetFillColor(33, 37, 41);
        $this->pdf->RoundedRect($x, $y, $this->width, 11, 1.5, '1111', 'F');
        $this->pdf->writeHTMLCell($this->width - 8, 9, $x + 4, $y + 2.4,
            '<span style="font-size:13pt;color:#ffffff;">' . escapeHtml($this->title) . '</span>'
            . ($this->subtitle !== '' ? '<span style="font-size:9pt;color:#c5c8cb;">&nbsp;&nbsp;&nbsp;' . escapeHtml($this->subtitle) . '</span>' : ''),
            0, 1, false, true, 'L', true);
        $this->pdf->SetY($y + 12.5);
        $meta = $this->find($el, 'itflow-report-print-meta')->item(0);
        if ($meta) {
            $this->paragraph(escapeHtml(self::text($meta)), self::GREY, 7.5);
        }
        $this->space(2);
    }

    /** The figures along the top: a row of cells - two rows when there are more than five. */
    private function stats(DOMElement $el) {
        $tiles = [];
        foreach ($this->find($el, 'itflow-report-stat') as $tile) {
            $label = $this->find($tile, 'itflow-report-stat-label')->item(0);
            $value = $this->find($tile, 'itflow-report-stat-value')->item(0);
            $sub = $this->find($tile, 'itflow-report-stat-sub')->item(0);
            $color = '#212529';
            if ($value) {
                foreach (self::classes($value) as $class) {
                    if (isset(self::COLORS[$class])) {
                        $color = self::COLORS[$class];
                    }
                }
            }
            $tiles[] = '<span style="font-size:7pt;color:' . self::GREY . ';">' . ($label ? escapeHtml(self::text($label)) : '') . '</span><br>'
                . '<span style="font-size:13pt;color:' . $color . ';">' . ($value ? $this->inline($value) : '') . '</span>'
                . ($sub ? '<br><span style="font-size:7pt;">' . $this->inline($sub) . '</span>' : '');
        }
        if (!$tiles) {
            return;
        }
        $per_row = count($tiles) <= 5 ? count($tiles) : (int) ceil(count($tiles) / ceil(count($tiles) / 4));
        $width = round(100 / $per_row, 3);
        $html = '<table cellpadding="5" cellspacing="0" width="100%">';
        foreach (array_chunk($tiles, $per_row) as $row) {
            $html .= '<tr>';
            foreach ($row as $tile) {
                $html .= '<td width="' . $width . '%" style="border:0.3px solid ' . self::LINE . ';">' . $tile . '</td>';
            }
            $html .= str_repeat('<td width="' . $width . '%"></td>', $per_row - count($row)) . '</tr>';
        }
        $this->room(20);
        $this->pdf->writeHTML($html . '</table>', true, false, true, false, '');
        $this->space(4);
    }

    /** A section card: its heading and what it says on the right, then what is in it. */
    private function section(DOMElement $el) {
        $head = null;
        foreach ($this->children($el) as $child) {
            if ($child instanceof DOMElement && self::has($child, 'card-header')) {
                $head = $child;
                break;
            }
        }
        if ($head) {
            $title = $this->find($head, 'card-title')->item(0);
            $title_text = $title ? self::text($title) : '';
            if ($title) {
                $title->parentNode->removeChild($title);
            }
            $meta = trim($this->inline($head));
            $this->room(28);
            if ($title_text !== '' || $meta !== '') {
                $this->pdf->writeHTML('<table cellpadding="0" cellspacing="0" width="100%"><tr>'
                    . '<td width="60%" style="font-size:10pt;"><b>' . escapeHtml($title_text) . '</b></td>'
                    . '<td width="40%" align="right" style="font-size:7.5pt;color:' . self::GREY . ';">' . $meta . '</td></tr></table>', true, false, true, false, '');
                $this->space(1.5);
            }
        }
        foreach ($this->children($el) as $child) {
            if ($child !== $head) {
                $this->block($child);
            }
        }
        $this->space(4);
    }

    /** A table as the page draws it: light headings, a hairline under each row, the totals in bold. */
    private function table(DOMElement $el) {
        $xpath = new DOMXPath($el->ownerDocument);
        $groups = ['head' => $xpath->query('./thead/tr', $el), 'body' => $xpath->query('./tbody/tr|./tr', $el), 'foot' => $xpath->query('./tfoot/tr', $el)];

        // Columns as wide as what is in them, so names get room and figures don't
        $columns = 0;
        $lengths = [];
        foreach ($groups as $part => $rows) {
            foreach ($rows as $tr) {
                $i = 0;
                foreach ($xpath->query('./th|./td', $tr) as $cell) {
                    if (self::skipped($cell)) {
                        continue;
                    }
                    $span = max(1, intval($cell->getAttribute('colspan')));
                    if ($span === 1) {
                        // A heading can wrap between its words, not inside one; the rest by its length, plus room for bold
                        $text = $this->cellText($cell);
                        $length = $part === 'head' ? max(array_map('mb_strlen', preg_split('/\s+/u', $text ?: ' '))) + 2 : min(36, mb_strlen($text) + 2);
                        // A figure shouldn't wrap
                        if ($part !== 'head' && preg_match('/^[^A-Za-z]*\d[^A-Za-z]*$/u', $text)) {
                            $length += 2;
                        }
                        $lengths[$i] = max($lengths[$i] ?? 5, $length);
                    }
                    $i += $span;
                }
                $columns = max($columns, $i);
            }
        }
        if (!$columns) {
            return;
        }
        $total = 0;
        for ($i = 0; $i < $columns; $i++) {
            $total += $lengths[$i] ?? 5;
        }
        $widths = [];
        for ($i = 0; $i < $columns; $i++) {
            $widths[$i] = ($lengths[$i] ?? 5) / $total * 100;
        }
        $size = $columns <= 5 ? 8.5 : ($columns <= 7 ? 8 : ($columns <= 9 ? 7.5 : ($columns <= 12 ? 6.8 : 6.2)));
        $previous = $this->size;
        $this->size = $size;

        $html = '<table cellpadding="3.2" cellspacing="0" width="100%" style="font-size:' . $size . 'pt;">';
        foreach ($groups as $part => $rows) {
            if (!$rows->length) {
                continue;
            }
            $html .= $part === 'head' ? '<thead>' : ($part === 'foot' ? '' : '<tbody>');
            foreach ($rows as $tr) {
                $row_classes = self::classes($tr);
                $group_row = in_array('itflow-report-group-row', $row_classes, true);
                $html .= '<tr>';
                $i = 0;
                foreach ($xpath->query('./th|./td', $tr) as $cell) {
                    if (self::skipped($cell)) {
                        continue;
                    }
                    $span = max(1, intval($cell->getAttribute('colspan')));
                    $width = 0;
                    for ($j = $i; $j < $i + $span && $j < $columns; $j++) {
                        $width += $widths[$j];
                    }
                    $i += $span;
                    $classes = array_merge(self::classes($cell), $row_classes);
                    $align = in_array('text-end', $classes, true) ? 'right' : (in_array('text-center', $classes, true) ? 'center' : 'left');
                    $style = 'border-bottom:0.3px solid ' . self::LINE . ';';
                    if ($part === 'head') {
                        $style = 'background-color:#f8f9fa;border-top:0.3px solid ' . self::LINE . ';border-bottom:0.5px solid #ced4da;font-weight:bold;';
                    } elseif ($part === 'foot') {
                        $style = 'border-top:0.6px solid #adb5bd;font-weight:bold;';
                    } elseif ($group_row) {
                        $style .= 'background-color:#f8f9fa;font-weight:bold;';
                    }
                    if ($part !== 'head' && (strtolower($cell->tagName) === 'th' || array_intersect($classes, ['fw-semibold', 'fw-bold']))) {
                        $style .= 'font-weight:bold;';
                    }
                    foreach ($classes as $class) {
                        if (isset(self::COLORS[$class])) {
                            $style .= 'color:' . self::COLORS[$class] . ';';
                            break;
                        }
                    }
                    $tag = $part === 'head' ? 'th' : 'td';
                    $html .= '<' . $tag . ' width="' . round($width, 3) . '%" align="' . $align . '"' . ($span > 1 ? ' colspan="' . $span . '"' : '')
                        . ' style="' . $style . '">' . trim($this->inline($cell)) . '</' . $tag . '>';
                }
                // A short row still fills the table
                if ($i < $columns) {
                    $width = 0;
                    for ($j = $i; $j < $columns; $j++) {
                        $width += $widths[$j];
                    }
                    $html .= '<td width="' . round($width, 3) . '%" style="border-bottom:0.3px solid ' . self::LINE . ';"></td>';
                }
                $html .= '</tr>';
            }
            $html .= $part === 'head' ? '</thead>' : ($part === 'foot' ? '' : '</tbody>');
        }
        $html .= '</table>';
        $this->room(18);
        $this->pdf->writeHTML($html, true, false, true, false, '');
        $this->size = $previous;
        $this->space(2);
    }

    private function cellText(DOMElement $cell) {
        $holder = $cell->ownerDocument->createElement('span');
        foreach ($cell->childNodes as $child) {
            $holder->appendChild($child->cloneNode(true));
        }
        return trim(html_entity_decode(strip_tags(str_replace('<br>', ' ', $this->inline($holder))), ENT_QUOTES, 'UTF-8'));
    }

    /** The weekday by hour grid - each hour shaded as on the page. */
    private function heatGrid(DOMElement $el) {
        $xpath = new DOMXPath($el->ownerDocument);
        $html = '<table cellpadding="1" cellspacing="0.6" width="100%" style="font-size:5.5pt;color:' . self::GREY . ';">';
        foreach ($xpath->query('.//tr', $el) as $tr) {
            $cells = $xpath->query('./th|./td', $tr);
            $html .= '<tr>';
            foreach ($cells as $i => $cell) {
                $width = $i === 0 ? 6 : 94 / max(1, $cells->length - 1);
                if (strtolower($cell->tagName) === 'td') {
                    $html .= '<td width="' . round($width, 3) . '%" bgcolor="' . $this->heat(self::heatOf($cell)) . '"> </td>';
                } else {
                    $html .= '<td width="' . round($width, 3) . '%" align="' . ($i === 0 ? 'right' : 'center') . '">' . escapeHtml(self::text($cell)) . '</td>';
                }
            }
            $html .= '</tr>';
        }
        $this->room(30);
        $this->pdf->writeHTML($html . '</table>', true, false, true, false, '');
        $this->space(2);
    }

    /** A chart, from the picture the browser sent of it. */
    private function chart(DOMElement $el) {
        $png = isset($this->canvases[$el]) ? ($this->charts[$this->canvases[$el]] ?? null) : null;
        $size = $png ? @getimagesizefromstring($png) : false;
        if (!$size || !$size[0] || !$size[1]) {
            return;
        }
        // A chart across the page; a doughnut, round in the middle of it, smaller
        $config = json_decode($el->getAttribute('data-report-chart'), true);
        $width = $this->width;
        $height = $width * $size[1] / $size[0];
        $most = ($config['type'] ?? '') === 'doughnut' ? 50 : 75;
        if ($height > $most) {
            $height = $most;
            $width = $height * $size[0] / $size[1];
        }
        $this->room($height + 2);
        $x = $this->pdf->getMargins()['left'] + ($this->width - $width) / 2;
        $y = $this->pdf->GetY();
        $this->pdf->Image('@' . $png, $x, $y, $width, $height, 'PNG');
        $this->pdf->SetY($y + $height + 2);
    }

    /** The geography map, drawn again from its outlines in the page's light colours. */
    private function map(DOMElement $el) {
        // The HTML parser keeps attribute names in lower case
        $box = preg_split('/[\s,]+/', trim($el->getAttribute('viewBox') ?: $el->getAttribute('viewbox')));
        if (count($box) !== 4 || floatval($box[2]) <= 0 || floatval($box[3]) <= 0) {
            return;
        }
        $shapes = $outline = '';
        foreach ($el->getElementsByTagName('path') as $path) {
            $d = self::absolutePath($path->getAttribute('d'));
            if (self::has($path, 'itflow-geo-current')) {
                $outline = '<path d="' . $d . '" fill="none" stroke="#212529" stroke-width="1.5"/>';
                continue;
            }
            $fill = self::has($path, 'itflow-geo-on') ? $this->heat(self::heatOf($path)) : self::NONE;
            $shapes .= '<path d="' . $d . '" fill="' . $fill . '" stroke="#ffffff" stroke-width="0.6"/>';
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . floatval($box[2]) . ' ' . floatval($box[3]) . '">' . $shapes . $outline . '</svg>';
        $width = $this->width;
        $height = $width * floatval($box[3]) / floatval($box[2]);
        if ($height > 95) {
            $height = 95;
            $width = $height * floatval($box[2]) / floatval($box[3]);
        }
        $this->room($height + 2);
        $x = $this->pdf->getMargins()['left'] + ($this->width - $width) / 2;
        $y = $this->pdf->GetY();
        $this->pdf->ImageSVG('@' . $svg, $x, $y, $width, $height);
        $this->pdf->SetY($y + $height + 3);
    }

    /**
     * A map outline with every point absolute. The outlines move from one ring
     * to the next relative to where the last ring started, as SVG allows, but
     * TCPDF takes such a move from where the last ring ended.
     */
    private static function absolutePath($d) {
        preg_match_all('/[MmLlZzAa]|-?[0-9]*\.?[0-9]+/', (string) $d, $m);
        $tokens = $m[0];
        $count = count($tokens);
        $out = '';
        $cmd = '';
        $x = $y = $sx = $sy = 0.0;
        $i = 0;
        while ($i < $count) {
            $token = $tokens[$i];
            if (ctype_alpha($token)) {
                $cmd = $token;
                $i++;
                if ($cmd === 'z' || $cmd === 'Z') {
                    $out .= 'Z';
                    $x = $sx;
                    $y = $sy;
                }
                continue;
            }
            $relative = ctype_lower($cmd);
            if (in_array($cmd, ['M', 'm', 'L', 'l'], true) && $i + 1 < $count) {
                $nx = floatval($tokens[$i]) + ($relative ? $x : 0);
                $ny = floatval($tokens[$i + 1]) + ($relative ? $y : 0);
                $i += 2;
                if ($cmd === 'M' || $cmd === 'm') {
                    $out .= "M$nx,$ny";
                    $sx = $nx;
                    $sy = $ny;
                    // Pairs after a move are lines
                    $cmd = $relative ? 'l' : 'L';
                } else {
                    $out .= "L$nx,$ny";
                }
                $x = $nx;
                $y = $ny;
            } elseif (in_array($cmd, ['A', 'a'], true) && $i + 6 < $count) {
                $nx = floatval($tokens[$i + 5]) + ($relative ? $x : 0);
                $ny = floatval($tokens[$i + 6]) + ($relative ? $y : 0);
                $out .= 'A' . floatval($tokens[$i]) . ',' . floatval($tokens[$i + 1]) . ' ' . floatval($tokens[$i + 2]) . ' ' . intval($tokens[$i + 3]) . ',' . intval($tokens[$i + 4]) . " $nx,$ny";
                $i += 7;
                $x = $nx;
                $y = $ny;
            } else {
                $i++;
            }
        }
        return $out;
    }

    /** A list of bars (who raised the most tickets): the name, a bar, the number. */
    private function bars(DOMElement $el) {
        $html = '<table cellpadding="2" cellspacing="0" width="100%" style="font-size:' . $this->size . 'pt;">';
        foreach ($this->find($el, 'itflow-dash-bar') as $bar) {
            $label = $this->find($bar, 'itflow-dash-bar-label')->item(0);
            $value = $this->find($bar, 'itflow-dash-bar-value')->item(0);
            $fill = $this->find($bar, 'itflow-dash-bar-fill')->item(0);
            $share = $fill && preg_match('/width:\s*([0-9.]+)%/', $fill->getAttribute('style'), $m) ? max(1, min(100, floatval($m[1]))) : 0;
            $color = $fill && self::has($fill, 'bg-info') ? self::BADGES['info'][0] : $this->accent;
            $html .= '<tr><td width="40%">' . ($label ? $this->inline($label) : '') . '</td>'
                . '<td width="48%"><table cellpadding="0" cellspacing="0" width="100%"><tr>'
                . '<td width="' . $share . '%" bgcolor="' . $color . '" style="font-size:5pt;"> </td>'
                . ($share < 100 ? '<td width="' . (100 - $share) . '%" bgcolor="' . self::NONE . '" style="font-size:5pt;"> </td>' : '')
                . '</tr></table></td>'
                . '<td width="12%" align="right"><b>' . ($value ? escapeHtml(self::text($value)) : '') . '</b></td></tr>';
        }
        $this->pdf->writeHTML($html . '</table>', true, false, true, false, '');
        $this->space(2);
    }
}
