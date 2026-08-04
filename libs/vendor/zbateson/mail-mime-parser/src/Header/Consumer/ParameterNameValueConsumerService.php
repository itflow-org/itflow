<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\IHeaderPart;
use ZBateson\MailMimeParser\Header\Part\ContainerPart;
use ZBateson\MailMimeParser\Header\Part\MimeTokenPartFactory;

/**
 * Parses an individual part of a parameter header.
 *
 * 'isStartToken' always returns true, so control is taken from
 * ParameterConsumerService always, and returned when a ';' is encountered (and
 * so processes a single part and returns it, then gets control back).0
 *
 * If an '=' is encountered, the ParameterValueConsumerService sub-consumer
 * takes control and parses the value of a parameter.
 *
 * If no '=' is encountered, it's assumed to be a single value element, which
 * should be the first part of a parameter header, e.g. 'text/html' in
 * Content-Type: text/html; charset=utf-8
 *
 * @author Zaahid Bateson
 */
class ParameterNameValueConsumerService extends AbstractGenericConsumerService
{
    public function __construct(
        LoggerInterface $logger,
        MimeTokenPartFactory $partFactory,
        ParameterValueConsumerService $parameterValueConsumerService,
        CommentConsumerService $commentConsumerService,
        QuotedStringConsumerService $quotedStringConsumerService
    ) {
        parent::__construct(
            $logger,
            $partFactory,
            [$parameterValueConsumerService, $commentConsumerService, $quotedStringConsumerService]
        );
    }

    /**
     * Returns semi-colon as a token separator, in addition to parent token
     * separators.
     *
     * @return string[]
     */
    protected function getTokenSeparators() : array
    {
        return \array_merge(parent::getTokenSeparators(), [';']);
    }

    /**
     * Always returns true to grab control from its parent
     * ParameterConsumerService.
     */
    protected function isStartToken(string $token) : bool
    {
        return true;
    }

    /**
     * Returns true if the token is a ';' char.
     */
    protected function isEndToken(string $token) : bool
    {
        return ($token === ';');
    }

    /**
     * Creates either a ContainerPart if an '=' wasn't encountered, indicating
     * this to be the main 'value' part of a header (or a malformed part of a
     * parameter header), or a ParameterPart if the last IHeaderPart in the
     * passed $parts array is already a ContainerPart (indicating it was parsed
     * in ParameterValueConsumerService.)
     *
     * @param IHeaderPart[] $parts The parsed parts.
     * @return IHeaderPart[] Array of resulting final parts.
     */
    protected function processParts(array $parts) : array
    {
        $nameOnly = $parts;
        $valuePart = \array_pop($nameOnly);
        if (!($valuePart instanceof ContainerPart)) {
            return [$this->partFactory->newContainerPart($parts)];
        }
        return [$this->partFactory->newParameterPart(
            $nameOnly,
            $valuePart
        )];
    }
}
