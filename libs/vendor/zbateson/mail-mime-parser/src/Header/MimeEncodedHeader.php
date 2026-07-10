<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\Consumer\IConsumerService;
use ZBateson\MailMimeParser\Header\Part\MimeToken;
use ZBateson\MailMimeParser\Header\Part\MimeTokenPartFactory;

/**
 * Allows a header to be mime-encoded and be decoded with a consumer after
 * decoding.
 *
 * @author Zaahid Bateson
 */
abstract class MimeEncodedHeader extends AbstractHeader
{
    /**
     * @var MimeTokenPartFactory for mime decoding.
     */
    protected MimeTokenPartFactory $mimeTokenPartFactory;

    /**
     * @var MimeLiteralPart[] the mime encoded parsed parts contained in this
     *      header
     */
    protected $mimeEncodedParsedParts = [];

    public function __construct(
        LoggerInterface $logger,
        MimeTokenPartFactory $mimeTokenPartFactory,
        IConsumerService $consumerService,
        string $name,
        string $value
    ) {
        $this->mimeTokenPartFactory = $mimeTokenPartFactory;
        parent::__construct($logger, $consumerService, $name, $value);
    }

    /**
     * Mime-decodes any mime-encoded parts prior to invoking
     * parent::parseHeaderValue.
     */
    protected function parseHeaderValue(IConsumerService $consumer, string $value) : void
    {
        // handled differently from MimeLiteralPart's decoding which ignores
        // whitespace between parts, etc...
        $matchp = '~(' . MimeToken::MIME_PART_PATTERN . ')~';
        $aMimeParts = \preg_split($matchp, $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $this->mimeEncodedParsedParts = \array_map([$this->mimeTokenPartFactory, 'newInstance'], $aMimeParts);
        parent::parseHeaderValue(
            $consumer,
            \implode('', \array_map(fn ($part) => $part->getValue(), $this->mimeEncodedParsedParts))
        );
    }

    protected function getErrorBagChildren() : array
    {
        return \array_values(\array_filter(\array_merge($this->getAllParts(), $this->mimeEncodedParsedParts)));
    }
}
