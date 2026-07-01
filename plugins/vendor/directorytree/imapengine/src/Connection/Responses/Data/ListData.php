<?php

namespace DirectoryTree\ImapEngine\Connection\Responses\Data;

use DirectoryTree\ImapEngine\Connection\Tokens\Token;

class ListData extends Data
{
    /**
     * Find the immediate successor token of the given field in the list.
     */
    public function lookup(string $field): Data|Token|null
    {
        foreach ($this->tokens as $index => $token) {
            if ((string) $token === $field) {
                return $this->tokenAt(++$index);
            }
        }

        return null;
    }

    /**
     * Convert alternating key/value tokens to an associative array.
     */
    public function toKeyValuePairs(): array
    {
        $pairs = [];

        for ($i = 0; $i < count($this->tokens) - 1; $i += 2) {
            $key = strtolower($this->tokens[$i]->value);

            $pairs[$key] = $this->tokens[$i + 1]->value;
        }

        return $pairs;
    }

    /**
     * Get the list as a string.
     */
    public function __toString(): string
    {
        return sprintf('(%s)', implode(
            ' ', array_map('strval', $this->tokens)
        ));
    }
}
