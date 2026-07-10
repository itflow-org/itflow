<?php

namespace DirectoryTree\ImapEngine;

use BackedEnum;
use DirectoryTree\ImapEngine\Collections\MessageCollection;
use DirectoryTree\ImapEngine\Collections\ResponseCollection;
use DirectoryTree\ImapEngine\Connection\ConnectionInterface;
use DirectoryTree\ImapEngine\Connection\ImapQueryBuilder;
use DirectoryTree\ImapEngine\Connection\Responses\Data\ListData;
use DirectoryTree\ImapEngine\Connection\Responses\UntaggedResponse;
use DirectoryTree\ImapEngine\Connection\Tokens\Token;
use DirectoryTree\ImapEngine\Enums\ImapFetchIdentifier;
use DirectoryTree\ImapEngine\Enums\ImapFlag;
use DirectoryTree\ImapEngine\Exceptions\ImapCapabilityException;
use DirectoryTree\ImapEngine\Exceptions\ImapCommandException;
use DirectoryTree\ImapEngine\Exceptions\RuntimeException;
use DirectoryTree\ImapEngine\Pagination\LengthAwarePaginator;
use DirectoryTree\ImapEngine\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\ItemNotFoundException;

/**
 * @mixin ImapQueryBuilder
 */
class MessageQuery implements MessageQueryInterface
{
    use QueriesMessages;

    /**
     * Constructor.
     */
    public function __construct(
        protected FolderInterface $folder,
        protected ImapQueryBuilder $query,
    ) {}

    /**
     * Count all available messages matching the current search criteria.
     */
    public function count(): int
    {
        return $this->search()->count();
    }

    /**
     * Get the first message in the resulting collection.
     */
    public function first(): ?MessageInterface
    {
        try {
            return $this->firstOrFail();
        } catch (ItemNotFoundException) {
            return null;
        }
    }

    /**
     * Get the first message in the resulting collection or throw an exception.
     */
    public function firstOrFail(): MessageInterface
    {
        return $this->limit(1)->get()->firstOrFail();
    }

    /**
     * Get the messages matching the current query.
     */
    public function get(): MessageCollection
    {
        return $this->process($this->sortKey ? $this->sort() : $this->search());
    }

    /**
     * Append a new message to the folder.
     */
    public function append(string $message, mixed $flags = null): int
    {
        $response = $this->connection()->append(
            $this->folder->path(), $message, (array) Str::enums($flags),
        );

        return (int) $response // TAG4 OK [APPENDUID <uidvalidity> <uid>] APPEND completed.
            ->tokenAt(2) // [APPENDUID <uidvalidity> <uid>]
            ->tokenAt(2) // <uid>
            ->value;
    }

    /**
     * Execute a callback over each message via a chunked query.
     */
    public function each(callable $callback, int $chunkSize = 10, int $startChunk = 1): void
    {
        $this->chunk(function (MessageCollection $messages) use ($callback) {
            foreach ($messages as $key => $message) {
                if ($callback($message, $key) === false) {
                    return false;
                }
            }
        }, $chunkSize, $startChunk);
    }

    /**
     * Execute a callback over each chunk of messages.
     */
    public function chunk(callable $callback, int $chunkSize = 10, int $startChunk = 1): void
    {
        $startChunk = max($startChunk, 1);
        $chunkSize = max($chunkSize, 1);

        // Get all search result tokens once.
        $messages = $this->search();

        // Calculate how many chunks there are
        $totalChunks = (int) ceil($messages->count() / $chunkSize);

        // If startChunk is beyond our total chunks, return early.
        if ($startChunk > $totalChunks) {
            return;
        }

        // Save previous state to restore later.
        $previousLimit = $this->limit;
        $previousPage = $this->page;

        $this->limit = $chunkSize;

        // Iterate from the starting chunk to the last chunk.
        for ($page = $startChunk; $page <= $totalChunks; $page++) {
            $this->page = $page;

            // populate() will use $this->page to slice the results.
            $hydrated = $this->populate($messages);

            // If no messages are returned, break out to prevent infinite loop.
            if ($hydrated->isEmpty()) {
                break;
            }

            // If the callback returns false, break out.
            if ($callback($hydrated, $page) === false) {
                break;
            }
        }

        // Restore the original state.
        $this->limit = $previousLimit;
        $this->page = $previousPage;
    }

    /**
     * Paginate the current query.
     */
    public function paginate(int $perPage = 5, $page = null, string $pageName = 'page'): LengthAwarePaginator
    {
        if (is_null($page) && isset($_GET[$pageName]) && $_GET[$pageName] > 0) {
            $this->page = intval($_GET[$pageName]);
        } elseif ($page > 0) {
            $this->page = (int) $page;
        }

        $this->limit = $perPage;

        return $this->get()->paginate($perPage, $this->page, $pageName, true);
    }

    /**
     * Find a message by the given identifier type or throw an exception.
     */
    public function findOrFail(int $id, ImapFetchIdentifier $identifier = ImapFetchIdentifier::Uid): MessageInterface
    {
        /** @var UntaggedResponse $response */
        $response = $this->id($id, $identifier)->firstOrFail();

        $uid = $response->tokenAt(3) // ListData
            ->tokenAt(1) // Atom
            ->value; // UID

        return $this->process(new MessageCollection([$uid]))->firstOrFail();
    }

    /**
     * Find a message by the given identifier type.
     */
    public function find(int $id, ImapFetchIdentifier $identifier = ImapFetchIdentifier::Uid): ?MessageInterface
    {
        $response = $this->id($id, $identifier)->first();

        if (! $response instanceof UntaggedResponse) {
            return null;
        }

        $uid = $response->tokenAt(3) // ListData
            ->tokenAt(1) // Atom
            ->value; // UID

        return $this->process(new MessageCollection([$uid]))->first();
    }

    /**
     * Destroy the given messages.
     */
    public function destroy(array|int $uids, bool $expunge = false): void
    {
        $uids = (array) $uids;

        $this->folder->mailbox()
            ->connection()
            ->store([ImapFlag::Deleted->value], $uids, mode: '+');

        if ($expunge) {
            $this->folder->expunge();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function flag(BackedEnum|string $flag, string $operation, bool $expunge = false): int
    {
        $uids = $this->search()->all();

        if (empty($uids)) {
            return 0;
        }

        $this->connection()->store(
            (array) Str::enums($flag),
            $uids,
            mode: $operation
        );

        if ($expunge) {
            $this->folder->expunge();
        }

        return count($uids);
    }

    /**
     * {@inheritDoc}
     */
    public function markRead(): int
    {
        return $this->flag(ImapFlag::Seen, '+');
    }

    /**
     * {@inheritDoc}
     */
    public function markUnread(): int
    {
        return $this->flag(ImapFlag::Seen, '-');
    }

    /**
     * {@inheritDoc}
     */
    public function markFlagged(): int
    {
        return $this->flag(ImapFlag::Flagged, '+');
    }

    /**
     * {@inheritDoc}
     */
    public function unmarkFlagged(): int
    {
        return $this->flag(ImapFlag::Flagged, '-');
    }

    /**
     * {@inheritDoc}
     */
    public function delete(bool $expunge = false): int
    {
        return $this->flag(ImapFlag::Deleted, '+', $expunge);
    }

    /**
     * {@inheritDoc}
     */
    public function move(string $folder, bool $expunge = false): int
    {
        $uids = $this->search()->all();

        if (empty($uids)) {
            return 0;
        }

        $this->connection()->move($folder, $uids);

        if ($expunge) {
            $this->folder->expunge();
        }

        return count($uids);
    }

    /**
     * {@inheritDoc}
     */
    public function copy(string $folder): int
    {
        $uids = $this->search()->all();

        if (empty($uids)) {
            return 0;
        }

        $this->connection()->copy($folder, $uids);

        return count($uids);
    }

    /**
     * Process the collection of messages.
     */
    protected function process(Collection $messages): MessageCollection
    {
        if ($messages->isNotEmpty()) {
            return $this->populate($messages);
        }

        return MessageCollection::make();
    }

    /**
     * Populate a given id collection and receive a fully fetched message collection.
     */
    protected function populate(Collection $uids): MessageCollection
    {
        $messages = MessageCollection::make();

        $messages->total($uids->count());

        foreach ($this->fetch($uids) as $uid => $response) {
            $messages->push(
                $this->newMessage(
                    $uid,
                    $response['flags'] ?? [],
                    $response['head'] ?? '',
                    $response['body'] ?? '',
                    $response['size'] ?? null,
                    $response['bodystructure'] ?? null,
                )
            );
        }

        return $messages;
    }

    /**
     * Fetch a given id collection.
     */
    protected function fetch(Collection $messages): array
    {
        // Only apply client-side sorting when not using server-side sorting.
        // When sortKey is set, the IMAP SORT command already returns UIDs
        // in the correct order, so we should preserve that order.
        if (! $this->sortKey) {
            $messages = match ($this->fetchOrder) {
                'asc' => $messages->sort(SORT_NUMERIC),
                'desc' => $messages->sortDesc(SORT_NUMERIC),
            };
        }

        $uids = $messages->forPage($this->page, $this->limit)->values();

        $fetch = [];

        if ($this->fetchFlags) {
            $fetch[] = 'FLAGS';
        }

        if ($this->fetchSize) {
            $fetch[] = 'RFC822.SIZE';
        }

        if ($this->fetchHeaders) {
            $fetch[] = $this->fetchAsUnread
                ? 'BODY.PEEK[HEADER]'
                : 'BODY[HEADER]';
        }

        if ($this->fetchBody) {
            $fetch[] = $this->fetchAsUnread
                ? 'BODY.PEEK[TEXT]'
                : 'BODY[TEXT]';
        }

        if ($this->fetchBodyStructure) {
            $fetch[] = 'BODYSTRUCTURE';
        }

        if (empty($fetch)) {
            return $uids->mapWithKeys(fn (string|int $uid) => [
                $uid => [
                    'size' => null,
                    'flags' => [],
                    'head' => '',
                    'body' => '',
                    'bodystructure' => null,
                ],
            ])->all();
        }

        return $this->connection()->fetch($fetch, $uids->all())->mapWithKeys(function (UntaggedResponse $response) {
            $data = $response->tokenAt(3);

            if (! $data instanceof ListData) {
                throw new RuntimeException(sprintf(
                    'Expected instance of %s at index 3 in FETCH response, got %s',
                    ListData::class,
                    get_debug_type($data)
                ));
            }

            $uid = $data->lookup('UID')->value;

            $size = $data->lookup('RFC822.SIZE')?->value;

            return [
                $uid => [
                    'size' => $size ? (int) $size : null,
                    'flags' => $data->lookup('FLAGS')?->values() ?? [],
                    'head' => $data->lookup('[HEADER]')->value ?? '',
                    'body' => $data->lookup('[TEXT]')->value ?? '',
                    'bodystructure' => $data->lookup('BODYSTRUCTURE'),
                ],
            ];
        })->all();
    }

    /**
     * Execute an IMAP search request.
     */
    protected function search(): Collection
    {
        // If the query is empty, default to fetching all.
        if ($this->query->isEmpty()) {
            $this->query->all();
        }

        $response = $this->connection()->search([
            $this->query->toImap(),
        ]);

        return new Collection(array_map(
            fn (Token $token) => $token->value,
            $response->tokensAfter(2)
        ));
    }

    /**
     * Execute an IMAP UID SORT request using RFC 5256.
     */
    protected function sort(): Collection
    {
        if (! in_array('SORT', $this->folder->mailbox()->capabilities())) {
            throw new ImapCapabilityException(
                'Unable to sort messages. IMAP server does not support SORT capability.'
            );
        }

        if ($this->query->isEmpty()) {
            $this->query->all();
        }

        $response = $this->connection()->sort(
            $this->sortKey,
            $this->sortDirection,
            [$this->query->toImap()]
        );

        return new Collection(array_map(
            fn (Token $token) => $token->value,
            $response->tokensAfter(2)
        ));
    }

    /**
     * Get the UID for the given identifier.
     */
    protected function id(int $id, ImapFetchIdentifier $identifier = ImapFetchIdentifier::Uid): ResponseCollection
    {
        try {
            return $this->connection()->uid([$id], $identifier);
        } catch (ImapCommandException $e) {
            // IMAP servers may return an error if the message number is not found.
            // If the identifier being used is a message number, and the message
            // number is in the command tokens, we can assume this has occurred
            // and safely ignore the error and return an empty collection.
            if (
                $identifier === ImapFetchIdentifier::MessageNumber
                && in_array($id, $e->command()->tokens())
            ) {
                return ResponseCollection::make();
            }

            // Otherwise, re-throw the exception.
            throw $e;
        }
    }

    /**
     * Make a new message from given raw components.
     */
    protected function newMessage(int $uid, array $flags, string $head, string $body, ?int $size = null, ?ListData $bodystructure = null): Message
    {
        return new Message($this->folder, $uid, $flags, $head, $body, $size, $bodystructure);
    }

    /**
     * Get the connection instance.
     */
    protected function connection(): ConnectionInterface
    {
        return $this->folder->mailbox()->connection();
    }
}
