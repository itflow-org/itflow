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
 * Represents a mime header comment -- text in a structured mime header
 * value existing within parentheses.
 *
 * @author Zaahid Bateson
 */
class CommentPart extends ContainerPart
{
    /**
     * @var HeaderPartFactory used to create intermediate parts.
     */
    protected HeaderPartFactory $partFactory;

    /**
     * @var string the contents of the comment
     */
    protected string $comment;

    public function __construct(
        LoggerInterface $logger,
        MbWrapper $charsetConverter,
        HeaderPartFactory $partFactory,
        array $children
    ) {
        $this->partFactory = $partFactory;
        parent::__construct($logger, $charsetConverter, $children);
        $this->comment = $this->value;
        $this->value = '';
        $this->isSpace = true;
        $this->canIgnoreSpacesBefore = true;
        $this->canIgnoreSpacesAfter = true;
    }

    protected function getValueFromParts(array $parts) : string
    {
        $partFactory = $this->partFactory;
        return parent::getValueFromParts(\array_map(
            function($p) use ($partFactory) {
                if ($p instanceof CommentPart) {
                    return $partFactory->newQuotedLiteralPart([$partFactory->newToken('(' . $p->getComment() . ')')]);
                } elseif ($p instanceof QuotedLiteralPart) {
                    return $partFactory->newQuotedLiteralPart([$partFactory->newToken('"' . \str_replace('(["\\])', '\$1', $p->getValue()) . '"')]);
                }
                return $p;
            },
            $parts
        ));
    }

    /**
     * Returns the comment's text.
     */
    public function getComment() : string
    {
        return $this->comment;
    }

    /**
     * Returns an empty string.
     */
    public function getValue() : string
    {
        return '';
    }
}
