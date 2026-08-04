<?php

namespace DirectoryTree\ImapEngine;

use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\MailMimeParser;

class MessageParser
{
    /**
     * The mail mime parser instance.
     */
    protected static ?MailMimeParser $parser = null;

    /**
     * Parse the given message contents.
     */
    public static function parse(string $contents): IMessage
    {
        return static::parser()->parse($contents, true);
    }

    /**
     * Get the mail mime parser instance.
     */
    protected static function parser(): MailMimeParser
    {
        return static::$parser ??= new MailMimeParser;
    }
}
