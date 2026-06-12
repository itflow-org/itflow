<?php

namespace DirectoryTree\ImapEngine;

use BackedEnum;
use DirectoryTree\ImapEngine\Enums\ImapFlag;
use DirectoryTree\ImapEngine\Support\Str;

trait HasFlags
{
    /**
     * {@inheritDoc}
     */
    public function markRead(): void
    {
        $this->markSeen();
    }

    /**
     * {@inheritDoc}
     */
    public function markUnread(): void
    {
        $this->unmarkSeen();
    }

    /**
     * {@inheritDoc}
     */
    public function markSeen(): void
    {
        $this->flag(ImapFlag::Seen, '+');
    }

    /**
     * {@inheritDoc}
     */
    public function unmarkSeen(): void
    {
        $this->flag(ImapFlag::Seen, '-');
    }

    /**
     * {@inheritDoc}
     */
    public function markAnswered(): void
    {
        $this->flag(ImapFlag::Answered, '+');
    }

    /**
     * {@inheritDoc}
     */
    public function unmarkAnswered(): void
    {
        $this->flag(ImapFlag::Answered, '-');
    }

    /**
     * {@inheritDoc}
     */
    public function markFlagged(): void
    {
        $this->flag(ImapFlag::Flagged, '+');
    }

    /**
     * {@inheritDoc}
     */
    public function unmarkFlagged(): void
    {
        $this->flag(ImapFlag::Flagged, '-');
    }

    /**
     * {@inheritDoc}
     */
    public function markDeleted(bool $expunge = false): void
    {
        $this->flag(ImapFlag::Deleted, '+', $expunge);
    }

    /**
     * {@inheritDoc}
     */
    public function unmarkDeleted(): void
    {
        $this->flag(ImapFlag::Deleted, '-');
    }

    /**
     * {@inheritDoc}
     */
    public function markDraft(): void
    {
        $this->flag(ImapFlag::Draft, '+');
    }

    /**
     * {@inheritDoc}
     */
    public function unmarkDraft(): void
    {
        $this->flag(ImapFlag::Draft, '-');
    }

    /**
     * {@inheritDoc}
     */
    public function markRecent(): void
    {
        $this->flag(ImapFlag::Recent, '+');
    }

    /**
     * {@inheritDoc}
     */
    public function unmarkRecent(): void
    {
        $this->flag(ImapFlag::Recent, '-');
    }

    /**
     * {@inheritDoc}
     */
    public function isSeen(): bool
    {
        return $this->hasFlag(ImapFlag::Seen);
    }

    /**
     * {@inheritDoc}
     */
    public function isAnswered(): bool
    {
        return $this->hasFlag(ImapFlag::Answered);
    }

    /**
     * {@inheritDoc}
     */
    public function isFlagged(): bool
    {
        return $this->hasFlag(ImapFlag::Flagged);
    }

    /**
     * {@inheritDoc}
     */
    public function isDeleted(): bool
    {
        return $this->hasFlag(ImapFlag::Deleted);
    }

    /**
     * {@inheritDoc}
     */
    public function isDraft(): bool
    {
        return $this->hasFlag(ImapFlag::Draft);
    }

    /**
     * {@inheritDoc}
     */
    public function isRecent(): bool
    {
        return $this->hasFlag(ImapFlag::Recent);
    }

    /**
     * {@inheritDoc}
     */
    public function hasFlag(BackedEnum|string $flag): bool
    {
        return in_array(Str::enum($flag), $this->flags());
    }

    /**
     * {@inheritDoc}
     */
    abstract public function flags(): array;

    /**
     * {@inheritDoc}
     */
    abstract public function flag(BackedEnum|string $flag, string $operation, bool $expunge = false): void;
}
