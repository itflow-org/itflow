<?php

namespace DirectoryTree\ImapEngine\Connection\Responses;

use DirectoryTree\ImapEngine\Connection\Responses\Data\Data;
use DirectoryTree\ImapEngine\Connection\Tokens\Token;

trait HasTokens
{
    /**
     * Get the response tokens.
     *
     * @return Token[]|Data[]
     */
    abstract public function tokens(): array;

    /**
     * Get the response token at the given index.
     */
    public function tokenAt(int $index): Token|Data|null
    {
        return $this->tokens()[$index] ?? null;
    }

    /**
     * Get the response tokens after the given index.
     */
    public function tokensAfter(int $index): array
    {
        return array_slice($this->tokens(), $index);
    }
}
