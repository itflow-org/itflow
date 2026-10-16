<?php

/*
 * Invoice, quote and packing slip PDFs - loaded by billingPdf() once TCPDF
 * itself is, as a class has to follow the one it extends.
 *
 * One look for all of them, the same as the document on screen: a band of
 * the accent colour, the logo and who it is from, what it is and its
 * number and dates, who it is for and what it comes to, the lines, the
 * totals and notes, and the footer from the billing settings with the page
 * number. An invoice still owed carries a code to scan to open it online
 * (and pay, when online payments are on); a quote, one to answer it. Paid,
 * cancelled, accepted and declined ones are stamped.
 */

class BillingPdf extends TCPDF {

    public $footer_text = '';
    public $footer_label = '';

    public function Header() {
        // The accent band across the top of every page
        $this->SetFillColor(13, 110, 253);
        $this->Rect(0, 0, $this->getPageWidth(), 2.5, 'F');
    }

    public function Footer() {
        $margins = $this->getMargins();
        $width = $this->getPageWidth() - $margins['left'] - $margins['right'];
        $this->SetY(-14);
        $this->SetDrawColor(222, 226, 230);
        $this->SetLineWidth(0.2);
        $this->Line($margins['left'], $this->GetY() - 1.5, $this->getPageWidth() - $margins['right'], $this->GetY() - 1.5);
        $this->SetFont('dejavusans', '', 7);
        $this->SetTextColor(108, 117, 125);
        $this->Cell($width * 0.75, 4, $this->footer_label, 0, 0, 'L');
        $this->Cell($width * 0.25, 4, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

/**
 * Draw an invoice, quote or recurring invoice - or an invoice's packing slip
 * ($variant 'packing') - and return ['name' => file name, 'data' => the PDF].
 */
function billingPdfRender($type, array $row, $variant = 'document') {
    global $config_invoice_footer, $config_quote_footer, $config_invoice_show_tax_id;

    $t = billingType($type);
    $c = $t['col'];
    $id = intval($row["{$c}_id"]);
    $company = billingCompany();
    $bill_to = billingBillTo($row['client_id']);
    $items = billingItems($type, $id);
    $totals = billingTotals($type, $row, $items);
    $currency = (string) $row["{$c}_currency_code"];
    $number = billingNumber($type, $row);
    $packing = $variant === 'packing';
    $e = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    $money = function ($amount) use ($currency, $e) {
        return $e(billingMoney($amount, $currency));
    };

    $status = $type === 'recurring' ? '' : (string) $row["{$c}_status"];
    $paid = $type === 'invoice' ? invoicePaid($id) : 0;
    $state = $type === 'invoice' ? invoiceState($row, $paid) : null;

    // Letter for North America, A4 everywhere else
    $format = in_array($company['company_country'] ?? '', ['United States', 'USA', 'US', 'Canada'], true) ? 'LETTER' : 'A4';
    $pdf = new BillingPdf('P', 'mm', $format, true, 'UTF-8', false);
    $title = $packing ? 'Packing Slip' : ['invoice' => 'Invoice', 'quote' => 'Quote', 'recurring' => 'Recurring Invoice'][$type];
    $pdf->SetCreator('ITFlow');
    $pdf->SetAuthor((string) ($company['company_name'] ?? ''));
    $pdf->SetTitle("$title $number");
    $pdf->SetMargins(16, 14, 16);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(12);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->setFontSubsetting(true);
    $pdf->setImageScale(1.5);
    $pdf->footer_label = trim(($company['company_name'] ?? '') . ' · ' . "$title $number", ' ·');
    $pdf->AddPage();
    $margins = $pdf->getMargins();
    $width = $pdf->getPageWidth() - $margins['left'] - $margins['right'];

    $grey = '#6c757d';
    $line = '#dee2e6';
    $label = function ($text) use ($grey, $e) {
        return '<span style="font-size:6.5pt; color:' . $grey . '; font-weight:bold;">' . $e(strtoupper($text)) . '</span>';
    };

    // Who it is from, and what it is
    $from_lines = array_filter([
        formatAddress($company['company_address'] ?? '', $company['company_city'] ?? '', $company['company_state'] ?? '', $company['company_zip'] ?? '', $company['company_country'] ?? '', "\n"),
        implode('  ·  ', array_filter([$company['company_email'] ?? '', $company['phone_display'] ?? ''])),
        $company['company_website'] ?? '',
        !empty($config_invoice_show_tax_id) && !empty($company['company_tax_id']) ? 'Tax ID: ' . $company['company_tax_id'] : '',
    ]);
    $logo = !empty($company['logo_path']) ? '<img src="' . $e($company['logo_path']) . '" height="42"><br>' : '';
    if ($packing) {
        $dates = ['Invoice' => $number, 'Date' => $row['invoice_date']];
    } elseif ($type === 'invoice') {
        $dates = ['Invoice date' => $row['invoice_date'], 'Due' => $row['invoice_due']];
    } elseif ($type === 'quote') {
        $dates = ['Quote date' => $row['quote_date'], 'Valid until' => $row['quote_expire']];
    } else {
        $dates = ['Billed' => recurringInvoiceFrequencies()[$row['recurring_invoice_frequency']] ?? $row['recurring_invoice_frequency'], 'Next invoice' => $row['recurring_invoice_next_date']];
    }
    $dates_html = '';
    foreach ($dates as $date_label => $value) {
        $dates_html .= '<tr><td align="right" style="color:' . $grey . ';">' . $e($date_label) . '</td><td align="right" width="30mm">' . $e($value) . '</td></tr>';
    }

    $html = '<table cellpadding="0" cellspacing="0" width="100%"><tr>
        <td width="58%">' . $logo . '<span style="font-size:12pt; font-weight:bold;">' . $e($company['company_name'] ?? '') . '</span><br><span style="font-size:7.5pt; color:' . $grey . '; line-height:1.4;">' . nl2br($e(implode("\n", $from_lines))) . '</span></td>
        <td width="42%" align="right"><span style="font-size:8pt; color:#0a58ca; font-weight:bold; letter-spacing:2px;">' . $e(strtoupper($title)) . '</span><br><span style="font-size:15pt; font-weight:bold;">' . $e($number) . '</span><br>
            <table cellpadding="1.2" cellspacing="0" width="100%" style="font-size:8pt;">' . $dates_html . '</table></td>
    </tr></table>';
    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->writeHTML($html, true, false, true, false, '');

    $pdf->SetDrawColor(222, 226, 230);
    $pdf->SetLineWidth(0.2);
    $pdf->Ln(1);
    $pdf->Line($margins['left'], $pdf->GetY(), $margins['left'] + $width, $pdf->GetY());
    $pdf->Ln(4);

    // Who it is for, and what it comes to
    $to_lines = array_filter([
        formatAddress($bill_to['location_address'] ?? '', $bill_to['location_city'] ?? '', $bill_to['location_state'] ?? '', $bill_to['location_zip'] ?? '', $bill_to['location_country'] ?? '', "\n"),
        $bill_to['contact_name'] ?? '',
        implode('  ·  ', array_filter([$bill_to['contact_email'] ?? '', $bill_to['contact_phone'] ?? ''])),
        !empty($bill_to['client_tax_id_number']) ? 'Tax ID: ' . $bill_to['client_tax_id_number'] : '',
    ]);
    $to_label = $packing ? 'Ship to' : ($type === 'quote' ? 'Prepared for' : 'Bill to');
    if ($packing) {
        $amount_html = '';
    } elseif ($type === 'invoice') {
        $done = in_array($status, ['Paid', 'Cancelled', 'Non-Billable'], true);
        $amount_html = $label($done ? 'Balance' : 'Amount due') . '<br><span style="font-size:17pt; font-weight:bold;">' . $money($state['balance']) . '</span>'
            . (!$done && $status !== 'Draft' && $state['balance'] > 0 ? '<br><span style="font-size:7.5pt; color:' . $grey . ';">by ' . $e($row['invoice_due']) . '</span>' : '');
    } elseif ($type === 'quote') {
        $amount_html = $label('Quote total') . '<br><span style="font-size:17pt; font-weight:bold;">' . $money($totals['total']) . '</span>';
    } else {
        $amount_html = $label('Each ' . recurringInvoicePeriodWord($row['recurring_invoice_frequency'])) . '<br><span style="font-size:17pt; font-weight:bold;">' . $money($totals['total']) . '</span>';
    }
    $html = '<table cellpadding="0" cellspacing="0" width="100%"><tr>
        <td width="60%">' . $label($to_label) . '<br><span style="font-size:10pt; font-weight:bold;">' . $e($bill_to['client_name'] ?? $row['client_name']) . '</span><br><span style="font-size:7.5pt; color:' . $grey . '; line-height:1.4;">' . nl2br($e(implode("\n", $to_lines))) . '</span></td>
        <td width="40%" align="right">' . $amount_html . '</td>
    </tr></table>';
    $pdf->writeHTML($html, true, false, true, false, '');

    // A stamp across a document that is done with
    $stamp = null;
    if (!$packing) {
        if ($type === 'invoice' && $status === 'Paid') {
            $stamp = ['PAID', [25, 135, 84]];
        } elseif ($type === 'invoice' && $status === 'Cancelled') {
            $stamp = ['CANCELLED', [220, 53, 69]];
        } elseif ($type === 'quote' && in_array($status, ['Accepted', 'Invoiced'], true)) {
            $stamp = ['ACCEPTED', [25, 135, 84]];
        } elseif ($type === 'quote' && $status === 'Declined') {
            $stamp = ['DECLINED', [220, 53, 69]];
        }
    }
    if ($stamp) {
        $y = $pdf->GetY();
        $pdf->SetAlpha(0.28);
        $pdf->StartTransform();
        $pdf->Rotate(12, $margins['left'] + $width / 2, 21);
        $pdf->SetFont('dejavusans', 'B', 20);
        $pdf->SetTextColor(...$stamp[1]);
        $pdf->SetDrawColor(...$stamp[1]);
        $pdf->SetLineWidth(0.8);
        $stamp_width = $pdf->GetStringWidth($stamp[0]) + 8;
        $pdf->SetXY($margins['left'] + $width / 2 - $stamp_width / 2, 15.5);
        $pdf->Cell($stamp_width, 11, $stamp[0], 1, 0, 'C');
        $pdf->StopTransform();
        $pdf->SetAlpha(1);
        $pdf->SetDrawColor(222, 226, 230);
        $pdf->SetLineWidth(0.2);
        $pdf->SetTextColor(33, 37, 41);
        $pdf->SetXY($margins['left'], $y);
    }

    $pdf->SetFont('dejavusans', '', 8.5);
    if (trim((string) $row["{$c}_scope"]) !== '') {
        $pdf->Ln(1);
        $pdf->writeHTML('<table cellpadding="5" cellspacing="0" width="100%"><tr><td style="background-color:#f1f3f5;">'
            . $label('For') . '&nbsp;&nbsp;' . $e($row["{$c}_scope"]) . '</td></tr></table>', true, false, true, false, '');
    }
    $pdf->Ln(2);

    // The lines
    $th = 'font-size:6.5pt; color:' . $grey . '; font-weight:bold; border-bottom:0.6px solid #adb5bd;';
    $td = 'border-bottom:0.3px solid ' . $line . ';';
    if ($packing) {
        $html = '<table cellpadding="4" cellspacing="0" width="100%"><thead><tr>
            <td width="74%" style="' . $th . '">ITEM</td><td width="13%" align="right" style="' . $th . '">QTY</td><td width="13%" align="center" style="' . $th . '">PACKED</td></tr></thead>';
        foreach ($items as $item) {
            $html .= '<tr nobr="true"><td width="74%" style="' . $td . '"><b>' . $e($item['item_name']) . '</b>'
                . (trim((string) $item['item_description']) !== '' ? '<br><span style="font-size:7.5pt; color:' . $grey . ';">' . nl2br($e($item['item_description'])) . '</span>' : '') . '</td>
                <td width="13%" align="right" style="' . $td . '">' . $e(billingQuantity($item['item_quantity'])) . '</td>
                <td width="13%" align="center" style="' . $td . '"><span style="font-size:11pt;">☐</span></td></tr>';
        }
        $html .= '</table>';
    } else {
        $html = '<table cellpadding="4" cellspacing="0" width="100%"><thead><tr>
            <td width="49%" style="' . $th . '">ITEM</td><td width="9%" align="right" style="' . $th . '">QTY</td>
            <td width="14%" align="right" style="' . $th . '">PRICE</td><td width="13%" align="right" style="' . $th . '">TAX</td>
            <td width="15%" align="right" style="' . $th . '">AMOUNT</td></tr></thead>';
        foreach ($items as $item) {
            $tax_label = floatval($item['item_tax']) != 0 || intval($item['item_tax_id'])
                ? '<br><span style="font-size:6.5pt; color:' . $grey . ';">' . $e(trim(($item['tax_name'] ?? '') . ' ' . ($item['tax_percent'] !== null ? billingQuantity($item['tax_percent']) . '%' : ''))) . '</span>'
                : '';
            $html .= '<tr nobr="true"><td width="49%" style="' . $td . '"><b>' . $e($item['item_name']) . '</b>'
                . (trim((string) $item['item_description']) !== '' ? '<br><span style="font-size:7.5pt; color:' . $grey . ';">' . nl2br($e($item['item_description'])) . '</span>' : '') . '</td>
                <td width="9%" align="right" style="' . $td . '">' . $e(billingQuantity($item['item_quantity'])) . '</td>
                <td width="14%" align="right" style="' . $td . '">' . $money($item['item_price']) . '</td>
                <td width="13%" align="right" style="' . $td . '">' . ($tax_label !== '' ? $money($item['item_tax']) . $tax_label : '<span style="color:' . $grey . ';">-</span>') . '</td>
                <td width="15%" align="right" style="' . $td . '"><b>' . $money($item['item_total']) . '</b></td></tr>';
        }
        if (!$items) {
            $html .= '<tr><td colspan="5" align="center" style="color:' . $grey . ';">Nothing on it yet.</td></tr>';
        }
        $html .= '</table>';
    }
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(3);

    if ($packing) {
        // Who packed it, and who took it
        $sign = '<table cellpadding="5" cellspacing="0" width="100%"><tr nobr="true">
            <td width="48%" style="border:0.4px solid #adb5bd; height:22mm;">' . $label('Packed by') . '<br><br><br><span style="font-size:7pt; color:' . $grey . ';">Name, signature and date</span></td>
            <td width="4%"></td>
            <td width="48%" style="border:0.4px solid #adb5bd; height:22mm;">' . $label('Received by') . '<br><br><br><span style="font-size:7pt; color:' . $grey . ';">Name, signature and date</span></td>
        </tr></table>';
        $pdf->writeHTML($sign, true, false, true, false, '');
    } else {
        // Notes, and what it all comes to
        $rows = '<tr><td style="color:' . $grey . ';">Subtotal</td><td align="right">' . $money($totals['subtotal']) . '</td></tr>';
        if ($totals['discount'] != 0) {
            $rows .= '<tr><td style="color:' . $grey . ';">Discount</td><td align="right">-' . $money($totals['discount']) . '</td></tr>';
        }
        foreach ($totals['taxes'] as $tax_label => $tax_amount) {
            $rows .= '<tr><td style="color:' . $grey . ';">' . $e($tax_label) . '</td><td align="right">' . $money($tax_amount) . '</td></tr>';
        }
        if ($totals['credit'] > 0) {
            $rows .= '<tr><td style="color:' . $grey . ';">Credit</td><td align="right">-' . $money($totals['credit']) . '</td></tr>';
        }
        $rows .= '<tr><td style="border-top:0.6px solid #adb5bd; font-size:10pt;"><b>Total</b></td><td align="right" style="border-top:0.6px solid #adb5bd; font-size:10pt;"><b>' . $money($totals['total']) . '</b></td></tr>';
        if ($type === 'invoice' && $state['paid'] > 0) {
            $rows .= '<tr><td style="color:#198754;">Paid</td><td align="right" style="color:#198754;">-' . $money($state['paid']) . '</td></tr>';
            $rows .= '<tr><td style="border-top:0.6px solid #adb5bd; font-size:10pt;"><b>Balance due</b></td><td align="right" style="border-top:0.6px solid #adb5bd; font-size:10pt;"><b>' . $money($state['balance']) . '</b></td></tr>';
        }
        $note = trim((string) $row["{$c}_note"]);
        $html = '<table cellpadding="0" cellspacing="0" width="100%"><tr nobr="true">
            <td width="54%">' . ($note !== '' ? $label('Notes') . '<br><span style="font-size:8pt;">' . nl2br($e($note)) . '</span>' : '') . '</td>
            <td width="6%"></td>
            <td width="40%"><table cellpadding="2.5" cellspacing="0" width="100%" style="font-size:8.5pt;">' . $rows . '</table></td>
        </tr></table>';
        $pdf->writeHTML($html, true, false, true, false, '');

        // Open it online - pay an invoice still owed, answer a quote still waiting
        $online = null;
        if ($type === 'invoice' && in_array($status, invoiceOpenStatuses(), true) && $state['balance'] > 0) {
            $online = [billingOnlinePayments() ? 'Pay online' : 'View online', billingOnlinePayments() ? 'Scan to open this invoice and pay it by card.' : 'Scan to open this invoice online.'];
        } elseif ($type === 'quote' && in_array($status, ['Sent', 'Viewed'], true) && !quoteExpired($row)) {
            $online = ['Answer online', 'Scan to open this quote and accept or decline it.'];
        }
        if ($online) {
            $url = billingGuestUrl($type, $row);
            if ($pdf->GetY() > $pdf->getPageHeight() - 60) {
                $pdf->AddPage();
            }
            $pdf->Ln(4);
            $y = $pdf->GetY();
            $pdf->SetFillColor(241, 243, 245);
            $pdf->RoundedRect($margins['left'], $y, $width, 28, 2, '1111', 'F');
            $pdf->write2DBarcode($url, 'QRCODE,M', $margins['left'] + 3, $y + 3, 22, 22, ['border' => false, 'padding' => 0, 'fgcolor' => [33, 37, 41], 'bgcolor' => false], 'N');
            $pdf->SetXY($margins['left'] + 30, $y + 6);
            $pdf->SetFont('dejavusans', 'B', 10);
            $pdf->Cell($width - 34, 5, $online[0], 0, 2, 'L');
            $pdf->SetFont('dejavusans', '', 7.5);
            $pdf->SetTextColor(108, 117, 125);
            $pdf->MultiCell($width - 34, 4, $online[1] . "\n" . $url, 0, 'L', false, 1, $margins['left'] + 30);
            $pdf->SetTextColor(33, 37, 41);
            $pdf->SetY($y + 30);
        }

        $footer = trim((string) ($type === 'quote' ? $config_quote_footer : $config_invoice_footer));
        if ($footer !== '') {
            $pdf->Ln(4);
            $pdf->SetFont('dejavusans', '', 7.5);
            $pdf->SetTextColor(108, 117, 125);
            $pdf->MultiCell($width, 4, $footer, 0, 'C', false, 1);
        }
    }

    $date = $type === 'recurring' ? date('Y-m-d') : (string) $row["{$c}_date"];
    $name = preg_replace('/[^A-Za-z0-9_\-]+/', '_', trim("{$date}_{$row['client_name']}_" . str_replace(' ', '_', $title) . "_$number", '_')) . '.pdf';
    return ['name' => $name, 'data' => $pdf->Output($name, 'S')];
}
