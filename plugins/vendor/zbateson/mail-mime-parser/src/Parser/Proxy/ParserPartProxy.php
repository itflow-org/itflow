<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Parser\Proxy;

use Psr\Http\Message\StreamInterface;
use ZBateson\MailMimeParser\Message\IMessagePart;
use ZBateson\MailMimeParser\Message\PartHeaderContainer;
use ZBateson\MailMimeParser\Parser\IParserService;
use ZBateson\MailMimeParser\Parser\PartBuilder;

/**
 * Proxy between a MessagePart and a Parser.
 *
 * ParserPartProxy objects are responsible for ferrying requests from message
 * parts to a proxy as they're requested, and for maintaining state information
 * for a parser as necessary.
 *
 * @author Zaahid Bateson
 */
abstract class ParserPartProxy extends PartBuilder
{
    /**
     * @var IParserService The parser.
     */
    protected IParserService $parser;

    /**
     * @var PartBuilder The part's PartBuilder.
     */
    protected PartBuilder $partBuilder;

    /**
     * @var IMessagePart The part.
     */
    private IMessagePart $part;

    public function __construct(PartBuilder $partBuilder, IParserService $parser)
    {
        $this->partBuilder = $partBuilder;
        $this->parser = $parser;
    }

    /**
     * Sets the associated part.
     *
     * @param IMessagePart $part The part
     */
    public function setPart(IMessagePart $part) : static
    {
        $this->part = $part;
        return $this;
    }

    /**
     * Returns the IMessagePart associated with this proxy.
     *
     * @return IMessagePart the part.
     */
    public function getPart() : IMessagePart
    {
        return $this->part;
    }

    /**
     * Requests the parser to parse this part's content, and call
     * setStreamContentStartPos/EndPos to setup this part's boundaries within
     * the main message's raw stream.
     *
     * The method first checks to see if the content has already been parsed,
     * and is safe to call multiple times.
     */
    public function parseContent() : static
    {
        if (!$this->isContentParsed()) {
            $this->parser->parseContent($this);
        }
        return $this;
    }

    /**
     * Parses everything under this part.
     *
     * For ParserPartProxy, this is just content, but sub-classes may override
     * this to parse all children as well for example.
     */
    public function parseAll() : static
    {
        $this->parseContent();
        return $this;
    }

    public function getParent() : ?ParserMimePartProxy
    {
        return $this->partBuilder->getParent();
    }

    public function getHeaderContainer() : PartHeaderContainer
    {
        return $this->partBuilder->getHeaderContainer();
    }

    public function getStream() : StreamInterface
    {
        return $this->partBuilder->getStream();
    }

    /**
     * @return resource
     */
    public function getMessageResourceHandle() : mixed
    {
        return $this->partBuilder->getMessageResourceHandle();
    }

    public function getMessageResourceHandlePos() : int
    {
        return $this->partBuilder->getMessageResourceHandlePos();
    }

    public function getStreamPartStartPos() : int
    {
        return $this->partBuilder->getStreamPartStartPos();
    }

    public function getStreamPartLength() : int
    {
        return $this->partBuilder->getStreamPartLength();
    }

    public function getStreamContentStartPos() : ?int
    {
        return $this->partBuilder->getStreamContentStartPos();
    }

    public function getStreamContentLength() : int
    {
        return $this->partBuilder->getStreamContentLength();
    }

    public function setStreamPartStartPos(int $streamPartStartPos) : static
    {
        $this->partBuilder->setStreamPartStartPos($streamPartStartPos);
        return $this;
    }

    public function setStreamPartEndPos(int $streamPartEndPos) : static
    {
        $this->partBuilder->setStreamPartEndPos($streamPartEndPos);
        return $this;
    }

    public function setStreamContentStartPos(int $streamContentStartPos) : static
    {
        $this->partBuilder->setStreamContentStartPos($streamContentStartPos);
        return $this;
    }

    public function setStreamPartAndContentEndPos(int $streamContentEndPos) : static
    {
        $this->partBuilder->setStreamPartAndContentEndPos($streamContentEndPos);
        return $this;
    }

    public function isContentParsed() : bool
    {
        return $this->partBuilder->isContentParsed();
    }

    public function isMime() : bool
    {
        return $this->partBuilder->isMime();
    }

    public function addError(string $message, string $level) : ParserPartProxy
    {
        $this->part->addError($message, $level);
        return $this;
    }
}
