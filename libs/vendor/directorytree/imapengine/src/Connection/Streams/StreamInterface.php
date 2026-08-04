<?php

namespace DirectoryTree\ImapEngine\Connection\Streams;

interface StreamInterface
{
    /**
     * Open the underlying stream.
     */
    public function open(string $transport, string $host, int $port, int $timeout, array $options = []): bool;

    /**
     * Close the underlying stream.
     */
    public function close(): void;

    /**
     * Read data from the stream.
     */
    public function read(int $length): string|false;

    /**
     * Read a single line from the stream.
     */
    public function fgets(): string|false;

    /**
     * Write data to the stream.
     */
    public function fwrite(string $data): int|false;

    /**
     * Return meta info (like stream_get_meta_data).
     */
    public function meta(): array;

    /**
     * Determine if the stream is open.
     */
    public function opened(): bool;

    /**
     * Set the timeout on the stream.
     */
    public function setTimeout(int $seconds): bool;

    /**
     * Set encryption state on an already connected socked.
     */
    public function setSocketSetCrypto(bool $enabled, ?int $method): bool|int;
}
