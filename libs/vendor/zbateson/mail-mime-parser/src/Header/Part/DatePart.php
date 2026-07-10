<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Part;

use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use ZBateson\MbWrapper\MbWrapper;

/**
 * Represents the value of a date header, parsing the date into a \DateTime
 * object.
 *
 * @author Zaahid Bateson
 */
class DatePart extends ContainerPart
{
    /**
     * @var DateTime the parsed date, or null if the date could not be parsed
     */
    protected ?DateTime $date = null;

    /**
     * Tries parsing the passed token as an RFC 2822 date, and failing that into
     * an RFC 822 date, and failing that, tries to parse it by calling
     * new DateTime($value).
     *
     * @param HeaderPart[] $children
     */
    public function __construct(
        LoggerInterface $logger,
        MbWrapper $charsetConverter,
        array $children
    ) {
        // parent::__construct converts character encoding -- may cause problems sometimes.
        parent::__construct($logger, $charsetConverter, $children);
        $this->value = $dateToken = \trim($this->value);

        // Missing "+" in timezone definition. eg: Thu, 13 Mar 2014 15:02:47 0000 (not RFC compliant)
        // Won't result in an Exception, but in a valid DateTime in year `0000` - therefore we need to check this first:
        if (\preg_match('# [0-9]{4}$#', $dateToken)) {
            $dateToken = \preg_replace('# ([0-9]{4})$#', ' +$1', $dateToken);
        // @see https://bugs.php.net/bug.php?id=42486
        } elseif (\preg_match('#UT$#', $dateToken)) {
            $dateToken = $dateToken . 'C';
        }

        try {
            $this->date = new DateTime($dateToken);
        } catch (Exception $e) {
            $this->addError(
                "Unable to parse date from header: \"{$dateToken}\"",
                LogLevel::ERROR,
                $e
            );
        }
    }

    /**
     * Returns a DateTime object or null if it can't be parsed.
     */
    public function getDateTime() : ?DateTime
    {
        return $this->date;
    }
}
