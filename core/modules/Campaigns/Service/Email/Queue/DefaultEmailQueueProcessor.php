<?php
/**
 * SuiteCRM is a customer relationship management program developed by SalesAgility Ltd.
 * Copyright (C) 2025 SalesAgility Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SALESAGILITY, SALESAGILITY DISCLAIMS THE
 * WARRANTY OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License
 * version 3, these Appropriate Legal Notices must retain the display of the
 * "Supercharged by SuiteCRM" logo. If the display of the logos is not reasonably
 * feasible for technical reasons, the Appropriate Legal Notices must display
 * the words "Supercharged by SuiteCRM".
 */

namespace App\Module\Campaigns\Service\Email\Queue;

use App\Data\Entity\Record;
use App\Data\Service\RecordProviderInterface;
use App\Emails\LegacyHandler\EmailProcessProcessor;
use App\Module\Campaigns\Service\Email\Log\EmailCampaignLogManagerInterface;
use App\Module\Campaigns\Service\Email\Targets\EmailTargetValidatorManager;
use App\Module\Campaigns\Service\Email\Targets\Validation\ValidationFeedback;
use App\Module\Campaigns\Service\EmailMarketing\EmailMarketingManagerInterface;
use App\Module\Service\ModuleNameMapperInterface;
use App\SystemConfig\LegacyHandler\SystemConfigHandler;
use Psr\Log\LoggerInterface;

class DefaultEmailQueueProcessor implements EmailQueueProcessorInterface
{
    public function __construct(
        protected EmailQueueManagerInterface $queueManager,
        protected EmailProcessProcessor $emailProcessor,
        protected RecordProviderInterface $recordProvider,
        protected EmailCampaignLogManagerInterface $campaignLogManager,
        protected EmailTargetValidatorManager $targetValidatorManager,
        protected SystemConfigHandler $systemConfigHandler,
        protected LoggerInterface $logger,
        protected EmailMarketingManagerInterface $emailMarketingManager,
        protected EmailQueueManagerInterface $emailQueueManager,
        protected ModuleNameMapperInterface $moduleNameMapper,
    ) {
    }

    public function processQueue(array $options = []): void
    {
        $emailMarketingRecords = $this->emailMarketingManager->getRecordsForQueueProcessing();

        foreach ($emailMarketingRecords as $emailMarketing) {
            $emailMarketingId = $emailMarketing['id'];
            $campaignId = $emailMarketing['campaign_id'];

            $queueEntries = $this->getQueueEntries($emailMarketingId);
            $emRecord = $this->emailMarketingManager->getRecord($emailMarketingId);

            $isQueueingFinished = $this->emailMarketingManager->isQueueingFinished($emRecord);

            if (empty($queueEntries) && !$isQueueingFinished) {
                $this->logger->debug(
                    'Campaigns:DefaultEmailQueueProcessor::processQueue - No entries to send and queueing in progress - skipping | email marketing id - ' . $emailMarketingId, [
                        'emailMarketingId' => $emailMarketingId,
                        'campaignId' => $campaignId,
                    ]
                );
                continue;
            }

            if (empty($queueEntries) && $isQueueingFinished) {
                $this->setSent($emRecord);
                continue;
            }

            $isSending = $this->emailMarketingManager->isSending($emRecord);
            if (!$isSending) {
                $this->setSending($emRecord);
            }

            foreach ($queueEntries as $entry) {
                $targetType = $entry['related_type'] ?? '';
                $targetId = $entry['related_id'] ?? '';
                $targetListId = $entry['list_id'] ?? '';

                $targetRecord = $this->recordProvider->getRecord($this->moduleNameMapper->toFrontEnd($targetType), $targetId);

                $feedback = $this->validateTarget($targetRecord, $emRecord, $campaignId, $targetListId);
                if (!$feedback->isSuccess()) {
                    $this->handleInvalidTarget($campaignId, $emailMarketingId, $targetRecord, $feedback, $targetListId, $targetId, $targetType);

                    continue;
                }

                $result = $this->sendEmail($emRecord, $targetRecord);

                if (!$result['success']) {
                    $this->handlerFailedSend(
                        $campaignId,
                        $emailMarketingId,
                        $targetRecord->getAttributes()['email1'] ?? '',
                        'send error',
                        $targetListId,
                        $targetId,
                        $targetType
                    );
                    continue;
                }

                $this->handleSuccessfulSend($campaignId, $emailMarketingId, $targetRecord, $targetListId, $targetId, $targetType);
            }

            $nextQueueEntries = $this->getQueueEntries($emailMarketingId);
            if (empty($nextQueueEntries) && !$isQueueingFinished) {
                continue;
            }

            if (empty($nextQueueEntries) && $isQueueingFinished) {
                $this->setSent($emRecord);
            }
        }
    }


    protected function getBatchSize(): int
    {
        return (int)($this->systemConfigHandler->getSystemConfig('emails_per_run')?->getValue() ?? 50);
    }

    protected function buildEmailRecord(Record $record, Record $prospect): Record
    {
        $recordAttributes = $record->getAttributes() ?? [];
        $prospectAttr = $prospect->getAttributes() ?? [];

        $emailRecord = new Record();

        $attributes = [
            'name' => $recordAttributes['subject'] ?? '',
            'description' => $recordAttributes['body'] ?? '',
            'description_html' => $recordAttributes['body'] ?? '',
            'outbound_email_id' => $recordAttributes['outbound_email_id'] ?? '',
            'parent_type' => $prospectAttr['module_name'] ?? '',
            'parent_id' => $prospectAttr['id'] ?? '',
            'to_addrs_names' => [
                [
                    'email1' => $prospectAttr['email1'] ?? $prospectAttr['email'] ?? '',
                ]
            ],
        ];

        $emailRecord->setId('');
        $emailRecord->setAttributes($attributes);
        $emailRecord->setModule('Emails');

        return $emailRecord;
    }

    public function handlerFailedSend(
        string $campaignId,
        string $marketingId,
        string $email,
        string $activityType,
        string $prospectListId,
        string $targetId,
        string $targetType
    ): void {

        $entry = $this->emailQueueManager->getQueueEntry($marketingId, $targetId, $targetType);
        $sendAttempts = (int)($entry['send_attempts'] ?? 0);

        if ($sendAttempts > 5) {
            $this->campaignLogManager->createCampaignLogEntry(
                $campaignId,
                $marketingId,
                $email,
                $activityType,
                $prospectListId,
                $targetId,
                $targetType
            );

            $this->emailQueueManager->deleteFromQueue($marketingId, $targetId, $targetType);
            $this->logger->debug(
                'Campaigns:DefaultEmailQueueProcessor::handlerFailedSend - Failed to send email after 5 attempts | email marketing id - ' . $marketingId . ' | target - ' . $targetType . '-' . $targetId, [
                    'emailMarketingId' => $marketingId,
                    'targetId' => $targetId,
                    'targetType' => $targetType,
                    'campaignId' => $campaignId,
                ]
            );
            return;
        }

        $this->emailQueueManager->updateSendAttempts($entry['id']);
        $this->logger->debug(
            'Campaigns:DefaultEmailQueueProcessor::handlerFailedSend - Failed to send email - increasing attempt count | email marketing id - ' . $marketingId . ' | target - ' . $targetType . '-' . $targetId, [
                'emailMarketingId' => $marketingId,
                'targetId' => $targetId,
                'targetType' => $targetType,
                'campaignId' => $campaignId,
            ]
        );
    }

    /**
     * @param Record $emRecord
     * @return void
     */
    protected function setSent(Record $emRecord): void
    {
        $this->logger->debug(
            'Campaigns:DefaultEmailQueueProcessor::setSent - No entries on queue and queueing finished - setting email marketing as sent | email marketing id - ' . $emRecord->getId(), [
                'emailMarketingId' => $emRecord->getId(),
            ]
        );
        $this->emailMarketingManager->setSent($emRecord);
    }

    /**
     * @param Record $emRecord
     * @return void
     */
    protected function setSending(Record $emRecord): void
    {
        $this->logger->debug(
            'Campaigns:DefaultEmailQueueProcessor::setSending - Queue entries found - setting email marketing as sending | email marketing id - ' . $emRecord->getId(), [
                'emailMarketingId' => $emRecord->getId(),
            ]
        );
        $this->emailMarketingManager->setSending($emRecord);
    }

    /**
     * @param mixed $emailMarketingId
     * @return array
     */
    protected function getQueueEntries(mixed $emailMarketingId): array
    {
        $queueEntries = $this->queueManager->getEntriesToSend($emailMarketingId, $this->getBatchSize());
        $this->logger->debug('Campaigns:DefaultEmailQueueProcessor::getQueueEntries - ' . count($queueEntries ?? []) . ' entries found for email marketing id - ' . $emailMarketingId);
        return $queueEntries;
    }

    /**
     * @param Record $targetRecord
     * @param Record $emRecord
     * @param mixed $campaignId
     * @param mixed $targetListId
     * @return ValidationFeedback
     */
    protected function validateTarget(Record $targetRecord, Record $emRecord, mixed $campaignId, mixed $targetListId): ValidationFeedback
    {
        $feedback = $this->targetValidatorManager->validate(
            $targetRecord,
            $emRecord,
            $campaignId,
            $targetListId
        );

        $message = 'Campaigns:DefaultEmailQueueProcessor::validateTarget - Validation feedback for target - ' . $targetRecord->getId() . ' - isValid -  ' . $feedback->isSuccess() ? 'true' : 'false';
        if (!$feedback->isSuccess()) {
            $message .= ' - failed validator - ' . $feedback->getValidatorKey();
        }
        $this->logger->debug(
            $message, [
                'targetId' => $targetRecord->getId(),
                'targetType' => $targetRecord->getModule(),
                'emailMarketingId' => $emRecord->getId(),
                'campaignId' => $campaignId,
                'targetListId' => $targetListId,
            ]
        );

        return $feedback;
    }

    /**
     * @param mixed $campaignId
     * @param mixed $emailMarketingId
     * @param Record $targetRecord
     * @param mixed $targetListId
     * @param mixed $targetId
     * @param mixed $targetType
     * @return void
     */
    protected function handleSuccessfulSend(mixed $campaignId, mixed $emailMarketingId, Record $targetRecord, mixed $targetListId, mixed $targetId, mixed $targetType): void
    {
        $this->campaignLogManager->createCampaignLogEntry(
            $campaignId,
            $emailMarketingId,
            $targetRecord->getAttributes()['email1'] ?? '',
            'targeted',
            $targetListId,
            $targetId,
            $targetType
        );

        $this->emailQueueManager->deleteFromQueue($emailMarketingId, $targetId, $targetType);

        $this->logger->debug(
            'Campaigns:DefaultEmailQueueProcessor::processQueue - Email sent successfully | email marketing id - ' . $emailMarketingId . ' | target - ' . $targetType . '-' . $targetId,
            [
                'emailMarketingId' => $emailMarketingId,
                'targetId' => $targetId,
                'targetType' => $targetType,
                'campaignId' => $campaignId,
            ]
        );
    }

    /**
     * @param Record $emRecord
     * @param Record $targetRecord
     * @return array
     * @throws \Doctrine\DBAL\Exception
     */
    protected function sendEmail(Record $emRecord, Record $targetRecord): array
    {
        $emailRecord = $this->buildEmailRecord($emRecord, $targetRecord);

        $this->logger->debug(
            'Campaigns:DefaultEmailQueueProcessor::sendEmail - Sending email | email marketing id - ' . $emRecord->getId() . ' | target - ' . $targetRecord->getModule() . '-' . $targetRecord->getId(),
            [
                'emailMarketingId' => $emRecord->getId(),
                'targetId' => $targetRecord->getId(),
                'targetType' => $targetRecord->getModule(),
            ]
        );

        return $this->emailProcessor->processEmail($emailRecord);
    }

    /**
     * @param mixed $campaignId
     * @param mixed $emailMarketingId
     * @param Record $targetRecord
     * @param ValidationFeedback $feedback
     * @param mixed $targetListId
     * @param mixed $targetId
     * @param mixed $targetType
     * @return void
     */
    protected function handleInvalidTarget(mixed $campaignId, mixed $emailMarketingId, Record $targetRecord, ValidationFeedback $feedback, mixed $targetListId, mixed $targetId, mixed $targetType): void
    {
        $this->campaignLogManager->createCampaignLogEntry(
            $campaignId,
            $emailMarketingId,
            $targetRecord->getAttributes()['email1'] ?? '',
            'blocked-' . $feedback->getValidatorKey(),
            $targetListId,
            $targetId,
            $targetType
        );

        $this->emailQueueManager->deleteFromQueue($emailMarketingId, $targetId, $targetType);
        $this->logger->debug(
            'Campaigns:DefaultEmailQueueProcessor::processQueue - Email not sent - target blocked/invalid - deleted from queue | email marketing id - ' . $emailMarketingId . ' | target - ' . $targetType . '-' . $targetId,
            [
                'emailMarketingId' => $emailMarketingId,
                'targetId' => $targetId,
                'targetType' => $targetType,
                'campaignId' => $campaignId,
            ]
        );
    }

}
