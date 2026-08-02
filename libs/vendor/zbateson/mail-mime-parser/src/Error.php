<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser;

use InvalidArgumentException;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Holds information about an error or notice that happened on a specific
 * object.
 *
 * @author Zaahid Bateson
 */
class Error
{
    /**
     * @var array<string, int>
     */
    private array $levelMap = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 2,
        LogLevel::ERROR => 3,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 5,
        LogLevel::INFO => 6,
        LogLevel::DEBUG => 7,
    ];

    /**
     *
     * @throws InvalidArgumentException if the passed $psrLevel
     *         is not a known PSR log level (see \Psr\Log\LogLevel)
     */
    public function __construct(
        protected readonly string $message,
        protected readonly string $psrLevel,
        protected readonly ErrorBag $object,
        protected readonly ?Throwable $exception = null
    ) {
        if (!isset($this->levelMap[$psrLevel])) {
            throw new InvalidArgumentException($psrLevel . ' is not a known PSR Log Level');
        }
    }

    /**
     * Returns the error message.
     */
    public function getMessage() : string
    {
        return $this->message;
    }

    /**
     * Returns the PSR string log level for this error message.
     */
    public function getPsrLevel() : string
    {
        return $this->psrLevel;
    }

    /**
     * Returns the class type the error occurred on.
     */
    public function getClass() : string
    {
        return \get_class($this->object);
    }

    /**
     * Returns the object the error occurred on.
     */
    public function getObject() : ErrorBag
    {
        return $this->object;
    }

    /**
     * Returns the exception that occurred, if any, or null.
     */
    public function getException() : ?Throwable
    {
        return $this->exception;
    }

    /**
     * Returns true if the PSR log level for this error is equal to or greater
     * than the one passed, e.g. passing LogLevel::ERROR would return true for
     * LogLevel::ERROR and LogLevel::CRITICAL, ALERT and EMERGENCY.
     */
    public function isPsrLevelGreaterOrEqualTo(string $minLevel) : bool
    {
        $minIntLevel = $this->levelMap[$minLevel] ?? 1000;
        $thisLevel = $this->levelMap[$this->psrLevel];
        return ($minIntLevel >= $thisLevel);
    }
}
