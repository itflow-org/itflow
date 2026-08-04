<?php

namespace DirectoryTree\ImapEngine\Support;

use ZBateson\MailMimeParser\Header\ParameterHeader;

class MimeParameterParser
{
    /**
     * Parse and normalize MIME parameters.
     *
     * @param  array<string, string>  $parameters
     * @return array<string, string>
     */
    public static function parse(array $parameters): array
    {
        if (empty($parameters)) {
            return [];
        }

        $header = new ParameterHeader(
            'Content-Type',
            'application/octet-stream; '.implode('; ', static::stringify($parameters))
        );

        $parsed = [];

        foreach (array_keys($parameters) as $name) {
            $name = strtolower(explode('*', $name, 2)[0]);

            if (array_key_exists($name, $parsed)) {
                continue;
            }

            if (! is_null($value = $header->getValueFor($name))) {
                $parsed[$name] = $value;
            }
        }

        return $parsed;
    }

    /**
     * Convert parameter values into MIME parameter syntax.
     *
     * @param  array<string, string>  $parameters
     * @return string[]
     */
    protected static function stringify(array $parameters): array
    {
        $values = [];

        foreach ($parameters as $name => $value) {
            $values[] = sprintf('%s="%s"', $name, Str::escape($value));
        }

        return $values;
    }
}
