<?php

namespace DirectoryTree\ImapEngine\Connection\Streams;

use DirectoryTree\ImapEngine\Exceptions\ImapConnectionFailedException;

class ImapStream implements StreamInterface
{
    /**
     * The underlying PHP stream resource.
     *
     * @var resource|null
     */
    protected mixed $stream = null;

    /**
     * {@inheritDoc}
     */
    public function open(string $transport, string $host, int $port, int $timeout, array $options = []): bool
    {
        $this->stream = @stream_socket_client(
            $address = "{$transport}://{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create($options)
        );

        if (! $this->stream) {
            throw new ImapConnectionFailedException("Unable to connect to {$address} ({$errstr})", $errno);
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        if ($this->opened()) {
            fclose($this->stream);
        }

        $this->stream = null;
    }

    /**
     * {@inheritDoc}
     */
    public function read(int $length): string|false
    {
        if (! $this->opened()) {
            return false;
        }

        $data = '';

        while (strlen($data) < $length && ! feof($this->stream)) {
            $chunk = fread($this->stream, $length - strlen($data));

            if ($chunk === false) {
                return false;
            }

            $data .= $chunk;
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function fgets(): string|false
    {
        return $this->opened() ? fgets($this->stream) : false;
    }

    /**
     * {@inheritDoc}
     */
    public function fwrite(string $data): int|false
    {
        return $this->opened() ? fwrite($this->stream, $data) : false;
    }

    /**
     * {@inheritDoc}
     */
    public function meta(): array
    {
        return $this->opened() ? stream_get_meta_data($this->stream) : [];
    }

    /**
     * {@inheritDoc}
     */
    public function opened(): bool
    {
        return is_resource($this->stream);
    }

    /**
     * {@inheritDoc}
     */
    public function setTimeout(int $seconds): bool
    {
        return stream_set_timeout($this->stream, $seconds);
    }

    /**
     * {@inheritDoc}
     */
    public function setSocketSetCrypto(bool $enabled, ?int $method): bool|int
    {
        return stream_socket_enable_crypto($this->stream, $enabled, $method);
    }
}
