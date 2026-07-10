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
    protected PartHeaderContainerFactory $partHeaderContainerFactory;

    protected PartChildrenContainerFactory $partChildrenContainerFactory;

    public function __construct(
        LoggerInterface $logger,
        StreamFactory $streamFactory,
        PartStreamContainerFactory $partStreamContainerFactory,
        PartHeaderContainerFactory $partHeaderContainerFactory,
        PartChildrenContainerFactory $partChildrenContainerFactory
    ) {
        parent::__construct($logger, $streamFactory, $partStreamContainerFactory);
        $this->partHeaderContainerFactory = $partHeaderContainerFactory;
        $this->partChildrenContainerFactory = $partChildrenContainerFactory;
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
            $this->partChildrenContainerFactory->newInstance()
        );
        $streamContainer->setStream($this->streamFactory->newMessagePartStream($part));
        return $part;
    }
}
