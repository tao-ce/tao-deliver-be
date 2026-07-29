<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\OpenTelemetry\Messenger\Middleware;

use App\OpenTelemetry\Service\AttributesPropagator;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Instrumentation\Symfony\MessengerInstrumentation;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SemConv\Incubating\Attributes\MessagingIncubatingAttributes;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

class Telemetry implements MiddlewareInterface
{
    private TracerInterface $tracer;

    public function __construct(
        private readonly AttributesPropagator $attributesPropagator,
        private readonly SerializerInterface $serializer,
        private readonly bool $captureProducerPayload,
        private readonly bool $captureConsumerPayload,
        private readonly string $capturedPayloadFormat = 'json',
    ) {
        $this->tracer = Globals::tracerProvider()->getTracer('async');
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (Sdk::isInstrumentationDisabled('async') === true) {
            return $stack->next()->handle($envelope, $stack);
        }

        return $envelope->last(ReceivedStamp::class)
            ? $this->handleReceiving($envelope, $stack)
            : $this->handleSending($envelope, $stack);
    }

    private function handleReceiving(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $parent = Globals::propagator()->extract($envelope, $this->attributesPropagator);

        $span = $this->tracer->spanBuilder(sprintf('RECEIVE %s', $message::class))
            ->setSpanKind(SpanKind::KIND_CONSUMER)
            ->setParent($parent)
            ->setAttribute(
                MessagingIncubatingAttributes::MESSAGING_SYSTEM,
                MessagingIncubatingAttributes::MESSAGING_SYSTEM_VALUE_GCP_PUBSUB,
            )
            ->setAttribute(
                MessagingIncubatingAttributes::MESSAGING_OPERATION_TYPE,
                MessagingIncubatingAttributes::MESSAGING_OPERATION_TYPE_VALUE_RECEIVE,
            )
            ->setAttribute(MessengerInstrumentation::ATTRIBUTE_MESSENGER_MESSAGE, $message::class)
            ->startSpan();

        if ($this->captureConsumerPayload) {
            try {
                $span->setAttribute(
                    'messaging.message.payload',
                    $this->serializer->serialize($message, $this->capturedPayloadFormat),
                );
            } catch (Throwable) {
            }
        }

        $scope = $span->activate();

        try {
            $result = $stack->next()->handle($envelope, $stack);
            $span->setStatus(StatusCode::STATUS_OK);
            return $result;
        } catch (Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $span->end();
            $scope->detach();
            Globals::tracerProvider()->forceFlush();
        }
    }

    private function handleSending(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $span = $this->tracer->spanBuilder(sprintf('SEND %s', $message::class))
            ->setSpanKind(SpanKind::KIND_PRODUCER)
            ->setAttribute(
                MessagingIncubatingAttributes::MESSAGING_SYSTEM,
                MessagingIncubatingAttributes::MESSAGING_SYSTEM_VALUE_GCP_PUBSUB,
            )
            ->setAttribute(
                MessagingIncubatingAttributes::MESSAGING_OPERATION_TYPE,
                MessagingIncubatingAttributes::MESSAGING_OPERATION_TYPE_VALUE_SEND,
            )
            ->setAttribute(MessengerInstrumentation::ATTRIBUTE_MESSENGER_MESSAGE, $message::class)
            ->startSpan();

        if ($this->captureProducerPayload) {
            try {
                $span->setAttribute(
                    'messaging.message.payload',
                    $this->serializer->serialize($message, $this->capturedPayloadFormat),
                );
            } catch (Throwable) {
            }
        }

        $scope = $span->activate();

        try {
            TraceContextPropagator::getInstance()->inject($envelope, $this->attributesPropagator);

            $result = $stack->next()->handle($envelope, $stack);

            $sentStamp = $result->last(SentStamp::class);
            if ($sentStamp) {
                $span->setAttribute(
                    MessagingIncubatingAttributes::MESSAGING_DESTINATION_NAME,
                    $sentStamp->getSenderAlias() ?? 'unknown',
                );
            }

            $span->setStatus(StatusCode::STATUS_OK);
            return $result;
        } catch (Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $span->end();
            $scope->detach();
        }
    }
}
