<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Message;

use GuzzleHttp\Psr7\StreamWrapper;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use SplObjectStorage;
use SplObserver;
use ZBateson\MailMimeParser\ErrorBag;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Stream\MessagePartStreamDecorator;

/**
 * Most basic representation of a single part of an email.
 *
 * @author Zaahid Bateson
 */
abstract class MessagePart extends ErrorBag implements IMessagePart
{
    /**
     * @var ?IMimePart parent part
     */
    protected ?IMimePart $parent;

    /**
     * @var PartStreamContainer holds 'stream' and 'content stream'.
     */
    protected PartStreamContainer $partStreamContainer;

    /**
     * @var ?string can be used to set an override for content's charset in cases
     *      where a user knows the charset on the content is not what it claims
     *      to be.
     */
    protected ?string $charsetOverride = null;

    /**
     * @var bool set to true when a user attaches a stream manually, it's
     *      assumed to already be decoded or to have relevant transfer encoding
     *      decorators attached already.
     */
    protected bool $ignoreTransferEncoding = false;

    /**
     * @var SplObjectStorage attached observers that need to be notified of
     *      modifications to this part.
     */
    protected SplObjectStorage $observers;

    public function __construct(
        LoggerInterface $logger,
        PartStreamContainer $streamContainer,
        ?IMimePart $parent = null
    ) {
        parent::__construct($logger);
        $this->partStreamContainer = $streamContainer;
        $this->parent = $parent;
        $this->observers = new SplObjectStorage();
    }

    public function attach(SplObserver $observer) : void
    {
        $this->observers->offsetSet($observer);
    }

    public function detach(SplObserver $observer) : void
    {
        $this->observers->offsetUnset($observer);
    }

    public function notify() : void
    {
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
        if ($this->parent !== null) {
            $this->parent->notify();
        }
    }

    public function getParent() : ?IMimePart
    {
        return $this->parent;
    }

    public function hasContent() : bool
    {
        return $this->partStreamContainer->hasContent();
    }

    public function getFilename() : ?string
    {
        return null;
    }

    public function setCharsetOverride(string $charsetOverride, bool $onlyIfNoCharset = false) : static
    {
        if (!$onlyIfNoCharset || $this->getCharset() === null) {
            $this->charsetOverride = $charsetOverride;
        }
        return $this;
    }

    public function getContentStream(string $charset = MailMimeParser::DEFAULT_CHARSET) : ?MessagePartStreamDecorator
    {
        if ($this->hasContent()) {
            $tr = ($this->ignoreTransferEncoding) ? '' : $this->getContentTransferEncoding();
            $ch = $this->charsetOverride ?? $this->getCharset();
            return $this->partStreamContainer->getContentStream(
                $this,
                $tr,
                $ch,
                $charset
            );
        }
        return null;
    }

    public function getBinaryContentStream() : ?MessagePartStreamDecorator
    {
        if ($this->hasContent()) {
            $tr = ($this->ignoreTransferEncoding) ? '' : $this->getContentTransferEncoding();
            return $this->partStreamContainer->getBinaryContentStream($this, $tr);
        }
        return null;
    }

    public function getBinaryContentResourceHandle() : mixed
    {
        $stream = $this->getBinaryContentStream();
        if ($stream !== null) {
            return StreamWrapper::getResource($stream);
        }
        return null;
    }

    public function saveContent($filenameResourceOrStream) : static
    {
        $resourceOrStream = $filenameResourceOrStream;
        if (\is_string($filenameResourceOrStream)) {
            $resourceOrStream = \fopen($filenameResourceOrStream, 'w+');
        }

        $stream = Utils::streamFor($resourceOrStream);
        Utils::copyToStream($this->getBinaryContentStream(), $stream);

        if (!\is_string($filenameResourceOrStream)
            && !($filenameResourceOrStream instanceof StreamInterface)) {
            // only detach if it wasn't a string or StreamInterface, so the
            // fopen call can be properly closed if it was
            $stream->detach();
        }
        return $this;
    }

    public function getContent(string $charset = MailMimeParser::DEFAULT_CHARSET) : ?string
    {
        $stream = $this->getContentStream($charset);
        if ($stream !== null) {
            return $stream->getContents();
        }
        return null;
    }

    public function attachContentStream(StreamInterface $stream, string $streamCharset = MailMimeParser::DEFAULT_CHARSET) : static
    {
        $ch = $this->charsetOverride ?? $this->getCharset();
        if ($ch !== null && $streamCharset !== $ch) {
            $this->charsetOverride = $streamCharset;
        }
        $this->ignoreTransferEncoding = true;
        $this->partStreamContainer->setContentStream($stream);
        $this->notify();
        return $this;
    }

    public function detachContentStream() : static
    {
        $this->partStreamContainer->setContentStream(null);
        $this->notify();
        return $this;
    }

    public function setContent($resource, string $charset = MailMimeParser::DEFAULT_CHARSET) : static
    {
        $stream = Utils::streamFor($resource);
        $this->attachContentStream($stream, $charset);
        // this->notify() called in attachContentStream
        return $this;
    }

    public function getResourceHandle() : mixed
    {
        return StreamWrapper::getResource($this->getStream());
    }

    public function getStream() : StreamInterface
    {
        return $this->partStreamContainer->getStream();
    }

    public function save($filenameResourceOrStream, string $filemode = 'w+') : static
    {
        $resourceOrStream = $filenameResourceOrStream;
        if (\is_string($filenameResourceOrStream)) {
            $resourceOrStream = \fopen($filenameResourceOrStream, $filemode);
        }

        $partStream = $this->getStream();
        $partStream->rewind();
        $stream = Utils::streamFor($resourceOrStream);
        Utils::copyToStream($partStream, $stream);

        if (!\is_string($filenameResourceOrStream)
            && !($filenameResourceOrStream instanceof StreamInterface)) {
            // only detach if it wasn't a string or StreamInterface, so the
            // fopen call can be properly closed if it was
            $stream->detach();
        }
        return $this;
    }

    public function __toString() : string
    {
        return $this->getStream()->getContents();
    }

    public function getErrorLoggingContextName() : string
    {
        $params = '';
        if (!empty($this->getContentId())) {
            $params .= ', content-id=' . $this->getContentId();
        }
        $params .= ', content-type=' . $this->getContentType();
        $nsClass = static::class;
        $class = \substr($nsClass, (\strrpos($nsClass, '\\') ?? -1) + 1);
        return $class . '(' . \spl_object_id($this) . $params . ')';
    }

    protected function getErrorBagChildren() : array
    {
        return [
            $this->partStreamContainer
        ];
    }
}
