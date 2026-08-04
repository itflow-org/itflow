<?php

namespace DirectoryTree\ImapEngine\Testing;

use BackedEnum;
use DirectoryTree\ImapEngine\Collections\MessageCollection;
use DirectoryTree\ImapEngine\Connection\ImapQueryBuilder;
use DirectoryTree\ImapEngine\Enums\ImapFetchIdentifier;
use DirectoryTree\ImapEngine\MessageInterface;
use DirectoryTree\ImapEngine\MessageQueryInterface;
use DirectoryTree\ImapEngine\Pagination\LengthAwarePaginator;
use DirectoryTree\ImapEngine\QueriesMessages;

class FakeMessageQuery implements MessageQueryInterface
{
    use QueriesMessages;

    /**
     * Constructor.
     */
    public function __construct(
        protected FakeFolder $folder,
        protected ImapQueryBuilder $query = new ImapQueryBuilder
    ) {}

    /**
     * {@inheritDoc}
     */
    public function get(): MessageCollection
    {
        return new MessageCollection(
            $this->folder->getMessages()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return count(
            $this->folder->getMessages()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function first(): ?MessageInterface
    {
        return $this->get()->first();
    }

    /**
     * {@inheritDoc}
     */
    public function firstOrFail(): MessageInterface
    {
        return $this->get()->firstOrFail();
    }

    /**
     * {@inheritDoc}
     */
    public function append(string $message, mixed $flags = null): int
    {
        $uid = 1;

        if ($lastMessage = $this->get()->last()) {
            $uid = $lastMessage->uid() + 1;
        }

        $this->folder->addMessage(
            new FakeMessage($uid, $flags === null ? [] : $flags, $message)
        );

        return $uid;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function chunk(callable $callback, int $chunkSize = 10, int $startChunk = 1): void
    {
        $page = $startChunk;

        foreach ($this->get()->chunk($chunkSize) as $chunk) {
            if ($page < $startChunk) {
                $page++;

                continue;
            }

            // If the callback returns false, break out.
            if ($callback($chunk, $page) === false) {
                break;
            }

            $page++;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function paginate(int $perPage = 5, $page = null, string $pageName = 'page'): LengthAwarePaginator
    {
        return $this->get()->paginate($perPage, $page, $pageName);
    }

    /**
     * {@inheritDoc}
     */
    public function findOrFail(int $id, ImapFetchIdentifier $identifier = ImapFetchIdentifier::Uid): MessageInterface
    {
        return $this->get()->findOrFail($id);
    }

    /**
     * {@inheritDoc}
     */
    public function find(int $id, ImapFetchIdentifier $identifier = ImapFetchIdentifier::Uid): ?MessageInterface
    {
        return $this->get()->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function destroy(array|int $uids, bool $expunge = false): void
    {
        $messages = $this->get()->keyBy(
            fn (MessageInterface $message) => $message->uid()
        );

        foreach ((array) $uids as $uid) {
            $messages->pull($uid);
        }

        $this->folder->setMessages(
            $messages->values()->all()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function flag(BackedEnum|string $flag, string $operation, bool $expunge = false): int
    {
        return count($this->folder->getMessages());
    }

    /**
     * {@inheritDoc}
     */
    public function markRead(): int
    {
        return count($this->folder->getMessages());
    }

    /**
     * {@inheritDoc}
     */
    public function markUnread(): int
    {
        return count($this->folder->getMessages());
    }

    /**
     * {@inheritDoc}
     */
    public function markFlagged(): int
    {
        return count($this->folder->getMessages());
    }

    /**
     * {@inheritDoc}
     */
    public function unmarkFlagged(): int
    {
        return count($this->folder->getMessages());
    }

    /**
     * {@inheritDoc}
     */
    public function delete(bool $expunge = false): int
    {
        $count = count($this->folder->getMessages());

        $this->folder->setMessages([]);

        return $count;
    }

    /**
     * {@inheritDoc}
     */
    public function move(string $folder, bool $expunge = false): int
    {
        return count($this->folder->getMessages());
    }

    /**
     * {@inheritDoc}
     */
    public function copy(string $folder): int
    {
        return count($this->folder->getMessages());
    }
}
