<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser;

use Psr\Log\LogLevel;
use Throwable;

/**
 * Defines an object that may contain a set of errors, and optionally perform
 * additional validation.
 *
 * @author Zaahid Bateson
 */
interface IErrorBag
{
    /**
     * Returns a context name for the current object to help identify it in
     * logs.
     */
    public function getErrorLoggingContextName() : string;

    /**
     * Creates and adds an Error object to this ErrorBag.
     */
    public function addError(string $message, string $psrLogLevel, ?Throwable $exception = null) : static;

    /**
     * Returns true if this object has an error in its error bag at or above
     * the passed $minPsrLevel (defaults to ERROR).  If $validate is true,
     * additional validation may be performed.
     *
     * The PSR levels are defined in Psr\Log\LogLevel.
     */
    public function hasErrors(bool $validate = false, string $minPsrLevel = LogLevel::ERROR) : bool;

    /**
     * Returns any local errors this object has at or above the passed PSR log
     * level in Psr\Log\LogLevel (defaulting to LogLevel::ERROR).
     *
     * If $validate is true, additional validation may be performed on the
     * object to check for errors.
     *
     * @return Error[]
     */
    public function getErrors(bool $validate = false, string $minPsrLevel = LogLevel::ERROR) : array;

    /**
     * Returns true if there are errors on this object, or any IErrorBag child
     * of this object at or above the passed PSR log level in Psr\Log\LogLevel
     * (defaulting to LogLevel::ERROR).  Note that this will stop after finding
     * the first error and return, so may be slightly more performant if an
     * error actually exists over calling getAllErrors if only interested in
     * whether an error exists.
     *
     * Care should be taken using this if the intention is to only 'preview' a
     * message without parsing it entirely, since this will cause the whole
     * message to be parsed as it traverses children, and could be slow on
     * messages with large attachments, etc...
     *
     * If $validate is true, additional validation may be performed to check for
     * errors.
     */
    public function hasAnyErrors(bool $validate = false, string $minPsrLevel = LogLevel::ERROR) : bool;

    /**
     * Returns any errors on this object, and all IErrorBag children of this
     * object at or above the passed PSR log level from Psr\Log\LogLevel
     * (defaulting to LogLevel::ERROR).
     *
     * Care should be taken using this if the intention is to only 'preview' a
     * message without parsing it entirely, since this will cause the whole
     * message to be parsed as it traverses children, and could be slow on
     * messages with large attachments, etc...
     *
     * If $validate is true, additional validation may be performed on children
     * to check for errors.
     *
     * @return Error[]
     */
    public function getAllErrors(bool $validate = false, string $minPsrLevel = LogLevel::ERROR) : array;
}
