<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Stream;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use ZBateson\MailMimeParser\Message\IMessagePart;

/**
 * Provides a readable stream for a MessagePart.
 *
 * @author Zaahid Bateson
 */
class MessagePartStreamDecorator implements StreamInterface
{
    use StreamDecoratorTrait {
        StreamDecoratorTrait::__construct as private traitConstruct;
        read as private decoratorRead;
    }

    protected ?StreamInterface $stream = null;

    protected IMessagePart $part;

    public function __construct(
        IMessagePart $part,
        ?StreamInterface $stream = null
    ) {
        $this->part = $part;
        if ($stream !== null) {
            $this->stream = $stream;
        }
    }

    /**
     * Returns the underlying stream, lazily creating it via createStream() if
     * not yet initialized.
     */
    protected function resolveStream() : StreamInterface
    {
        if ($this->stream === null) {
            $this->stream = $this->createStream();
        }
        return $this->stream;
    }

    public function __get(string $name) : StreamInterface
    {
        if ($name === 'stream') {
            return $this->resolveStream();
        }
        throw new \UnexpectedValueException("$name not found on class");
    }

    /**
     * Overridden to wrap exceptions in MessagePartReadException which provides
     * 'getPart' to inspect the part the error occurs on.
     *
     * @throws MessagePartStreamReadException
     */
    public function read(int $length) : string
    {
        try {
            return $this->resolveStream()->read($length);
        } catch (MessagePartStreamReadException $me) {
            throw $me;
        } catch (RuntimeException $e) {
            throw new MessagePartStreamReadException(
                $this->part,
                'Exception occurred reading a part stream: cid=' . $this->part->getContentId()
                . ' type=' . $this->part->getContentType() . ', message: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function close() : void
    {
        $this->resolveStream()->close();
    }

    /**
     * @return mixed
     */
    public function getMetadata($key = null)
    {
        return $this->resolveStream()->getMetadata($key);
    }

    public function detach()
    {
        return $this->resolveStream()->detach();
    }

    public function getSize() : ?int
    {
        return $this->resolveStream()->getSize();
    }

    public function eof() : bool
    {
        return $this->resolveStream()->eof();
    }

    public function tell() : int
    {
        return $this->resolveStream()->tell();
    }

    public function isReadable() : bool
    {
        return $this->resolveStream()->isReadable();
    }

    public function isWritable() : bool
    {
        return $this->resolveStream()->isWritable();
    }

    public function isSeekable() : bool
    {
        return $this->resolveStream()->isSeekable();
    }

    public function seek($offset, $whence = SEEK_SET) : void
    {
        $this->resolveStream()->seek($offset, $whence);
    }

    public function write($string) : int
    {
        return $this->resolveStream()->write($string);
    }

    public function rewind() : void
    {
        $this->seek(0);
    }

    public function __toString() : string
    {
        try {
            if ($this->isSeekable()) {
                $this->seek(0);
            }
            return $this->getContents();
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    public function getContents() : string
    {
        return \GuzzleHttp\Psr7\Utils::copyToString($this);
    }

    /**
     * @param array<mixed> $args
     * @return mixed
     */
    public function __call(string $method, array $args)
    {
        /** @var callable $callable */
        $callable = [$this->resolveStream(), $method];
        $result = ($callable)(...$args);
        return $result === $this->stream ? $this : $result;
    }
}
