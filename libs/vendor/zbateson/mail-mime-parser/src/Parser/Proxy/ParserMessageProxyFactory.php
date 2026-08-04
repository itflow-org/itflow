<?php

/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Parser\Proxy;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Message;
use ZBateson\MailMimeParser\Message\Factory\PartHeaderContainerFactory;
use ZBateson\MailMimeParser\Message\Helper\MultipartHelper;
use ZBateson\MailMimeParser\Message\Helper\PrivacyHelper;
use ZBateson\MailMimeParser\Parser\IParserService;
use ZBateson\MailMimeParser\Parser\Part\ParserPartChildrenContainerFactory;
use ZBateson\MailMimeParser\Parser\Part\ParserPartStreamContainerFactory;
use ZBateson\MailMimeParser\Parser\PartBuilder;
use ZBateson\MailMimeParser\Stream\StreamFactory;

/**
 * Responsible for creating proxied IMessage instances wrapped in a
 * ParserMessageProxy.
 *
 * @author Zaahid Bateson
 */
class ParserMessageProxyFactory extends ParserMimePartProxyFactory
{
    public function __construct(
        LoggerInterface $logger,
        StreamFactory $streamFactory,
        PartHeaderContainerFactory $partHeaderContainerFactory,
        ParserPartStreamContainerFactory $parserPartStreamContainerFactory,
        ParserPartChildrenContainerFactory $parserPartChildrenContainerFactory,
        protected readonly MultipartHelper $multipartHelper,
        protected readonly PrivacyHelper $privacyHelper,
        string $defaultFallbackCharset = 'ISO-8859-1'
    ) {
        parent::__construct($logger, $streamFactory, $partHeaderContainerFactory, $parserPartStreamContainerFactory, $parserPartChildrenContainerFactory, $defaultFallbackCharset);
    }

    /**
     * Constructs a new ParserMessageProxy wrapping an IMessage object that will
     * dynamically parse a message's content and parts as they're requested.
     */
    public function newInstance(PartBuilder $partBuilder, IParserService $parser) : ParserMessageProxy
    {
        $parserProxy = new ParserMessageProxy($partBuilder, $parser);

        $streamContainer = $this->parserPartStreamContainerFactory->newInstance($parserProxy);
        $headerContainer = $this->partHeaderContainerFactory->newInstance($parserProxy->getHeaderContainer());
        $childrenContainer = $this->parserPartChildrenContainerFactory->newInstance($parserProxy);

        $message = new Message(
            $this->logger,
            $streamContainer,
            $headerContainer,
            $childrenContainer,
            $this->multipartHelper,
            $this->privacyHelper,
            $this->defaultFallbackCharset
        );
        $parserProxy->setPart($message);

        $streamContainer->setStream($this->streamFactory->newMessagePartStream($message));
        $message->attach($streamContainer);
        return $parserProxy;
    }
}
