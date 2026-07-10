<?php

namespace DirectoryTree\ImapEngine;

use BackedEnum;
use BadMethodCallException;

class FileMessage implements MessageInterface
{
    use HasFlags, HasMessageAccessors, HasParsedMessage;

    /**
     * Constructor.
     */
    public function __construct(
        protected string $contents
    ) {}

    /**
     * {@inheritDoc}
     */
    public function uid(): int
    {
        throw new BadMethodCallException('FileMessage does not support a UID');
    }

    /**
     * {@inheritDoc}
     */
    public function size(): ?int
    {
        return strlen($this->contents);
    }

    /**
     * {@inheritDoc}
     */
    public function flag(BackedEnum|string $flag, string $operation, bool $expunge = false): void
    {
        throw new BadMethodCallException('FileMessage does not support flagging');
    }

    /**
     * Get the string representation of the message.
     */
    public function __toString(): string
    {
        return $this->contents;
    }

    /**
     * Determine if this message is equal to another.
     */
    public function is(MessageInterface $message): bool
    {
        return $message instanceof self
            && $this->contents === $message->contents;
    }

    /**
     * Get the message flags.
     */
    public function flags(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function bodyStructure(): ?BodyStructureCollection
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function hasBodyStructure(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function bodyPart(string $partNumber, bool $peek = true): ?string
    {
        throw new BadMethodCallException('FileMessage does not support fetching body parts');
    }

    /**
     * Determine if the message is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->contents);
    }
}
