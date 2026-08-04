<?php

namespace DirectoryTree\ImapEngine\Connection;

use DirectoryTree\ImapEngine\Collections\ResponseCollection;
use DirectoryTree\ImapEngine\Connection\Responses\Response;

class Result
{
    /**
     * Constructor.
     */
    public function __construct(
        protected ImapCommand $command,
        protected array $responses = [],
    ) {}

    /**
     * Get the executed command.
     */
    public function command(): ImapCommand
    {
        return $this->command;
    }

    /**
     * Add a response to the result.
     */
    public function addResponse(Response $response): void
    {
        $this->responses[] = $response;
    }

    /**
     * Get the recently received responses.
     */
    public function responses(): ResponseCollection
    {
        return new ResponseCollection($this->responses);
    }
}
