<?php

namespace DirectoryTree\ImapEngine\Connection;

use Stringable;

class ImapCommand implements Stringable
{
    /**
     * The compiled command lines.
     *
     * @var string[]
     */
    protected ?array $compiled = null;

    /**
     * Constructor.
     */
    public function __construct(
        protected string $tag,
        protected string $command,
        protected array $tokens = [],
    ) {}

    /**
     * Get the IMAP tag.
     */
    public function tag(): string
    {
        return $this->tag;
    }

    /**
     * Get the IMAP command.
     */
    public function command(): string
    {
        return $this->command;
    }

    /**
     * Get the IMAP tokens.
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    /**
     * Compile the command into lines for transmission.
     *
     * @return string[]
     */
    public function compile(): array
    {
        if (is_array($this->compiled)) {
            return $this->compiled;
        }

        $lines = [];

        $line = trim("{$this->tag} {$this->command}");

        foreach ($this->tokens as $token) {
            if (is_array($token)) {
                // For tokens provided as arrays, the first element is a placeholder
                // (for example, "{20}") that signals a literal value will follow.
                // The second element holds the actual literal content.
                [$placeholder, $literal] = $token;

                $lines[] = "{$line} {$placeholder}";

                $line = $literal;
            } else {
                $line .= " {$token}";
            }
        }

        $lines[] = $line;

        return $this->compiled = $lines;
    }

    /**
     * Get a redacted version of the command for safe exposure.
     */
    public function redacted(): ImapCommand
    {
        return new static($this->tag, $this->command, array_map(
            function (mixed $token) {
                return is_array($token)
                    ? array_map(fn () => '[redacted]', $token)
                    : '[redacted]';
            }, $this->tokens)
        );
    }

    /**
     * Get the command as a string.
     */
    public function __toString(): string
    {
        return implode("\r\n", $this->compile());
    }
}
