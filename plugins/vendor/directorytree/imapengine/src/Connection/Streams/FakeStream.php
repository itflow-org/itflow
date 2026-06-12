<?php

namespace DirectoryTree\ImapEngine\Connection\Streams;

use PHPUnit\Framework\Assert;
use RuntimeException;

class FakeStream implements StreamInterface
{
    /**
     * Lines queued for testing; each call to fgets() pops the next line.
     *
     * @var string[]
     */
    protected array $buffer = [];

    /**
     * Data that has been "written" to this fake stream (for assertion).
     *
     * @var string[]
     */
    protected array $written = [];

    /**
     * The connection info.
     */
    protected ?array $connection = null;

    /**
     * The mock meta info.
     */
    protected array $meta = [
        'crypto' => [
            'protocol' => '',
            'cipher_name' => '',
            'cipher_bits' => 0,
            'cipher_version' => '',
        ],
        'mode' => 'c',
        'eof' => false,
        'blocked' => false,
        'timed_out' => false,
        'seekable' => false,
        'unread_bytes' => 0,
        'stream_type' => 'tcp_socket/unknown',
    ];

    /**
     * Feed a line to the stream buffer with a newline character.
     */
    public function feed(array|string $lines): self
    {
        // We'll ensure that each line ends with a CRLF,
        // as this is the expected behavior of every
        // reply that comes from an IMAP server.
        $lines = array_map(fn (string $line) => (
            rtrim($line, "\r\n")."\r\n"
        ), (array) $lines);

        array_push($this->buffer, ...$lines);

        return $this;
    }

    /**
     * Feed a raw line to the stream buffer.
     */
    public function feedRaw(array|string $lines): self
    {
        array_push($this->buffer, ...(array) $lines);

        return $this;
    }

    /**
     * Set the timed out status.
     */
    public function setMeta(string $attribute, mixed $value): self
    {
        if (! isset($this->meta[$attribute])) {
            throw new RuntimeException(
                "Unknown metadata attribute: {$attribute}"
            );
        }

        if (gettype($this->meta[$attribute]) !== gettype($value)) {
            throw new RuntimeException(
                "Metadata attribute {$attribute} must be of type ".gettype($this->meta[$attribute])
            );
        }

        $this->meta[$attribute] = $value;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function open(?string $transport = null, ?string $host = null, ?int $port = null, ?int $timeout = null, array $options = []): bool
    {
        $this->connection = compact('transport', 'host', 'port', 'timeout', 'options');

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        $this->buffer = [];
        $this->connection = null;
    }

    /**
     * {@inheritDoc}
     */
    public function read(int $length): string|false
    {
        if (! $this->opened()) {
            return false;
        }

        if ($this->meta['eof'] && empty($this->buffer)) {
            return false; // EOF and no data left. Indicate end of stream.
        }

        $data = implode('', $this->buffer);

        $availableLength = strlen($data);

        if ($availableLength === 0) {
            // No data available right now (but not EOF).
            // Simulate non-blocking behavior.
            return '';
        }

        $bytesToRead = min($length, $availableLength);

        $result = substr($data, 0, $bytesToRead);

        $remainingData = substr($data, $bytesToRead);

        $this->buffer = $remainingData !== '' ? [$remainingData] : [];

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function fgets(): string|false
    {
        if (! $this->opened()) {
            return false;
        }

        // Simulate timeout/eof checks.
        if ($this->meta['timed_out'] || $this->meta['eof']) {
            return false;
        }

        return array_shift($this->buffer) ?? false;
    }

    /**
     * {@inheritDoc}
     */
    public function fwrite(string $data): int|false
    {
        if (! $this->opened()) {
            return false;
        }

        $this->written[] = $data;

        return strlen($data);
    }

    /**
     * {@inheritDoc}
     */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * {@inheritDoc}
     */
    public function opened(): bool
    {
        return (bool) $this->connection;
    }

    /**
     * {@inheritDoc}
     */
    public function setTimeout(int $seconds): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function setSocketSetCrypto(bool $enabled, ?int $method): bool|int
    {
        return true;
    }

    /**
     * Assert that the given data was written to the stream.
     */
    public function assertWritten(string $string): void
    {
        $found = false;

        foreach ($this->written as $index => $written) {
            if (str_contains($written, $string)) {
                unset($this->written[$index]);

                $found = true;

                break;
            }
        }

        Assert::assertTrue($found, "Failed asserting that the string '{$string}' was written to the stream.");
    }
}
