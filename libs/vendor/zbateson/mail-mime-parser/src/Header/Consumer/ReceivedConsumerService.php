<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

use Iterator;
use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\Consumer\Received\DomainConsumerService;
use ZBateson\MailMimeParser\Header\Consumer\Received\GenericReceivedConsumerService;
use ZBateson\MailMimeParser\Header\Consumer\Received\ReceivedDateConsumerService;
use ZBateson\MailMimeParser\Header\Part\HeaderPartFactory;
use ZBateson\MailMimeParser\Header\Part\Token;

/**
 * Parses a Received header into ReceivedParts, ReceivedDomainParts, a DatePart,
 * and CommentParts.
 *
 * Parts that don't correspond to any of the above are discarded.
 *
 * @author Zaahid Bateson
 */
class ReceivedConsumerService extends AbstractConsumerService
{
    public function __construct(
        LoggerInterface $logger,
        HeaderPartFactory $partFactory,
        DomainConsumerService $fromDomainConsumerService,
        DomainConsumerService $byDomainConsumerService,
        GenericReceivedConsumerService $viaGenericReceivedConsumerService,
        GenericReceivedConsumerService $withGenericReceivedConsumerService,
        GenericReceivedConsumerService $idGenericReceivedConsumerService,
        GenericReceivedConsumerService $forGenericReceivedConsumerService,
        ReceivedDateConsumerService $receivedDateConsumerService,
        CommentConsumerService $commentConsumerService
    ) {
        parent::__construct(
            $logger,
            $partFactory,
            [
                $fromDomainConsumerService,
                $byDomainConsumerService,
                $viaGenericReceivedConsumerService,
                $withGenericReceivedConsumerService,
                $idGenericReceivedConsumerService,
                $forGenericReceivedConsumerService,
                $receivedDateConsumerService,
                $commentConsumerService
            ]
        );
    }

    /**
     * ReceivedConsumerService doesn't have any token separators of its own.
     * Sub-Consumers will return separators matching 'part' word separators, for
     * example 'from' and 'by', and ';' for date, etc...
     *
     * @return string[] an array of regex pattern matchers
     */
    protected function getTokenSeparators() : array
    {
        return [];
    }

    /**
     * ReceivedConsumerService doesn't have an end token, and so this just
     * returns false.
     */
    protected function isEndToken(string $token) : bool
    {
        return false;
    }

    /**
     * ReceivedConsumerService doesn't start consuming at a specific token, it's
     * the base handler for the Received header, and so this always returns
     * false.
     *
     * @codeCoverageIgnore
     */
    protected function isStartToken(string $token) : bool
    {
        return false;
    }

    /**
     * Overridden to exclude the MimeLiteralPart pattern that comes by default
     * in AbstractConsumer.
     *
     * @return string the regex pattern
     */
    protected function getTokenSplitPattern() : string
    {
        $sChars = \implode('|', $this->getAllTokenSeparators());
        return '~(' . $sChars . ')~';
    }

    /**
     * Overridden to /not/ advance when the end token matches a start token for
     * a sub-consumer.
     */
    protected function advanceToNextToken(Iterator $tokens, bool $isStartToken) : static
    {
        if ($isStartToken) {
            $tokens->next();
        } elseif ($tokens->valid() && !$this->isEndToken($tokens->current())) {
            foreach ($this->subConsumers as $consumer) {
                if ($consumer->isStartToken($tokens->current())) {
                    return $this;
                }
            }
            $tokens->next();
        }
        return $this;
    }

    /**
     * @param \ZBateson\MailMimeParser\Header\IHeaderPart[] $parts
     * @return \ZBateson\MailMimeParser\Header\IHeaderPart[]
     */
    protected function processParts(array $parts) : array
    {
        // filtering out tokens (filters out the names, e.g. 'by' or 'with')
        return \array_values(\array_filter($parts, fn ($p) => !$p instanceof Token));
    }
}
