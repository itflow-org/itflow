<?php

namespace DirectoryTree\ImapEngine\Connection\Responses\Data;

class ResponseCodeData extends Data
{
    /**
     * Get the group as a string.
     */
    public function __toString(): string
    {
        return sprintf('[%s]', implode(
            ' ', array_map('strval', $this->tokens)
        ));
    }
}
