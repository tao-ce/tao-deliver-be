<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model;

use App\Domain\DeliveryExecution\Helper\DeliveryExecutionKeyHelper;
use App\Domain\DeliveryExecution\Model\Comment\InlineFeedbackCollection;
use App\Domain\DeliveryExecution\Model\ExtraStateData\OverallComment;
use App\Domain\DeliveryExecution\Model\ExtraStateData\PlagiarismReport;
use App\Domain\Tenant\Model\TenantAwareInterface;
use App\Lti\LtiCustomSettings;
use App\Messenger\Message\DeliveryExecution\NormalizedExecutionControlMessage;
use App\Messenger\Message\DeliveryExecutionUIEventMessage;
use App\Validator\Exception\RequestValidationException;
use Carbon\Carbon;
use DateTimeInterface;
use OAT\Bundle\DocumentManagerBundle\Document\AbstractDocument;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Bundle\QtiBundle\Model\TestAwareInterface;
use OAT\Library\Lti1p3Core\Resource\LtiResourceLink\LtiResourceLink;
use OAT\Library\Lti1p3Core\Resource\LtiResourceLink\LtiResourceLinkInterface;
use OAT\Library\TaoTimerClient\Model\Contract\TimerDefinitionInterface;

class DeliveryExecution extends AbstractDocument implements TestAwareInterface, TenantAwareInterface
{
    public const DOCUMENT_KEY_DELIMITER = '#';
    public const DRY_RUN_ATTEMPT_ID = 'dry-run';
    public const ATTEMPT_ID = 'anonymous';
    public const REVIEW_MODE_PREFIX = 'review';
    public const UNLISTED_REVIEW_MODE_PREFIX = 'unlisted_review';

    public const STATUS_INITIAL = 'initial';
    public const STATUS_INTERACTING = 'interacting';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_TERMINATED = 'terminated';
    public const STATUS_DELETED = 'deleted';

    private ?self $initiallyScoredDeliveryExecution = null;
    private ?DeliveryExecutionKeyInfo $keyInfo = null;
    private string $deliveryId;
    private string $tenantId;
    private DateTimeInterface $startedAt;
    private array $ltiLaunchParameters;
    private string $status;
    private ?string $qtiSdkEncodedTestSession;
    private DeliveryExecutionExtraStateData $extraStateData;
    private ?string $resultId;
    private ?string $locale = null;
    private ?DateTimeInterface $finishedAt;
    private ?DateTimeInterface $closeAt;
    private ?DateTimeInterface $updatedAt;
    private bool $isDeleted;
    private ?InlineFeedbackCollection $reviewInlineComment = null;
    private ?Invalidation $invalidation = null;

    public function __construct(
        string $id,
        string $deliveryId,
        string $tenantId,
        DateTimeInterface $startedAt,
        array $ltiLaunchParameters,
        ?string $qtiSdkEncodedTestSession,
        ?DeliveryExecutionExtraStateData $extraStateData = null,
        string $status = self::STATUS_INITIAL,
        ?DateTimeInterface $finishedAt = null,
        ?DateTimeInterface $closeAt = null,
        ?DateTimeInterface $updatedAt = null,
        bool $isDeleted = false,
        ?InlineFeedbackCollection $reviewInlineComment = null,
        public readonly ?self $originalDeliveryExecution = null,
        ?string $locale = null,
        ?Invalidation $invalidation = null,
        ?string $initiallyScoredQtiSdkEncodedTestSession = null,
    ) {
        $this->id = $id;

        $this
            ->setExtraStateData($extraStateData ?? new DeliveryExecutionExtraStateData())
            ->setDeliveryId($deliveryId)
            ->setTenantId($tenantId)
            ->setStartedAt($startedAt)
            ->setLtiLaunchParameters($ltiLaunchParameters)
            ->setResultId($ltiLaunchParameters['result_id'])
            ->setIsDeleted($isDeleted)
            ->setStatus($status)
            ->setFinishedAt($finishedAt)
            ->setCloseAt($closeAt)
            ->setUpdatedAt($updatedAt)
            ->setQtiSdkEncodedTestSession($qtiSdkEncodedTestSession)
            ->setReviewInlineComment($reviewInlineComment)
            ->setLocale($locale)
            ->setinvalidation($invalidation)
            ->setInitiallyScoredQtiSdkEncodedTestSession($initiallyScoredQtiSdkEncodedTestSession);
    }

    public function getUpdates(): array
    {
        $updates = parent::getUpdates();

        if (!$updates) {
            return $updates;
        }

        $this->setUpdatedAt();

        return parent::getUpdates();
    }

    public function clone(): self
    {
        return new self(
            (string)$this->getDeliveryExecutionKeyInfo()->withAttempt($this->getAttempt()),
            $this->getDeliveryId(),
            $this->getTenantId(),
            $this->getStartedAt(),
            $this->getLtiLaunchParameters(),
            $this->getQtiSdkEncodedTestSession(),
            $this->getExtraStateData(),
            $this->getStatus(),
            $this->getFinishedAt(),
            reviewInlineComment: $this->getReviewInlineComment(),
            originalDeliveryExecution: $this,
            locale: $this->getLocale(),
            invalidation: $this->getinvalidation(),
        );
    }

    public function getOriginalId(): string
    {
        return $this->originalDeliveryExecution?->getId()
            ?? $this->getDeliveryExecutionKeyInfo()->getOriginalId(
                $this->getOriginalLtiLaunchParameters()['custom'][LtiCustomSettings::PARAM_ATTEMPT_ID] ?? null,
            );
    }

    public function getDeliveryId(): string
    {
        return $this->deliveryId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getStartedAt(): DateTimeInterface
    {
        return $this->startedAt;
    }

    public function getInitialStartTimestamp(): int
    {
        return $this->extraStateData->getInitialStartTimestamp()
            ?: $this->getStartedAt()->getTimestamp();
    }

    public function getLtiLaunchParameters(): array
    {
        return $this->ltiLaunchParameters;
    }

    public function getOriginalLtiLaunchParameters(): array
    {
        return $this->originalDeliveryExecution?->getOriginalLtiLaunchParameters() ?? $this->getLtiLaunchParameters();
    }

    public function setLtiLaunchParameters(array $parameters): self
    {
        $this->ltiLaunchParameters = $parameters;
        $this->addUpdate('ltiLaunchParameters');

        return $this;
    }

    public function getResourceLink(): LtiResourceLinkInterface
    {
        if (empty($this->ltiLaunchParameters['resource_link_id'])) {
            throw new RequestValidationException('Mandatory resource_link.id claim is absent.');
        }

        return new LtiResourceLink($this->ltiLaunchParameters['resource_link_id']);
    }

    public function getBatteryId(): ?string
    {
        return $this->ltiLaunchParameters['battery_id'] ?? null;
    }

    public function getLtiToken(): string
    {
        if (empty($this->ltiLaunchParameters['id_token'])) {
            throw new RequestValidationException('Mandatory LTI token is absent.');
        }

        return $this->ltiLaunchParameters['id_token'];
    }

    public function getQtiCompactTestFilePath(): string
    {
        return $this->getDeliveryExecutionKeyInfo()->getCompactTestXmlPath($this->locale);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->addUpdate('status');

        return $this;
    }

    /**
     * Whether the result of the delivery execution can be processed based on its current state
     */
    public function isResultProcessable(): bool
    {
        return !$this->isStateInitial() && !$this->isStateFinal();
    }

    public function isStateInitial(): bool
    {
        return DeliveryExecutionStatus::STATUS_INITIAL->equals($this->getStatus());
    }

    public function isStateFinal(): bool
    {
        return DeliveryExecutionStatus::STATUS_CLOSED->equals($this->getStatus())
            || DeliveryExecutionStatus::STATUS_TERMINATED->equals($this->getStatus());
    }

    public function isStateAvailableForAuthorisation(): bool
    {
        return $this->isStateInitial() || $this->isStateFinal();
    }

    public function start(): self
    {
        return $this->setStatus(self::STATUS_INTERACTING)
            ->setStartedAt(Carbon::now());
    }

    public function getInitiallyScoredDeliveryExecution(): ?self
    {
        return $this->initiallyScoredDeliveryExecution;
    }

    public function getInitiallyScoredQtiSdkEncodedTestSession(): ?string
    {
        return $this->initiallyScoredDeliveryExecution?->getQtiSdkEncodedTestSession();
    }

    public function setInitiallyScoredQtiSdkEncodedTestSession(?string $initiallyScoredQtiSdkEncodedTestSession): self
    {
        if ($this->getInitiallyScoredQtiSdkEncodedTestSession() === $initiallyScoredQtiSdkEncodedTestSession) {
            return $this;
        }

        $this->initiallyScoredDeliveryExecution = $this
            ->clone()
            ->setQtiSdkEncodedTestSession($initiallyScoredQtiSdkEncodedTestSession);
        // Makes sure this fake delivery execution never gets accidentally persisted
        $this->initiallyScoredDeliveryExecution->id = sprintf(
            '%s%s%s',
            self::REVIEW_MODE_PREFIX,
            self::DOCUMENT_KEY_DELIMITER,
            $this->initiallyScoredDeliveryExecution->id,
        );
        $this->addUpdate('initiallyScoredQtiSdkEncodedTestSession');

        return $this;
    }

    public function initializeInitiallyScoredQtiSdkEncodedTestSession(): self
    {
        return $this->setInitiallyScoredQtiSdkEncodedTestSession($this->getQtiSdkEncodedTestSession())
            ->getInitiallyScoredDeliveryExecution();
    }

    public function getQtiSdkEncodedTestSession(): ?string
    {
        return $this->qtiSdkEncodedTestSession;
    }

    public function setQtiSdkEncodedTestSession(?string $qtiSdkEncodedTestSession): self
    {
        $this->qtiSdkEncodedTestSession = $qtiSdkEncodedTestSession;
        $this->addUpdate('qtiSdkEncodedTestSession');

        return $this;
    }

    public function getExtraStateData(): DeliveryExecutionExtraStateData
    {
        return $this->extraStateData;
    }

    public function getResultId(): ?string
    {
        return $this->resultId;
    }

    public function getFinishedAt(): ?DateTimeInterface
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?DateTimeInterface $finishedAt): self
    {
        $this->finishedAt = $finishedAt;
        $this->addUpdate('finishedAt');

        return $this;
    }

    public function resetFinishedAt(): self
    {
        $this->finishedAt = null;
        $this->addUpdate('finishedAt');

        return $this;
    }

    public function close(string $status = self::STATUS_CLOSED): self
    {
        return $this->setStatus($status)
            ->setFinishedAt(Carbon::now())
            ->incrementAttempt();
    }

    public function getCloseAt(): ?DateTimeInterface
    {
        return $this->closeAt;
    }

    public function setCloseAt(?DateTimeInterface $closeAt): self
    {
        $this->closeAt = $closeAt;
        $this->addUpdate('closeAt');

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt = null): self
    {
        $this->updatedAt = $updatedAt ?: Carbon::now();
        $this->addUpdate('updatedAt');

        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted = true): self
    {
        $this->isDeleted = $isDeleted;
        $this->addUpdate('isDeleted');

        return $this;
    }

    public function isSnapshot(): bool
    {
        return $this->getDeliveryExecutionKeyInfo()->isSnapshot();
    }

    public function isUnlistedReview(): bool
    {
        return $this->getDeliveryExecutionKeyInfo()->isUnlistedReview();
    }

    public function isReview(): bool
    {
        return $this->getDeliveryExecutionKeyInfo()->isReview();
    }

    public function isDryRun(): bool
    {
        return $this->getDeliveryExecutionKeyInfo()->isDryRun();
    }

    public function getUserId(): ?string
    {
        return $this->getDeliveryExecutionKeyInfo()->getUserId();
    }

    public function getOriginalUserId(): ?string
    {
        return $this->getDeliveryExecutionKeyInfo()->getOriginalUserId();
    }

    public function isFlagged(): bool
    {
        return $this->extraStateData->isFlagged();
    }

    public function setIsFlagged(bool $isFlagged): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withIsFlagged($isFlagged),
        );
    }

    public function setExternalAliasId(string $aliasId): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withAliasId($aliasId),
        );
    }

    public function getAliasId(): ?string
    {
        return $this->extraStateData->getAliasId();
    }

    public function flagItem(string $itemIdentifier): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withFlaggedItem($itemIdentifier),
        );
    }

    public function unFlagItem(string $itemIdentifier): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withUnFlaggedItem($itemIdentifier),
        );
    }

    public function addItemComment(string $itemIdentifier, string $comment): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withItemComment($itemIdentifier, $comment),
        );
    }

    public function addTraceData(array $traceData): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withTraceData($traceData),
        );
    }

    public function addToolState(string $toolState): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withToolState($toolState),
        );
    }

    public function addItemState(string $itemIdentifier, string $itemState): self
    {
        return $this->setExtraStateData(
            $this->extraStateData
                ->withItemState($itemIdentifier, $itemState)
                ->withTemporaryItemState($itemIdentifier),
        )->removeReviewInlineComment($itemIdentifier);
    }

    public function clearAllItemState(): self
    {
        return $this->setExtraStateData(
            $this->extraStateData
                ->withNoItemState()
                ->withNoTemporaryItemState(),
        )->setReviewInlineComment();
    }

    public function addTemporaryItemState(string $itemIdentifier, string $itemState): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withTemporaryItemState($itemIdentifier, $itemState),
        );
    }

    public function addIsTimerEnabledState(bool $flag): self
    {
        return $this->setExtraStateData(
            $this->extraStateData
                ->withHasTimer($flag)
                ->withNoExternalTimerData(),
        );
    }

    public function removeTemporaryItemState(string $itemIdentifier): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withTemporaryItemState($itemIdentifier),
        );
    }

    public function addPlagiarismReport(PlagiarismReport $report): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withPlagiarismReport($report),
        );
    }

    public function addExternalTimerDefinition(TimerDefinitionInterface $timerDefinition): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withExternalTimerData($timerDefinition),
        );
    }

    public function markItemAsExternalScored(string $itemIdentifier): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withExternalScoredItem($itemIdentifier),
        );
    }

    public function isItemScoredExternally(string $itemIdentifier): bool
    {
        return $this->extraStateData->isItemScoredExternally($itemIdentifier);
    }

    public function startServerTimer(string $qtiComponentIdentifier): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withDurationStorage(
                $this->extraStateData->getDurationStorage()->withStartedServerTimer($qtiComponentIdentifier),
            ),
        );
    }

    public function endServerTimer(string $qtiComponentIdentifier): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withDurationStorage(
                $this->extraStateData->getDurationStorage()->withStoppedServerTimer($qtiComponentIdentifier),
            ),
        );
    }

    public function clearDurations(): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withDurationStorage(
                $this->extraStateData->getDurationStorage()->withClearedDurations(),
            ),
        );
    }

    public function hasUiEvents(): bool
    {
        return !empty($this->extraStateData->getUiEvents());
    }

    public function popAllUiEvents(): DeliveryExecutionUIEventMessage
    {
        $events = $this->extraStateData->getUiEvents(); // preserving the events before clearing them
        return new DeliveryExecutionUIEventMessage($this->clearUiEvents(), $events);
    }

    public function pushUiEvents(DeliveryExecutionUIEventMessage $message): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withAddedUiEvents($message->getEvents()),
        );
    }

    public function clearUiEvents(): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withNoUiEvents(),
        );
    }

    public function hasAssessmentEvents(): bool
    {
        return !empty($this->extraStateData->getAssessmentEvents());
    }

    /**
     * @return iterable|NormalizedExecutionControlMessage[]
     */
    public function popAllAssessmentEvents(): iterable
    {
        foreach ($this->extraStateData->getAssessmentEvents() as $assessmentEvent) {
            yield new NormalizedExecutionControlMessage($this, $assessmentEvent);
        }
        $this->clearAssessmentEvents();
    }

    public function pushAssessmentEvent(array $normalizedAssessmentEvent): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withAddedAssessmentEvent($normalizedAssessmentEvent),
        );
    }

    public function clearAssessmentEvents(): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withNoAssessmentEvents(),
        );
    }

    public function preserveOriginalSession(): self
    {
        if ($this->extraStateData->getOriginalSession()) {
            return $this;
        }

        return $this->setExtraStateData(
            $this->extraStateData->withOriginalSession(
                $this->getQtiSdkEncodedTestSession(),
            ),
        );
    }

    public function restoreOriginalSession(): self
    {
        $originalSession = $this->extraStateData->getOriginalSession();

        if ($originalSession) {
            $this->setQtiSdkEncodedTestSession($originalSession);
        }

        return $this->setExtraStateData(
            $this->extraStateData
                ->withOriginalSession()
                ->withNoManuallyGradedItem()
                ->resetExternalScoredItems(),
        );
    }

    public function getAttempt(): int
    {
        return $this->extraStateData->getAttempt();
    }

    public function reopen(): self
    {
        return $this->resetFinishedAt()
            ->restoreOriginalSession();
    }

    public function getItemDataPath(string $id): string
    {
        return $this->getDeliveryExecutionKeyInfo()->getItemDataPath($id, $this->locale);
    }

    public function getPortableItemDataPath(string $id): string
    {
        return $this->getDeliveryExecutionKeyInfo()->getPortableItemDataPath($id, $this->locale);
    }

    public function getVariableElementsPath(string $id): string
    {
        return $this->getDeliveryExecutionKeyInfo()->getVariableElementsPath($id, $this->locale);
    }

    public function hasItemState(string $itemid): bool
    {
        $hasItemState = false;
        if ($this->getExtraStateData()->hasItemStates()) {
            $extraStateData = $this->getExtraStateData();
            $itemState = $extraStateData->getTemporaryItemState($itemid);
            $hasItemState = $itemState !== null;
        }
        return $hasItemState;
    }

    public function getItemAttachments(string $itemId): array
    {
        return $this->extraStateData->getItemAttachments($itemId);
    }

    public function setItemAttachments(string $itemId, array $attachments): self
    {
        return $this->setExtraStateData(
            $this->getExtraStateData()->withItemAttachments($itemId, $attachments),
        );
    }

    public function setAttachments(array $attachments): self
    {
        return $this->setExtraStateData(
            $this->getExtraStateData()->withAttachments($attachments),
        );
    }

    public function getRequestIp(): ?string
    {
        return $this->extraStateData->getRequestIp();
    }

    public function setRequestIp(?string $requestIp): self
    {
        return $requestIp
            ? $this->setExtraStateData(
                $this->getExtraStateData()->withRequestIp($requestIp),
            )
            : $this;
    }

    public function getAssetPath(string $assetName, ?string $id = null): string
    {
        return $this->getDeliveryExecutionKeyInfo()->getAssetPath($assetName, $id, $this->locale);
    }

    public function getItemOverallComment(string $itemId): ?OverallComment
    {
        return $this->getExtraStateData()->getItemOverallReviewComment($itemId);
    }

    public function withItemOverallComment(string $itemId, OverallComment $itemOverallComment): self
    {
        return $this->setExtraStateData(
            $this->getExtraStateData()->withItemOverallComment($itemId, $itemOverallComment),
        );
    }

    private function incrementAttempt(): self
    {
        return $this->setExtraStateData(
            $this->extraStateData
                ->withOriginalSession()
                ->withIncrementedAttempt(),
        );
    }

    private function setDeliveryId(string $deliveryId): self
    {
        $this->deliveryId = $deliveryId;
        $this->addUpdate('deliveryId');
        return $this;
    }

    private function setTenantId(string $tenantId): self
    {
        $this->tenantId = $tenantId;
        $this->addUpdate('tenantId');
        return $this;
    }

    private function setStartedAt(DateTimeInterface $startedAt): self
    {
        $this->startedAt = $startedAt;
        $this->addUpdate('startedAt');
        return $this->setExtraStateData(
            $this->extraStateData->withInitialStartTimestamp($startedAt->getTimestamp()),
        );
    }

    public function setExtraStateData(DeliveryExecutionExtraStateData $extraStateData): self
    {
        $diff = $extraStateData->diff(isset($this->extraStateData) ? $this->extraStateData->toArray() : []);
        if (empty($diff)) {
            return $this;
        }

        foreach ($diff as $update) {
            $this->addUpdate($update);
        }
        $this->extraStateData = $extraStateData;
        $this->addUpdate('extraStateData');
        return $this;
    }

    public function getReviewInlineComment(): ?InlineFeedbackCollection
    {
        return $this->reviewInlineComment;
    }

    public function removeReviewInlineComment(string $itemId): self
    {
        $this->reviewInlineComment = $this->reviewInlineComment ?? new InlineFeedbackCollection();
        $this->reviewInlineComment->removeFeedback($itemId);

        $this->addUpdate('reviewInlineComment');

        return $this;
    }

    public function addReviewInlineComment(?string $owner, string $itemId, array $feedback): self
    {
        $this->reviewInlineComment = $this->reviewInlineComment ?? new InlineFeedbackCollection();
        if ($owner === null) {
            $this->reviewInlineComment->addFeedback($itemId, $feedback);
        } else {
            $this->reviewInlineComment->addOwnerFeedback($owner, $itemId, $feedback);
        }

        $this->addUpdate('reviewInlineComment');

        return $this;
    }

    public function getReviewInlineCommentForItem(?string $owner, string $itemId): array
    {
        $reviewInlineComment = $this->reviewInlineComment ?? new InlineFeedbackCollection();
        return $owner === null
            ? $reviewInlineComment->getFeedback($itemId)
            : $reviewInlineComment->getOwnerFeedback($owner, $itemId);
    }

    public function setReviewInlineComment(?InlineFeedbackCollection $reviewInlineComment = null): self
    {
        $this->addUpdate('reviewInlineComment');
        $this->reviewInlineComment = $reviewInlineComment;

        return $this;
    }

    private function setResultId(?string $resultId): self
    {
        $this->resultId = $resultId;
        $this->addUpdate('resultId');
        return $this;
    }

    private function getDeliveryExecutionKeyInfo(): DeliveryExecutionKeyInfo
    {
        if (null === $this->keyInfo) {
            $this->keyInfo = DeliveryExecutionKeyHelper::createDeliveryExecutionKeyInfo($this->getId());
        }

        return $this->keyInfo;
    }

    public function getUserSelectedLocale(): ?string
    {
        if ($this->isStateInitial()) {
            return null;
        }

        return $this->getLocale() ?: $this->getMainLocale();
    }

    public function getUserSelectedLocaleForMultiLanguage(): ?string
    {
        if (!$this->isMultiLanguage()) {
            return null;
        }

        return $this->getUserSelectedLocale();
    }

    public function isMultiLanguage(): bool
    {
        return $this->extraStateData->isMultilanguage();
    }

    public function setMultiLanguage(): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withMultiLanguage(),
        );
    }

    public function getMainLocale(): ?string
    {
        return $this->extraStateData->getMainLocale();
    }

    public function setMainLocale(string $locale): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withMainLocale($locale),
        );
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;
        $this->addUpdate('locale');
        return $locale ? $this->setMultiLanguage() : $this;
    }

    public function setDeliveryPublicationTime(DateTimeInterface $deliveryPublicationTime): self
    {
        return $this->setExtraStateData(
            $this->extraStateData->withDeliveryPublicationTime($deliveryPublicationTime),
        );
    }

    public function doesBelongToBattery(): bool
    {
        return $this->getBatteryId() !== null;
    }

    public function getDeliveryPublicationTimestamp(): ?int
    {
        return $this->extraStateData->getDeliveryPublicationTime()?->getTimestamp();
    }

    public function isResultInvalidated(): bool
    {
        return $this->invalidation !== null && $this->invalidation->isResultInvalidated();
    }

    public function getinvalidation(): ?Invalidation
    {
        return $this->invalidation;
    }

    public function setinvalidation(?Invalidation $invalidation): self
    {
        $this->invalidation = $invalidation;
        $this->addUpdate('invalidation');

        return $this;
    }

    public function getAttemptId(): ?string
    {
        return $this->getOriginalLtiLaunchParameters()['custom'][LtiCustomSettings::PARAM_ATTEMPT_ID] ?? null;
    }

    public function getPlagiarismReport(string $id): PlagiarismReport
    {
        $report = $this->getExtraStateData()->getPlagiarismReports()[$id] ?? null;
        if (!$report) {
            throw new DocumentNotFoundException("Report $id not found in Delivery Execution $this->id");
        }
        return $report;
    }

    public function withInitialManuallyGradedItem(string $itemId, int|string|DateTimeInterface $gradedAt): self
    {
        return $this->setExtraStateData(
            $this->getExtraStateData()->withInitialManuallyGradedItem($itemId, $gradedAt),
        );
    }

    public function withFinalManuallyGradedItem(string $itemId, int|string|DateTimeInterface $gradedAt): self
    {
        return $this->setExtraStateData(
            $this->getExtraStateData()->withFinalManuallyGradedItem($itemId, $gradedAt),
        );
    }

    public function addAnnotationComment(?string $owner, string $itemId, array $comments): self
    {
        return $this->setExtraStateData(
            $this->getExtraStateData()->withAnnotationComment($owner, $itemId, $comments),
        );
    }

    public function getAnnotationCommentForItem(?string $owner, string $itemId): array
    {
        return $this->getExtraStateData()->getItemAnnotationComment($owner, $itemId);
    }
}
