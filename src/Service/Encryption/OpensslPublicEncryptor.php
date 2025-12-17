<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Encryption;

use App\Service\Encryption\Contract\EncryptorInterface;
use App\Service\Encryption\Exception\EncryptionKeyNotInitializedException;
use OpenSSLAsymmetricKey;

class OpensslPublicEncryptor implements EncryptorInterface
{
    private const PADDING_SCHEME_SIZE_IN_BYTES = 11;
    private const BYTE_SIZE_IN_BITS = 8;

    private ?OpenSSLAsymmetricKey $encryptionKey = null;
    private int $chunkLength = 1;

    /**
     * @inheritDoc
     */
    public function encrypt(string $data): string
    {
        if ($this->encryptionKey === null) {
            throw new EncryptionKeyNotInitializedException('Encryption key is not set');
        }

        $result = '';
        do {
            $input = substr($data, 0, $this->chunkLength);
            $data = substr($data, $this->chunkLength);
            openssl_public_encrypt($input, $encrypted, $this->encryptionKey);
            $result .= $encrypted;
        } while ($data);

        return $result;
    }

    public function setEncryptionKey(string $key): static
    {
        $this->encryptionKey = openssl_get_publickey($key) ?: null;
        $this->initializeChunkLength();

        return $this;
    }

    private function initializeChunkLength(): void
    {
        if (!($this->encryptionKey && $keyDetails = openssl_pkey_get_details($this->encryptionKey))) {
            throw new \InvalidArgumentException("Asymmetric public key is expected, $this->encryptionKey provided");
        }
        $this->chunkLength = $keyDetails['bits'] / self::BYTE_SIZE_IN_BITS - self::PADDING_SCHEME_SIZE_IN_BYTES;
    }
}
