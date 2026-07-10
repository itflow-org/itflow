<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Parser\Part;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Parser\Proxy\ParserPartProxy;
use ZBateson\MailMimeParser\Stream\StreamFactory;
use ZBateson\MbWrapper\MbWrapper;

/**
 * Creates ParserPartStreamContainer instances.
 *
 * @author Zaahid Bateson
 */
class ParserPartStreamContainerFactory
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

    public function newInstance(ParserPartProxy $parserProxy) : ParserPartStreamContainer
    {
        return new ParserPartStreamContainer(
            $this->logger,
            $this->streamFactory,
            $this->mbWrapper,
            $this->throwExceptionReadingPartContentFromUnsupportedCharsets,
            $parserProxy
        );
    }
}
