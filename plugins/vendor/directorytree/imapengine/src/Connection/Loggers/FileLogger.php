<?php

namespace DirectoryTree\ImapEngine\Connection\Loggers;

class FileLogger extends Logger
{
    /**
     * Constructor.
     */
    public function __construct(
        protected string $path
    ) {}

    /**
     * {@inheritDoc}
     */
    public function write(string $message): void
    {
        file_put_contents($this->path, $message, FILE_APPEND);
    }
}
