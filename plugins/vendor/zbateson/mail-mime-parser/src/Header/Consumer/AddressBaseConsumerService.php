<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

use Iterator;
use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\IHeaderPart;
use ZBateson\MailMimeParser\Header\Part\HeaderPartFactory;

/**
 * Serves as a base-consumer for recipient/sender email address headers (like
 * From and To).
 *
 * AddressBaseConsumerService passes on token processing to its sub-consumer, an
 * AddressConsumerService, and collects Part\AddressPart objects processed and
 * returned by AddressConsumerService.
 *
 * @author Zaahid Bateson
 */
class AddressBaseConsumerService extends AbstractConsumerService
{
    public function __construct(
        LoggerInterface $logger,
        HeaderPartFactory $partFactory,
        AddressConsumerService $addressConsumerService
    ) {
        parent::__construct($logger, $partFactory, [$addressConsumerService]);
    }

    /**
     * Returns an empty array.
     *
     * @return string[] an array of regex pattern matchers
     */
    protected function getTokenSeparators() : array
    {
        return [];
    }

    /**
     * Disables advancing for start tokens.
     *
     * The start token for AddressBaseConsumerService is part of an
     * {@see AddressPart} (or a sub-consumer) and so must be passed on.
     */
    protected function advanceToNextToken(Iterator $tokens, bool $isStartToken) : static
    {
        if ($isStartToken) {
            return $this;
        }
        parent::advanceToNextToken($tokens, $isStartToken);
        return $this;
    }

    /**
     * AddressBaseConsumerService doesn't have start/end tokens, and so always
     * returns false.
     *
     * @return false
     */
    protected function isEndToken(string $token) : bool
    {
        return false;
    }

    /**
     * AddressBaseConsumerService doesn't have start/end tokens, and so always
     * returns false.
     *
     * @codeCoverageIgnore
     * @return false
     */
    protected function isStartToken(string $token) : bool
    {
        return false;
    }

    /**
     * Overridden so tokens aren't handled at this level, and instead are passed
     * on to AddressConsumerService.
     *
     * @return \ZBateson\MailMimeParser\Header\IHeaderPart[]|array
     */
    protected function getTokenParts(Iterator $tokens) : array
    {
        return $this->getConsumerTokenParts($tokens);
    }

    /**
     * Never reached by AddressBaseConsumerService. Overridden to satisfy
     * AbstractConsumerService.
     *
     * @codeCoverageIgnore
     */
    protected function getPartForToken(string $token, bool $isLiteral) : ?IHeaderPart
    {
        return null;
    }
}
