<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Message;

use ArrayAccess;
use InvalidArgumentException;
use RecursiveIterator;

/**
 * Container of IMessagePart items for a parent IMultiPart.
 *
 * @author Zaahid Bateson
 */
class PartChildrenContainer implements ArrayAccess, RecursiveIterator
{
    /**
     * @var IMessagePart[] array of child parts of the IMultiPart object that is
     *      holding this container.
     */
    protected array $children;

    /**
     * @var int current key position within $children for iteration.
     */
    protected $position = 0;

    /**
     * @param IMessagePart[] $children
     */
    public function __construct(array $children = [])
    {
        $this->children = $children;
    }

    /**
     * Returns true if the current element is an IMultiPart.  Note that the
     * iterator may still be empty.
     */
    public function hasChildren() : bool
    {
        return ($this->current() instanceof IMultiPart);
    }

    /**
     * If the current element points to an IMultiPart, its child iterator is
     * returned by calling {@see IMultiPart::getChildIterator()}.
     *
     * @return RecursiveIterator<IMessagePart>|null the iterator
     */
    public function getChildren() : ?RecursiveIterator
    {
        if ($this->current() instanceof IMultiPart) {
            return $this->current()->getChildIterator();
        }
        return null;
    }

    /**
     * @return IMessagePart
     */
    public function current() : mixed
    {
        return $this->offsetGet($this->position);
    }

    public function key() : int
    {
        return $this->position;
    }

    public function next() : void
    {
        ++$this->position;
    }

    public function rewind() : void
    {
        $this->position = 0;
    }

    public function valid() : bool
    {
        return $this->offsetExists($this->position);
    }

    /**
     * Adds the passed IMessagePart to the container in the passed position.
     *
     * If position is not passed or null, the part is added to the end, as the
     * last child in the container.
     *
     * @param IMessagePart $part The part to add
     * @param int $position An optional index position (0-based) to add the
     *        child at.
     */
    public function add(IMessagePart $part, $position = null) : static
    {
        $index = $position ?? \count($this->children);
        \array_splice(
            $this->children,
            $index,
            0,
            [$part]
        );
        return $this;
    }

    /**
     * Removes the passed part, and returns the integer position it occupied.
     *
     * @param IMessagePart $part The part to remove.
     * @return int the 0-based position it previously occupied.
     */
    public function remove(IMessagePart $part) : ?int
    {
        foreach ($this->children as $key => $child) {
            if ($child === $part) {
                $this->offsetUnset($key);
                return $key;
            }
        }
        return null;
    }

    /**
     * @param int $offset
     */
    public function offsetExists(mixed $offset) : bool
    {
        return isset($this->children[$offset]);
    }

    /**
     * @param int $offset
     */
    public function offsetGet(mixed $offset) : mixed
    {
        return $this->offsetExists($offset) ? $this->children[$offset] : null;
    }

    /**
     * @param int $offset
     * @param IMessagePart $value
     */
    public function offsetSet(mixed $offset, mixed $value) : void
    {
        if (!$value instanceof IMessagePart) {
            throw new InvalidArgumentException(
                \get_class($value) . ' is not a ZBateson\MailMimeParser\Message\IMessagePart'
            );
        }
        $index = $offset ?? \count($this->children);
        $this->children[$index] = $value;
        if ($index < $this->position) {
            ++$this->position;
        }
    }

    /**
     * @param int $offset
     */
    public function offsetUnset($offset) : void
    {
        \array_splice($this->children, $offset, 1);
        if ($this->position >= $offset) {
            --$this->position;
        }
    }
}
