<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Message\Factory;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Message\PartStreamContainer;
use ZBateson\MailMimeParser\Stream\StreamFactory;
use ZBateson\MbWrapper\MbWrapper;

/**
 * Creates PartStreamContainer instances.
 *
 * @author Zaahid Bateson
 */
class PartStreamContainerFactory
{
    protected LoggerInterface $logger;

    protected StreamFactory $streamFactory;

    protected MbWrapper $mbWrapper;

    protected bool $throwExceptionReadingPartContentFromUnsupportedCharsets;

    public function __construct(
        LoggerInterface $logger,
        StreamFactory $streamFactory,
        MbWrapper $mbWrapper,
        bool $throwExceptionReadingPartContentFromUnsupportedCharsets
    ) {
        $this->logger = $logger;
        $this->streamFactory = $streamFactory;
        $this->mbWrapper = $mbWrapper;
        $this->throwExceptionReadingPartContentFromUnsupportedCharsets = $throwExceptionReadingPartContentFromUnsupportedCharsets;
    }

    public function newInstance() : PartStreamContainer
    {
        return new PartStreamContainer(
            $this->logger,
            $this->streamFactory,
            $this->mbWrapper,
            $this->throwExceptionReadingPartContentFromUnsupportedCharsets
        );
    }
}
