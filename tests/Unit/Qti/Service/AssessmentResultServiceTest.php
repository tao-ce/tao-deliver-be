<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Qti\Service;

use App\Qti\Exception\ResultCannotBePersistedException;
use App\Qti\Exception\ResultNotFoundException;
use App\Qti\Service\AssessmentResultService;
use App\Service\Asset\MimeTypeDetectorService;
use Exception;
use JsonSerializable;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub\Exception as StubException;
use PHPUnit\Framework\MockObject\Stub\ReturnStub;
use PHPUnit\Framework\TestCase;

class AssessmentResultServiceTest extends TestCase
{
    private FilesystemOperator|MockObject $storageMock;
    private AssessmentResultService $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageMock = $this->createMock(FilesystemOperator::class);

        $this->sut = new AssessmentResultService(
            $this->storageMock,
            new MimeTypeDetectorService(
                new ExtensionMimeTypeDetector(),
            ),
        );
    }

    /**
     * @dataProvider getAssessmentResultDataProvider
     *
     * @param string $resultId
     * @param string|bool $assessmentResult
     * @param UnableToReadFile|null $fileNotFoundException
     */
    public function testGetAssessmentResult(
        string $resultId,
        string|bool $assessmentResult,
        ?UnableToReadFile $fileNotFoundException = null,
    ): void {
        $isResultExpectedToBeSuccessfullyFetched = false !== $assessmentResult;

        $this->assertFetching(
            $resultId,
            $isResultExpectedToBeSuccessfullyFetched ? $assessmentResult : false,
            $fileNotFoundException,
        );

        if (!$isResultExpectedToBeSuccessfullyFetched || null !== $fileNotFoundException) {
            $this->expectExceptionObject(
                ResultNotFoundException::createFromResultId($resultId, $fileNotFoundException),
            );
        }

        $this->assertSame(
            $assessmentResult,
            stream_get_contents($this->sut->getStreamedAssessmentResult($resultId)),
        );
    }

    /**
     * @dataProvider persistDataProvider
     *
     * @param string $resultId
     * @param JsonSerializable|array $assessmentResult
     * @param bool $isPersistenceSuccessful
     * @param Exception|null $expectedException
     */
    public function testPersist(
        string $resultId,
        string $assessmentResult,
        bool $isPersistenceSuccessful = true,
        ?Exception $expectedException = null,
    ): void {
        $this->assertPersistence($resultId, $assessmentResult, $isPersistenceSuccessful);

        if (null !== $expectedException) {
            $this->expectExceptionObject($expectedException);
        }

        $this->sut->persist($resultId, $assessmentResult);
    }

    public function getAssessmentResultDataProvider(): array
    {
        return [
            'Simple result ID'                       => ['result_id', '<xml></xml>'],
            'Slash-containing result ID'             => ['result/id\\1', '<xml></xml>'],
            'URI Result ID and serializable payload' => ['http://localhost#result_id', '<xml></xml>'],
            'Unsuccessful fetch'                     => ['result_id', false],
            'Storage exception'                      => [
                'result_id',
                '<xml></xml>',
                UnableToReadFile::fromLocation('result_id'),
            ],
        ];
    }

    public function persistDataProvider(): array
    {
        return [
            'Simple result ID and array payload' => ['result_id', '<xml></xml>'],
            'Slash-containing result ID and array payload' => ['result/id\\1', '<xml></xml>'],
            'URI Result ID and serializable payload' => ['http://localhost#result_id', '<xml></xml>'],
            'Unsuccessful persistence' => [
                'result_id',
                '<xml></xml>',
                false,
                ResultCannotBePersistedException::createFromResultId('result_id'),
            ],
            'Malformed data' => [
                'result_id',
                strtoupper(substr('réßπøñßë', 2)),
            ],
        ];
    }

    private function normalizeResultId(string $resultId): string
    {
        return preg_replace('~[/\\\\]~', '_', $resultId) . '.xml';
    }

    private function assertPersistence(
        string $resultId,
        string $assessmentResult,
        bool $isPersistenceSuccessful = true,
    ): void {
        $location = $this->normalizeResultId($resultId);
        $mocker = $this->storageMock
            ->expects(static::once())
            ->method('write')
            ->with(
                $location,
                $assessmentResult,
            );

        if (!$isPersistenceSuccessful) {
            $mocker->willThrowException(UnableToWriteFile::atLocation($location));
        }
    }

    private function assertFetching(
        string $resultId,
        string|bool $assessmentResult,
        ?Exception $expectedException = null,
    ): ?Exception {

        $this->storageMock
            ->expects(static::once())
            ->method('readStream')
            ->with(
                $this->normalizeResultId($resultId),
            )
            ->will(
                null !== $expectedException
                    ? new StubException($expectedException)
                    : new ReturnStub($this->getStringStream($assessmentResult)),
            );

        return null;
    }

    private function getStringStream(string|bool $content)
    {
        if (is_bool($content)) {
            return false;
        }
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }
}
