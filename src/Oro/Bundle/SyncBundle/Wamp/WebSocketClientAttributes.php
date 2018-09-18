<?php

namespace Oro\Bundle\SyncBundle\Wamp;

/**
 * This class represents attributes which will be use to create a Client
 */
class WebSocketClientAttributes
{
    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /** @var string */
    private $path;

    /**
     * Any registered socket transport returned by http://php.net/manual/en/function.stream-get-transports.php
     *
     * @var string
     */
    private $transport;

    /**
     * Will be passed to a context create function http://php.net/manual/en/function.stream-context-create.php
     *
     * @var array
     */
    private $contextOptions;

    /**
     * @param string $host
     * @param int $port
     * @param string|null $path
     * @param string $transport
     * @param array $contextOptions
     */
    public function __construct(
        string $host,
        int $port,
        ?string $path,
        string $transport,
        array $contextOptions
    ) {
        if ($host === '*') {
            $host =  '127.0.0.1';
        }

        $this->host = $host;
        $this->port = $port;
        $this->path = (string) $path;
        $this->transport = $transport;
        $this->contextOptions = $contextOptions;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getTransport(): string
    {
        return $this->transport;
    }

    /**
     * @return array
     */
    public function getContextOptions(): array
    {
        return $this->contextOptions;
    }
}
