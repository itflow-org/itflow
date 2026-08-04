<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

use ZBateson\MailMimeParser\Header\IHeaderPart;

/**
 * Represents a quoted part of a header value starting at a double quote, and
 * ending at the next double quote.
 *
 * A quoted-pair part in a header is a literal.  There are no sub-consumers for
 * it and a Part\LiteralPart is returned.
 *
 * Newline characters (CR and LF) are stripped entirely from the quoted part.
 * This is based on the example at:
 *
 * https://tools.ietf.org/html/rfc822#section-3.1.1
 *
 * And https://www.w3.org/Protocols/rfc1341/7_2_Multipart.html in section 7.2.1
 * splitting the boundary.
 *
 * @author Zaahid Bateson
 */
class QuotedStringConsumerService extends AbstractConsumerService
{
    /**
     * Returns true if the token is a double quote.
     */
    protected function isStartToken(string $token) : bool
    {
        return ($token === '"');
    }

    /**
     * Returns true if the token is a double quote.
     */
    protected function isEndToken(string $token) : bool
    {
        return ($token === '"');
    }

    /**
     * Returns a single regex pattern for a double quote.
     *
     * @return string[]
     */
    protected function getTokenSeparators() : array
    {
        return ['\"'];
    }

    /**
     * Constructs a LiteralPart and returns it.
     */
    protected function getPartForToken(string $token, bool $isLiteral) : ?IHeaderPart
    {
        return $this->partFactory->newToken($token, $isLiteral, true);
    }

    /**
     * Overridden to combine all part values into a single string and return it
     * as an array with a single element.
     *
     * The returned IHeaderParts is an array containing a single
     * QuotedLiteralPart.
     *
     * @param IHeaderPart[] $parts
     * @return IHeaderPart[]
     */
    protected function processParts(array $parts) : array
    {
        return [$this->partFactory->newQuotedLiteralPart($parts)];
    }
}
