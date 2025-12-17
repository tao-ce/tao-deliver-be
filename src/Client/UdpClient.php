<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Client;

use Socket;

class UdpClient
{
    public const DATAGRAM_MAX_LENGTH = 65023;

    /** @var string */
    protected $ip;

    /** @var int */
    protected $port;

    /** @var resource|Socket|null */
    protected $socket;

    public function __construct(string $ip, int $port)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP) ?: null;
    }

    public function write(string $line, string $header = ''): self
    {
        $this->send($this->assembleMessage($line, $header));

        return $this;
    }

    public function close(): void
    {
        if (is_resource($this->socket) || $this->socket instanceof Socket) {
            socket_close($this->socket);
            $this->socket = null;
        }
    }

    protected function send(string $chunk): void
    {
        if (!is_resource($this->socket) && !$this->socket instanceof Socket) {
            throw new \LogicException('The UdpSocket to ' . $this->ip . ':' . $this->port . ' has been closed and can not be written to anymore');
        }
        socket_sendto($this->socket, $chunk, strlen($chunk), $flags = 0, $this->ip, $this->port);
    }

    protected function assembleMessage(string $line, string $header): string
    {
        $chunkSize = self::DATAGRAM_MAX_LENGTH - strlen($header);

        return $header . substr($line, 0, $chunkSize);
    }
}
