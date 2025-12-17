<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\PublicationCommand;
use App\Domain\Publication\Model\Publication;
use App\Messenger\Message\PublicationMessage;
use App\Repository\PublicationRepository;
use App\Service\Publication\CreatePublicationService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use League\Flysystem\FilesystemReader;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Exception\InvalidParameterException;

class PublicationCommandTest extends KernelTestCase
{
    use DomainTestingTrait;
    use MessengerTestingTrait;
    use LoggerTestingTrait;

    protected function setUp(): void
    {
        parent::setUp();
        static::bootKernel();

        $this->setUpTestLogHandler();
        $this->setUpTestMessageBus();
    }

    /**
     * @dataProvider getFilesData
     */
    public function testItSendsToPublication(string $zipFilePath, bool $isBase64Encoded): void
    {
        $command = new PublicationCommand(
            static::getContainer()->get(LoggerInterface::class),
            static::getContainer()->get(CreatePublicationService::class),
        );

        /** @var FilesystemReader $storage */
        $storage = static::getContainer()->get('base64_zip.storage');

        $commandTester = new CommandTester($command);

        $this->assertCountTransportMessages('publication', 0);

        $arguments = [
            'testPath' => $zipFilePath,
            'tenantId' => '1',
            '--configuration' => '{"label": "test manual publish","status": true,"metadata": {"http://psi.udir.no/ontologi/pet/arrangementstype": ["http://psi.udir.no/pet/eksempeloppgave"],"http://psi.udir.no/ontologi/pet/oppgavesett": ["http://psi.oasis-open.org/iso/639/#eng"]},"availabilityDate": null,"expiryDate": null}',
        ];

        if ($isBase64Encoded) {
            $arguments['--base64'] = true;
        }

        $commandTester->execute($arguments);

        $this->assertCountTransportMessages('publication', 1);

        $envelope = $this->getTransportMessages('publication')[0];

        self::assertSame(0, $commandTester->getStatusCode());

        /** @var PublicationMessage $publicationMessage */
        $publicationMessage = $envelope->getMessage();

        self::assertEquals('1', $publicationMessage->getTenantId());
        self::assertEquals($publicationMessage->getPublicationId() . '/base64PackageContent.txt', $publicationMessage->getBase64ZipPath());
        self::assertTrue($publicationMessage->getConfiguration()['status']);
        self::assertEquals(
            ['http://psi.udir.no/pet/eksempeloppgave'],
            $publicationMessage->getConfiguration()['metadata']['http://psi.udir.no/ontologi/pet/arrangementstype'],
        );
        self::assertEquals(
            ['http://psi.oasis-open.org/iso/639/#eng'],
            $publicationMessage->getConfiguration()['metadata']['http://psi.udir.no/ontologi/pet/oppgavesett'],
        );

        $repository = static::getContainer()->get(PublicationRepository::class);

        /** @var Publication $publication */
        $publication = $repository->find($publicationMessage->getPublicationId());

        self::assertEquals(Publication::STATUS_CREATED, $publication->getStatus());
        self::assertEquals('test manual publish', $publication->getPackageConfiguration()['label']);
        self::assertEquals(
            ['http://psi.udir.no/pet/eksempeloppgave'],
            $publication->getPackageConfiguration()['metadata']['http://psi.udir.no/ontologi/pet/arrangementstype'],
        );
        self::assertEquals(
            ['http://psi.oasis-open.org/iso/639/#eng'],
            $publication->getPackageConfiguration()['metadata']['http://psi.udir.no/ontologi/pet/oppgavesett'],
        );

        $fileContent = file_get_contents($zipFilePath);

        if (false === $isBase64Encoded) {
            $fileContent = base64_encode($fileContent);
        }

        self::assertEquals($fileContent, $storage->read($publication->getPackagePath()));
    }

    public function testItControlsConfigurationKeys(): void
    {
        $this->expectException(InvalidParameterException::class);
        $command = new PublicationCommand(
            static::getContainer()->get(LoggerInterface::class),
            static::getContainer()->get(CreatePublicationService::class),
        );

        $commandTester = new CommandTester($command);

        $this->assertCountTransportMessages('publication', 0);
        $commandTester->execute(
            [
                'testPath' => __DIR__ . '/../../Resources/Qti/ZipPackages/basic.zip',
                'tenantId' => '1',
                '--configuration' => '{"some_forbidden_key": "test manual publish","status": true,"metadata": {"http://psi.udir.no/ontologi/pet/arrangementstype": ["http://psi.udir.no/pet/eksempeloppgave"],"http://psi.udir.no/ontologi/pet/oppgavesett": ["http://psi.oasis-open.org/iso/639/#eng"]},"availabilityDate": null,"expiryDate": null}',
            ],
        );

        $this->assertCountTransportMessages('publication', 0);

        self::assertNotEquals(0, $commandTester->getStatusCode());
        self::assertStringContainsString('some_forbidden_key', $commandTester->getDisplay());
    }

    public function getFilesData(): array
    {
        return [
            'zip archive' => [__DIR__ . '/../../Resources/Qti/ZipPackages/basic.zip', false],
            'base64 encoded file' => [__DIR__ . '/../../Resources/Qti/Base64EncodedPackages/basic_package.txt', true],
        ];
    }
}
