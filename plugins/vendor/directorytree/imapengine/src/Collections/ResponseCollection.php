<?php

namespace DirectoryTree\ImapEngine\Collections;

use DirectoryTree\ImapEngine\Connection\Responses\ContinuationResponse;
use DirectoryTree\ImapEngine\Connection\Responses\TaggedResponse;
use DirectoryTree\ImapEngine\Connection\Responses\UntaggedResponse;
use Illuminate\Support\Collection;

/**
 * @template TKey of array-key
 *
 * @template-covariant TValue
 *
 * @extends Collection<array-key, TValue>
 */
class ResponseCollection extends Collection
{
    /**
     * Filter the collection to only tagged responses.
     *
     * @return self<array-key, TaggedResponse>
     */
    public function tagged(): self
    {
        return $this->whereInstanceOf(TaggedResponse::class);
    }

    /**
     * Filter the collection to only untagged responses.
     *
     * @return self<array-key, UntaggedResponse>
     */
    public function untagged(): self
    {
        return $this->whereInstanceOf(UntaggedResponse::class);
    }

    /**
     * Filter the collection to only continuation responses.
     *
     * @return self<array-key, ContinuationResponse>
     */
    public function continuation(): self
    {
        return $this->whereInstanceOf(ContinuationResponse::class);
    }
}
