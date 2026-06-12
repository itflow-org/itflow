<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Part;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use ZBateson\MbWrapper\MbWrapper;

/**
 * Holds a group of addresses and a group name.
 *
 * @author Zaahid Bateson
 */
class AddressGroupPart extends NameValuePart
{
    /**
     * @var AddressPart[] an array of AddressParts
     */
    protected array $addresses;

    /**
     * Creates an AddressGroupPart out of the passed array of AddressParts/
     * AddressGroupParts and name.
     *
     * @param HeaderPart[] $nameParts
     * @param AddressPart[]|AddressGroupPart[] $addressesAndGroupParts
     */
    public function __construct(
        LoggerInterface $logger,
        MbWrapper $charsetConverter,
        array $nameParts,
        array $addressesAndGroupParts
    ) {
        parent::__construct(
            $logger,
            $charsetConverter,
            $nameParts,
            $addressesAndGroupParts
        );
        $this->addresses = \array_merge(...\array_map(
            fn ($p) => ($p instanceof AddressGroupPart) ? $p->getAddresses() : [$p],
            $addressesAndGroupParts
        ));
        // for backwards compatibility
        $this->value = $this->name;
    }

    /**
     * Return the AddressGroupPart's array of addresses.
     *
     * @return AddressPart[] An array of address parts.
     */
    public function getAddresses() : array
    {
        return $this->addresses;
    }

    /**
     * Returns the AddressPart at the passed index or null.
     *
     * @param int $index The 0-based index.
     * @return ?AddressPart The address.
     */
    public function getAddress(int $index) : ?AddressPart
    {
        if (!isset($this->addresses[$index])) {
            return null;
        }
        return $this->addresses[$index];
    }

    protected function validate() : void
    {
        if ($this->name === null || \mb_strlen($this->name) === 0) {
            $this->addError('Address group doesn\'t have a name', LogLevel::ERROR);
        }
        if (empty($this->addresses)) {
            $this->addError('Address group doesn\'t have any email addresses defined in it', LogLevel::NOTICE);
        }
    }
}
