<?php

/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

/**
 * Interface defining a consumer service class.
 *
 * @author Zaahid Bateson
 */
interface IConsumerService
{
    /**
     * Invokes parsing of a header's value into header parts.
     *
     * @param string $value the raw header value
     * @return \ZBateson\MailMimeParser\Header\IHeaderPart[] the array of parsed
     *         parts
     */
    public function __invoke(string $value) : array;
}
