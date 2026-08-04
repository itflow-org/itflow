<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Part;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\IHeaderPart;
use ZBateson\MbWrapper\MbWrapper;

/**
 * Constructs and returns IHeaderPart objects.
 *
 * @author Zaahid Bateson
 */
class HeaderPartFactory
{
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly MbWrapper $charsetConverter
    ) {
    }

    /**
     * Creates and returns a default IHeaderPart for this factory, allowing
     * subclass factories for specialized IHeaderParts.
     *
     * The default implementation returns a new Token
     */
    public function newInstance(string $value) : IHeaderPart
    {
        return $this->newToken($value);
    }

    /**
     * Initializes and returns a new Token.
     */
    public function newToken(string $value, bool $isLiteral = false, bool $preserveSpaces = false) : Token
    {
        return new Token($this->logger, $this->charsetConverter, $value, $isLiteral, $preserveSpaces);
    }

    /**
     * Initializes and returns a new SubjectToken.
     */
    public function newSubjectToken(string $value) : SubjectToken
    {
        return new SubjectToken($this->logger, $this->charsetConverter, $value);
    }

    /**
     * Initializes and returns a new MimeToken.
     */
    public function newMimeToken(string $value) : MimeToken
    {
        return new MimeToken($this->logger, $this->charsetConverter, $value);
    }

    /**
     * Initializes and returns a new ContainerPart.
     *
     * @param IHeaderPart[] $children
     */
    public function newContainerPart(array $children) : ContainerPart
    {
        return new ContainerPart($this->logger, $this->charsetConverter, $children);
    }

    /**
     * Instantiates and returns a SplitParameterPart.
     *
     * @param ParameterPart[] $children
     */
    public function newSplitParameterPart(array $children) : SplitParameterPart
    {
        return new SplitParameterPart($this->logger, $this->charsetConverter, $this, $children);
    }

    /**
     * Initializes and returns a new QuotedLiteralPart.
     *
     * @param IHeaderPart[] $parts
     */
    public function newQuotedLiteralPart(array $parts) : QuotedLiteralPart
    {
        return new QuotedLiteralPart($this->logger, $this->charsetConverter, $parts);
    }

    /**
     * Initializes and returns a new CommentPart.
     *
     * @param IHeaderPart[] $children
     */
    public function newCommentPart(array $children) : CommentPart
    {
        return new CommentPart($this->logger, $this->charsetConverter, $this, $children);
    }

    /**
     * Initializes and returns a new AddressPart.
     *
     * @param IHeaderPart[] $nameParts
     * @param IHeaderPart[] $emailParts
     */
    public function newAddress(array $nameParts, array $emailParts) : AddressPart
    {
        return new AddressPart($this->logger, $this->charsetConverter, $nameParts, $emailParts);
    }

    /**
     * Initializes and returns a new AddressGroupPart
     *
     * @param IHeaderPart[] $nameParts
     * @param IHeaderPart[] $addressesAndGroups
     */
    public function newAddressGroupPart(array $nameParts, array $addressesAndGroups) : AddressGroupPart
    {
        return new AddressGroupPart($this->logger, $this->charsetConverter, $nameParts, $addressesAndGroups);
    }

    /**
     * Initializes and returns a new DatePart
     *
     * @param IHeaderPart[] $children
     */
    public function newDatePart(array $children) : DatePart
    {
        return new DatePart($this->logger, $this->charsetConverter, $children);
    }

    /**
     * Initializes and returns a new ParameterPart.
     *
     * @param IHeaderPart[] $nameParts
     */
    public function newParameterPart(array $nameParts, ContainerPart $valuePart) : ParameterPart
    {
        return new ParameterPart($this->logger, $this->charsetConverter, $nameParts, $valuePart);
    }

    /**
     * Initializes and returns a new ReceivedPart.
     *
     * @param IHeaderPart[] $children
     */
    public function newReceivedPart(string $name, array $children) : ReceivedPart
    {
        return new ReceivedPart($this->logger, $this->charsetConverter, $name, $children);
    }

    /**
     * Initializes and returns a new ReceivedDomainPart.
     *
     * @param IHeaderPart[] $children
     */
    public function newReceivedDomainPart(string $name, array $children) : ReceivedDomainPart
    {
        return new ReceivedDomainPart(
            $this->logger,
            $this->charsetConverter,
            $name,
            $children
        );
    }
}
