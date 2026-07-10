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
 * Holds a running value for an RFC-2231 split header parameter.
 *
 * ParameterConsumer creates SplitParameterTokens when a split header parameter
 * is first found, and adds subsequent split parts to an already created one if
 * the parameter name matches.
 *
 * @author Zaahid Bateson
 */
class SplitParameterPart extends ParameterPart
{
    /**
     * @var HeaderPartFactory used to create combined MimeToken parts.
     */
    protected HeaderPartFactory $partFactory;

    /**
     * Initializes a SplitParameterToken.
     *
     * @param ParameterPart[] $children
     */
    public function __construct(
        LoggerInterface $logger,
        MbWrapper $charsetConverter,
        HeaderPartFactory $headerPartFactory,
        array $children
    ) {
        $this->partFactory = $headerPartFactory;
        NameValuePart::__construct($logger, $charsetConverter, [$children[0]], $children);
        $this->children = $children;
    }

    protected function getNameFromParts(array $parts) : string
    {
        return $parts[0]->getName();
    }

    private function getMimeTokens(string $value) : array
    {
        $pattern = MimeToken::MIME_PART_PATTERN;
        // remove whitespace between two adjacent mime encoded parts
        $normed = \preg_replace("/($pattern)\\s+(?=$pattern)/", '$1', $value);
        // with PREG_SPLIT_DELIM_CAPTURE, matched and unmatched parts are returned
        $aMimeParts = \preg_split("/($pattern)/", $normed, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        return \array_map(
            fn ($p) => (\preg_match("/$pattern/", $p)) ? $this->partFactory->newMimeToken($p) : $this->partFactory->newToken($p, true, true),
            $aMimeParts
        );
    }

    private function combineAdjacentUnencodedParts(array $parts) : array
    {
        $runningValue = '';
        $returnedParts = [];
        foreach ($parts as $part) {
            if (!$part->encoded) {
                $runningValue .= $part->value;
                continue;
            }
            if (!empty($runningValue)) {
                $returnedParts = \array_merge($returnedParts, $this->getMimeTokens($runningValue));
                $runningValue = '';
            }
            $returnedParts[] = $part;
        }
        if (!empty($runningValue)) {
            $returnedParts = \array_merge($returnedParts, $this->getMimeTokens($runningValue));
        }
        return $returnedParts;
    }

    protected function getValueFromParts(array $parts) : string
    {
        $sorted = $parts;
        \usort($sorted, fn ($a, $b) => $a->index <=> $b->index);

        $first = $sorted[0];
        $this->language = $first->language;
        $charset = $this->charset = $first->charset;

        $combined = $this->combineAdjacentUnencodedParts($sorted);

        return \implode('', \array_map(
            fn ($p) => ($p instanceof ParameterPart && $p->encoded)
                ? $this->decodePartValue($p->getValue(), ($p->charset === null) ? $charset : $p->charset)
                : $p->getValue(),
            $combined
        ));
    }
}
