<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\Encryption;

use App\Service\Encryption\Exception\EncryptionKeyNotInitializedException;
use App\Service\Encryption\OpensslPublicEncryptor;
use PHPUnit\Framework\TestCase;

class OpensslPublicEncryptorTest extends TestCase
{
    private const EXPECTED_VALUE = 'test';
    private const EXPECTED_LONG_VALUE = 'Amidst the swirling currents of uncertainty, the human spirit remains resolute, drawing strength from the depths of adversity, and forging a path towards a brighter tomorrow, where possibilities stretch far beyond the horizons of today.';
    private const DECRYPTION_CHUNK_SIZE = 128;

    private const PUBLIC_KEY = <<<PUBKEY
-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDEhwkAL7seZXDhmON8QdgnjV+5
msgHgANiZlD2dWHIjGFgodKEwXNA568nbtd2jEWVSeMVwG7OD4r3O8cjjbAeIgvy
MgmIArv3hvOZ5hHpk9AyRccIaiLWDIZ/T8zZ5cS4tu6Ukn41mfvYkew+3SEWbmnC
cWoRccH65zKZp4tZ1QIDAQAB
-----END PUBLIC KEY-----
PUBKEY;
    private const PRIVATE_KEY = <<<PRIVKEY
-----BEGIN RSA PRIVATE KEY-----
MIICXQIBAAKBgQDEhwkAL7seZXDhmON8QdgnjV+5msgHgANiZlD2dWHIjGFgodKE
wXNA568nbtd2jEWVSeMVwG7OD4r3O8cjjbAeIgvyMgmIArv3hvOZ5hHpk9AyRccI
aiLWDIZ/T8zZ5cS4tu6Ukn41mfvYkew+3SEWbmnCcWoRccH65zKZp4tZ1QIDAQAB
AoGAT1x8bP/ZX0f1kpRr4MSyJh639jqj5itysmzA6xWxvAu8Uwpl+cgo45/rok/n
YG76tnOU6TbBSCMMqhQQsbHI2VyE8NNjWTmdgoKtBNa5wiN8EbJ51G4MYQFlPG3Q
5IESnUM2mxc2Jdk4m9nmSprflNikBWUP3VlaPi6z/FU9Gk0CQQDzK8vgAAkLS+tB
GPPVNG9kNG1pknd+KFelaofxaBPgxP+IsDwDMOglXECUbNAxC2012RFmkN07sk7m
sGrG1NSzAkEAzuVIZVLcY+VJDCiFs7T3qexpjM2ROQvdwNNNoiamQNODiPWrKRzK
hp7n/fD9gR/UiQIoRdooWYvZxCV8JQ8rVwJBAKXUpztCKujGRE/niVlLYe+PBVJq
rQyezG6lQMHzfSLalX0M2lA+yQG5cN0He88GgNqpBoHQpt6wEbimdJrVx5sCQHHd
Ne6tn6VKttz+IDc6zWKzPZPEPrxKj4xjvkITS0Q6JBXoPn6t3bghFERpsNqzjeCp
U0i+O56snPiaOKycoJkCQQDxk4TWmCLWK70gq8RVSYhMTZlUzTn6axGtruwzikJO
CEJhmlULraLVdu3Posv9fCNsiNJoHmSi4klH7YuRoFTx
-----END RSA PRIVATE KEY-----
PRIVKEY;


    private OpensslPublicEncryptor $subject;

    protected function setUp(): void
    {
        $this->subject = new OpensslPublicEncryptor();
    }

    public function testEncryptionKeySetter()
    {
        self::assertInstanceOf(
            OpensslPublicEncryptor::class,
            $this->subject->setEncryptionKey(self::PUBLIC_KEY),
        );
    }

    public function testFailedEncryptionWithoutSetKey()
    {
        $this->expectException(EncryptionKeyNotInitializedException::class);
        $this->subject->encrypt(self::EXPECTED_VALUE);
    }

    public function testSuccessEncryption(): void
    {
        $encrypted = $this->subject->setEncryptionKey(self::PUBLIC_KEY)
            ->encrypt(self::EXPECTED_VALUE);
        openssl_private_decrypt($encrypted, $decrypted, self::PRIVATE_KEY);

        self::assertEquals(self::EXPECTED_VALUE, $decrypted);
    }

    public function testSuccessEncryptionLongText(): void
    {
        $encrypted = $this->subject->setEncryptionKey(self::PUBLIC_KEY)
            ->encrypt(self::EXPECTED_LONG_VALUE);

        self::assertEquals(self::EXPECTED_LONG_VALUE, $this->decrypt($encrypted));
    }

    private function decrypt(string $encryptedString): string
    {
        $result = '';
        do {
            $input = substr($encryptedString, 0, self::DECRYPTION_CHUNK_SIZE);
            $encryptedString = substr($encryptedString, self::DECRYPTION_CHUNK_SIZE);
            openssl_private_decrypt($input, $decrypted, self::PRIVATE_KEY);
            $result .= $decrypted;
        } while ($encryptedString);
        return $result;
    }
}
