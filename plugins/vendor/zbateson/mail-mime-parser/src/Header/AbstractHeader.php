<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use ZBateson\MailMimeParser\ErrorBag;
use ZBateson\MailMimeParser\Header\Consumer\IConsumerService;
use ZBateson\MailMimeParser\Header\Part\CommentPart;
use ZBateson\MailMimeParser\MailMimeParser;

/**
 * Abstract base class representing a mime email's header.
 *
 * The base class sets up the header's consumer for parsing, sets the name of
 * the header, and calls the consumer to parse the header's value.
 *
 * @author Zaahid Bateson
 */
abstract class AbstractHeader extends ErrorBag implements IHeader
{
    /**
     * @var string the name of the header
     */
    protected string $name;

    /**
     * @var IHeaderPart[] all parts not including CommentParts.
     */
    protected array $parts = [];

    /**
     * @var IHeaderPart[] the header's parts (as returned from the consumer),
     *      including commentParts
     */
    protected array $allParts = [];

    /**
     * @var string the raw value
     */
    protected string $rawValue;

    /**
     * @var string[] array of comments, initialized on demand in getComments()
     */
    private ?array $comments = null;

    /**
     * Assigns the header's name and raw value, then calls parseHeaderValue to
     * extract a parsed value.
     *
     * @param IConsumerService $consumerService For parsing the value.
     * @param string $name Name of the header.
     * @param string $value Value of the header.
     */
    public function __construct(
        LoggerInterface $logger,
        IConsumerService $consumerService,
        string $name,
        string $value
    ) {
        parent::__construct($logger);
        $this->name = $name;
        $this->rawValue = $value;
        $this->parseHeaderValue($consumerService, $value);
    }

    /**
     * Filters $this->allParts into the parts required by $this->parts
     * and assigns it.
     *
     * The AbstractHeader::filterAndAssignToParts method filters out CommentParts.
     */
    protected function filterAndAssignToParts() : void
    {
        $this->parts = \array_values(\array_filter($this->allParts, function($p) {
            return !($p instanceof CommentPart);
        }));
    }

    /**
     * Calls the consumer and assigns the parsed parts to member variables.
     *
     * The default implementation assigns the returned value to $this->allParts
     * and filters out comments from it, assigning the filtered array to
     * $this->parts by calling filterAndAssignToParts.
     */
    protected function parseHeaderValue(IConsumerService $consumer, string $value) : void
    {
        $this->allParts = $consumer($value);
        $this->filterAndAssignToParts();
    }

    /**
     * @return IHeaderPart[]
     */
    public function getParts() : array
    {
        return $this->parts;
    }

    /**
     * @return IHeaderPart[]
     */
    public function getAllParts() : array
    {
        return $this->allParts;
    }

    /**
     * @return string[]
     */
    public function getComments() : array
    {
        if ($this->comments === null) {
            $this->comments = \array_map(fn (IHeaderPart $c) => $c->getComment(), \array_merge(...\array_map(
                fn ($p) => ($p instanceof CommentPart) ? [$p] : $p->getComments(),
                $this->allParts
            )));
        }
        return $this->comments;
    }

    public function getValue() : ?string
    {
        if (!empty($this->parts)) {
            return $this->parts[0]->getValue();
        }
        return null;
    }

    public function getRawValue() : string
    {
        return $this->rawValue;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function __toString() : string
    {
        return "{$this->name}: {$this->rawValue}";
    }

    public function getErrorLoggingContextName() : string
    {
        return 'Header::' . $this->getName();
    }

    protected function getErrorBagChildren() : array
    {
        return $this->getAllParts();
    }

    protected function validate() : void
    {
        if (\strlen(\trim($this->name)) === 0) {
            $this->addError('Header doesn\'t have a name', LogLevel::ERROR);
        }
        if (\strlen(\trim($this->rawValue)) === 0) {
            $this->addError('Header doesn\'t have a value', LogLevel::NOTICE);
        }
    }

    /**
     * Checks if the passed $value parameter is null, and if so tries to parse
     * a header line from $nameOrLine splitting on first occurrence of a ':'
     * character.
     *
     * The returned array always contains two elements.  The first being the
     * name (or blank if a ':' char wasn't found and $value is null), and the
     * second being the value.
     *
     * @return string[]
     */
    protected static function getHeaderPartsFrom(string $nameOrLine, ?string $value = null) : array
    {
        $namePart = $nameOrLine;
        $valuePart = $value;
        if ($value === null) {
            // full header line
            $parts = \explode(':', $nameOrLine, 2);
            $namePart = (\count($parts) > 1) ? $parts[0] : '';
            $valuePart = \trim((\count($parts) > 1) ? $parts[1] : $parts[0]);
        }
        return [$namePart, $valuePart];
    }

    /**
     * Parses the passed parameters into an IHeader object.
     *
     * The type of returned IHeader is determined by the name of the header.
     * See {@see HeaderFactory::newInstance} for more details.
     *
     * The required $nameOrLine parameter may contain either the name of a
     * header to parse, or a full header line, e.g. From: email@example.com.  If
     * passing a full header line, the $value parameter must be set to null (the
     * default).
     *
     * Note that more specific types can be called on directly.  For instance an
     * AddressHeader may be created by calling AddressHeader::from() which will
     * ignore the name of the header, and always return an AddressHeader, or by
     * calling `new AddressHeader('name', 'value')` directly.
     *
     * @param string $nameOrLine The header's name or full header line.
     * @param string|null $value The header's value, or null if passing a full
     *        header line to parse.
     */
    public static function from(string $nameOrLine, ?string $value = null) : IHeader
    {
        $parts = static::getHeaderPartsFrom($nameOrLine, $value);
        $container = MailMimeParser::getGlobalContainer();
        $hf = $container->get(HeaderFactory::class);
        if (self::class !== static::class) {
            return $hf->newInstanceOf($parts[0], $parts[1], static::class);
        }
        return $hf->newInstance($parts[0], $parts[1]);
    }
}
