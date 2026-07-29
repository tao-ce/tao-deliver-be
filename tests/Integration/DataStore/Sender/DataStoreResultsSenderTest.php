<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\DataStore\Sender;

use App\DataStore\Sender\DataStoreResultsSender;
use App\Environment\FeatureFlagAdapterInterface;
use App\Lti\LtiCustomSettings;
use App\Messenger\Message\DataStoreResultMessage;
use App\Qti\Extractor\ItemResponseStatusResolver;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Factory\AssessmentTestSessionFactory;
use App\Tests\Traits\DataStoreTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DataStoreResultsSenderTest extends KernelTestCase
{
    use DataStoreTestingTrait;
    use MessengerTestingTrait;

    /** @var DataStoreResultsSender */
    private $subject;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->setUpTestMessageBus();

        $this->subject = new DataStoreResultsSender(
            $this->createMock(FeatureFlagAdapterInterface::class),
            $this->createMock(ItemResponseStatusResolver::class),
            new DeliveryExecutionPropertyService(
                $this->getTestSessionAccessorFactoryMock(),
                static::getContainer()->get(LtiCustomSettings::class),
                static::getContainer()->get(AssessmentTestSessionFactory::class),
            ),
            $this->createExtractDeliveryExecutionResultServiceMock(),
            static::getContainer()->get(LoggerInterface::class),
            static::getContainer()->get(NormalizerInterface::class),
            $this->testMessageBus,
            static::getContainer()->get(LtiCustomSettings::class),
        );
    }

    public function testSend(): void
    {
        $this->subject->send(
            $this->getDeliveryExecution($this->getLtiParameters()),
        );

        $this->assertCountTransportMessages('datastore-result', 1);
        $this->assertHasTransportMessage('datastore-result', DataStoreResultMessage::class);
    }
}
