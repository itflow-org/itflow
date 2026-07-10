<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Message;

use AppendIterator;
use ArrayIterator;
use Iterator;
use Psr\Log\LoggerInterface;
use RecursiveIterator;
use RecursiveIteratorIterator;

/**
 * A message part that contains children.
 *
 * @author Zaahid Bateson
 */
abstract class MultiPart extends MessagePart implements IMultiPart
{
    /**
     * @var PartChildrenContainer child part container
     */
    protected PartChildrenContainer $partChildrenContainer;

    public function __construct(
        LoggerInterface $logger,
        PartStreamContainer $streamContainer,
        PartChildrenContainer $partChildrenContainer,
        ?IMimePart $parent = null
    ) {
        parent::__construct($logger, $streamContainer, $parent);
        $this->partChildrenContainer = $partChildrenContainer;
    }

    private function getAllPartsIterator() : AppendIterator
    {
        $iter = new AppendIterator();
        $iter->append(new ArrayIterator([$this]));
        $iter->append(new RecursiveIteratorIterator($this->partChildrenContainer, RecursiveIteratorIterator::SELF_FIRST));
        return $iter;
    }

    private function iteratorFindAt(Iterator $iter, int $index, ?callable $fnFilter = null) : ?IMessagePart
    {
        $pos = 0;
        foreach ($iter as $part) {
            if (($fnFilter === null || $fnFilter($part))) {
                if ($index === $pos) {
                    return $part;
                }
                ++$pos;
            }
        }
        return null;
    }

    public function getPart(int $index, ?callable $fnFilter = null) : ?IMessagePart
    {
        return $this->iteratorFindAt(
            $this->getAllPartsIterator(),
            $index,
            $fnFilter
        );
    }

    public function getAllParts(?callable $fnFilter = null) : array
    {
        $array = \iterator_to_array($this->getAllPartsIterator(), false);
        if ($fnFilter !== null) {
            return \array_values(\array_filter($array, $fnFilter));
        }
        return $array;
    }

    public function getPartCount(?callable $fnFilter = null) : int
    {
        return \count($this->getAllParts($fnFilter));
    }

    public function getChild(int $index, ?callable $fnFilter = null) : ?IMessagePart
    {
        return $this->iteratorFindAt(
            $this->partChildrenContainer,
            $index,
            $fnFilter
        );
    }

    public function getChildIterator() : RecursiveIterator
    {
        return $this->partChildrenContainer;
    }

    public function getChildParts(?callable $fnFilter = null) : array
    {
        $array = \iterator_to_array($this->partChildrenContainer, false);
        if ($fnFilter !== null) {
            return \array_values(\array_filter($array, $fnFilter));
        }
        return $array;
    }

    public function getChildCount(?callable $fnFilter = null) : int
    {
        return \count($this->getChildParts($fnFilter));
    }

    public function getPartByMimeType(string $mimeType, int $index = 0) : ?IMessagePart
    {
        return $this->getPart($index, PartFilter::fromContentType($mimeType));
    }

    public function getAllPartsByMimeType(string $mimeType) : array
    {
        return $this->getAllParts(PartFilter::fromContentType($mimeType));
    }

    public function getCountOfPartsByMimeType(string $mimeType) : int
    {
        return $this->getPartCount(PartFilter::fromContentType($mimeType));
    }

    public function getPartByContentId(string $contentId) : ?IMessagePart
    {
        $sanitized = \preg_replace('/^\s*<|>\s*$/', '', $contentId);
        return $this->getPart(0, function(IMessagePart $part) use ($sanitized) {
            $cid = $part->getContentId();
            return ($cid !== null && \strcasecmp($cid, $sanitized) === 0);
        });
    }

    public function addChild(MessagePart $part, ?int $position = null) : static
    {
        if ($part !== $this) {
            $part->parent = $this;
            $this->partChildrenContainer->add($part, $position);
            $this->notify();
        }
        return $this;
    }

    public function removePart(IMessagePart $part) : ?int
    {
        $parent = $part->getParent();
        if ($this !== $parent && $parent !== null) {
            return $parent->removePart($part);
        }

        $position = $this->partChildrenContainer->remove($part);
        if ($position !== null) {
            $this->notify();
        }
        return $position;
    }

    public function removeAllParts(?callable $fnFilter = null) : int
    {
        $parts = $this->getAllParts($fnFilter);
        $count = \count($parts);
        foreach ($parts as $part) {
            if ($part === $this) {
                --$count;
                continue;
            }
            $this->removePart($part);
        }
        return $count;
    }

    protected function getErrorBagChildren() : array
    {
        return \array_merge(parent::getErrorBagChildren(), $this->getChildParts());
    }
}
