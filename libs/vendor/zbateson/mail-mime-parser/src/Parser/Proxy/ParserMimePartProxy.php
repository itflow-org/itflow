<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Parser\Proxy;

use Psr\Log\LogLevel;
use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\Header\ParameterHeader;
use ZBateson\MailMimeParser\Message\IMessagePart;

/**
 * A bi-directional parser-to-part proxy for MimeParser and IMimeParts.
 *
 * @author Zaahid Bateson
 */
class ParserMimePartProxy extends ParserPartProxy
{
    /**
     * @var bool set to true once the end boundary of the currently-parsed
     *      part is found.
     */
    protected bool $endBoundaryFound = false;

    /**
     * @var bool set to true once a boundary belonging to this parent's part
     *      is found.
     */
    protected bool $parentBoundaryFound = false;

    /**
     * @var bool true once all children of this part have been parsed.
     */
    protected bool $allChildrenParsed = false;

    /**
     * @var ParserPartProxy[] Array of all parsed children.
     */
    protected array $children = [];

    /**
     * @var ParserPartProxy[] Parsed children used as a 'first-in-first-out'
     *      stack as children are parsed.
     */
    protected array $childrenStack = [];

    /**
     * @var ParserPartProxy Reference to the last child added to this part.
     */
    protected ?ParserPartProxy $lastAddedChild = null;

    /**
     * @var ?string NULL if the current part does not have a boundary, and
     *      otherwise contains the value of the boundary parameter of the
     *      content-type header if the part contains one.
     */
    private ?string $mimeBoundary = null;

    /**
     * @var bool FALSE if not queried for in the content-type header of this
     *      part and set in $mimeBoundary.
     */
    private bool $mimeBoundaryQueried = false;

    /**
     * Ensures that the last child added to this part is fully parsed (content
     * and children).
     */
    protected function ensureLastChildParsed() : static
    {
        if ($this->lastAddedChild !== null) {
            $this->lastAddedChild->parseAll();
        }
        return $this;
    }

    /**
     * Parses the next child of this part and adds it to the 'stack' of
     * children.
     */
    protected function parseNextChild() : static
    {
        if ($this->allChildrenParsed) {
            return $this;
        }
        $this->parseContent();
        $this->ensureLastChildParsed();
        $next = $this->parser->parseNextChild($this);
        if ($next !== null) {
            $this->children[] = $next;
            $this->childrenStack[] = $next;
            $this->lastAddedChild = $next;
        } else {
            $this->allChildrenParsed = true;
        }
        return $this;
    }

    /**
     * Returns the next child part if one exists, popping it from the internal
     * 'stack' of children, attempting to parse a new one if the stack is empty,
     * and returning null if there are no more children.
     *
     * @return ?IMessagePart the child part.
     */
    public function popNextChild() : ?IMessagePart
    {
        if (empty($this->childrenStack)) {
            $this->parseNextChild();
        }
        $proxy = \array_shift($this->childrenStack);
        return ($proxy !== null) ? $proxy->getPart() : null;
    }

    /**
     * Parses all content and children for this part.
     */
    public function parseAll() : static
    {
        $this->parseContent();
        while (!$this->allChildrenParsed) {
            $this->parseNextChild();
        }
        return $this;
    }

    /**
     * Returns a ParameterHeader representing the parsed Content-Type header for
     * this part.
     */
    public function getContentType() : ?ParameterHeader
    {
        return $this->getHeaderContainer()->get(HeaderConsts::CONTENT_TYPE);
    }

    /**
     * Returns the parsed boundary parameter of the Content-Type header if set
     * for a multipart message part.
     *
     */
    public function getMimeBoundary() : ?string
    {
        if ($this->mimeBoundaryQueried === false) {
            $this->mimeBoundaryQueried = true;
            $contentType = $this->getContentType();
            if ($contentType !== null) {
                $this->mimeBoundary = $contentType->getValueFor('boundary');
            }
        }
        return $this->mimeBoundary;
    }

    /**
     * Returns true if the passed $line of read input matches this part's mime
     * boundary, or any of its parent's mime boundaries for a multipart message.
     *
     * If the passed $line is the ending boundary for the current part,
     * $this->isEndBoundaryFound will return true after.
     */
    public function setEndBoundaryFound(string $line) : bool
    {
        $boundary = $this->getMimeBoundary();
        if ($this->getParent() !== null && $this->getParent()->setEndBoundaryFound($line)) {
            $this->parentBoundaryFound = true;
            return true;
        } elseif ($boundary !== null) {
            if ($line === "--$boundary--") {
                $this->endBoundaryFound = true;
                return true;
            } elseif ($line === "--$boundary") {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if the parser passed an input line to setEndBoundary that
     * matches a parent's mime boundary, and the following input belongs to a
     * new part under its parent.
     *
     */
    public function isParentBoundaryFound() : bool
    {
        return ($this->parentBoundaryFound);
    }

    /**
     * Returns true if an end boundary was found for this part.
     *
     */
    public function isEndBoundaryFound() : bool
    {
        return ($this->endBoundaryFound);
    }

    /**
     * Called once EOF is reached while reading content.  The method sets the
     * flag used by isParentBoundaryFound() to true on this part and all parent
     * parts.
     *
     */
    public function setEof() : static
    {
        $this->parentBoundaryFound = true;
        if ($this->getParent() !== null) {
            $this->getParent()->setEof();
        }
        return $this;
    }

    /**
     * Overridden to set a 0-length content length, and a stream end pos of -2
     * if the passed end pos is before the start pos (can happen if a mime
     * end boundary doesn't have an empty line before the next parent start
     * boundary).
     */
    public function setStreamPartAndContentEndPos(int $streamContentEndPos) : static
    {
        // check if we're expecting a boundary and didn't find one
        if (!$this->endBoundaryFound && !$this->parentBoundaryFound) {
            if (!empty($this->mimeBoundary) || ($this->getParent() !== null && !empty($this->getParent()->mimeBoundary))) {
                $this->addError('End boundary for part not found', LogLevel::WARNING);
            }
        }
        $start = $this->getStreamContentStartPos();
        if ($streamContentEndPos - $start < 0) {
            parent::setStreamPartAndContentEndPos($start);
            $this->setStreamPartEndPos($streamContentEndPos);
        } else {
            parent::setStreamPartAndContentEndPos($streamContentEndPos);
        }
        return $this;
    }

    /**
     * Sets the length of the last line ending read by MimeParser (e.g. 2 for
     * '\r\n', or 1 for '\n').
     *
     * The line ending may not belong specifically to this part, so
     * ParserMimePartProxy simply calls setLastLineEndingLength on its parent,
     * which must eventually reach a ParserMessageProxy which actually stores
     * the length.
     */
    public function setLastLineEndingLength(int $length) : static
    {
        $this->getParent()->setLastLineEndingLength($length);
        return $this;
    }

    /**
     * Returns the length of the last line ending read by MimeParser (e.g. 2 for
     * '\r\n', or 1 for '\n').
     *
     * The line ending may not belong specifically to this part, so
     * ParserMimePartProxy simply calls getLastLineEndingLength on its parent,
     * which must eventually reach a ParserMessageProxy which actually keeps
     * the length and returns it.
     *
     * @return int the length of the last line ending read
     */
    public function getLastLineEndingLength() : int
    {
        return $this->getParent()->getLastLineEndingLength();
    }

    /**
     * Returns the last part that was added.
     */
    public function getLastAddedChild() : ?ParserPartProxy
    {
        return $this->lastAddedChild;
    }

    /**
     * Returns the added child at the provided index, useful for looking at
     * previously parsed children.
     */
    public function getAddedChildAt(int $index) : ?ParserPartProxy
    {
        return $this->children[$index] ?? null;
    }
}
