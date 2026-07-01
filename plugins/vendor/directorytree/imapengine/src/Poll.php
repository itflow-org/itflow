<?php

namespace DirectoryTree\ImapEngine;

use Closure;
use DirectoryTree\ImapEngine\Exceptions\Exception;
use DirectoryTree\ImapEngine\Exceptions\ImapConnectionClosedException;

class Poll
{
    /**
     * The last seen message UID.
     */
    protected ?int $lastSeenUid = null;

    /**
     * Constructor.
     */
    public function __construct(
        protected Mailbox $mailbox,
        protected string $folder,
        protected Closure|int $frequency,
    ) {}

    /**
     * Destructor.
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Poll for new messages at a given frequency.
     */
    public function start(callable $callback, callable $query): void
    {
        $this->connect();

        while ($frequency = $this->getNextFrequency()) {
            try {
                $this->check($callback, $query);
            } catch (ImapConnectionClosedException) {
                $this->reconnect();
            }

            sleep($frequency);
        }
    }

    /**
     * Check for new messages since the last seen UID.
     */
    protected function check(callable $callback, callable $query): void
    {
        $folder = $this->folder();

        // If we don't have a last seen UID, we will fetch
        // the last one in the folder as a starting point.
        if (! $this->lastSeenUid) {
            $this->lastSeenUid = $folder->messages()
                ->first()
                ?->uid() ?? 0;

            return;
        }

        $query($folder->messages())
            ->uid($this->lastSeenUid + 1, INF)
            ->each(function (MessageInterface $message) use ($callback) {
                // Avoid processing the same message twice on subsequent polls.
                // Some IMAP servers will always return the last seen UID in
                // the search results regardless of given UID search range.
                if ($this->lastSeenUid === $message->uid()) {
                    return;
                }

                $callback($message);

                $this->lastSeenUid = $message->uid();
            });
    }

    /**
     * Get the folder to poll.
     */
    protected function folder(): FolderInterface
    {
        return $this->mailbox->folders()->findOrFail($this->folder);
    }

    /**
     * Reconnect the client and restart the poll session.
     */
    protected function reconnect(): void
    {
        $this->mailbox->disconnect();

        $this->connect();
    }

    /**
     * Connect the client and select the folder to poll.
     */
    protected function connect(): void
    {
        $this->mailbox->connect();

        $this->mailbox->select($this->folder(), true);
    }

    /**
     * Disconnect the client.
     */
    protected function disconnect(): void
    {
        try {
            $this->mailbox->disconnect();
        } catch (Exception) {
            // Do nothing.
        }
    }

    /**
     * Get the next frequency in seconds.
     */
    protected function getNextFrequency(): int|false
    {
        if (is_numeric($seconds = value($this->frequency))) {
            return abs((int) $seconds);
        }

        return false;
    }
}
