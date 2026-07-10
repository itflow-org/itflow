<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Part;

use Psr\Log\LogLevel;

/**
 * Holds a single address or name/address pair.
 *
 * The name part of the address may be mime-encoded, but the email address part
 * can't be mime-encoded.  Any whitespace in the email address part is stripped
 * out.
 *
 * A convenience method, getEmail, is provided for clarity -- but getValue
 * returns the email address as well.
 *
 * @author Zaahid Bateson
 */
class AddressPart extends NameValuePart
{
    protected function getValueFromParts(array $parts) : string
    {
        return \implode('', \array_map(
            function($p) {
                if ($p instanceof AddressPart) {
                    return $p->getValue();
                } elseif ($p instanceof QuotedLiteralPart && $p->getValue() !== '') {
                    return '"' . \preg_replace('/(["\\\])/', '\\\$1', $p->getValue()) . '"';
                }
                return \preg_replace('/\s+/', '', $p->getValue());
            },
            $parts
        ));
    }

    /**
     * Returns the email address.
     *
     * @return string The email address.
     */
    public function getEmail() : string
    {
        return $this->value;
    }

    protected function validate() : void
    {
        if (empty($this->value)) {
            $this->addError('Address doesn\'t contain an email address', LogLevel::ERROR);
        }
    }
}
