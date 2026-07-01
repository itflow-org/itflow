<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer\Received;

use ZBateson\MailMimeParser\Header\Consumer\DateConsumerService;

/**
 * Parses the date portion of a Received header into a DatePart.
 *
 * The only difference between DateConsumerService and
 * ReceivedDateConsumerService is the addition of a start token, ';', and a
 * token separator (also ';').
 *
 * @author Zaahid Bateson
 */
class ReceivedDateConsumerService extends DateConsumerService
{
    /**
     * Returns true if the token is a ';'
     */
    protected function isStartToken(string $token) : bool
    {
        return ($token === ';');
    }

    /**
     * Returns an array containing ';'.
     *
     * @return string[] an array of regex pattern matchers
     */
    protected function getTokenSeparators() : array
    {
        return [';'];
    }
}
