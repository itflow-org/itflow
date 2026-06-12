<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\Consumer\AddressBaseConsumerService;
use ZBateson\MailMimeParser\Header\Part\AddressGroupPart;
use ZBateson\MailMimeParser\Header\Part\AddressPart;
use ZBateson\MailMimeParser\MailMimeParser;

/**
 * A header containing one or more email addresses and/or groups of addresses.
 *
 * An address is separated by a comma, and each group separated by a semi-colon.
 * The AddressHeader provides a complete list of all addresses referenced in a
 * header including any addresses in groups, in addition to being able to access
 * the groups separately if needed.
 *
 * For full specifications, see {@link https://www.ietf.org/rfc/rfc2822.txt}
 *
 * @author Zaahid Bateson
 */
class AddressHeader extends AbstractHeader
{
    /**
     * @var AddressPart[] array of addresses, included all addresses contained
     *      in groups.
     */
    protected array $addresses = [];

    /**
     * @var AddressGroupPart[] array of address groups (lists).
     */
    protected array $groups = [];

    public function __construct(
        string $name,
        string $value,
        ?LoggerInterface $logger = null,
        ?AddressBaseConsumerService $consumerService = null
    ) {
        $di = MailMimeParser::getGlobalContainer();
        parent::__construct(
            $logger ?? $di->get(LoggerInterface::class),
            $consumerService ?? $di->get(AddressBaseConsumerService::class),
            $name,
            $value
        );
    }

    /**
     * Filters $this->allParts into the parts required by $this->parts
     * and assignes it.
     *
     * The AbstractHeader::filterAndAssignToParts method filters out CommentParts.
     */
    protected function filterAndAssignToParts() : void
    {
        parent::filterAndAssignToParts();
        foreach ($this->parts as $part) {
            if ($part instanceof AddressPart) {
                $this->addresses[] = $part;
            } elseif ($part instanceof AddressGroupPart) {
                $this->addresses = \array_merge($this->addresses, $part->getAddresses());
                $this->groups[] = $part;
            }
        }
    }

    /**
     * Returns all address parts in the header including any addresses that are
     * in groups (lists).
     *
     * @return AddressPart[] The addresses.
     */
    public function getAddresses() : array
    {
        return $this->addresses;
    }

    /**
     * Returns all group parts (lists) in the header.
     *
     * @return AddressGroupPart[]
     */
    public function getGroups() : array
    {
        return $this->groups;
    }

    /**
     * Returns true if an address exists with the passed email address.
     *
     * Comparison is done case insensitively.
     *
     */
    public function hasAddress(string $email) : bool
    {
        foreach ($this->addresses as $addr) {
            if (\strcasecmp($addr->getEmail(), $email) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the first email address in the header.
     *
     * @return ?string The email address
     */
    public function getEmail() : ?string
    {
        if (!empty($this->addresses)) {
            return $this->addresses[0]->getEmail();
        }
        return null;
    }

    /**
     * Returns the name associated with the first email address to complement
     * getEmail() if one is set, or null if not.
     *
     * @return string|null The person name.
     */
    public function getPersonName() : ?string
    {
        if (!empty($this->addresses)) {
            return $this->addresses[0]->getName();
        }
        return null;
    }
}
