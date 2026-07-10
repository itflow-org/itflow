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
    /**
     * @var MbWrapper $charsetConverter passed to IHeaderPart constructors
     *      for converting strings in IHeaderPart::convertEncoding
     */
    protected MbWrapper $charsetConverter;

    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, MbWrapper $charsetConverter)
    {
        $this->logger = $logger;
        $this->charsetConverter = $charsetConverter;
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
     * @param HeaderPart[] $children
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
     * @param HeaderPart[] $parts
     */
    public function newQuotedLiteralPart(array $parts) : QuotedLiteralPart
    {
        return new QuotedLiteralPart($this->logger, $this->charsetConverter, $parts);
    }

    /**
     * Initializes and returns a new CommentPart.
     *
     * @param HeaderPart[] $children
     */
    public function newCommentPart(array $children) : CommentPart
    {
        return new CommentPart($this->logger, $this->charsetConverter, $this, $children);
    }

    /**
     * Initializes and returns a new AddressPart.
     *
     * @param HeaderPart[] $nameParts
     * @param HeaderPart[] $emailParts
     */
    public function newAddress(array $nameParts, array $emailParts) : AddressPart
    {
        return new AddressPart($this->logger, $this->charsetConverter, $nameParts, $emailParts);
    }

    /**
     * Initializes and returns a new AddressGroupPart
     *
     * @param HeaderPart[] $nameParts
     * @param AddressPart[]|AddressGroupPart[] $addressesAndGroups
     */
    public function newAddressGroupPart(array $nameParts, array $addressesAndGroups) : AddressGroupPart
    {
        return new AddressGroupPart($this->logger, $this->charsetConverter, $nameParts, $addressesAndGroups);
    }

    /**
     * Initializes and returns a new DatePart
     *
     * @param HeaderPart[] $children
     */
    public function newDatePart(array $children) : DatePart
    {
        return new DatePart($this->logger, $this->charsetConverter, $children);
    }

    /**
     * Initializes and returns a new ParameterPart.
     *
     * @param HeaderPart[] $nameParts
     */
    public function newParameterPart(array $nameParts, ContainerPart $valuePart) : ParameterPart
    {
        return new ParameterPart($this->logger, $this->charsetConverter, $nameParts, $valuePart);
    }

    /**
     * Initializes and returns a new ReceivedPart.
     *
     * @param HeaderPart[] $children
     */
    public function newReceivedPart(string $name, array $children) : ReceivedPart
    {
        return new ReceivedPart($this->logger, $this->charsetConverter, $name, $children);
    }

    /**
     * Initializes and returns a new ReceivedDomainPart.
     *
     * @param HeaderPart[] $children
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
