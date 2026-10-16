<?php

/*
 * The documentation PDF's page furniture - loaded by clientExportPdf() once
 * TCPDF itself is, as a class has to follow the one it extends. A running
 * header with the client's name on every page but the cover, and a footer
 * with the company, the date and "Page 3 of 18".
 *
 * The bookmarks open beside the PDF. TCPDF's own Error() kills the request
 * with die(); this one throws, so one document whose HTML TCPDF cannot draw
 * is caught and printed as plain text instead of ending the whole export.
 */

class ClientExportPdf extends TCPDF {

    public $running_title = '';
    public $running_footer = '';

    public function __construct(...$args) {
        parent::__construct(...$args);
        // Leave the page mode to the bookmarks, which open beside the PDF -
        // TCPDF would otherwise write a second, conflicting /PageMode
        $this->PageMode = '';
    }

    public function Header() {
        if ($this->getPage() <= 1) {
            return;
        }
        $margins = $this->getMargins();
        $width = $this->getPageWidth() - $margins['left'] - $margins['right'];
        $this->SetY(9);
        $this->SetFont('dejavusans', '', 7.5);
        $this->SetTextColor(108, 117, 125);
        $this->Cell($width / 2, 4, $this->running_title, 0, 0, 'L');
        $this->Cell($width / 2, 4, 'IT Documentation', 0, 1, 'R');
        $this->SetDrawColor(222, 226, 230);
        $this->SetLineWidth(0.2);
        $this->Line($margins['left'], 14, $this->getPageWidth() - $margins['right'], 14);
    }

    public function Footer() {
        if ($this->getPage() <= 1) {
            return;
        }
        $margins = $this->getMargins();
        $width = $this->getPageWidth() - $margins['left'] - $margins['right'];
        $this->SetY(-13);
        $this->SetFont('dejavusans', '', 7.5);
        $this->SetTextColor(108, 117, 125);
        $this->Cell($width * 0.7, 4, $this->running_footer, 0, 0, 'L');
        $this->Cell($width * 0.3, 4, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
    }

    public function Error($msg) {
        throw new Exception('TCPDF: ' . $msg);
    }
}
