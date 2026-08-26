<?php

namespace DirectoryTree\ImapEngine\Connection;

use Stringable;

class ImapCommandLine implements Stringable
{
    /**
     * Constructor.
     */
    public function __construct(
        public string $value,
        public bool $synchronizing = false,
    ) {}

    /**
     * Get the command line as a string.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
