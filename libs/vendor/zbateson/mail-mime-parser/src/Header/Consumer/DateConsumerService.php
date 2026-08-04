<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

use ZBateson\MailMimeParser\Header\IHeaderPart;

/**
 * Parses a date header into a Part\DatePart taking care of comment and quoted
 * parts as necessary.
 *
 * @author Zaahid Bateson
 */
class DateConsumerService extends GenericConsumerService
{
    /**
     * Returns a Part\LiteralPart for the current token
     *
     * @param string $token the token
     * @param bool $isLiteral set to true if the token represents a literal -
     *        e.g. an escaped token
     */
    protected function getPartForToken(string $token, bool $isLiteral) : ?IHeaderPart
    {
        return $this->partFactory->newToken($token, false);
    }

    /**
     * Constructs a single Part\DatePart of any parsed parts returning it in an
     * array with a single element.
     *
     * @param \ZBateson\MailMimeParser\Header\IHeaderPart[] $parts The parsed
     *        parts.
     * @return \ZBateson\MailMimeParser\Header\IHeaderPart[] Array of resulting
     *         final parts.
     */
    protected function processParts(array $parts) : array
    {
        return [$this->partFactory->newDatePart($parts)];
    }
}
