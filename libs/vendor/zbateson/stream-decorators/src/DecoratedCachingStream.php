<?php
/**
 * This file is part of the ZBateson\StreamDecorators project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\StreamDecorators;

use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\BufferStream;
use GuzzleHttp\Psr7\CachingStream;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Utils;

/**
 * A version of Guzzle's CachingStream that will read bytes from one stream,
 * write them into another decorated stream, and read them back from a 3rd,
 * undecorated, buffered stream where the bytes are written to.
 *
 * A read operation is basically:
 *
 * Read from A, write to B (which decorates C), read and return from C (which is
 * backed by a BufferedStream).
 *
 * Note that the DecoratedCachingStream doesn't support write operations.
 */
class DecoratedCachingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /**
     *  @var StreamInterface the stream to read from and fill writeStream with
     */
    private StreamInterface $readStream;

    /**
     * @var StreamInterface the underlying undecorated stream to read from,
     *      where $writeStream is being written to
     */
    private StreamInterface $stream;

    /**
     * @var StreamInterface decorated $stream that will be written to for
     *      caching that wraps $stream.  Once filled, the stream is closed so it
     *      supports a Base64Stream which writes bytes at the end.
     */
    private ?StreamInterface $writeStream;

    /**
     * @var int Minimum buffer read length. At least this many bytes will be
     *      read and cached into $writeStream on each call to read from
     *      $readStream
     */
    private int $minBytesCache;

    /**
     * @param StreamInterface $stream Stream to cache. The cursor is assumed to
     *        be at the beginning of the stream.
     * @param callable(StreamInterface) : StreamInterface $decorator takes the
     *        passed StreamInterface and decorates it, and returns the decorated
     *        StreamInterface
     */
    public function __construct(
        StreamInterface $stream,
        callable $decorator,
        int $minBytesCache = 16384
    ) {
        $this->readStream = $stream;
        $bufferStream = new TellZeroStream(new BufferStream());
        $this->stream = new CachingStream($bufferStream);
        $this->writeStream = $decorator(new NonClosingStream($bufferStream));
        $this->minBytesCache = $minBytesCache;
    }

    public function getSize(): ?int
    {
        // the decorated stream could be a different size
        $this->cacheEntireStream();
        return $this->stream->getSize();
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        if ($whence === SEEK_SET) {
            $byte = $offset;
        } elseif ($whence === SEEK_CUR) {
            $byte = $offset + $this->tell();
        } elseif ($whence === SEEK_END) {
            $size = $this->getSize();
            $byte = $size + $offset;
        } else {
            throw new \InvalidArgumentException('Invalid whence');
        }

        $diff = $byte - $this->stream->getSize();

        if ($diff > 0) {
            // Read the remoteStream until we have read in at least the amount
            // of bytes requested, or we reach the end of the file.
            while ($diff > 0 && !$this->readStream->eof()) {
                $this->read($diff);
                $diff = $byte - $this->stream->getSize();
            }
        } else {
            // We can just do a normal seek since we've already seen this byte.
            $this->stream->seek($byte);
        }
    }

    private function cacheBytes(int $size) : void {
        if (!$this->readStream->eof()) {
            $data = $this->readStream->read(max($this->minBytesCache, $size));
            $this->writeStream->write($data);
            if ($this->readStream->eof()) {
                // needed because Base64Stream writes bytes on closing
                $this->writeStream->close();
                $this->writeStream = null;
            }
        }
    }

    public function read($length): string
    {
        $data = $this->stream->read($length);
        $remaining = $length - strlen($data);
        if ($remaining > 0) {
            $this->cacheBytes($remaining);
            $data .= $this->stream->read($remaining);
        }
        return $data;
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write($string): int
    {
        throw new \RuntimeException('Cannot write to a DecoratedCachingStream');
    }

    public function eof(): bool
    {
        return $this->stream->eof() && $this->readStream->eof();
    }

    /**
     * Close both the remote stream and buffer stream
     */
    public function close(): void
    {
        $this->readStream->close();
        $this->stream->close();
        if ($this->writeStream !== null) {
            $this->writeStream->close();
        }
    }

    private function cacheEntireStream(): int
    {
        // as-is from CachingStream
        $target = new FnStream(['write' => 'strlen']);
        Utils::copyToStream($this, $target);

        return $this->tell();
    }
}