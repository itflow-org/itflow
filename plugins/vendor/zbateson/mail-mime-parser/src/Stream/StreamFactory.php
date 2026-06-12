<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Stream;

use Psr\Http\Message\StreamInterface;
use ZBateson\MailMimeParser\Message\IMessagePart;
use ZBateson\MailMimeParser\Parser\PartBuilder;
use ZBateson\StreamDecorators\Base64Stream;
use ZBateson\StreamDecorators\CharsetStream;
use ZBateson\StreamDecorators\ChunkSplitStream;
use ZBateson\StreamDecorators\DecoratedCachingStream;
use ZBateson\StreamDecorators\NonClosingStream;
use ZBateson\StreamDecorators\PregReplaceFilterStream;
use ZBateson\StreamDecorators\QuotedPrintableStream;
use ZBateson\StreamDecorators\SeekingLimitStream;
use ZBateson\StreamDecorators\UUStream;

/**
 * Factory class for Psr7 stream decorators used in MailMimeParser.
 *
 * @author Zaahid Bateson
 */
class StreamFactory
{
    /**
     * @var bool if true, saving a content stream with an unsupported charset
     *      will be written in the default charset.
     */
    protected bool $throwExceptionReadingPartContentFromUnsupportedCharsets;

    public function __construct(bool $throwExceptionReadingPartContentFromUnsupportedCharsets)
    {
        $this->throwExceptionReadingPartContentFromUnsupportedCharsets = $throwExceptionReadingPartContentFromUnsupportedCharsets;
    }

    /**
     * Returns a SeekingLimitStream using $part->getStreamPartLength() and
     * $part->getStreamPartStartPos()
     */
    public function getLimitedPartStream(PartBuilder $part) : StreamInterface
    {
        return $this->newLimitStream(
            $part->getStream(),
            $part->getStreamPartLength(),
            $part->getStreamPartStartPos()
        );
    }

    /**
     * Returns a SeekingLimitStream using $part->getStreamContentLength() and
     * $part->getStreamContentStartPos()
     */
    public function getLimitedContentStream(PartBuilder $part) : ?StreamInterface
    {
        $length = $part->getStreamContentLength();
        if ($length !== 0) {
            return $this->newLimitStream(
                $part->getStream(),
                $part->getStreamContentLength(),
                $part->getStreamContentStartPos()
            );
        }
        return null;
    }

    /**
     * Creates and returns a SeekingLimitedStream.
     */
    private function newLimitStream(StreamInterface $stream, int $length, int $start) : StreamInterface
    {
        return new SeekingLimitStream(
            $this->newNonClosingStream($stream),
            $length,
            $start
        );
    }

    /**
     * Creates and returns a SeekingLimitedStream without limits, so it's a
     * stream that preserves its current position on the underlying stream it
     * reads from.
     */
    public function newSeekingStream(StreamInterface $stream) : StreamInterface
    {
        return new SeekingLimitStream($this->newNonClosingStream($stream));
    }

    /**
     * Creates a non-closing stream that doesn't close it's internal stream when
     * closing/detaching.
     */
    public function newNonClosingStream(StreamInterface $stream) : StreamInterface
    {
        return new NonClosingStream($stream);
    }

    /**
     * Creates a ChunkSplitStream.
     */
    public function newChunkSplitStream(StreamInterface $stream) : StreamInterface
    {
        return new ChunkSplitStream($stream);
    }

    /**
     * Creates and returns a Base64Stream with an internal
     * PregReplaceFilterStream that filters out non-base64 characters.
     */
    public function newBase64Stream(StreamInterface $stream) : StreamInterface
    {
        return new Base64Stream(
            new PregReplaceFilterStream($stream, '/[^a-zA-Z0-9\/\+=]/', '')
        );
    }

    /**
     * Creates and returns a QuotedPrintableStream.
     */
    public function newQuotedPrintableStream(StreamInterface $stream) : StreamInterface
    {
        return new QuotedPrintableStream($stream);
    }

    /**
     * Creates and returns a UUStream
     */
    public function newUUStream(StreamInterface $stream) : StreamInterface
    {
        return new UUStream($stream);
    }

    public function getTransferEncodingDecoratedStream(StreamInterface $stream, ?string $transferEncoding, ?string $filename = null) : StreamInterface
    {
        $decorated = null;
        switch ($transferEncoding) {
            case 'quoted-printable':
                $decorated = $this->newQuotedPrintableStream($stream);
                break;
            case 'base64':
                $decorated = $this->newBase64Stream(
                    $this->newChunkSplitStream($stream)
                );
                break;
            case 'x-uuencode':
                $decorated = $this->newUUStream($stream);
                if ($filename !== null) {
                    $decorated->setFilename($filename);
                }
                break;
            default:
                return $stream;
        }
        return $decorated;
    }

    /**
     * Creates and returns a CharsetStream
     */
    public function newCharsetStream(StreamInterface $stream, string $streamCharset, string $stringCharset) : StreamInterface
    {
        return new CharsetStream($stream, $streamCharset, $stringCharset);
    }

    /**
     * Creates and returns a MessagePartStream
     */
    public function newMessagePartStream(IMessagePart $part) : MessagePartStreamDecorator
    {
        return new MessagePartStream($this, $part, $this->throwExceptionReadingPartContentFromUnsupportedCharsets);
    }

    /**
     * Creates and returns a DecoratedCachingStream
     */
    public function newDecoratedCachingStream(StreamInterface $stream, callable $decorator) : StreamInterface
    {
        // seems to perform best locally, would be good to test this out more
        return new DecoratedCachingStream($stream, $decorator, 204800);
    }

    /**
     * Creates and returns a HeaderStream
     */
    public function newHeaderStream(IMessagePart $part) : StreamInterface
    {
        return new HeaderStream($part);
    }

    public function newDecoratedMessagePartStream(IMessagePart $part, StreamInterface $stream) : MessagePartStreamDecorator
    {
        return new MessagePartStreamDecorator($part, $stream);
    }
}
