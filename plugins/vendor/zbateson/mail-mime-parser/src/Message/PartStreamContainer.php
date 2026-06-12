<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Message;

use GuzzleHttp\Psr7\CachingStream;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use ZBateson\MailMimeParser\ErrorBag;
use ZBateson\MailMimeParser\Stream\MessagePartStreamDecorator;
use ZBateson\MailMimeParser\Stream\StreamFactory;
use ZBateson\MbWrapper\MbWrapper;
use ZBateson\MbWrapper\UnsupportedCharsetException;

/**
 * Holds the stream and content stream objects for a part.
 *
 * Note that streams are not explicitly closed or detached on destruction of the
 * PartSreamContainer by design: the passed StreamInterfaces will be closed on
 * their destruction when no references to them remain, which is useful when the
 * streams are passed around.
 *
 * In addition, all the streams passed to PartStreamContainer should be wrapping
 * a ZBateson\StreamDecorators\NonClosingStream unless attached to a part by a
 * user, this is because MMP uses a single seekable stream for content and wraps
 * it in ZBateson\StreamDecorators\SeekingLimitStream objects for each part.
 *
 * @author Zaahid Bateson
 */
class PartStreamContainer extends ErrorBag
{
    /**
     * @var MbWrapper to test charsets and see if they're supported.
     */
    protected MbWrapper $mbWrapper;

    /**
     * @var bool if false, reading from a content stream with an unsupported
     *      charset will be tried with the default charset, otherwise the stream
     *      created with the unsupported charset, and an exception will be
     *      thrown when read from.
     */
    protected bool $throwExceptionReadingPartContentFromUnsupportedCharsets;

    /**
     * @var StreamFactory used to apply psr7 stream decorators to the
     *      attached StreamInterface based on encoding.
     */
    protected StreamFactory $streamFactory;

    /**
     * @var MessagePartStreamDecorator stream containing the part's headers,
     *      content and children wrapped in a MessagePartStreamDecorator
     */
    protected MessagePartStreamDecorator $stream;

    /**
     * @var StreamInterface a stream containing this part's content
     */
    protected ?StreamInterface $contentStream = null;

    /**
     * @var StreamInterface the content stream after attaching transfer encoding
     *      streams to $contentStream.
     */
    protected ?StreamInterface $decodedStream = null;

    /**
     * @var StreamInterface attached charset stream to $decodedStream
     */
    protected ?StreamInterface $charsetStream = null;

    /**
     * @var bool true if the stream should be detached when this container is
     *      destroyed.
     */
    protected bool $detachParsedStream = false;

    /**
     * @var array<string, null> map of the active encoding filter on the current handle.
     */
    private array $encoding = [
        'type' => null,
        'filter' => null
    ];

    /**
     * @var array<string, null> map of the active charset filter on the current handle.
     */
    private array $charset = [
        'from' => null,
        'to' => null,
        'filter' => null
    ];

    public function __construct(
        LoggerInterface $logger,
        StreamFactory $streamFactory,
        MbWrapper $mbWrapper,
        bool $throwExceptionReadingPartContentFromUnsupportedCharsets
    ) {
        parent::__construct($logger);
        $this->streamFactory = $streamFactory;
        $this->mbWrapper = $mbWrapper;
        $this->throwExceptionReadingPartContentFromUnsupportedCharsets = $throwExceptionReadingPartContentFromUnsupportedCharsets;
    }

    /**
     * Sets the part's stream containing the part's headers, content, and
     * children.
     */
    public function setStream(MessagePartStreamDecorator $stream) : static
    {
        $this->stream = $stream;
        return $this;
    }

    /**
     * Returns the part's stream containing the part's headers, content, and
     * children.
     */
    public function getStream() : MessagePartStreamDecorator
    {
        // error out if called before setStream, getStream should never return
        // null.
        $this->stream->rewind();
        return $this->stream;
    }

    /**
     * Returns true if there's a content stream associated with the part.
     */
    public function hasContent() : bool
    {
        return ($this->contentStream !== null);
    }

    /**
     * Attaches the passed stream as the content portion of this
     * StreamContainer.
     *
     * The content stream would represent the content portion of $this->stream.
     *
     * If the content is overridden, $this->stream should point to a dynamic
     * {@see ZBateson\Stream\MessagePartStream} that dynamically creates the
     * RFC822 formatted message based on the IMessagePart this
     * PartStreamContainer belongs to.
     *
     * setContentStream can be called with 'null' to indicate the IMessagePart
     * does not contain any content.
     */
    public function setContentStream(?StreamInterface $contentStream = null) : static
    {
        $this->contentStream = $contentStream;
        $this->decodedStream = null;
        $this->charsetStream = null;
        return $this;
    }

    /**
     * Returns true if the attached stream filter used for decoding the content
     * on the current handle is different from the one passed as an argument.
     */
    private function isTransferEncodingFilterChanged(?string $transferEncoding) : bool
    {
        return ($transferEncoding !== $this->encoding['type']);
    }

    /**
     * Returns true if the attached stream filter used for charset conversion on
     * the current handle is different from the one needed based on the passed
     * arguments.
     *
     */
    private function isCharsetFilterChanged(string $fromCharset, string $toCharset) : bool
    {
        return ($fromCharset !== $this->charset['from']
            || $toCharset !== $this->charset['to']);
    }

    /**
     * Attaches a decoding filter to the attached content handle, for the passed
     * $transferEncoding.
     */
    protected function attachTransferEncodingFilter(?string $transferEncoding) : static
    {
        if ($this->decodedStream !== null) {
            $this->encoding['type'] = $transferEncoding;
            $this->decodedStream = new CachingStream($this->streamFactory->getTransferEncodingDecoratedStream(
                $this->decodedStream,
                $transferEncoding
            ));
        }
        return $this;
    }

    /**
     * Attaches a charset conversion filter to the attached content handle, for
     * the passed arguments.
     *
     * @param string $fromCharset the character set the content is encoded in
     * @param string $toCharset the target encoding to return
     */
    protected function attachCharsetFilter(string $fromCharset, string $toCharset) : static
    {
        if ($this->charsetStream !== null) {
            if (!$this->throwExceptionReadingPartContentFromUnsupportedCharsets) {
                try {
                    $this->mbWrapper->convert('t', $fromCharset, $toCharset);
                    $this->charsetStream = new CachingStream($this->streamFactory->newCharsetStream(
                        $this->charsetStream,
                        $fromCharset,
                        $toCharset
                    ));
                } catch (UnsupportedCharsetException $ex) {
                    $this->addError('Unsupported character set found', LogLevel::ERROR, $ex);
                    $this->charsetStream = new CachingStream($this->charsetStream);
                }
            } else {
                $this->charsetStream = new CachingStream($this->streamFactory->newCharsetStream(
                    $this->charsetStream,
                    $fromCharset,
                    $toCharset
                ));
            }
            $this->charsetStream->rewind();
            $this->charset['from'] = $fromCharset;
            $this->charset['to'] = $toCharset;
        }
        return $this;
    }

    /**
     * Resets just the charset stream, and rewinds the decodedStream.
     */
    private function resetCharsetStream() : static
    {
        $this->charset = [
            'from' => null,
            'to' => null,
            'filter' => null
        ];
        $this->decodedStream->rewind();
        $this->charsetStream = $this->decodedStream;
        return $this;
    }

    /**
     * Resets cached encoding and charset streams, and rewinds the stream.
     */
    public function reset() : static
    {
        $this->encoding = [
            'type' => null,
            'filter' => null
        ];
        $this->charset = [
            'from' => null,
            'to' => null,
            'filter' => null
        ];
        $this->contentStream->rewind();
        $this->decodedStream = $this->contentStream;
        $this->charsetStream = $this->contentStream;
        return $this;
    }

    /**
     * Checks what transfer-encoding decoder stream and charset conversion
     * stream are currently attached on the underlying contentStream, and resets
     * them if the requested arguments differ from the currently assigned ones.
     *
     * @param IMessagePart $part the part the stream belongs to
     * @param string $transferEncoding the transfer encoding
     * @param string $fromCharset the character set the content is encoded in
     * @param string $toCharset the target encoding to return
     */
    public function getContentStream(
        IMessagePart $part,
        ?string $transferEncoding,
        ?string $fromCharset,
        ?string $toCharset
    ) : ?MessagePartStreamDecorator {
        if ($this->contentStream === null) {
            return null;
        }
        if (empty($fromCharset) || empty($toCharset)) {
            return $this->getBinaryContentStream($part, $transferEncoding);
        }
        if ($this->charsetStream === null
            || $this->isTransferEncodingFilterChanged($transferEncoding)
            || $this->isCharsetFilterChanged($fromCharset, $toCharset)) {
            if ($this->charsetStream === null
                || $this->isTransferEncodingFilterChanged($transferEncoding)) {
                $this->reset();
                $this->attachTransferEncodingFilter($transferEncoding);
            }
            $this->resetCharsetStream();
            $this->attachCharsetFilter($fromCharset, $toCharset);
        }
        $this->charsetStream->rewind();
        return $this->streamFactory->newDecoratedMessagePartStream(
            $part,
            $this->charsetStream
        );
    }

    /**
     * Checks what transfer-encoding decoder stream is attached on the
     * underlying stream, and resets it if the requested arguments differ.
     */
    public function getBinaryContentStream(IMessagePart $part, ?string $transferEncoding = null) : ?MessagePartStreamDecorator
    {
        if ($this->contentStream === null) {
            return null;
        }
        if ($this->decodedStream === null
            || $this->isTransferEncodingFilterChanged($transferEncoding)) {
            $this->reset();
            $this->attachTransferEncodingFilter($transferEncoding);
        }
        $this->decodedStream->rewind();
        return $this->streamFactory->newDecoratedMessagePartStream($part, $this->decodedStream);
    }

    protected function getErrorBagChildren() : array
    {
        return [];
    }
}
