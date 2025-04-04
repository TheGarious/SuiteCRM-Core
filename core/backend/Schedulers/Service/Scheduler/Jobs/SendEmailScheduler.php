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

    protected string $limit;


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

        $this->limit = $this->getLimit();
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
     * @throws \Exception
     */
    public function run(): bool
    {

        $sending = $this->getMarketingByStatus('sending');

        foreach ($sending as $item) {
            $id = $item['id'];

            $toSend = $this->getEmailsToSend($id);

            $this->buildAndSend($id, $toSend);

            $this->setStatus('EmailMarketing', $id, 'sent');
        }

        $inQueue = $this->getMarketingByStatus('in_queue');

        foreach ($inQueue as $queued) {
            $id = $queued['id'];
            $this->setStatus('EmailMarketing', $id, 'sending');

            $toSend = $this->getEmailsToSend($id);

            $this->buildAndSend($id, $toSend);

            $this->setStatus('EmailMarketing', $id, 'sent');
        }

        return true;
    }


    /**
     * @throws \Exception
     */
    protected function buildAndSend(string $id, array $toSend): void
    {
        $user = $this->getUser();
        $suppressedEmails = $this->emailManagerHandler->getSuppressedEmails($id);
        $confirmOptInEnabled = $this->emailManagerHandler->getConfigurator()->isConfirmOptInEnabled();

        foreach ($toSend as $row) {

            $confirmOptIn = $row['related_confirm_opt_in'] ?? 0;

            if (empty($confirmOptIn) && empty($row['campaign_id'])) {
                $this->logger->error('Unable to find Campaign ID for' . $row['id']);
                continue;
            }

            if (!$user->id || $row['user_id'] !== $user->id) {
                $user->retrieve($row['user_id']);
            }


            $prospectBean = $this->emailManagerHandler->getBean($row['related_type'], $row['related_id']);
            $email = $prospectBean->email1 ?? $prospectBean->email ?? '';
            $prospectId = $row['related_id'];
            $prospectListId = $row['list_id'];

            $validated = $this->emailManagerHandler->validateEmail(
                $prospectBean,
                $row['campaign_id'] ?? '',
                $id,
                $prospectListId,
                $suppressedEmails
            );

            if (!$validated) {
                continue;
            }

            $isDuplicate = $this->emailManagerHandler->checkForDuplicateEmail($email, $id);

            if ($isDuplicate){
                $this->logger->info('duplicate email');
                $this->emailManagerHandler->setSentStatus(
                    $email,
                    $prospectId,
                    $row['related_type'] ,
                    true,
                    'blocked',
                    $prospectListId,
                    $row['campaign_id'] ?? '',
                    $id
                );
                continue;
            }

            $prospectRecord = $this->recordProvider->getRecord($row['related_type'], $row['related_id']);

            if (!empty($confirmOptIn)){

                if ($confirmOptInEnabled) {
                    $email = $this->buildOptInEmail($prospectRecord);
                    $this->emailProcessProcessor->processEmail($email);
                    continue;
                }

                $this->logger->warning('Confirm Opt In email in queue but Confirm Opt In is disabled.');
                continue;
            }

            $emRecord = $this->recordProvider->getRecord('EmailMarketing', $id);
            $emailRecord = $this->buildEmailRecord($emRecord, $prospectRecord);

            $result = $this->emailProcessProcessor->processEmail($emailRecord);

            if (!$result['success']){
                $this->logger->warning('Failed to send email.' . $email);
                $this->emailManagerHandler->setSentStatus(
                    $email,
                    $prospectId,
                    $row['related_type'] ,
                    true,
                    'send error',
                    $prospectListId,
                    $row['campaign_id'] ?? '',
                    $id
                );

                continue;
            }

            $this->logger->info('Email sent');
            $this->emailManagerHandler->setSentStatus(
                $email,
                $prospectId,
                $row['related_type'] ,
                true,
                'targeted',
                $prospectListId,
                $row['campaign_id'] ?? '',
                $id
            );
        }
    }

    protected function getUser()
    {
        $this->init();

        $user = \BeanFactory::newBean('Users')->getSystemUser();

        $this->close();

        return $user;
    }

    protected function buildEmailRecord(Record $record, Record $prospect): Record
    {
        $recordAttributes = $record->getAttributes() ?? [];
        $prospectAttr = $prospect->getAttributes() ?? [];

        $emailRecord = new Record();

        $attributes = [
            'name' => $recordAttributes['subject'] ?? '',
            'description' => $recordAttributes['body'] ?? '',
            'description_html' => $recordAttributes['body_html'] ?? '',
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

    protected function buildOptInEmail(Record $prospect): Record|bool
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

        return $this->buildEmailRecord($templateRecord, $prospect);
    }

    public function getEmailsToSend(string $marketingId): mixed
    {
        $timedate = $this->emailManagerHandler->getTimeDate();
        $now = $timedate->nowDb();
        $str = strtotime($timedate->fromString("-1 day")?->asDb());
        $table = $this->emailManagerHandler->getTable();
        $confirmOptInEnabled = $this->emailManagerHandler->getConfigurator()->isConfirmOptInEnabled();

        $query = "SELECT * FROM $table WHERE marketing_id = :mkt_id AND send_date_time <= :now ";
        $query .= "AND deleted = 0 ";
        $query .= "AND (in_queue ='0' OR in_queue IS NULL OR ( in_queue ='1' AND in_queue_date <= :queue_date )) ";
        $query .= ($confirmOptInEnabled ? ' OR related_confirm_opt_in = 1 ' : ' AND related_confirm_opt_in = 0 ');
        $query .= "ORDER BY send_date_time ASC, user_id, list_id ";
        $query .= "LIMIT " . (int)$this->limit;

        try {
            $results = $this->preparedStatementHandler->fetchAll($query,
                [
                    'mkt_id' => $marketingId,
                    'now' => $now,
                    'queue_date' => $str,
                ],
                [
                    ['param' => 'now', 'type' => 'string'],
                    ['param' => 'queue_date', 'type' => 'smallint'],
                ]
            );
        } catch (Exception $e) {
            $results = [];
            $this->logger->error($e->getMessage());
        }

        $this->limit -= count($results);

        return $results;
    }

    protected function getMarketingByStatus(string $status)
    {
        $timedate = $this->emailManagerHandler->getTimeDate();
        $now = $timedate->nowDb();

        $query = 'SELECT * FROM email_marketing WHERE date_start <= :now AND status = :status AND deleted = 0 ORDER BY date_start ASC';

        try {
            $emRecords = $this->preparedStatementHandler->fetchAll($query, [
                'now' => $now,
                'status' => $status,
            ]);
        } catch (Exception $e) {
            $emRecords = [];
            $this->logger->error($e->getMessage());
        }

         return $emRecords;
    }

    protected function setStatus(string $module, string $id, string $status): void
    {
        $record = $this->recordProvider->getRecord($module, $id);
        $attr = $record->getAttributes();
        $attr['status'] = $status;
        $record->setAttributes($attr);
        $this->recordProvider->saveRecord($record);
    }

    protected function getLimit(): string
    {
        return $this->systemConfigHandler->getSystemConfig('emails_per_run')?->getValue() ?? '500';
    }

}
