<?php

namespace DirectoryTree\ImapEngine\Support;

use DirectoryTree\ImapEngine\BodyStructurePart;

class MimeMessage
{
    /**
     * Build a minimal MIME message from body structure metadata and raw content.
     */
    public static function make(BodyStructurePart $part, string $content): string
    {
        $headers = ["Content-Type: {$part->contentType()}"];

        if ($charset = $part->charset()) {
            $headers[0] .= "; charset=\"{$charset}\"";
        }

        if ($encoding = $part->encoding()) {
            $headers[] = "Content-Transfer-Encoding: {$encoding}";
        }

        return implode("\r\n", $headers)."\r\n\r\n".$content;
    }
}
