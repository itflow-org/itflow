<?php

namespace DirectoryTree\ImapEngine\Connection\Loggers;

class RayLogger extends Logger
{
    /**
     * {@inheritDoc}
     */
    protected function write(string $message): void
    {
        ray($message);
    }
}
