<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\Part\MimeTokenPartFactory;

/**
 * Starts processing tokens after a '=' character is found, indicating the
 * 'value' portion of a name/value pair in a parameter header.
 *
 * The value portion will consist of all tokens, quoted parts, and comment parts
 * parsed up to a semi-colon token indicating control should be returned to the
 * parent ParameterNameValueConsumerService.
 *
 * @author Zaahid Bateson
 */
class ParameterValueConsumerService extends GenericConsumerMimeLiteralPartService
{
    public function __construct(
        LoggerInterface $logger,
        MimeTokenPartFactory $partFactory,
        CommentConsumerService $commentConsumerService,
        QuotedStringMimeLiteralPartConsumerService $quotedStringConsumerService
    ) {
        parent::__construct(
            $logger,
            $partFactory,
            $commentConsumerService,
            $quotedStringConsumerService
        );
    }

    /**
     * Returns semi-colon and equals char as token separators.
     *
     * @return string[]
     */
    protected function getTokenSeparators() : array
    {
        return \array_merge(parent::getTokenSeparators(), ['=', ';']);
    }

    /**
     * Returns true if the token is an '=' character.
     */
    protected function isStartToken(string $token) : bool
    {
        return ($token === '=');
    }

    /**
     * Returns true if the token is a ';' character.
     */
    protected function isEndToken(string $token) : bool
    {
        return ($token === ';');
    }
}
