<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

use ArrayIterator;
use Iterator;
use NoRewindIterator;
use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\IHeaderPart;
use ZBateson\MailMimeParser\Header\Part\HeaderPartFactory;
use ZBateson\MailMimeParser\Header\Part\MimeToken;

/**
 * Abstract base class for all header token consumers.
 *
 * Defines the base parser that loops over tokens, consuming them and creating
 * header parts.
 *
 * @author Zaahid Bateson
 */
abstract class AbstractConsumerService implements IConsumerService
{
    protected LoggerInterface $logger;

    /**
     * @var HeaderPartFactory used to construct IHeaderPart objects
     */
    protected HeaderPartFactory $partFactory;

    /**
     * @var AbstractConsumerService[] array of sub-consumers used by this
     *      consumer if any, or an empty array if none exist.
     */
    protected array $subConsumers = [];

    /**
     * @var ?string the generated token split pattern on first run, so it doesn't
     *      need to be regenerated every time.
     */
    private ?string $tokenSplitPattern = null;

    /**
     * @param AbstractConsumerService[] $subConsumers
     */
    public function __construct(LoggerInterface $logger, HeaderPartFactory $partFactory, array $subConsumers = [])
    {
        $this->logger = $logger;
        $this->partFactory = $partFactory;
        $this->subConsumers = $subConsumers;
    }

    public function __invoke(string $value) : array
    {
        $this->logger->debug('Starting {class} for "{value}"', ['class' => static::class, 'value' => $value]);
        if ($value !== '') {
            $parts = $this->parseRawValue($value);
            $this->logger->debug(
                'Ending {class} for "{value}": parsed into {cnt} header part objects',
                ['class' => static::class, 'value' => $value, 'cnt' => \count($parts)]
            );
            return $parts;
        }
        return [];
    }

    /**
     * Returns this consumer and all unique sub consumers.
     *
     * Loops into the sub-consumers (and their sub-consumers, etc...) finding
     * all unique consumers, and returns them in an array.
     *
     * @return AbstractConsumerService[] Array of unique consumers.
     */
    protected function getAllConsumers() : array
    {
        $found = [$this];
        do {
            $current = \current($found);
            $subConsumers = $current->subConsumers;
            foreach ($subConsumers as $consumer) {
                if (!\in_array($consumer, $found)) {
                    $found[] = $consumer;
                }
            }
        } while (\next($found) !== false);
        return $found;
    }

    /**
     * Parses the raw header value into header parts.
     *
     * Calls splitTokens to split the value into token part strings, then calls
     * parseParts to parse the returned array.
     *
     * @return \ZBateson\MailMimeParser\Header\IHeaderPart[] the array of parsed
     *         parts
     */
    private function parseRawValue(string $value) : array
    {
        $tokens = $this->splitRawValue($value);
        return $this->parseTokensIntoParts(new NoRewindIterator(new ArrayIterator($tokens)));
    }

    /**
     * Returns an array of regular expression separators specific to this
     * consumer.
     *
     * The returned patterns are used to split the header value into tokens for
     * the consumer to parse into parts.
     *
     * Each array element makes part of a generated regular expression that is
     * used in a call to preg_split().  RegEx patterns can be used, and care
     * should be taken to escape special characters.
     *
     * @return string[] Array of regex patterns.
     */
    abstract protected function getTokenSeparators() : array;

    /**
     * Returns a list of regular expression markers for this consumer and all
     * sub-consumers by calling getTokenSeparators().
     *
     * @return string[] Array of regular expression markers.
     */
    protected function getAllTokenSeparators() : array
    {
        $markers = $this->getTokenSeparators();
        $subConsumers = $this->getAllConsumers();
        foreach ($subConsumers as $consumer) {
            $markers = \array_merge($consumer->getTokenSeparators(), $markers);
        }
        return \array_unique($markers);
    }

    /**
     * Returns a regex pattern used to split the input header string.
     *
     * The default implementation calls
     * {@see AbstractConsumerService::getAllTokenSeparators()} and implodes the
     * returned array with the regex OR '|' character as its glue.
     *
     * @return string the regex pattern
     */
    protected function getTokenSplitPattern() : string
    {
        $sChars = \implode('|', $this->getAllTokenSeparators());
        $mimePartPattern = MimeToken::MIME_PART_PATTERN;
        return '~(' . $mimePartPattern . '|\\\\\r\n|\\\\.|' . $sChars . ')~ms';
    }

    /**
     * Returns an array of split tokens from the input string.
     *
     * The method calls preg_split using
     * {@see AbstractConsumerService::getTokenSplitPattern()}.  The split array
     * will not contain any empty parts and will contain the markers.
     *
     * @param string $rawValue the raw string
     * @return string[] the array of tokens
     */
    protected function splitRawValue($rawValue) : array
    {
        if ($this->tokenSplitPattern === null) {
            $this->tokenSplitPattern = $this->getTokenSplitPattern();
            $this->logger->debug(
                'Configuring {class} with token split pattern: {pattern}',
                ['class' => static::class, 'pattern' => $this->tokenSplitPattern]
            );
        }
        return \preg_split(
            $this->tokenSplitPattern,
            $rawValue,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
    }

    /**
     * Returns true if the passed string token marks the beginning marker for
     * the current consumer.
     *
     * @param string $token The current token
     */
    abstract protected function isStartToken(string $token) : bool;

    /**
     * Returns true if the passed string token marks the end marker for the
     * current consumer.
     *
     * @param string $token The current token
     */
    abstract protected function isEndToken(string $token) : bool;

    /**
     * Constructs and returns an IHeaderPart for the passed string token.
     *
     * If the token should be ignored, the function must return null.
     *
     * The default created part uses the instance's partFactory->newInstance
     * method.
     *
     * @param string $token the token
     * @param bool $isLiteral set to true if the token represents a literal -
     *        e.g. an escaped token
     * @return ?IHeaderPart The constructed header part or null if the token
     *         should be ignored.
     */
    protected function getPartForToken(string $token, bool $isLiteral) : ?IHeaderPart
    {
        if ($isLiteral) {
            return $this->partFactory->newToken($token, true);
        }
        // can be overridden with custom PartFactory
        return $this->partFactory->newInstance($token);
    }

    /**
     * Iterates through this consumer's sub-consumers checking if the current
     * token triggers a sub-consumer's start token and passes control onto that
     * sub-consumer's parseTokenIntoParts().
     *
     * If no sub-consumer is responsible for the current token, calls
     * {@see AbstractConsumerService::getPartForToken()} and returns it in an
     * array.
     *
     * @param Iterator<string> $tokens
     * @return IHeaderPart[]
     */
    protected function getConsumerTokenParts(Iterator $tokens) : array
    {
        $token = $tokens->current();
        $subConsumers = $this->subConsumers;
        foreach ($subConsumers as $consumer) {
            if ($consumer->isStartToken($token)) {
                $this->logger->debug(
                    'Token: "{value}" in {class} starting sub-consumer {consumer}',
                    ['value' => $token, 'class' => static::class, 'consumer' => \get_class($consumer)]
                );
                $this->advanceToNextToken($tokens, true);
                return $consumer->parseTokensIntoParts($tokens);
            }
        }
        $part = $this->getPartForToken($token, false);
        return ($part !== null) ? [$part] : [];
    }

    /**
     * Returns an array of IHeaderPart for the current token on the iterator.
     *
     * If the current token is a start token from a sub-consumer, the sub-
     * consumer's {@see AbstractConsumerService::parseTokensIntoParts()} method
     * is called.
     *
     * @param Iterator<string> $tokens The token iterator.
     * @return IHeaderPart[]
     */
    protected function getTokenParts(Iterator $tokens) : array
    {
        $token = $tokens->current();
        if ($token === "\\\r\n" || (\strlen($token) === 2 && $token[0] === '\\')) {
            $part = $this->getPartForToken(\substr($token, 1), true);
            return ($part !== null) ? [$part] : [];
        }
        return $this->getConsumerTokenParts($tokens);
    }

    /**
     * Determines if the iterator should be advanced to the next token after
     * reading tokens or finding a start token.
     *
     * The default implementation will advance for a start token, but not
     * advance on the end token of the current consumer, allowing the end token
     * to be passed up to a higher-level consumer.
     *
     * @param Iterator $tokens The token iterator.
     * @param bool $isStartToken true for the start token.
     */
    protected function advanceToNextToken(Iterator $tokens, bool $isStartToken) : static
    {
        $checkEndToken = (!$isStartToken && $tokens->valid());
        $isEndToken = ($checkEndToken && $this->isEndToken($tokens->current()));
        if (($isStartToken) || ($checkEndToken && !$isEndToken)) {
            $tokens->next();
        }
        return $this;
    }

    /**
     * Iterates over the passed token Iterator and returns an array of parsed
     * IHeaderPart objects.
     *
     * The method checks each token to see if the token matches a sub-consumer's
     * start token, or if it matches the current consumer's end token to stop
     * processing.
     *
     * If a sub-consumer's start token is matched, the sub-consumer is invoked
     * and its returned parts are merged to the current consumer's header parts.
     *
     * After all tokens are read and an array of Header\Parts are constructed,
     * the array is passed to {@see AbstractConsumerService::processParts} for
     * any final processing if there are any parts.
     *
     * @param Iterator<string> $tokens An iterator over a string of tokens
     * @return IHeaderPart[] An array of parsed parts
     */
    protected function parseTokensIntoParts(Iterator $tokens) : array
    {
        $parts = [];
        while ($tokens->valid() && !$this->isEndToken($tokens->current())) {
            $this->logger->debug('Parsing token: {token} in class: {consumer}', ['token' => $tokens->current(), 'consumer' => static::class]);
            $parts = \array_merge($parts, $this->getTokenParts($tokens));
            $this->advanceToNextToken($tokens, false);
        }
        return (empty($parts)) ? [] : $this->processParts($parts);
    }

    /**
     * Performs any final processing on the array of parsed parts before
     * returning it to the consumer client.  The passed $parts array is
     * guaranteed to not be empty.
     *
     * The default implementation simply returns the passed array after
     * filtering out null/empty parts.
     *
     * @param IHeaderPart[] $parts The parsed parts.
     * @return IHeaderPart[] Array of resulting final parts.
     */
    protected function processParts(array $parts) : array
    {
        $this->logger->debug('Processing parts array {parts} in {consumer}', ['parts' => $parts, 'consumer' => static::class]);
        return $parts;
    }
}
