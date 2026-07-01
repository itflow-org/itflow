<?php

namespace DirectoryTree\ImapEngine\Connection;

use DirectoryTree\ImapEngine\Connection\Streams\StreamInterface;
use DirectoryTree\ImapEngine\Connection\Tokens\Atom;
use DirectoryTree\ImapEngine\Connection\Tokens\Crlf;
use DirectoryTree\ImapEngine\Connection\Tokens\EmailAddress;
use DirectoryTree\ImapEngine\Connection\Tokens\ListClose;
use DirectoryTree\ImapEngine\Connection\Tokens\ListOpen;
use DirectoryTree\ImapEngine\Connection\Tokens\Literal;
use DirectoryTree\ImapEngine\Connection\Tokens\Nil;
use DirectoryTree\ImapEngine\Connection\Tokens\Number;
use DirectoryTree\ImapEngine\Connection\Tokens\QuotedString;
use DirectoryTree\ImapEngine\Connection\Tokens\ResponseCodeClose;
use DirectoryTree\ImapEngine\Connection\Tokens\ResponseCodeOpen;
use DirectoryTree\ImapEngine\Connection\Tokens\Token;
use DirectoryTree\ImapEngine\Exceptions\ImapParserException;
use DirectoryTree\ImapEngine\Exceptions\ImapStreamException;

class ImapTokenizer
{
    /**
     * The current position in the buffer.
     */
    protected int $position = 0;

    /**
     * The buffer of characters read from the stream.
     */
    protected string $buffer = '';

    /**
     * Constructor.
     */
    public function __construct(
        protected StreamInterface $stream
    ) {}

    /**
     * Returns the next token from the stream.
     */
    public function nextToken(): ?Token
    {
        $this->skipWhitespace();

        $this->ensureBuffer(1);

        $char = $this->currentChar();

        if ($char === null || $char === '') {
            return null;
        }

        // Check for line feed.
        if ($char === "\n") {
            // With a valid IMAP response, we should never reach this point,
            // but in case we receive a malformed response, we will flush
            // the buffer and return null to prevent an infinite loop.
            $this->flushBuffer();

            return null;
        }

        // Check for carriage return. (\r\n)
        if ($char === "\r") {
            $this->advance(); // Consume CR

            $this->ensureBuffer(1);

            if ($this->currentChar() !== "\n") {
                throw new ImapParserException('Expected LF after CR');
            }

            $this->advance(); // Consume LF (\n)

            return new Crlf("\r\n");
        }

        // Check for parameter list opening.
        if ($char === '(') {
            $this->advance();

            return new ListOpen('(');
        }

        // Check for a parameter list closing.
        if ($char === ')') {
            $this->advance();

            return new ListClose(')');
        }

        // Check for a response group open.
        if ($char === '[') {
            $this->advance();

            return new ResponseCodeOpen('[');
        }

        // Check for response group close.
        if ($char === ']') {
            $this->advance();

            return new ResponseCodeClose(']');
        }

        // Check for angle bracket open (email addresses).
        if ($char === '<') {
            $this->advance();

            return $this->readEmailAddress();
        }

        // Check for quoted string.
        if ($char === '"') {
            return $this->readQuotedString();
        }

        // Check for literal block open.
        if ($char === '{') {
            return $this->readLiteral();
        }

        // Otherwise, parse a number or atom.
        return $this->readNumberOrAtom();
    }

    /**
     * Skips whitespace characters (spaces and tabs only, preserving CRLF).
     */
    protected function skipWhitespace(): void
    {
        while (true) {
            $this->ensureBuffer(1);

            $char = $this->currentChar();

            // Break on EOF.
            if ($char === null || $char === '') {
                break;
            }

            // Break on CRLF.
            if ($char === "\r" || $char === "\n") {
                break;
            }

            // Break on non-whitespace.
            if ($char !== ' ' && $char !== "\t") {
                break;
            }

            $this->advance();
        }
    }

    /**
     * Reads a quoted string token.
     *
     * Quoted strings are enclosed in double quotes and may contain escaped characters.
     */
    protected function readQuotedString(): QuotedString
    {
        // Skip the opening quote.
        $this->advance();

        $value = '';

        while (true) {
            $this->ensureBuffer(1);

            $char = $this->currentChar();

            if ($char === null) {
                throw new ImapParserException(sprintf(
                    'Unterminated quoted string at buffer offset %d. Buffer: "%s"',
                    $this->position,
                    substr($this->buffer, max(0, $this->position - 10), 20)
                ));
            }

            if ($char === '\\') {
                $this->advance(); // Skip the backslash.

                $this->ensureBuffer(1);

                $escapedChar = $this->currentChar();

                if ($escapedChar === null) {
                    throw new ImapParserException('Unterminated escape sequence in quoted string');
                }

                $value .= $escapedChar;

                $this->advance();

                continue;
            }

            if ($char === '"') {
                $this->advance(); // Skip the closing quote.

                break;
            }

            $value .= $char;

            $this->advance();
        }

        return new QuotedString($value);
    }

    /**
     * Reads a literal token.
     *
     * Literal blocks in IMAP have the form {<length>}\r\n<data>.
     */
    protected function readLiteral(): Literal
    {
        // Skip the opening '{'.
        $this->advance();

        // This will contain the size of the literal block in a sequence of digits.
        // {<size>}\r\n<data>
        $numStr = '';

        while (true) {
            $this->ensureBuffer(1);

            $char = $this->currentChar();

            if ($char === null) {
                throw new ImapParserException('Unterminated literal specifier');
            }

            if ($char === '}') {
                $this->advance(); // Skip the '}'.

                break;
            }

            $numStr .= $char;

            $this->advance();
        }

        // Expect carriage return after the literal specifier.
        $this->ensureBuffer(2);

        // Get the carriage return.
        $crlf = substr($this->buffer, $this->position, 2);

        if ($crlf !== "\r\n") {
            throw new ImapParserException('Expected CRLF after literal specifier');
        }

        // Skip the CRLF.
        $this->advance(2);

        $length = (int) $numStr;

        // Use any data that is already in our buffer.
        $available = strlen($this->buffer) - $this->position;

        if ($available >= $length) {
            $literal = substr($this->buffer, $this->position, $length);

            $this->advance($length);
        } else {
            // Consume whatever is available without flushing the whole buffer.
            $literal = substr($this->buffer, $this->position);

            $consumed = strlen($literal);

            // Advance the pointer by the number of bytes we took.
            $this->advance($consumed);

            // Calculate how many bytes are still needed.
            $remaining = $length - $consumed;

            // Read the missing bytes from the stream.
            $data = $this->stream->read($remaining);

            if ($data === false || strlen($data) !== $remaining) {
                throw new ImapStreamException('Unexpected end of stream while trying to fill the buffer');
            }

            $literal .= $data;
        }

        // Verify that the literal length matches the expected length.
        if (strlen($literal) !== $length) {
            throw new ImapParserException(sprintf(
                'Literal length mismatch: expected %d, got %d',
                $length,
                strlen($literal)
            ));
        }

        return new Literal($literal);
    }

    /**
     * Reads a number or atom token.
     */
    protected function readNumberOrAtom(): Token
    {
        $position = $this->position;

        // First char must be a digit to even consider a number.
        if (! ctype_digit($this->buffer[$position] ?? '')) {
            return $this->readAtom();
        }

        // Walk forward to find the end of the digit run.
        while (ctype_digit($this->buffer[$position] ?? '')) {
            $position++;

            $this->ensureBuffer($position - $this->position + 1);
        }

        $next = $this->buffer[$position] ?? null;

        // If next is EOF or a delimiter, it's a Number.
        if ($next === null || $this->isDelimiter($next)) {
            return $this->readNumber();
        }

        // Otherwise it's an Atom.
        return $this->readAtom();
    }

    /**
     * Reads a number token.
     *
     * A number consists of one or more digit characters and represents a numeric value.
     */
    protected function readNumber(): Number
    {
        $start = $this->position;

        while (true) {
            $this->ensureBuffer(1);

            $char = $this->currentChar();

            if ($char === null) {
                break;
            }

            if (! ctype_digit($char)) {
                break;
            }

            $this->advance();
        }

        return new Number(substr($this->buffer, $start, $this->position - $start));
    }

    /**
     * Reads an atom token.
     *
     * ATOMs are sequences of printable ASCII characters that do not contain delimiters.
     */
    protected function readAtom(): Atom
    {
        $value = '';

        while (true) {
            $this->ensureBuffer(1);

            $char = $this->currentChar();

            if ($char === null) {
                break;
            }

            if (! $this->isValidAtomCharacter($char)) {
                break;
            }

            $value .= $char;

            $this->advance();
        }

        if (strcasecmp($value, 'NIL') === 0) {
            return new Nil($value);
        }

        return new Atom($value);
    }

    /**
     * Reads an email address token enclosed in angle brackets.
     *
     * Email addresses are enclosed in angle brackets ("<" and ">").
     *
     * For example "<johndoe@email.com>"
     */
    protected function readEmailAddress(): ?EmailAddress
    {
        $value = '';

        while (true) {
            $this->ensureBuffer(1);

            $char = $this->currentChar();

            if ($char === null) {
                throw new ImapParserException('Unterminated email address, expected ">"');
            }

            if ($char === '>') {
                $this->advance(); // Skip the closing '>'.

                break;
            }

            $value .= $char;

            $this->advance();
        }

        return new EmailAddress($value);
    }

    /**
     * Ensures that at least the given length in characters are available in the buffer.
     */
    protected function ensureBuffer(int $length): void
    {
        // If we have enough data in the buffer, return early.
        while ((strlen($this->buffer) - $this->position) < $length) {
            $data = $this->stream->fgets();

            if ($data === false) {
                return;
            }

            $this->buffer .= $data;
        }
    }

    /**
     * Returns the current character in the buffer.
     */
    protected function currentChar(): ?string
    {
        return $this->buffer[$this->position] ?? null;
    }

    /**
     * Advances the internal pointer by $n characters.
     */
    protected function advance(int $n = 1): void
    {
        $this->position += $n;

        // If we have consumed the entire buffer, reset it.
        if ($this->position >= strlen($this->buffer)) {
            $this->flushBuffer();
        }
    }

    /**
     * Flush the buffer and reset the position.
     */
    protected function flushBuffer(): void
    {
        $this->buffer = '';
        $this->position = 0;
    }

    /**
     * Determine if the given character is a valid atom character.
     */
    protected function isValidAtomCharacter(string $char): bool
    {
        // Get the ASCII code.
        $code = ord($char);

        // Allow only printable ASCII (32-126).
        if ($code < 32 || $code > 126) {
            return false;
        }

        // Delimiters are not allowed inside ATOMs.
        if ($this->isDelimiter($char)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the given character is a delimiter for tokenizing responses.
     */
    protected function isDelimiter(string $char): bool
    {
        // This delimiter list includes additional characters (such as square
        // brackets, curly braces, and angle brackets) to ensure that tokens
        // like the response code group brackets are split out. This is fine
        // for tokenizing responses, even though it’s more restrictive
        // than the IMAP atom definition in RFC 3501 (section 9).
        return in_array($char, [' ', '(', ')', '[', ']', '{', '}', '<', '>'], true);
    }
}
