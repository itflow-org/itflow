<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Parser\Proxy;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Message\IMimePart;
use ZBateson\MailMimeParser\Message\UUEncodedPart;
use ZBateson\MailMimeParser\Parser\IParserService;
use ZBateson\MailMimeParser\Parser\Part\ParserPartStreamContainerFactory;
use ZBateson\MailMimeParser\Parser\PartBuilder;
use ZBateson\MailMimeParser\Stream\StreamFactory;

/**
 * Responsible for creating proxied IUUEncodedPart instances wrapped in a
 * ParserUUEncodedPartProxy and used by NonMimeParser.
 *
 * @author Zaahid Bateson
 */
class ParserUUEncodedPartProxyFactory extends ParserPartProxyFactory
{
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly StreamFactory $streamFactory,
        protected readonly ParserPartStreamContainerFactory $parserPartStreamContainerFactory,
        protected readonly string $defaultFallbackCharset = 'ISO-8859-1'
    ) {
    }

    /**
     * Constructs a new ParserUUEncodedPartProxy wrapping an IUUEncoded object.
     */
    public function newInstance(PartBuilder $partBuilder, IParserService $parser) : ParserUUEncodedPartProxy
    {
        $parserProxy = new ParserUUEncodedPartProxy($partBuilder, $parser);
        $streamContainer = $this->parserPartStreamContainerFactory->newInstance($parserProxy);

        $parent = $partBuilder->getParent()?->getPart();
        \assert($parent === null || $parent instanceof IMimePart);
        $part = new UUEncodedPart(
            $parserProxy->getUnixFileMode(),
            $parserProxy->getFileName(),
            $parent,
            $this->logger,
            $streamContainer,
            $this->defaultFallbackCharset
        );
        $parserProxy->setPart($part);

        $streamContainer->setStream($this->streamFactory->newMessagePartStream($part));
        $part->attach($streamContainer);
        return $parserProxy;
    }
}
