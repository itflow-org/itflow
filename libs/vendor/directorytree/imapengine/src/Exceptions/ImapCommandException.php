<?php

namespace DirectoryTree\ImapEngine\Exceptions;

use DirectoryTree\ImapEngine\Connection\ImapCommand;
use DirectoryTree\ImapEngine\Connection\Responses\Response;

class ImapCommandException extends Exception
{
    /**
     * The IMAP response.
     */
    protected Response $response;

    /**
     * The failed IMAP command.
     */
    protected ImapCommand $command;

    /**
     * Make a new instance from a failed command and response.
     */
    public static function make(ImapCommand $command, Response $response): static
    {
        $exception = new static(sprintf('IMAP command "%s" failed. Response: "%s"', $command, $response));

        $exception->command = $command;
        $exception->response = $response;

        return $exception;
    }

    /**
     * Get the failed IMAP command.
     */
    public function command(): ImapCommand
    {
        return $this->command;
    }

    /**
     * Get the IMAP response.
     */
    public function response(): Response
    {
        return $this->response;
    }
}
