<?php

namespace DirectoryTree\ImapEngine\Connection\Loggers;

abstract class Logger implements LoggerInterface
{
    /**
     * Write a message to the log.
     */
    abstract protected function write(string $message): void;

    /**
     * {@inheritDoc}
     */
    public function sent(string $message): void
    {
        $this->write(sprintf('%s: >> %s', $this->date(), $message).PHP_EOL);
    }

    /**
     * {@inheritDoc}
     */
    public function received(string $message): void
    {
        $this->write(sprintf('%s: << %s', $this->date(), $message).PHP_EOL);
    }

    /**
     * Get the current date and time.
     */
    protected function date(): string
    {
        return date('Y-m-d H:i:s');
    }
}
