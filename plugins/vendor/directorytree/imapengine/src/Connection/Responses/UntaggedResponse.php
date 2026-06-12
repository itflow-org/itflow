<?php

namespace DirectoryTree\ImapEngine\Connection\Responses;

use DirectoryTree\ImapEngine\Connection\Tokens\Atom;
use DirectoryTree\ImapEngine\Connection\Tokens\Number;

class UntaggedResponse extends Response
{
    /**
     * Get the response type token.
     */
    public function type(): Atom|Number
    {
        return $this->tokens[1];
    }

    /**
     * Get the data tokens.
     *
     * @return Atom[]
     */
    public function data(): array
    {
        return array_slice($this->tokens, 2);
    }
}
