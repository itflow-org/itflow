<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\IHeaderPart;
use ZBateson\MailMimeParser\Header\Part\HeaderPartFactory;

/**
 * Serves as a base-consumer for ID headers (like Message-ID and Content-ID).
 *
 * IdBaseConsumerService handles invalidly-formatted IDs not within '<' and '>'
 * characters.  Processing for validly-formatted IDs are passed on to its
 * sub-consumer, IdConsumer.
 *
 * @author Zaahid Bateson
 */
class IdBaseConsumerService extends AbstractConsumerService
{
    public function __construct(
        LoggerInterface $logger,
        HeaderPartFactory $partFactory,
        CommentConsumerService $commentConsumerService,
        QuotedStringConsumerService $quotedStringConsumerService,
        IdConsumerService $idConsumerService
    ) {
        parent::__construct(
            $logger,
            $partFactory,
            [
                $commentConsumerService,
                $quotedStringConsumerService,
                $idConsumerService
            ]
        );
    }

    /**
     * Returns '\s+' as a whitespace separator.
     *
     * @return string[] an array of regex pattern matchers.
     */
    protected function getTokenSeparators() : array
    {
        return ['\s+'];
    }

    /**
     * IdBaseConsumerService doesn't have start/end tokens, and so always
     * returns false.
     */
    protected function isEndToken(string $token) : bool
    {
        return false;
    }

    /**
     * IdBaseConsumerService doesn't have start/end tokens, and so always
     * returns false.
     *
     * @codeCoverageIgnore
     */
    protected function isStartToken(string $token) : bool
    {
        return false;
    }

    /**
     * Returns null for whitespace, and
     * {@see ZBateson\MailMimeParser\Header\Part\Token} for anything else.
     *
     * @param string $token the token
     * @param bool $isLiteral set to true if the token represents a literal -
     *        e.g. an escaped token
     * @return ?IHeaderPart The constructed header part or null if the token
     *         should be ignored
     */
    protected function getPartForToken(string $token, bool $isLiteral) : ?IHeaderPart
    {
        if (\preg_match('/^\s+$/', $token)) {
            return null;
        }
        return $this->partFactory->newToken($token, true);
    }
}
