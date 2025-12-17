<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Normalizer;

use App\DocumentManager\Normalizer\ExternalTimerDefinitionNormalizerTrait;
use App\Tests\Traits\ExternalTimerDefinitionTestingTrait;
use PHPUnit\Framework\MockObject\MockTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use ReflectionClass;

class ExternalTimerDefinitionNormalizerTraitTest extends KernelTestCase
{
    use ExternalTimerDefinitionTestingTrait;

    /** @var ExternalTimerDefinitionNormalizerTrait | MockTrait */
    private $subject;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->subject = $this->getMockBuilder(ExternalTimerDefinitionNormalizerTrait::class)->getMockForTrait();
    }

    public function testNormalizeExternalTimerDefinition()
    {
        $normaliseMethod = $this->getTraitMethod('normalizeExternalTimerDefinition');

        $this->assertEquals(
            ['externalTimerData' => null],
            $normaliseMethod->invoke($this->subject, null),
        );
    }

    public function testAllowToNormalizeEmptyData()
    {
        $normaliseMethod = $this->getTraitMethod('normalizeExternalTimerDefinition');

        $this->assertEquals(
            [
                'externalTimerData' => json_encode($this->timerDataExample),
            ],
            $normaliseMethod->invoke(
                $this->subject,
                $this->createExternalDefinitionTimerFromArray($this->timerDataExample),
            ),
        );
    }

    public function testDenormalizeExternalTimerDefinitionAllowEmptyInput()
    {
        $denormalizeMethod = $this->getTraitMethod('denormalizeExternalTimerDefinition');
        $this->assertEmpty($denormalizeMethod->invoke($this->subject, []));
    }

    public function testDenormalizeExternalTimerDefinition()
    {
        $denormalizeMethod = $this->getTraitMethod('denormalizeExternalTimerDefinition');
        $this->assertEquals(
            $this->createExternalDefinitionTimerFromArray($this->timerDataExample),
            $denormalizeMethod->invoke(
                $this->subject,
                [
                    'externalTimerData' => json_encode($this->timerDataExample),
                ],
            ),
        );
    }

    private function getTraitMethod($name)
    {
        $class = new ReflectionClass($this->subject);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }
}
