<?php

namespace DirectoryTree\ImapEngine\Connection\Responses;

use DirectoryTree\ImapEngine\Connection\Responses\Data\ResponseCodeData;

class MessageResponseParser
{
    /**
     * Get the UID from a tagged move or copy response.
     */
    public static function getUidFromCopy(TaggedResponse $response): ?int
    {
        if (! $data = $response->tokenAt(2)) {
            return null;
        }

        if (! $data instanceof ResponseCodeData) {
            return null;
        }

        if (! $value = $data->tokenAt(3)?->value) {
            return null;
        }

        return (int) $value;
    }
}
