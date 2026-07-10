<?php

namespace DirectoryTree\ImapEngine;

interface FolderInterface
{
    /**
     * Get the folder's mailbox.
     */
    public function mailbox(): MailboxInterface;

    /**
     * Get the folder path.
     */
    public function path(): string;

    /**
     * Get the folder flags.
     *
     * @return string[]
     */
    public function flags(): array;

    /**
     * Get the folder delimiter.
     */
    public function delimiter(): string;

    /**
     * Get the folder name.
     */
    public function name(): string;

    /**
     * Determine if the current folder is the same as the given.
     */
    public function is(FolderInterface $folder): bool;

    /**
     * Begin querying for messages.
     */
    public function messages(): MessageQueryInterface;

    /**
     * Begin idling on the current folder for the given timeout in seconds.
     */
    public function idle(callable $callback, ?callable $query = null, callable|int $timeout = 300): void;

    /**
     * Begin polling for new messages at the given frequency in seconds.
     */
    public function poll(callable $callback, ?callable $query = null, callable|int $frequency = 60): void;

    /**
     * Move or rename the current folder.
     */
    public function move(string $newPath): void;

    /**
     * Select the current folder.
     */
    public function select(bool $force = false): void;

    /**
     * Get the folder's quotas.
     */
    public function quota(): array;

    /**
     * Get the folder's status.
     */
    public function status(): array;

    /**
     * Examine the current folder and get detailed status information.
     */
    public function examine(): array;

    /**
     * Expunge the mailbox and return the expunged message sequence numbers.
     */
    public function expunge(): array;

    /**
     * Delete the current folder.
     */
    public function delete(): void;
}
