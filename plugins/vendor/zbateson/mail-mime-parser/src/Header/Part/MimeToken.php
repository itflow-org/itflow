<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Part;

use Psr\Log\LoggerInterface;
use ZBateson\MbWrapper\MbWrapper;

/**
 * Represents a single mime header part token, with the possibility of it being
 * MIME-Encoded as per RFC-2047.
 *
 * MimeToken automatically decodes the value if it's encoded.
 *
 * @author Zaahid Bateson
 */
class MimeToken extends Token
{
    /**
     * @var string regex pattern matching a mime-encoded part
     */
    public const MIME_PART_PATTERN = '=\?[^?=]+\?[QBqb]\?[^\?]+\?=';

    /**
     * @var string regex pattern used when parsing parameterized headers
     */
    public const MIME_PART_PATTERN_NO_QUOTES = '=\?[^\?=]+\?[QBqb]\?[^\?"]+\?=';

    /**
     * @var ?string the language code if any, or null otherwise
     */
    protected ?string $language = null;

    /**
     * @var ?string the charset if any, or null otherwise
     */
    protected ?string $charset = null;

    public function __construct(LoggerInterface $logger, MbWrapper $charsetConverter, string $value)
    {
        parent::__construct($logger, $charsetConverter, $value);
        $this->value = $this->decodeMime(\preg_replace('/\r|\n/', '', $this->value));
        $pattern = self::MIME_PART_PATTERN;
        $this->canIgnoreSpacesBefore = (bool) \preg_match("/^\s*{$pattern}|\s+/", $this->rawValue);
        $this->canIgnoreSpacesAfter = (bool) \preg_match("/{$pattern}\s*|\s+\$/", $this->rawValue);
    }

    /**
     * Finds and replaces mime parts with their values.
     *
     * The method splits the token value into an array on mime-part-patterns,
     * either replacing a mime part with its value by calling iconv_mime_decode
     * or converts the encoding on the text part by calling convertEncoding.
     */
    protected function decodeMime(string $value) : string
    {
        if (\preg_match('/^=\?([A-Za-z\-_0-9]+)\*?([A-Za-z\-_0-9]+)?\?([QBqb])\?([^\?]*)\?=$/', $value, $matches)) {
            return $this->decodeMatchedEntity($matches);
        }
        return $this->convertEncoding($value);
    }

    /**
     * Decodes a matched mime entity part into a string and returns it, after
     * adding the string into the languages array.
     *
     * @param string[] $matches
     */
    private function decodeMatchedEntity(array $matches) : string
    {
        $body = $matches[4];
        if (\strtoupper($matches[3]) === 'Q') {
            $body = \quoted_printable_decode(\str_replace('_', '=20', $body));
        } else {
            $body = \base64_decode($body);
        }
        $this->charset = $matches[1];
        $this->language = (!empty($matches[2])) ? $matches[2] : null;
        if ($this->charset !== null) {
            return $this->convertEncoding($body, $this->charset, true);
        }
        return $this->convertEncoding($body, 'ISO-8859-1', true);
    }

    /**
     * Returns the language code for the mime part.
     */
    public function getLanguage() : ?string
    {
        return $this->language;
    }

    /**
     * Returns the charset for the encoded part.
     */
    public function getCharset() : ?string
    {
        return $this->charset;
    }

    public function getRawValue() : string
    {
        return $this->rawValue;
    }
}
