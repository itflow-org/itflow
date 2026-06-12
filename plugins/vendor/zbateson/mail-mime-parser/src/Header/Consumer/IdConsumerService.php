<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

use ZBateson\MailMimeParser\Header\IHeaderPart;

/**
 * Parses a single ID from an ID header.  Begins consuming on a '<' char, and
 * ends on a '>' char.
 *
 * @author Zaahid Bateson
 */
class IdConsumerService extends GenericConsumerService
{
    /**
     * Overridden to return patterns matching the beginning part of an ID ('<'
     * and '>' chars).
     *
     * @return string[] the patterns
     */
    public function getTokenSeparators() : array
    {
        return \array_merge(parent::getTokenSeparators(), ['<', '>']);
    }

    /**
     * Returns true for '>'.
     */
    protected function isEndToken(string $token) : bool
    {
        return ($token === '>');
    }

    /**
     * Returns true for '<'.
     */
    protected function isStartToken(string $token) : bool
    {
        return ($token === '<');
    }

    /**
     * Returns null for whitespace, and Token for anything else.
     */
    protected function getPartForToken(string $token, bool $isLiteral) : ?IHeaderPart
    {
        if (\preg_match('/^\s+$/', $token)) {
            return null;
        }
        return $this->partFactory->newToken($token, true);
    }
}
