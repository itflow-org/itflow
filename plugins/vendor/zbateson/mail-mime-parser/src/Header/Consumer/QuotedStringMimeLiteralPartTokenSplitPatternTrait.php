<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

use ZBateson\MailMimeParser\Header\Part\MimeToken;

/**
 * Provides a getTokenSplitPattern for consumers that could have quoted parts
 * that are mime-header-encoded.
 *
 * @author Zaahid Bateson
 */
trait QuotedStringMimeLiteralPartTokenSplitPatternTrait
{
    /**
     * Overridden to use a specialized regex for finding mime-encoded parts
     * (RFC 2047).
     *
     * Some implementations seem to place mime-encoded parts within quoted
     * parameters, and split the mime-encoded parts across multiple split
     * parameters.  The specialized regex doesn't allow double quotes inside a
     * mime encoded part, so it can be "continued" in another parameter.
     *
     * @return string the regex pattern
     */
    protected function getTokenSplitPattern() : string
    {
        $sChars = \implode('|', $this->getAllTokenSeparators());
        $mimePartPattern = MimeToken::MIME_PART_PATTERN_NO_QUOTES;
        return '~(' . $mimePartPattern . '|\\\\\r\n|\\\\.|' . $sChars . ')~ms';
    }
}
