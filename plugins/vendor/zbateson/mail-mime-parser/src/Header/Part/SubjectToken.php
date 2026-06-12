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
 * Specialized token for subjects that preserves whitespace, except for new
 * lines.
 *
 * New lines are either discarded if followed by a whitespace as should happen
 * with folding whitespace, or replaced by a single space character if somehow
 * aren't followed by whitespace.
 *
 * @author Zaahid Bateson
 */
class SubjectToken extends Token
{
    public function __construct(
        LoggerInterface $logger,
        MbWrapper $charsetConverter,
        string $value
    ) {
        parent::__construct($logger, $charsetConverter, $value, true);
        $this->value = \preg_replace(['/(\r|\n)+(\s)\s*/', '/(\r|\n)+/'], ['$2', ' '], $value);
        $this->isSpace = (\preg_match('/^\s*$/m', $this->value) === 1);
        $this->canIgnoreSpacesBefore = $this->canIgnoreSpacesAfter = $this->isSpace;
    }

    public function getValue() : string
    {
        return $this->convertEncoding($this->value);
    }
}
