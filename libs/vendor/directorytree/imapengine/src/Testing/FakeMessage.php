<?php

namespace DirectoryTree\ImapEngine\Testing;

use BackedEnum;
use DirectoryTree\ImapEngine\BodyStructureCollection;
use DirectoryTree\ImapEngine\HasFlags;
use DirectoryTree\ImapEngine\HasMessageAccessors;
use DirectoryTree\ImapEngine\HasParsedMessage;
use DirectoryTree\ImapEngine\MessageInterface;
use DirectoryTree\ImapEngine\Support\Str;

class FakeMessage implements MessageInterface
{
    use HasFlags, HasMessageAccessors, HasParsedMessage;

    /**
     * Constructor.
     */
    public function __construct(
        protected int $uid,
        protected array $flags = [],
        protected string $contents = '',
        protected ?int $size = null,
        protected ?BodyStructureCollection $bodyStructure = null,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function uid(): int
    {
        return $this->uid;
    }

    /**
     * {@inheritDoc}
     */
    public function size(): int
    {
        return $this->size ?? strlen($this->contents);
    }

    /**
     * {@inheritDoc}
     */
    public function is(MessageInterface $message): bool
    {
        return $message instanceof self
            && $this->uid === $message->uid
            && $this->flags === $message->flags
            && $this->contents === $message->contents;
    }

    /**
     * {@inheritDoc}
     */
    public function flag(BackedEnum|string $flag, string $operation, bool $expunge = false): void
    {
        $flag = Str::enum($flag);

        if ($operation === '+') {
            $this->flags = array_unique([...$this->flags, $flag]);
        } else {
            $this->flags = array_filter($this->flags, fn (string $value) => $value !== $flag);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function flags(): array
    {
        return $this->flags;
    }

    /**
     * {@inheritDoc}
     */
    public function bodyStructure(): ?BodyStructureCollection
    {
        return $this->bodyStructure;
    }

    /**
     * {@inheritDoc}
     */
    public function hasBodyStructure(): bool
    {
        return (bool) $this->bodyStructure;
    }

    /**
     * {@inheritDoc}
     */
    public function bodyPart(string $partNumber, bool $peek = true): ?string
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty(): bool
    {
        return empty($this->contents);
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return $this->contents;
    }
}
