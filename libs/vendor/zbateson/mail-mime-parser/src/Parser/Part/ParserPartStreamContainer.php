<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Parser\Part;

use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use SplObserver;
use SplSubject;
use ZBateson\MailMimeParser\Message\IMessagePart;
use ZBateson\MailMimeParser\Message\PartStreamContainer;
use ZBateson\MailMimeParser\Parser\Proxy\ParserPartProxy;
use ZBateson\MailMimeParser\Stream\MessagePartStreamDecorator;
use ZBateson\MailMimeParser\Stream\StreamFactory;
use ZBateson\MbWrapper\MbWrapper;

/**
 * A part stream container that proxies requests for content streams to a parser
 * to read the content.
 *
 * Keeps reference to the original stream a part was parsed from, using that
 * stream as the part's stream instead of the PartStreamContainer's
 * MessagePartStream (which dynamically creates a stream from an IMessagePart)
 * unless the part changed.
 *
 * The ParserPartStreamContainer must also be attached to its underlying part
 * with SplSubject::attach() so the ParserPartStreamContainer gets notified of
 * any changes.
 *
 * @author Zaahid Bateson
 */
class ParserPartStreamContainer extends PartStreamContainer implements SplObserver
{
    /**
     * @var ParserPartProxy The parser proxy to ferry requests to on-demand.
     */
    protected ParserPartProxy $parserProxy;

    /**
     * @var MessagePartStreamDecorator the original stream for a parsed message,
     *      wrapped in a MessagePartStreamDecorator, and used when the message
     *      hasn't changed
     */
    protected ?MessagePartStreamDecorator $parsedStream = null;

    /**
     * @var bool set to true if the part's been updated since it was created.
     */
    protected bool $partUpdated = false;

    /**
     * @var bool false if the content for the part represented by this container
     *      has not yet been requested from the parser.
     */
    protected bool $contentParseRequested = false;

    public function __construct(
        LoggerInterface $logger,
        StreamFactory $streamFactory,
        MbWrapper $mbWrapper,
        bool $throwExceptionReadingPartContentFromUnsupportedCharsets,
        ParserPartProxy $parserProxy
    ) {
        parent::__construct($logger, $streamFactory, $mbWrapper, $throwExceptionReadingPartContentFromUnsupportedCharsets);
        $this->parserProxy = $parserProxy;
    }

    public function __destruct()
    {
        if ($this->detachParsedStream && $this->parsedStream !== null) {
            $this->parsedStream->detach();
        }
    }

    /**
     * Requests content from the parser if not previously requested, and calls
     * PartStreamContainer::setContentStream().
     */
    protected function requestParsedContentStream() : static
    {
        if (!$this->contentParseRequested) {
            $this->contentParseRequested = true;
            $this->parserProxy->parseContent();
            parent::setContentStream($this->streamFactory->getLimitedContentStream(
                $this->parserProxy
            ));
        }
        return $this;
    }

    /**
     * Ensures the parser has parsed the entire part, and sets
     * $this->parsedStream to the original parsed stream (or a limited part of
     * it corresponding to the current part this stream container belongs to).
     */
    protected function requestParsedStream() : static
    {
        if ($this->parsedStream === null) {
            $this->parserProxy->parseAll();
            $this->parsedStream = $this->streamFactory->newDecoratedMessagePartStream(
                $this->parserProxy->getPart(),
                $this->streamFactory->getLimitedPartStream(
                    $this->parserProxy
                )
            );
            if ($this->parsedStream !== null) {
                $this->detachParsedStream = ($this->parsedStream->getMetadata('mmp-detached-stream') === true);
            }
        }
        return $this;
    }

    public function hasContent() : bool
    {
        $this->requestParsedContentStream();
        return parent::hasContent();
    }

    public function getContentStream(IMessagePart $part, ?string $transferEncoding, ?string $fromCharset, ?string $toCharset) : ?MessagePartStreamDecorator
    {
        $this->requestParsedContentStream();
        return parent::getContentStream($part, $transferEncoding, $fromCharset, $toCharset);
    }

    public function getBinaryContentStream(IMessagePart $part, ?string $transferEncoding = null) : ?MessagePartStreamDecorator
    {
        $this->requestParsedContentStream();
        return parent::getBinaryContentStream($part, $transferEncoding);
    }

    public function setContentStream(?StreamInterface $contentStream = null) : static
    {
        // has to be overridden because requestParsedContentStream calls
        // parent::setContentStream as well, so needs to be parsed before
        // overriding the contentStream with a manual 'set'.
        $this->requestParsedContentStream();
        parent::setContentStream($contentStream);
        return $this;
    }

    public function getStream() : MessagePartStreamDecorator
    {
        $this->requestParsedStream();
        if (!$this->partUpdated) {
            if ($this->parsedStream !== null) {
                $this->parsedStream->rewind();
                return $this->parsedStream;
            }
        }
        return parent::getStream();
    }

    public function update(SplSubject $subject) : void
    {
        $this->partUpdated = true;
    }
}
