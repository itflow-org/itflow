<?php

namespace DirectoryTree\ImapEngine\Connection;

use DirectoryTree\ImapEngine\Collections\ResponseCollection;
use DirectoryTree\ImapEngine\Connection\Responses\TaggedResponse;
use DirectoryTree\ImapEngine\Connection\Responses\UntaggedResponse;
use DirectoryTree\ImapEngine\Enums\ImapFetchIdentifier;
use DirectoryTree\ImapEngine\Enums\ImapSortKey;
use Generator;

interface ConnectionInterface
{
    /**
     * Open a new connection.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-state-and-flow-diagram
     */
    public function connect(string $host, ?int $port = null, array $options = []): void;

    /**
     * Close the current connection.
     */
    public function disconnect(): void;

    /**
     * Determine if the current session is connected.
     */
    public function connected(): bool;

    /**
     * Send a "LOGIN" command.
     *
     * Login to a new session.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-login-command
     */
    public function login(string $user, string $password): TaggedResponse;

    /**
     * Send a "LOGOUT" command.
     *
     * Logout of the current server session.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-logout-command
     */
    public function logout(): void;

    /**
     * Send an "AUTHENTICATE" command.
     *
     * Authenticate the current session.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-authenticate-command
     */
    public function authenticate(string $user, string $token): TaggedResponse;

    /**
     * Send a "STARTTLS" command.
     *
     * Upgrade the current plaintext connection to a secure TLS-encrypted connection.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-starttls-command
     */
    public function startTls(): void;

    /**
     * Send an "IDLE" command.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-idle-command
     */
    public function idle(int $timeout): Generator;

    /**
     * Send a "DONE" command.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#section-6.3.13
     */
    public function done(): void;

    /**
     * Send a "NOOP" command.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-noop-command
     */
    public function noop(): TaggedResponse;

    /**
     * Send a "EXPUNGE" command.
     *
     * Apply session saved changes to the server.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-expunge-command
     */
    public function expunge(): ResponseCollection;

    /**
     * Send a "CAPABILITY" command.
     *
     * Get the mailbox's available capabilities.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-capability-command
     */
    public function capability(): UntaggedResponse;

    /**
     * Send a "SEARCH" command.
     *
     * Execute a search request.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-search-command
     */
    public function search(array $params): UntaggedResponse;

    /**
     * Send a "SORT" command.
     *
     * Execute a sort request using RFC 5256.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc5256
     */
    public function sort(ImapSortKey $key, string $direction, array $params): UntaggedResponse;

    /**
     * Send a "FETCH" command.
     *
     * Exchange identification information.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc2971.
     */
    public function id(?array $ids = null): UntaggedResponse;

    /**
     * Send a "FETCH UID" command.
     *
     * Fetch message UIDs using the given message numbers.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-uid-command
     */
    public function uid(int|array $ids, ImapFetchIdentifier $identifier): ResponseCollection;

    /**
     * Send a "FETCH BODY[TEXT]" command.
     *
     * Fetch message text contents.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#section-6.4.5-9.9
     */
    public function bodyText(int|array $ids, bool $peek = true): ResponseCollection;

    /**
     * Send a "FETCH BODY[HEADER]" command.
     *
     * Fetch message headers.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#section-6.4.5-9.9
     */
    public function bodyHeader(int|array $ids, bool $peek = true): ResponseCollection;

    /**
     * Send a "FETCH BODYSTRUCTURE" command.
     *
     * Fetch message body structure.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#section-6.4.5-9.9
     */
    public function bodyStructure(int|array $ids): ResponseCollection;

    /**
     * Send a "FETCH BODY[i]" command.
     *
     * Fetch a specific part of the message BODY, such as BODY[1], BODY[1.2], etc.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#section-6.4.5-9.9
     */
    public function bodyPart(string $partIndex, int|array $ids, bool $peek = false): ResponseCollection;

    /**
     * Send a "FETCH FLAGS" command.
     *
     * Fetch a message flags.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#section-6.4.5-9.17
     */
    public function flags(int|array $ids): ResponseCollection;

    /**
     * Send a "FETCH" command.
     *
     * Fetch one or more items for one or more messages.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-fetch-command
     */
    public function fetch(array|string $items, array|int $from, mixed $to = null, ImapFetchIdentifier $identifier = ImapFetchIdentifier::Uid): ResponseCollection;

    /**
     * Send a "RFC822.SIZE" command.
     *
     * Fetch message sizes for one or more messages.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#section-6.4.5-9.21
     */
    public function size(int|array $ids): ResponseCollection;

    /**
     * Send an IMAP command.
     */
    public function send(string $name, array $tokens = [], ?string &$tag = null): void;

    /**
     * Send a "SELECT" command.
     *
     * Select the specified folder.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-select-command
     */
    public function select(string $folder): ResponseCollection;

    /**
     * Send a "EXAMINE" command.
     *
     * Examine a given folder.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-examine-command
     */
    public function examine(string $folder): ResponseCollection;

    /**
     * Send a "LIST" command.
     *
     * Get a list of available folders.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-list-command
     */
    public function list(string $reference = '', string $folder = '*'): ResponseCollection;

    /**
     * Send a "STATUS" command.
     *
     * Get the status of a given folder.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-status-command
     */
    public function status(string $folder, array $arguments = ['MESSAGES', 'UNSEEN', 'RECENT', 'UIDNEXT', 'UIDVALIDITY']): UntaggedResponse;

    /**
     * Send a "STORE" command.
     *
     * Set message flags.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-store-command
     */
    public function store(array|string $flags, array|int $from, ?int $to = null, ?string $mode = null, bool $silent = true, ?string $item = null): ResponseCollection;

    /**
     * Send a "APPEND" command.
     *
     * Append a new message to given folder.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-append-command
     */
    public function append(string $folder, string $message, ?array $flags = null): TaggedResponse;

    /**
     * Send a "UID COPY" command.
     *
     * Copy message set from current folder to other folder.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-copy-command
     */
    public function copy(string $folder, array|int $from, ?int $to = null): TaggedResponse;

    /**
     * Send a "UID MOVE" command.
     *
     * Move a message set from current folder to another folder.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-move-command
     */
    public function move(string $folder, array|int $from, ?int $to = null): TaggedResponse;

    /**
     * Send a "CREATE" command.
     *
     * Create a new folder.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-create-command
     */
    public function create(string $folder): ResponseCollection;

    /**
     * Send a "DELETE" command.
     *
     * Delete a folder.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-delete-command
     */
    public function delete(string $folder): TaggedResponse;

    /**
     * Send a "RENAME" command.
     *
     * Rename an existing folder.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-rename-command
     */
    public function rename(string $oldPath, string $newPath): TaggedResponse;

    /**
     * Send a "SUBSCRIBE" command.
     *
     * Subscribe to a folder.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-subscribe-command
     */
    public function subscribe(string $folder): TaggedResponse;

    /**
     * Send a "UNSUBSCRIBE" command.
     *
     * Unsubscribe from a folder.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9051#name-unsubscribe-command
     */
    public function unsubscribe(string $folder): TaggedResponse;

    /**
     * Send a "GETQUOTA" command.
     *
     * Retrieve quota information about a specific quota root.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9208#name-getquota
     */
    public function quota(string $root): UntaggedResponse;

    /**
     * Send a "GETQUOTAROOT" command.
     *
     * Retrieve quota root information about a mailbox.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9208#name-getquotaroot
     */
    public function quotaRoot(string $mailbox): ResponseCollection;
}
