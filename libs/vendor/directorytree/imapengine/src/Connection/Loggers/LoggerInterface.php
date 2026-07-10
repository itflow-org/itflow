<?php

namespace DirectoryTree\ImapEngine\Connection\Loggers;

interface LoggerInterface
{
    /**
     * Log when a message is sent.
     */
    public function sent(string $message): void;

    /**
     * Log when a message is received.
     */
    public function received(string $message): void;
}
