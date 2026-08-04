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
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly StreamFactory $streamFactory,
        protected readonly MbWrapper $mbWrapper,
        protected readonly bool $throwExceptionReadingPartContentFromUnsupportedCharsets
    ) {
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
