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
use App\Data\LegacyHandler\PreparedStatementHandler;
use App\Data\Service\RecordProviderInterface;
use App\Module\Campaigns\Service\Email\Log\EmailCampaignLogManagerInterface;
use App\Module\Campaigns\Service\Email\Targets\EmailTargetProviderInterface;
use App\Module\Campaigns\Service\Email\Targets\EmailTargetValidatorManager;
use App\Module\Campaigns\Service\Email\Targets\Validation\ValidationFeedback;
use App\Module\Campaigns\Service\EmailMarketing\EmailMarketingManagerInterface;
use App\SystemConfig\LegacyHandler\SystemConfigHandler;
use Psr\Log\LoggerInterface;

class DefaultEmailQueueingService implements EmailQueueingServiceInterface
{

    public function __construct(
        protected PreparedStatementHandler $preparedStatementHandler,
        protected LoggerInterface $logger,
        protected RecordProviderInterface $recordProvider,
        protected SystemConfigHandler $systemConfigHandler,
        protected EmailQueueManagerInterface $queueManager,
        protected EmailTargetProviderInterface $targetProvider,
        protected EmailTargetValidatorManager $targetValidatorManager,
        protected EmailCampaignLogManagerInterface $campaignLogManager,
        protected EmailMarketingManagerInterface $emailMarketingManager
    ) {
    }

    public function queueEmails(array $options = []): void
    {
        $emailMarketingRecords = $this->getRecordsForQueueing();

        foreach ($emailMarketingRecords as $emailMarketing) {
            $emailMarketingId = $emailMarketing['id'];
            $campaignId = $emailMarketing['campaign_id'];
            $sendDate = $emailMarketing['date_start'];

            $targets = $this->getTargets($emailMarketingId);
            $emRecord = $this->emailMarketingManager->getRecord($emailMarketingId);

            if (empty($targets)) {
                $this->setQueueingFinished($emailMarketingId, $emRecord);
                continue;
            }

            $isQueueingInProgress = $this->emailMarketingManager->isQueueingInProgress($emRecord);
            if (!$isQueueingInProgress) {
                $this->setQueueingInProgress($emailMarketingId, $emRecord);
            }

            foreach ($targets as $target) {

                $targetRecord = $this->recordProvider->getRecord($target['target_type'], $target['target_id']);

                $feedback = $this->validateTarget($targetRecord, $emRecord, $campaignId, $target['target_list_id']);

                if (!$feedback->isSuccess()) {
                    $this->campaignLogManager->createCampaignLogEntry(
                        $campaignId,
                        $emailMarketingId,
                        $targetRecord->getAttributes()['email1'] ?? '',
                        'blocked-' . $feedback->getValidatorKey(),
                        $target['target_list_id'],
                        $target['target_id'],
                        $target['target_type']
                    );
                    continue;
                }

                $this->queueManager->addToQueue(
                    $campaignId,
                    $emailMarketingId,
                    $target['target_list_id'],
                    $target['target_id'],
                    $target['target_type'],
                    $sendDate
                );
            }

            $nextTargets = $this->getTargets($emailMarketingId);
            if (empty($nextTargets)) {
                $this->setQueueingFinished($emailMarketingId, $emRecord);
            }
        }

    }

    protected function getBatchSize(): int
    {
        return (int)($this->systemConfigHandler->getSystemConfig('emails_per_run')?->getValue() ?? 50);
    }

    /**
     * @return array
     */
    protected function getRecordsForQueueing(): array
    {
        $records = $this->emailMarketingManager->getRecordsForQueueing();
        $this->logger->debug('Campaigns:DefaultEmailQueueingService::getRecordsForQueueing - ' . count($records ?? []) . ' email marketing records found for queueing');
        return $records;
    }

    /**
     * @param string $emailMarketingId
     * @return array
     */
    protected function getTargets(string $emailMarketingId): array
    {
        $targets = $this->targetProvider->getTargets($emailMarketingId, $this->getBatchSize());
        $this->logger->debug('Campaigns:DefaultEmailQueueingService::getTargets - ' . count($targets ?? []) . ' targets found for email marketing id - ' . $emailMarketingId);
        return $targets;
    }

    /**
     * @param Record $targetRecord
     * @param Record $emRecord
     * @param string $campaignId
     * @param string $targetListId
     * @return ValidationFeedback
     */
    protected function validateTarget(Record $targetRecord, Record $emRecord, string $campaignId, string $targetListId): ValidationFeedback
    {
        $feedback = $this->targetValidatorManager->validate(
            $targetRecord,
            $emRecord,
            $campaignId,
            $targetListId
        );

        $message = 'Campaigns:DefaultEmailQueueingService::validateTarget - Validation feedback for target - ' . $targetRecord->getId() . ' - isValid -  ' . $feedback->isSuccess() ? 'true' : 'false';
        if (!$feedback->isSuccess()) {
            $message .= ' - failed validator - ' . $feedback->getValidatorKey();
        }
        $this->logger->debug(
            $message,
            [
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
     * @param string $emailMarketingId
     * @param Record $emRecord
     * @return void
     */
    protected function setQueueingFinished(string $emailMarketingId, Record $emRecord): void
    {
        $this->logger->debug('Campaigns:DefaultEmailQueueingService::queueEmails - No targets found for email marketing id - ' . $emailMarketingId) . ' - setting queueing status as finished';
        $this->emailMarketingManager->setQueueingFinished($emRecord);
    }

    /**
     * @param string $emailMarketingId
     * @param Record $emRecord
     * @return void
     */
    protected function setQueueingInProgress(string $emailMarketingId, Record $emRecord): void
    {
        $this->logger->debug('Campaigns:DefaultEmailQueueingService::queueEmails - Setting queueing status as in progress for email marketing id - ' . $emailMarketingId);
        $this->emailMarketingManager->setQueueingInProgress($emRecord);
    }
}
