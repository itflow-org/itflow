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
 * Holds extra information about a parsed Received header part, for FROM and BY
 * parts, namely: ehlo name, hostname, and address.
 *
 * The parsed parts would be mapped as follows:
 *
 * FROM ehlo name (hostname [address]), for example: FROM computer (domain.com
 * [1.2.3.4]) would contain "computer" for getEhloName(), domain.com for
 * getHostname and 1.2.3.4 for getAddress().
 *
 * This doesn't change if the ehlo name is an address, it is still returned in
 * getEhloName(), and not in getAddress().  Additionally square brackets are not
 * stripped from getEhloName() if its an address.  For example: "FROM [1.2.3.4]"
 * would return "[1.2.3.4]" in a call to getEhloName().
 *
 * For further information on how the header's parsed, check the documentation
 * for {@see \ZBateson\MailMimeParser\Header\Consumer\Received\DomainConsumer}.
 *
 * @author Zaahid Bateson
 */
class ReceivedDomainPart extends ReceivedPart
{
    /**
     * @var string The name used to identify the server in the EHLO line.
     */
    protected ?string $ehloName = null;

    /**
     * @var string The hostname.
     */
    protected ?string $hostname = null;

    /**
     * @var string The address.
     */
    protected ?string $address = null;

    /**
     * @param HeaderPart[] $children
     */
    public function __construct(
        LoggerInterface $logger,
        MbWrapper $charsetConverter,
        string $name,
        array $children
    ) {
        parent::__construct($logger, $charsetConverter, $name, $children);

        $this->ehloName = ($this->value !== '') ? $this->value : null;
        $cps = $this->getComments();
        $commentPart = (!empty($cps)) ? $cps[0] : null;

        $pattern = '~^(\[(IPv[64])?(?P<addr1>[a-f\d\.\:]+)\])?\s*(helo=)?(?P<name>[a-z0-9\-]+[a-z0-9\-\.]+)?\s*(\[(IPv[64])?(?P<addr2>[a-f\d\.\:]+)\])?$~i';
        if ($commentPart !== null && \preg_match($pattern, $commentPart->getComment(), $matches)) {
            $this->value .= ' (' . $commentPart->getComment() . ')';
            $this->hostname = (!empty($matches['name'])) ? $matches['name'] : null;
            $this->address = (!empty($matches['addr1'])) ? $matches['addr1'] : ((!empty($matches['addr2'])) ? $matches['addr2'] : null);
        }
    }

    /**
     * Returns the name used to identify the server in the first part of the
     * extended-domain line.
     *
     * Note that this is not necessarily the name used in the EHLO line to an
     * SMTP server, since implementations differ so much, not much can be
     * guaranteed except the position it was parsed in.
     */
    public function getEhloName() : ?string
    {
        return $this->ehloName;
    }

    /**
     * Returns the hostname of the server, or whatever string in the hostname
     * position when parsing (but never an address).
     */
    public function getHostname() : ?string
    {
        return $this->hostname;
    }

    /**
     * Returns the address of the server, or whatever string that looks like an
     * address in the address position when parsing (but never a hostname).
     */
    public function getAddress() : ?string
    {
        return $this->address;
    }
}
