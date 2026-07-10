<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\Part\HeaderPartFactory;

/**
 * Parses the Address portion of an email address header, for an address part
 * that contains both a name and an email address, e.g. "name" <email@tld.com>.
 *
 * The address portion found within the '<' and '>' chars may contain comments
 * and quoted portions.
 *
 * @author Zaahid Bateson
 */
class AddressEmailConsumerService extends AbstractConsumerService
{
    public function __construct(
        LoggerInterface $logger,
        HeaderPartFactory $partFactory,
        CommentConsumerService $commentConsumerService,
        QuotedStringConsumerService $quotedStringConsumerService
    ) {
        parent::__construct(
            $logger,
            $partFactory,
            [$commentConsumerService, $quotedStringConsumerService]
        );
    }

    /**
     * Overridden to return patterns matching the beginning/end part of an
     * address in a name/address part ("<" and ">" chars).
     *
     * @return string[] the patterns
     */
    public function getTokenSeparators() : array
    {
        return ['<', '>'];
    }

    /**
     * Returns true for the '>' char.
     */
    protected function isEndToken(string $token) : bool
    {
        return ($token === '>');
    }

    /**
     * Returns true for the '<' char.
     */
    protected function isStartToken(string $token) : bool
    {
        return ($token === '<');
    }

    /**
     * Returns a single {@see ZBateson\MailMimeParser\Header\Part\AddressPart}
     * with its 'email' portion set, so an {@see AddressConsumerService} can
     * identify it and create an
     * {@see ZBateson\MailMimeParser\Header\Part\AddressPart} Address with
     * both a name and email set.
     *
     * @param \ZBateson\MailMimeParser\Header\IHeaderPart[] $parts
     * @return \ZBateson\MailMimeParser\Header\IHeaderPart[]|array
     */
    protected function processParts(array $parts) : array
    {
        return [$this->partFactory->newAddress([], $parts)];
    }
}
