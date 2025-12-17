<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Response;

use Carbon\Carbon;
use LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AssetResponse extends Response
{
    private $resource;

    /** @var string|null */
    private $assetMimeType;

    /** @var int|null */
    private $assetTimestamp;

    /** @var int|null */
    private $assetSize;

    /** @var int */
    private $offset = 0;

    /** @var int */
    private $maxlength = -1;

    public function __construct(
        $resource,
        ?string $assetMimeType,
        ?int $assetTimestamp,
        ?int $assetSize,
        int $status = Response::HTTP_OK,
        array $headers = [],
        bool $public = true,
        bool $autoLastModified = true,
    ) {
        parent::__construct(null, $status, $headers);

        $this->assetSize = $assetSize;
        $this->assetTimestamp = $assetTimestamp;
        $this->assetMimeType = $assetMimeType;
        $this->setResource($resource);
        if ($autoLastModified) {
            $this->setAutoLastModified();
        }

        if ($public) {
            $this->setPublic();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): string|false
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(Request $request): static
    {
        $this->processContentType();

        if ('HTTP/1.0' !== $request->server->get('SERVER_PROTOCOL')) {
            $this->setProtocolVersion('1.1');
        }

        $this->ensureIEOverSSLCompatibility($request);

        $this->offset = 0;
        $this->maxlength = -1;

        if (!$this->assetSize) {
            return $this;
        }
        $this->headers->set('Content-Length', (string)$this->assetSize);

        $this->processAcceptRanges($request);

        // Process the range headers.
        $this->processRangeHeaders($request);

        return $this;
    }

    /**
     * Sends the file.
     *
     * {@inheritdoc}
     */
    public function sendContent(): static
    {
        if (!$this->isSuccessful()) {
            return parent::sendContent();
        }

        if (0 === $this->maxlength) {
            return $this;
        }

        $out = fopen('php://output', 'wb');

        stream_copy_to_stream($this->resource, $out, $this->maxlength, $this->offset);

        if (is_resource($this->resource)) {
            fclose($this->resource);
        }

        fclose($out);

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws LogicException when the content is not null
     */
    public function setContent($content): static
    {
        if (null !== $content) {
            throw new LogicException('The content cannot be set on a AssetResponse instance.');
        }

        return $this;
    }

    /**
     * @throws HttpException
     */
    private function setResource($resource): static
    {
        if (false === is_resource($resource)) {
            throw new HttpException(Response::HTTP_NOT_FOUND, 'The requested asset is not a resource');
        }

        $this->resource = $resource;

        return $this;
    }

    private function setAutoLastModified(): static
    {
        if ($this->assetTimestamp) {
            $this->setLastModified(Carbon::createFromFormat('U', (string)$this->assetTimestamp));
        }

        return $this;
    }

    private function hasValidIfRangeHeader($header): bool
    {
        if (null === $lastModified = $this->getLastModified()) {
            return false;
        }

        return $lastModified->format('D, d M Y H:i:s') . ' GMT' === $header;
    }

    /**
     * @param Request $request
     */
    private function processAcceptRanges(Request $request): void
    {
        if (!$this->headers->has('Accept-Ranges')) {
            // Only accept ranges on safe HTTP methods
            $this->headers->set('Accept-Ranges', $request->isMethodSafe(false) ? 'bytes' : 'none');
        }
    }

    private function processContentType(): void
    {
        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', $this->assetMimeType ?: 'application/octet-stream');
        }
    }

    /**
     * @param Request $request
     */
    private function processRangeHeaders(Request $request): void
    {
        if (
            $request->headers->has('Range')
            && (
                !$request->headers->has('If-Range')
                || $this->hasValidIfRangeHeader($request->headers->get('If-Range'))
            )
        ) {
            $range = $request->headers->get('Range');
            [$start, $end] = $this->processRangeParameters($range);

            $this->processContentRange($start, $end);
        }
    }

    private function processContentRange(int $start, int $end): void
    {
        if ($start <= $end) {
            if ($start < 0 || $end > $this->assetSize - 1) {
                $this->setStatusCode(Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE);
                $this->headers->set('Content-Range', sprintf('bytes */%s', $this->assetSize));
            } elseif (0 !== $start || $end !== $this->assetSize - 1) {
                $this->maxlength = $end < $this->assetSize ? $end - $start + 1 : -1;
                $this->offset = $start;

                $this->setStatusCode(Response::HTTP_PARTIAL_CONTENT);
                $this->headers->set(
                    'Content-Range',
                    sprintf('bytes %s-%s/%s', $start, $end, $this->assetSize),
                );
                $this->headers->set('Content-Length', strval($end - $start + 1));
            }
        }
    }

    private function processRangeParameters($range): array
    {
        [$start, $end] = explode('-', substr($range, 6), 2) + [0];

        $end = ('' === $end) ? $this->assetSize - 1 : (int)$end;

        if ('' === $start) {
            $start = $this->assetSize - $end;
            $end = $this->assetSize - 1;
        } else {
            $start = (int)$start;
        }

        return [$start, $end];
    }
}
