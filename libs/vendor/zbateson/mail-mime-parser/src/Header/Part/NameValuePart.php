<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Part;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use ZBateson\MailMimeParser\ErrorBag;
use ZBateson\MbWrapper\MbWrapper;

/**
 * Represents a name/value pair part of a header.
 *
 * @author Zaahid Bateson
 */
class NameValuePart extends ContainerPart
{
    /**
     * @var string the name of the part
     */
    protected string $name;

    public function __construct(
        LoggerInterface $logger,
        MbWrapper $charsetConverter,
        array $nameParts,
        array $valueParts
    ) {
        ErrorBag::__construct($logger);
        $this->charsetConverter = $charsetConverter;
        $this->name = (!empty($nameParts)) ? $this->getNameFromParts($nameParts) : '';
        parent::__construct($logger, $charsetConverter, $valueParts);
        \array_unshift($this->children, ...$nameParts);
    }

    /**
     * Creates the string 'name' representation of this part constructed from
     * the child name parts passed to it.
     *
     * @param HeaderParts[] $parts
     */
    protected function getNameFromParts(array $parts) : string
    {
        return \array_reduce($this->filterIgnoredSpaces($parts), fn ($c, $p) => $c . $p->getValue(), '');
    }

    /**
     * Returns the name of the name/value part.
     */
    public function getName() : string
    {
        return $this->name;
    }

    protected function validate() : void
    {
        if ($this->value === '') {
            $this->addError('NameValuePart value is empty', LogLevel::NOTICE);
        }
    }
}
