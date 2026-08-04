<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

/**
 * A minimal implementation of AbstractConsumerService splitting tokens by
 * whitespace.
 *
 * Although the class doesn't have any abstract methods, it's defined as
 * abstract because it doesn't define specific sub-consumers as constructor
 * dependencies, and so is defined as abstract to avoid its direct use (use
 * the concrete GenericConsumerService or GenericConsumerMimeLiteralPartService
 * classes instead).
 *
 * @author Zaahid Bateson
 */
abstract class AbstractGenericConsumerService extends AbstractConsumerService
{
    /**
     * Returns the regex '\s+' (whitespace) pattern matcher as a token marker so
     * the header value is split along whitespace characters.
     *
     * @return string[] an array of regex pattern matchers
     */
    protected function getTokenSeparators() : array
    {
        return ['\s+'];
    }

    /**
     * AbstractGenericConsumerService doesn't have start/end tokens, and so
     * always returns false.
     */
    protected function isEndToken(string $token) : bool
    {
        return false;
    }

    /**
     * AbstractGenericConsumerService doesn't have start/end tokens, and so
     * always returns false.
     *
     * @codeCoverageIgnore
     */
    protected function isStartToken(string $token) : bool
    {
        return false;
    }

    /**
     * Overridden to combine all part values into a single string and return it
     * as an array with a single element.
     *
     * The returned IHeaderPart array consists of a single ContainerPart created
     * out of all passed IHeaderParts.
     *
     * @param \ZBateson\MailMimeParser\Header\IHeaderPart[] $parts
     * @return \ZBateson\MailMimeParser\Header\IHeaderPart[]
     */
    protected function processParts(array $parts) : array
    {
        return [$this->partFactory->newContainerPart($parts)];
    }
}
