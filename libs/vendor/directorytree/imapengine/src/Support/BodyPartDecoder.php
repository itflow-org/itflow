<?php

namespace DirectoryTree\ImapEngine\Support;

use DirectoryTree\ImapEngine\BodyStructurePart;
use DirectoryTree\ImapEngine\MessageParser;

class BodyPartDecoder
{
    /**
     * Decode raw text/html content using the part's metadata.
     */
    public static function text(BodyStructurePart $part, ?string $content): ?string
    {
        $content = rtrim($content ?? '', "\r\n");

        if ($content === '') {
            return null;
        }

        $parsed = MessageParser::parse(
            MimeMessage::make($part, $content)
        );

        return $part->subtype() === 'html'
            ? $parsed->getHtmlContent()
            : $parsed->getTextContent();
    }

    /**
     * Decode raw binary content using the part's metadata.
     */
    public static function binary(BodyStructurePart $part, ?string $content): ?string
    {
        $content = rtrim($content ?? '', "\r\n");

        if ($content === '') {
            return null;
        }

        $parsed = MessageParser::parse(
            MimeMessage::make($part, $content)
        );

        return $parsed->getBinaryContentStream()?->getContents();
    }
}
