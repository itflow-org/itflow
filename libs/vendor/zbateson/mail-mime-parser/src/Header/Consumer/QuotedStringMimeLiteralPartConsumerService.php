<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

use ZBateson\MailMimeParser\Header\IHeaderPart;
use ZBateson\MailMimeParser\Header\Part\MimeToken;

/**
 * Allows for mime-encoded parts inside a quoted part.
 *
 * @author Zaahid Bateson
 */
class QuotedStringMimeLiteralPartConsumerService extends QuotedStringConsumerService
{
    /**
     * Constructs a LiteralPart and returns it.
     *
     * @param bool $isLiteral not used - everything in a quoted string is a
     *        literal
     */
    protected function getPartForToken(string $token, bool $isLiteral) : ?IHeaderPart
    {
        if (!$isLiteral && \preg_match('/' . MimeToken::MIME_PART_PATTERN . '/', $token)) {
            return $this->partFactory->newMimeToken($token);
        }
        return $this->partFactory->newToken($token, $isLiteral);
    }
}
