<?php

/*
 * A contract as a PDF - loaded by contractPdf() once TCPDF itself is, as a
 * class has to follow the one it extends.
 *
 * The same look as an invoice: the accent band, the logo and who it is
 * from, then the document (contractDocumentHtml() - the copy sent, for a
 * round), the signature block, and for a signed copy a last section saying
 * how it was signed - when it was sent and opened, who signed, when and from
 * where, and the document's SHA-256 fingerprint. TCPDF's own Error() kills
 * the request; this one throws, so terms TCPDF cannot draw are printed as
 * plain text instead.
 */

class ContractPdf extends TCPDF {

    public $footer_label = '';

    public function Header() {
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

    public function Error($msg) {
        throw new Exception('TCPDF: ' . $msg);
    }
}

/** How the document's classes look in the PDF - TCPDF reads a style block. */
function contractPdfStyle() {
    return '<style>
        h1.itflow-contract-doc-title { font-size: 15pt; font-weight: bold; color: #212529; }
        h2.itflow-contract-doc-heading { font-size: 10pt; font-weight: bold; color: #0a58ca; }
        p.itflow-contract-doc-between { color: #495057; }
        div.itflow-contract-doc-label { font-size: 6.5pt; font-weight: bold; color: #6c757d; }
        span.itflow-contract-doc-muted { font-size: 7.5pt; color: #6c757d; }
        table.itflow-contract-doc-table th { color: #6c757d; font-weight: bold; background-color: #f1f3f5; border-bottom: 0.3px solid #dee2e6; }
        table.itflow-contract-doc-table td { border-bottom: 0.3px solid #dee2e6; }
        table.itflow-contract-doc-summary th { background-color: #ffffff; }
    </style>';
}

/*
 * HTML TCPDF can draw: pictures in the page itself (data: URIs) kept, as
 * TCPDF takes them, and anything pointing elsewhere dropped - TCPDF would go
 * and fetch any address, and a format it cannot draw ends the PDF.
 */
function contractPdfSafeHtml($html) {
    if (stripos((string) $html, '<img') === false) {
        return (string) $html;
    }
    return preg_replace_callback('/<img\b[^>]*>/i', function ($match) {
        $tag = $match[0];
        $src = preg_match('/\bsrc\s*=\s*"([^"]*)"/i', $tag, $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : '';
        $alt = preg_match('/\balt\s*=\s*"([^"]*)"/i', $tag, $m) ? $m[1] : '';
        $data = preg_match('#^data:image/(png|jpe?g|gif);base64,(.+)$#is', $src, $m) ? (base64_decode($m[2], true) ?: null) : null;
        $info = $data ? @getimagesizefromstring($data) : false;
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF], true)) {
            return $alt !== '' ? '<i>[' . $alt . ']</i>' : '<i>[image]</i>';
        }
        return '<img src="@' . base64_encode($data) . '" width="' . min(intval($info[0]), 640) . '" alt="' . $alt . '">';
    }, (string) $html);
}

/** Draw a contract - a round's copy when $round is given, else as it is today - ['name' => file name, 'data' => the PDF]. */
function contractPdfRender(array $row, $round = null) {
    $company = billingCompany();
    $e = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    $grey = '#6c757d';
    $signed = $round && !empty($round['contract_signature_client_signed_at']) && !empty($round['contract_signature_agent_signed_at']);
    $document = $round ? (string) $round['contract_signature_document'] : contractDocumentHtml($row);

    $format = in_array($company['company_country'] ?? '', ['United States', 'USA', 'US', 'Canada'], true) ? 'LETTER' : 'A4';
    $pdf = new ContractPdf('P', 'mm', $format, true, 'UTF-8', false);
    $pdf->SetCreator('ITFlow');
    $pdf->SetAuthor((string) ($company['company_name'] ?? ''));
    $pdf->SetTitle((string) $row['contract_name']);
    $pdf->SetMargins(16, 14, 16);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(12);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->setFontSubsetting(true);
    $pdf->setImageScale(1.5);
    $pdf->footer_label = trim(($company['company_name'] ?? '') . ' · ' . $row['contract_name'] . ($signed ? ' · Signed' : ($round ? '' : ' · ' . date('M j, Y'))), ' ·');
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->SetTextColor(33, 37, 41);

    // Who it is from
    $from_lines = array_filter([
        formatAddress($company['company_address'] ?? '', $company['company_city'] ?? '', $company['company_state'] ?? '', $company['company_zip'] ?? '', $company['company_country'] ?? '', ', '),
        implode('  ·  ', array_filter([$company['company_email'] ?? '', $company['phone_display'] ?? '', $company['company_website'] ?? ''])),
    ]);
    $logo = !empty($company['logo_path']) ? '<img src="' . $e($company['logo_path']) . '" height="38"><br>' : '';
    $status = $signed ? 'SIGNED' : ($round && !empty($round['contract_signature_client_signed_at']) ? 'SIGNED BY CLIENT' : 'CONTRACT');
    $pdf->writeHTML('<table cellpadding="0" cellspacing="0" width="100%"><tr>
        <td width="62%">' . $logo . '<span style="font-size:11pt; font-weight:bold;">' . $e($company['company_name'] ?? '') . '</span><br><span style="font-size:7.5pt; color:' . $grey . ';">' . nl2br($e(implode("\n", $from_lines))) . '</span></td>
        <td width="38%" align="right"><span style="font-size:8pt; color:' . ($signed ? '#198754' : '#0a58ca') . '; font-weight:bold; letter-spacing:2px;">' . $status . '</span><br>
            <span style="font-size:7.5pt; color:' . $grey . ';">' . $e($round && !empty($round['contract_signature_sent_at']) ? 'Sent ' . date('M j, Y', strtotime($round['contract_signature_sent_at'])) : 'As of ' . date('M j, Y')) . '</span></td>
    </tr></table>', true, false, true, false, '');
    $pdf->Ln(2);

    // The document - plain text when TCPDF can't draw it
    try {
        $pdf->startTransaction();
        $pdf->writeHTML(contractPdfStyle() . contractPdfSafeHtml($document), true, false, true, false, '');
        $pdf->commitTransaction();
    } catch (Throwable $error) {
        $pdf = $pdf->rollbackTransaction();
        $text = trim(preg_replace('/\n{3,}/', "\n\n", html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</tr>', '</h1>', '</h2>', '</li>'], "\n", $document)), ENT_QUOTES, 'UTF-8')));
        $pdf->writeHTML('<span style="font-size:8.5pt;">' . nl2br($e($text)) . '</span>', true, false, true, false, '');
    }
    $pdf->Ln(4);

    // The signatures
    $cells = '';
    $whos = [];
    foreach (['client' => (string) $row['client_name'], 'agent' => (string) ($company['company_name'] ?? '')] as $side => $for) {
        $has = $round && !empty($round["contract_signature_{$side}_signed_at"]);
        $mark = '<br><br><br>';
        if ($has && !empty($round["contract_signature_{$side}_image"]) && preg_match('#^data:image/png;base64,(.+)$#s', (string) $round["contract_signature_{$side}_image"], $m)) {
            $mark = '<img src="@' . $m[1] . '" height="34">';
        } elseif ($has) {
            $mark = '<span style="font-family:dejavuserif; font-style:italic; font-size:17pt;">' . $e($round["contract_signature_{$side}_name"]) . '</span>';
        }
        $who = $has
            ? '<b>' . $e($round["contract_signature_{$side}_name"]) . '</b><br><span style="color:' . $grey . ';">' . $e(implode(' · ', array_filter([(string) $round["contract_signature_{$side}_title"], date('M j, Y', strtotime($round["contract_signature_{$side}_signed_at"]))]))) . '</span>'
            : '<span style="color:' . $grey . ';">Name, title and date</span><br>&nbsp;';
        $cells .= '<td width="48%" style="border-bottom:0.4px solid #adb5bd;"><span style="font-size:6.5pt; color:' . $grey . '; font-weight:bold;">SIGNED FOR ' . $e(strtoupper($for)) . '</span><br>' . $mark . '</td>';
        $cells .= $side === 'client' ? '<td width="4%"></td>' : '';
        $whos[] = $who;
    }
    $pdf->writeHTML('<table cellpadding="3" cellspacing="0" width="100%" nobr="true"><tr>' . $cells . '</tr><tr><td width="48%" style="font-size:8pt;">' . $whos[0] . '</td><td width="4%"></td><td width="48%" style="font-size:8pt;">' . $whos[1] . '</td></tr></table>',
        true, false, true, false, '');

    // How it was signed
    if ($round && $round['contract_signature_client_via'] !== null) {
        $rows = '';
        foreach (contractSignatureTrail($round) as [$when, $what, $detail]) {
            $rows .= '<tr><td width="27%" style="color:' . $grey . ';">' . $e($when) . '</td><td width="73%">' . $e($what) . ($detail !== '' ? '<br><span style="color:' . $grey . ';">' . $e($detail) . '</span>' : '') . '</td></tr>';
        }
        $html = '<table cellpadding="2.5" cellspacing="0" width="100%" nobr="true"><tr><td colspan="2"><span style="font-size:9pt; font-weight:bold;">How it was signed</span></td></tr>' . $rows
            . '<tr><td width="27%" style="color:' . $grey . ';">Document fingerprint</td><td width="73%"><span style="font-family:dejavusansmono; font-size:7pt;">SHA-256 ' . $e($round['contract_signature_document_hash']) . '</span></td></tr></table>';
        $pdf->Ln(6);
        $pdf->SetFont('dejavusans', '', 7.5);
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($row['client_name'] . ' ' . $row['contract_name'])) . ($signed ? '-signed' : '') . '.pdf';
    return ['name' => trim($name, '-'), 'data' => $pdf->Output('', 'S')];
}
