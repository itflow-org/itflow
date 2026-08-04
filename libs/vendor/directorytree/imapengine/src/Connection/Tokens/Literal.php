<?php

namespace DirectoryTree\ImapEngine\Connection\Tokens;

class Literal extends Token
{
    /**
     * Get the token's value.
     */
    public function __toString(): string
    {
        return sprintf("{%d}\r\n%s", strlen($this->value), $this->value);
    }
}
