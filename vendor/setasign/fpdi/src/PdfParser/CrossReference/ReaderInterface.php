<?php
/**
 * This file is part of FPDI
 *
 * @package   setasign\Fpdi
 * @copyright Copyright (c) 2019 Setasign - Jan Slabon (https://www.setasign.com)
 * @license   http://opensource.org/licenses/mit-license The MIT License
 */

namespace setasign\Fpdi\PdfParser\CrossReference;

use setasign\Fpdi\PdfParser\Type\PdfDictionary;

/**
 * ReaderInterface for cross-reference readers.
 *
 * @package setasign\Fpdi\PdfParser\CrossReference
 */
interface ReaderInterface
{
    /**
     * Get an offset by an object number.
     *
     * @param int $objectNumber
     * @return int|bool False if the offset was not found.
     */
    public function getOffsetFor($objectNumber);

    /**
     * Get the trailer related to this cross reference.
     *
     * @return PdfDictionary
     */
    public function getTrailer();
}
