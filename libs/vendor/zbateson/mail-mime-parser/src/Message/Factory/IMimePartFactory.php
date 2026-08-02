<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Message\Factory;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Message\IMimePart;
use ZBateson\MailMimeParser\Message\MimePart;
use ZBateson\MailMimeParser\Stream\StreamFactory;

/**
 * Responsible for creating IMimePart instances.
 *
 * @author Zaahid Bateson
 */
class IMimePartFactory extends IMessagePartFactory
{
    public function __construct(
        LoggerInterface $logger,
        StreamFactory $streamFactory,
        PartStreamContainerFactory $partStreamContainerFactory,
        protected readonly PartHeaderContainerFactory $partHeaderContainerFactory,
        protected readonly PartChildrenContainerFactory $partChildrenContainerFactory,
        string $defaultFallbackCharset = 'ISO-8859-1'
    ) {
        parent::__construct($logger, $streamFactory, $partStreamContainerFactory, $defaultFallbackCharset);
    }

    /**
     * Constructs a new IMimePart object and returns it
     */
    public function newInstance(?IMimePart $parent = null) : IMimePart
    {
        $streamContainer = $this->partStreamContainerFactory->newInstance();
        $headerContainer = $this->partHeaderContainerFactory->newInstance();
        $part = new MimePart(
            $parent,
            $this->logger,
            $streamContainer,
            $headerContainer,
            $this->partChildrenContainerFactory->newInstance(),
            $this->defaultFallbackCharset
        );
        $streamContainer->setStream($this->streamFactory->newMessagePartStream($part));
        return $part;
    }
}
