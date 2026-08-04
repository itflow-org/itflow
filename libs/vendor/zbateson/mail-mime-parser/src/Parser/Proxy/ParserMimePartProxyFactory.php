<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Parser\Proxy;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Message\Factory\PartHeaderContainerFactory;
use ZBateson\MailMimeParser\Message\IMimePart;
use ZBateson\MailMimeParser\Message\MimePart;
use ZBateson\MailMimeParser\Parser\IParserService;
use ZBateson\MailMimeParser\Parser\Part\ParserPartChildrenContainerFactory;
use ZBateson\MailMimeParser\Parser\Part\ParserPartStreamContainerFactory;
use ZBateson\MailMimeParser\Parser\PartBuilder;
use ZBateson\MailMimeParser\Stream\StreamFactory;

/**
 * Responsible for creating proxied IMimePart instances wrapped in a
 * ParserMimePartProxy with a MimeParser.
 *
 * @author Zaahid Bateson
 */
class ParserMimePartProxyFactory extends ParserPartProxyFactory
{
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly StreamFactory $streamFactory,
        protected readonly PartHeaderContainerFactory $partHeaderContainerFactory,
        protected readonly ParserPartStreamContainerFactory $parserPartStreamContainerFactory,
        protected readonly ParserPartChildrenContainerFactory $parserPartChildrenContainerFactory,
        protected readonly string $defaultFallbackCharset = 'ISO-8859-1'
    ) {
    }

    /**
     * Constructs a new ParserMimePartProxy wrapping an IMimePart object that
     * will dynamically parse a message's content and parts as they're
     * requested.
     */
    public function newInstance(PartBuilder $partBuilder, IParserService $parser) : ParserMimePartProxy
    {
        $parserProxy = new ParserMimePartProxy($partBuilder, $parser);

        $streamContainer = $this->parserPartStreamContainerFactory->newInstance($parserProxy);
        $headerContainer = $this->partHeaderContainerFactory->newInstance($parserProxy->getHeaderContainer());
        $childrenContainer = $this->parserPartChildrenContainerFactory->newInstance($parserProxy);

        $parent = $partBuilder->getParent()?->getPart();
        \assert($parent === null || $parent instanceof IMimePart);
        $part = new MimePart(
            $parent,
            $this->logger,
            $streamContainer,
            $headerContainer,
            $childrenContainer,
            $this->defaultFallbackCharset
        );
        $parserProxy->setPart($part);

        $streamContainer->setStream($this->streamFactory->newMessagePartStream($part));
        $part->attach($streamContainer);
        return $parserProxy;
    }
}
