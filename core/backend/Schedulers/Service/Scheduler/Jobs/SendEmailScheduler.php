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

namespace App\Schedulers\Service\Scheduler\Jobs;

use App\Data\Entity\Record;
use App\Data\LegacyHandler\PreparedStatementHandler;
use App\Data\Service\RecordProviderInterface;
use App\Emails\LegacyHandler\EmailManagerHandler;
use App\Emails\LegacyHandler\EmailProcessProcessor;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\Schedulers\Service\SchedulerInterface;
use App\SystemConfig\LegacyHandler\SystemConfigHandler;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use SugarBean;
use Symfony\Component\HttpFoundation\RequestStack;

class SendEmailScheduler extends LegacyHandler implements SchedulerInterface
{

    public const SCHEDULER_KEY = 'scheduler::send-from-queue';

    protected PreparedStatementHandler $preparedStatementHandler;
    protected SystemConfigHandler $systemConfigHandler;
    protected LoggerInterface $logger;
    protected RecordProviderInterface $recordProvider;
    protected EmailProcessProcessor $emailProcessProcessor;
    protected EmailManagerHandler $emailManagerHandler;


    public function __construct(
        string                   $projectDir,
        string                   $legacyDir,
        string                   $legacySessionName,
        string                   $defaultSessionName,
        LegacyScopeState         $legacyScopeState,
        RequestStack             $requestStack,
        PreparedStatementHandler $preparedStatementHandler,
        SystemConfigHandler      $systemConfigHandler,
        LoggerInterface          $logger,
        RecordProviderInterface  $recordProvider,
        EmailProcessProcessor $emailProcessProcessor,
        EmailManagerHandler     $emailManagerHandler
    )
    {
        parent::__construct(
            $projectDir,
            $legacyDir,
            $legacySessionName,
            $defaultSessionName,
            $legacyScopeState,
            $requestStack
        );
        $this->preparedStatementHandler = $preparedStatementHandler;
        $this->systemConfigHandler = $systemConfigHandler;
        $this->logger = $logger;
        $this->recordProvider = $recordProvider;
        $this->emailProcessProcessor = $emailProcessProcessor;
        $this->emailManagerHandler = $emailManagerHandler;
    }

    public function getHandlerKey(): string
    {
        return self::SCHEDULER_KEY;
    }

    public function getKey(): string
    {
        return self::SCHEDULER_KEY;
    }

    /**
     * @throws Exception
     */
    public function run(): bool
    {

        $timedate = $this->emailManagerHandler->getTimeDate();

        $maxPerRun = $this->systemConfigHandler->getSystemConfig('emails_per_run')?->getValue() ?? '500';
        $emailMan = $this->emailManagerHandler->getBean('EmailMan');
        $table = $this->emailManagerHandler->getTable();
        $confirmOptInEnabled = $this->emailManagerHandler->getConfigurator()->isConfirmOptInEnabled();

        $now = $timedate->nowDb();
        $str = strtotime($timedate->fromString("-1 day")?->asDb());

        $query = "SELECT * FROM $table WHERE send_date_time <= :now ";
        $query .= "AND deleted = 0 ";
        $query .= "AND (in_queue ='0' OR in_queue IS NULL OR ( in_queue ='1' AND in_queue_date <= :queue_date )) ";
        $query .= ($confirmOptInEnabled ? ' OR related_confirm_opt_in = 1 ' : ' AND related_confirm_opt_in = 0 ');
        $query .= "ORDER BY send_date_time ASC, user_id, list_id ";
        $query .= "LIMIT " . (int)$maxPerRun;

        $results = $this->preparedStatementHandler->fetchAll($query,
            [
                'now' => $now,
                'queue_date' => $str,
            ],
            [
                ['param' => 'now', 'type' => 'string'],
                ['param' => 'queue_date', 'type' => 'smallint'],
            ]
        );

        $user = $this->getUser();

        foreach ($results as $row) {

            $confirmOptIn = $row['related_confirm_opt_in'] ?? 0;

            if (empty($confirmOptIn) && empty($row['campaign_id'])) {
                $this->logger->error('Unable to find Campaign ID for' . $row['id']);
                continue;
            }

            if (empty($confirmOptIn) && empty($row['marketing_id'])) {
                $this->logger->error('Unable to find Campaign ID for' . $row['id']);
                continue;
            }

            if (!$user->id || $row['user_id'] !== $user->id) {
                $user->retrieve($row['user_id']);
            }

            $marketingId = $row['marketing_id'];

            if (!$marketingId) {
                continue;
            }

            $prospectBean = $this->emailManagerHandler->getBean($row['related_type'], $row['related_id']);
            $email = $prospectBean->email1 ?? $prospectBean->email ?? '';

            $prospect = $this->emailManagerHandler->getRecord($row['related_type'], $row['related_id']);
            $prospectId = $prospect->getAttributes()['id'] ?? '';
            $emRecord = $this->emailManagerHandler->getRecord('EmailMarketing', $marketingId);

            $outboundEmailId = $emRecord->getAttributes()['outbound_email_id'] ?? '';

            $suppressedEmails = $this->emailManagerHandler->getSuppressedEmails($marketingId);

            $emailRecord = $this->buildEmailRecord($emRecord, $prospect, $outboundEmailId);

            $validated = $this->emailManagerHandler->validateEmail(
                $prospectBean,
                $emRecord->getAttributes()['campaign_id'] ?? '',
                $emRecord->getId(),
                $emailMan,
                $row['list_id'] ?? '',
                $suppressedEmails
            );

            if (!$validated) {
                continue;
            }

            $isDuplicate = $this->emailManagerHandler->checkForDuplicateEmail($email, $marketingId);

            if ($isDuplicate){
                $this->logger->info('duplicate email');
                $this->emailManagerHandler->setAsSent(
                    $email,
                    $row['related_id'],
                    $row['related_type'] ,
                    true,
                    'blocked',
                    $prospectId,
                    $emRecord->getAttributes()['campaign_id'] ?? '',
                    $marketingId
                );
                continue;
            }

            if (!empty($confirmOptIn)){

                if ($confirmOptInEnabled) {
                    $email = $this->buildOptInEmail($prospect, $outboundEmailId);
                    $this->emailProcessProcessor->processEmail($email);
                    continue;
                }

                $this->logger->warning('Confirm Opt In email in queue but Confirm Opt In is disabled.');
                continue;
            }

            $result = $this->emailProcessProcessor->processEmail($emailRecord);

            if (!$result['success']){
                $this->logger->warning('Failed to send email.' . $prospect->getAttributes()['email1']);
                $this->emailManagerHandler->setAsSent(
                    $email,
                    $row['related_id'],
                    $row['related_type'] ,
                    true,
                    'send error',
                    $prospectId,
                    $emRecord->getAttributes()['campaign_id'] ?? '',
                    $marketingId
                );

                continue;
            }

            $this->logger->info('Email sent');
            $this->emailManagerHandler->setAsSent(
                $email,
                $row['related_id'],
                $row['related_type'] ,
                true,
                'send error',
                $prospectId,
                $emRecord->getAttributes()['campaign_id'] ?? '',
                $marketingId
            );
        }

        return true;
    }

    protected function getUser()
    {
        $this->init();

        $user = \BeanFactory::newBean('Users')->getSystemUser();

        $this->close();

        return $user;
    }

    protected function buildEmailRecord(Record $record, Record $prospect, string $outboundId): Record
    {
        $recordAttributes = $record->getAttributes() ?? [];
        $prospectAttr = $prospect->getAttributes() ?? [];

        $emailRecord = new Record();

        $attributes = [
            'name' => $recordAttributes['subject'] ?? '',
            'description' => $recordAttributes['body'] ?? '',
            'description_html' => $recordAttributes['body_html'] ?? '',
            'outbound_email_id' => $outboundId,
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

    protected function buildOptInEmail(Record $prospect, string $outboundId): Record|bool
    {
        $configurator = $this->emailManagerHandler->getConfigurator();

        $emailTemplate = $this->emailManagerHandler->getBean('EmailTemplates');
        $templateId = $configurator->getConfirmOptInTemplateId() ?? '';

        if (empty($templateId)) {
            $this->logger->error('Opt In Email Template is not configured.');
            return false;
        }

        $emailTemplate->retrieve($templateId);
        $templateRecord = $this->recordProvider->mapToRecord($emailTemplate);

        return $this->buildEmailRecord($templateRecord, $prospect, $outboundId);
    }

}
