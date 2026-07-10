<?php

namespace DirectoryTree\ImapEngine\Connection\Loggers;

class EchoLogger extends Logger
{
    /**
     * {@inheritDoc}
     */
    public function write(string $message): void
    {
        echo $message;
    }
}
