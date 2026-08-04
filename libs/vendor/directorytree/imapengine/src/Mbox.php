<?php

namespace DirectoryTree\ImapEngine;

use DirectoryTree\ImapEngine\Exceptions\RuntimeException;
use Generator;

class Mbox
{
    /**
     * Constructor.
     */
    public function __construct(
        protected string $filepath
    ) {}

    /**
     * Get the messages from the mbox file.
     */
    public function messages(
        string $delimiter = '/^From\s+\S+\s+' // From
        .'(Mon|Tue|Wed|Thu|Fri|Sat|Sun)\s+' // Day
        .'(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+' // Month
        .'\d{1,2}\s+\d{2}:\d{2}:\d{2}' // Time (HH:MM:SS)
        .'(?:\s+[+-]\d{4})?' // Optional Timezone ("+0000")
        .'\s+\d{4}/' // Year
    ): Generator {
        if (! $handle = fopen($this->filepath, 'r')) {
            throw new RuntimeException('Failed to open mbox file: '.$this->filepath);
        }

        $buffer = '';

        while (($line = fgets($handle)) !== false) {
            if (preg_match($delimiter, $line) && $buffer !== '') {
                yield new FileMessage($buffer);

                $buffer = '';
            }

            $buffer .= $line;
        }

        if ($buffer !== '') {
            yield new FileMessage($buffer);
        }

        fclose($handle);
    }
}
