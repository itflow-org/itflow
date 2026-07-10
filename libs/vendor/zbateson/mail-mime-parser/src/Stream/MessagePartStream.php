<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Stream;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\AppendStream;
use Psr\Http\Message\StreamInterface;
use SplObserver;
use SplSubject;
use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message\IMessagePart;
use ZBateson\MailMimeParser\Message\IMimePart;
use ZBateson\MbWrapper\UnsupportedCharsetException;

/**
 * Provides a readable stream for a MessagePart.
 *
 * @author Zaahid Bateson
 */
class MessagePartStream extends MessagePartStreamDecorator implements SplObserver, StreamInterface
{
    /**
     * @var StreamFactory For creating needed stream decorators.
     */
    protected StreamFactory $streamFactory;

    /**
     * @var IMessagePart The part to read from.
     */
    protected IMessagePart $part;

    /**
     * @var bool if false, saving a content stream with an unsupported charset
     *      will be written in the default charset, otherwise the stream will be
     *      created with the unsupported charset, and an exception will be
     *      thrown when read from.
     */
    protected bool $throwExceptionReadingPartContentFromUnsupportedCharsets;

    /**
     * @var ?AppendStream
     */
    protected ?AppendStream $appendStream = null;

    public function __construct(StreamFactory $sdf, IMessagePart $part, bool $throwExceptionReadingPartContentFromUnsupportedCharsets)
    {
        parent::__construct($part);
        $this->streamFactory = $sdf;
        $this->part = $part;
        $this->throwExceptionReadingPartContentFromUnsupportedCharsets = $throwExceptionReadingPartContentFromUnsupportedCharsets;
        $part->attach($this);

        // unsetting the property forces the first access to go through
        // __get().
        unset($this->stream);
    }

    public function __destruct()
    {
        $this->part->detach($this);
    }

    public function update(SplSubject $subject) : void
    {
        if ($this->appendStream !== null) {
            // unset forces recreation in StreamDecoratorTrait with a call to __get
            unset($this->stream);
            $this->appendStream = null;
        }
    }

    /**
     * Attaches and returns a CharsetStream decorator to the passed $stream.
     *
     * If the current attached IMessagePart doesn't specify a charset, $stream
     * is returned as-is.
     */
    private function getCharsetDecoratorForStream(StreamInterface $stream) : StreamInterface
    {
        $charset = $this->part->getCharset();
        if (!empty($charset)) {
            if (!$this->throwExceptionReadingPartContentFromUnsupportedCharsets) {
                $test = $this->streamFactory->newCharsetStream(
                    Psr7\Utils::streamFor(),
                    $charset,
                    MailMimeParser::DEFAULT_CHARSET
                );
                try {
                    $test->write('t');
                } catch (UnsupportedCharsetException $e) {
                    return $stream;
                } finally {
                    $test->close();
                }
            }
            $stream = $this->streamFactory->newCharsetStream(
                $stream,
                $charset,
                MailMimeParser::DEFAULT_CHARSET
            );
        }
        return $stream;
    }

    /**
     * Creates an array of streams based on the attached part's mime boundary
     * and child streams.
     *
     * @param IMimePart $part passed in because $this->part is declared
     *        as IMessagePart
     * @return StreamInterface[]
     */
    protected function getBoundaryAndChildStreams(IMimePart $part) : array
    {
        $boundary = $part->getHeaderParameter(HeaderConsts::CONTENT_TYPE, 'boundary');
        if ($boundary === null) {
            return \array_map(
                function($child) {
                    return $child->getStream();
                },
                $part->getChildParts()
            );
        }
        $streams = [];
        foreach ($part->getChildParts() as $i => $child) {
            if ($i !== 0 || $part->hasContent()) {
                $streams[] = Psr7\Utils::streamFor("\r\n");
            }
            $streams[] = Psr7\Utils::streamFor("--$boundary\r\n");
            $streams[] = $child->getStream();
        }
        $streams[] = Psr7\Utils::streamFor("\r\n--$boundary--\r\n");

        return $streams;
    }

    /**
     * Returns an array of Psr7 Streams representing the attached part and it's
     * direct children.
     *
     * @return StreamInterface[]
     */
    protected function getStreamsArray() : array
    {
        $contentStream = $this->part->getContentStream();
        if ($contentStream !== null) {
            // wrapping in a SeekingLimitStream because the underlying
            // ContentStream could be rewound, etc...
            $contentStream = $this->streamFactory->newDecoratedCachingStream(
                $this->streamFactory->newSeekingStream($contentStream),
                function($stream) {
                    $es = $this->streamFactory->getTransferEncodingDecoratedStream(
                        $stream,
                        $this->part->getContentTransferEncoding(),
                        $this->part->getFilename()
                    );
                    $cs = $this->getCharsetDecoratorForStream($es);
                    return $cs;
                }
            );
        }

        $streams = [$this->streamFactory->newHeaderStream($this->part), $contentStream ?: Psr7\Utils::streamFor()];

        if ($this->part instanceof IMimePart && $this->part->getChildCount() > 0) {
            $streams = \array_merge($streams, $this->getBoundaryAndChildStreams($this->part));
        }

        return $streams;
    }

    /**
     * Creates the underlying stream lazily when required.
     */
    protected function createStream() : StreamInterface
    {
        if ($this->appendStream === null) {
            $this->appendStream = new AppendStream($this->getStreamsArray());
        }
        return $this->appendStream;
    }
}
