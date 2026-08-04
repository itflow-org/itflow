<?php

namespace DirectoryTree\ImapEngine;

use BackedEnum;
use DirectoryTree\ImapEngine\Collections\MessageCollection;
use DirectoryTree\ImapEngine\Connection\ImapQueryBuilder;
use DirectoryTree\ImapEngine\Enums\ImapFetchIdentifier;
use DirectoryTree\ImapEngine\Enums\ImapSortKey;
use DirectoryTree\ImapEngine\Pagination\LengthAwarePaginator;

/**
 * @mixin ImapQueryBuilder
 */
interface MessageQueryInterface
{
    /**
     * Don't mark messages as read when fetching.
     */
    public function leaveUnread(): MessageQueryInterface;

    /**
     * Mark all messages as read when fetching.
     */
    public function markAsRead(): MessageQueryInterface;

    /**
     * Set the limit and page for the current query.
     */
    public function limit(int $limit, int $page = 1): MessageQueryInterface;

    /**
     * Get the set fetch limit.
     */
    public function getLimit(): ?int;

    /**
     * Set the fetch limit.
     */
    public function setLimit(int $limit): MessageQueryInterface;

    /**
     * Get the set page.
     */
    public function getPage(): int;

    /**
     * Set the page.
     */
    public function setPage(int $page): MessageQueryInterface;

    /**
     * Determine if the body of messages is being fetched.
     */
    public function isFetchingBody(): bool;

    /**
     * Determine if the flags of messages is being fetched.
     */
    public function isFetchingFlags(): bool;

    /**
     * Determine if the headers of messages is being fetched.
     */
    public function isFetchingHeaders(): bool;

    /**
     * Determine if the size of messages is being fetched.
     */
    public function isFetchingSize(): bool;

    /**
     * Determine if the body structure of messages is being fetched.
     */
    public function isFetchingBodyStructure(): bool;

    /**
     * Fetch the flags of messages.
     */
    public function withFlags(): MessageQueryInterface;

    /**
     * Fetch the body of messages.
     */
    public function withBody(): MessageQueryInterface;

    /**
     * Fetch the headers of messages.
     */
    public function withHeaders(): MessageQueryInterface;

    /**
     * Fetch the size of messages.
     */
    public function withSize(): MessageQueryInterface;

    /**
     * Fetch the body structure of messages.
     */
    public function withBodyStructure(): MessageQueryInterface;

    /**
     * Don't fetch the body of messages.
     */
    public function withoutBody(): MessageQueryInterface;

    /**
     * Don't fetch the headers of messages.
     */
    public function withoutHeaders(): MessageQueryInterface;

    /**
     * Don't fetch the flags of messages.
     */
    public function withoutFlags(): MessageQueryInterface;

    /**
     * Don't fetch the size of messages.
     */
    public function withoutSize(): MessageQueryInterface;

    /**
     * Don't fetch the body structure of messages.
     */
    public function withoutBodyStructure(): MessageQueryInterface;

    /**
     * Set the fetch order.
     */
    public function setFetchOrder(string $fetchOrder): MessageQueryInterface;

    /**
     * Get the fetch order.
     */
    public function getFetchOrder(): string;

    /**
     * Set the fetch order to 'ascending'.
     */
    public function setFetchOrderAsc(): MessageQueryInterface;

    /**
     * Set the fetch order to 'descending'.
     */
    public function setFetchOrderDesc(): MessageQueryInterface;

    /**
     * Set the fetch order to show oldest messages first (ascending).
     */
    public function oldest(): MessageQueryInterface;

    /**
     * Set the fetch order to show newest messages first (descending).
     */
    public function newest(): MessageQueryInterface;

    /**
     * Set the sort key for server-side sorting (RFC 5256).
     */
    public function setSortKey(ImapSortKey|string|null $key): MessageQueryInterface;

    /**
     * Get the sort key for server-side sorting.
     */
    public function getSortKey(): ?ImapSortKey;

    /**
     * Set the sort direction for server-side sorting.
     */
    public function setSortDirection(string $direction): MessageQueryInterface;

    /**
     * Get the sort direction for server-side sorting.
     */
    public function getSortDirection(): string;

    /**
     * Sort messages by a field using server-side sorting (RFC 5256).
     */
    public function sortBy(ImapSortKey|string $key, string $direction = 'asc'): MessageQueryInterface;

    /**
     * Sort messages by a field in descending order using server-side sorting.
     */
    public function sortByDesc(ImapSortKey|string $key): MessageQueryInterface;

    /**
     * Count all available messages matching the current search criteria.
     */
    public function count(): int;

    /**
     * Get the first message in the resulting collection.
     */
    public function first(): ?MessageInterface;

    /**
     * Get the first message in the resulting collection or throw an exception.
     */
    public function firstOrFail(): MessageInterface;

    /**
     * Get the messages matching the current query.
     */
    public function get(): MessageCollection;

    /**
     * Append a new message to the folder.
     */
    public function append(string $message, mixed $flags = null): int;

    /**
     * Execute a callback over each message via a chunked query.
     */
    public function each(callable $callback, int $chunkSize = 10, int $startChunk = 1): void;

    /**
     * Execute a callback over each chunk of messages.
     */
    public function chunk(callable $callback, int $chunkSize = 10, int $startChunk = 1): void;

    /**
     * Paginate the current query.
     */
    public function paginate(int $perPage = 5, $page = null, string $pageName = 'page'): LengthAwarePaginator;

    /**
     * Find a message by the given identifier type or throw an exception.
     */
    public function findOrFail(int $id, ImapFetchIdentifier $identifier = ImapFetchIdentifier::Uid): MessageInterface;

    /**
     * Find a message by the given identifier type.
     */
    public function find(int $id, ImapFetchIdentifier $identifier = ImapFetchIdentifier::Uid): ?MessageInterface;

    /**
     * Destroy the given messages.
     */
    public function destroy(array|int $uids, bool $expunge = false): void;

    /**
     * Add or remove a flag from all messages matching the current query.
     *
     * @param  string  $operation  '+'|'-'
     * @return int The number of messages affected.
     */
    public function flag(BackedEnum|string $flag, string $operation, bool $expunge = false): int;

    /**
     * Mark all messages matching the current query as read.
     *
     * @return int The number of messages affected.
     */
    public function markRead(): int;

    /**
     * Mark all messages matching the current query as unread.
     *
     * @return int The number of messages affected.
     */
    public function markUnread(): int;

    /**
     * Mark all messages matching the current query as flagged.
     *
     * @return int The number of messages affected.
     */
    public function markFlagged(): int;

    /**
     * Unmark all messages matching the current query as flagged.
     *
     * @return int The number of messages affected.
     */
    public function unmarkFlagged(): int;

    /**
     * Delete all messages matching the current query.
     *
     * @return int The number of messages affected.
     */
    public function delete(bool $expunge = false): int;

    /**
     * Move all messages matching the current query to the given folder.
     *
     * @return int The number of messages affected.
     */
    public function move(string $folder, bool $expunge = false): int;

    /**
     * Copy all messages matching the current query to the given folder.
     *
     * @return int The number of messages affected.
     */
    public function copy(string $folder): int;
}
