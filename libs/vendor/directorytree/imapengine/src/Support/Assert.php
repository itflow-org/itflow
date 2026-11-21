<?php

namespace DirectoryTree\ImapEngine\Support;

use InvalidArgumentException;

class Assert
{
    /**
     * Ensure command syntax cannot contain control characters.
     */
    public static function hasNoControlCharacters(string $token): void
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $token)) {
            throw new InvalidArgumentException('IMAP command syntax cannot contain control characters.');
        }
    }

    /**
     * Ensure a flag contains a valid IMAP atom.
     */
    public static function isValidFlag(string $flag): void
    {
        $atom = str_starts_with($flag, '\\') ? substr($flag, 1) : $flag;

        if ($atom === '' || preg_match('/[^\x21-\x7E]/', $atom) || strpbrk($atom, '(){%*"\\]') !== false) {
            throw new InvalidArgumentException('Invalid IMAP flag.');
        }
    }

    /**
     * Ensure a sequence set contains valid message numbers and ranges.
     */
    public static function isValidSequenceSet(string $set): void
    {
        if (! preg_match('/\A(?:[1-9][0-9]*|\*)(?::(?:[1-9][0-9]*|\*))?(?:,(?:[1-9][0-9]*|\*)(?::(?:[1-9][0-9]*|\*))?)*\z/', $set)) {
            throw new InvalidArgumentException('Invalid IMAP sequence set.');
        }
    }
}
