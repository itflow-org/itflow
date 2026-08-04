<?php

namespace DirectoryTree\ImapEngine;

use BackedEnum;

interface FlaggableInterface
{
    /**
     * Mark the message as read. Alias for markSeen.
     */
    public function markRead(): void;

    /**
     * Mark the message as unread. Alias for unmarkSeen.
     */
    public function markUnread(): void;

    /**
     * Mark the message as seen.
     */
    public function markSeen(): void;

    /**
     * Unmark the seen flag.
     */
    public function unmarkSeen(): void;

    /**
     * Mark the message as answered.
     */
    public function markAnswered(): void;

    /**
     * Unmark the answered flag.
     */
    public function unmarkAnswered(): void;

    /**
     * Mark the message as flagged.
     */
    public function markFlagged(): void;

    /**
     * Unmark the flagged flag.
     */
    public function unmarkFlagged(): void;

    /**
     * Mark the message as deleted.
     */
    public function markDeleted(bool $expunge = false): void;

    /**
     * Unmark the deleted flag.
     */
    public function unmarkDeleted(): void;

    /**
     * Mark the message as a draft.
     */
    public function markDraft(): void;

    /**
     * Unmark the draft flag.
     */
    public function unmarkDraft(): void;

    /**
     * Mark the message as recent.
     */
    public function markRecent(): void;

    /**
     * Unmark the recent flag.
     */
    public function unmarkRecent(): void;

    /**
     * Determine if the message is marked as seen.
     */
    public function isSeen(): bool;

    /**
     * Determine if the message is marked as answered.
     */
    public function isAnswered(): bool;

    /**
     * Determine if the message is flagged.
     */
    public function isFlagged(): bool;

    /**
     * Determine if the message is marked as deleted.
     */
    public function isDeleted(): bool;

    /**
     * Determine if the message is marked as a draft.
     */
    public function isDraft(): bool;

    /**
     * Determine if the message is marked as recent.
     */
    public function isRecent(): bool;

    /**
     * Get the message's flags.
     *
     * @return string[]
     */
    public function flags(): array;

    /**
     * Determine if the message has the given flag.
     */
    public function hasFlag(BackedEnum|string $flag): bool;

    /**
     * Add or remove a flag from the message.
     *
     * @param  '+'|'-'  $operation
     */
    public function flag(BackedEnum|string $flag, string $operation, bool $expunge = false): void;
}
