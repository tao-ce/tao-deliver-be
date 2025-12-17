<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Logger\ExceptionContextLogger\ExceptionContextLoggerService;
use App\Lti\Exception\LtiLaunchAuthException;
use App\Lti\Exception\LtiLaunchException;
use App\Responder\SerializerResponder;
use OAT\Bundle\Lti1p3Bundle\Security\Authentication\Token\Message\LtiToolMessageSecurityToken;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class ErrorHandlerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ExceptionContextLoggerService $exceptionContextLoggerService,
        private SerializerResponder $responder,
        private ParameterBagInterface $parameterBag,
        private TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
            ConsoleErrorEvent::class => 'onConsoleErrorEvent',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $this->exceptionContextLoggerService->logException($exception);

        if (!$event->isMainRequest()) {
            return;
        }

        if ($this->handleLtiAuthenticationErrors($event)) {
            return;
        }

        if ($event->getThrowable() instanceof LtiLaunchException) {
            $this->handleLtiLaunchErrors($event);
            return;
        }

        $event->setResponse(
            $this->responder->createErrorJsonResponse($event->getThrowable()),
        );
    }

    public function onConsoleErrorEvent(ConsoleErrorEvent $consoleErrorEvent)
    {
        $exception = $consoleErrorEvent->getError();
        $this->exceptionContextLoggerService->logException($exception);
    }

    private function handleLtiAuthenticationErrors(ExceptionEvent $event): bool
    {
        $authenticationException = $event->getThrowable()->getPrevious();

        if (
            !$authenticationException instanceof AuthenticationException
            || !$authenticationException->getToken() instanceof LtiToolMessageSecurityToken
        ) {
            return false;
        }

        $event->setResponse(
            $this->buildDeliverFrontendErrorRedirectResponse($authenticationException),
        );

        return true;
    }

    private function handleLtiLaunchErrors(ExceptionEvent $event): void
    {
        /** @var LtiLaunchException $exception */
        $exception = $event->getThrowable();
        $ltiMessage = $exception->getLtiMessage();
        $locale = null;

        if ($ltiMessage->getUserIdentity() !== null && $ltiMessage->getUserIdentity()->getLocale() !== null) {
            $locale = $ltiMessage->getUserIdentity()->getLocale();
        }

        if ($ltiMessage->getLaunchPresentation() !== null) {
            if ($ltiMessage->getLaunchPresentation()->getReturnUrl() !== null) {
                $returnUrl = $ltiMessage->getLaunchPresentation()->getReturnUrl();
            }

            if ($locale === null && $ltiMessage->getLaunchPresentation()->getLocale() !== null) {
                $locale = $ltiMessage->getLaunchPresentation()->getLocale();
            }
        }

        $message = $this->buildErrorMessage($exception, $locale);

        $errorLog = !preg_match('/^\[(\w)+]/', $exception->getMessage())
            ? sprintf("[UNKNOWN] %s", $exception->getMessage())
            : $exception->getMessage();

        $event->setResponse(
            $this->buildDeliverFrontendErrorRedirectResponse(
                $exception,
                $returnUrl ?? null,
                $message,
                $errorLog,
                $locale,
            ),
        );
    }

    private function buildErrorMessage(Throwable $exception, ?string $locale): string
    {
        if ($exception->getPrevious() instanceof NotFoundHttpException) {
            return sprintf(
                '%s %s %s',
                $this->translator->trans('test.not.available', [], null, $locale),
                $this->translator->trans('test.not.found', [], null, $locale),
                $this->translator->trans('contact.administrator', [], null, $locale),
            );
        }

        if ($exception instanceof LtiLaunchAuthException) {
            return sprintf(
                '%s %s %s',
                $this->translator->trans('test.cannot.start', [], null, $locale),
                $this->translator->trans('test.not.access', [], null, $locale),
                $this->translator->trans('contact.administrator', [], null, $locale),
            );
        }

        return sprintf(
            '%s %s %s',
            $this->translator->trans('test.not.available', [], null, $locale),
            $this->translator->trans('test.cannot.start', [], null, $locale),
            $this->translator->trans('test.relaunch.contact.administrator', [], null, $locale),
        );
    }

    private function buildDeliverFrontendErrorRedirectResponse(
        Throwable $exception,
        ?string $redirectUrl = null,
        ?string $errorMessage = null,
        ?string $errorLog = null,
        ?string $locale = null,
    ): RedirectResponse {
        $redirectUrl = $redirectUrl ?? $this->parameterBag->get('deliver.frontend.error_url');

        return new RedirectResponse(
            sprintf(
                '%s%slti_errormsg=%s&lti_errorlog=%s&lti_locale=%s',
                $redirectUrl,
                strpos($redirectUrl, '?') ? '&' : '?',
                $errorMessage ? urlencode($errorMessage) : urlencode($exception->getMessage()),
                $errorLog ? urlencode($errorLog) : urlencode($exception->getMessage()),
                $locale ? urlencode($locale) : "en-US",
            ),
        );
    }
}
