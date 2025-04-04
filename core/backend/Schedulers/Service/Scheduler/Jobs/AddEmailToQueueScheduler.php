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

use App\Data\LegacyHandler\PreparedStatementHandler;
use App\Data\Service\RecordProviderInterface;
use App\Emails\LegacyHandler\EmailManagerHandler;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\Schedulers\Service\SchedulerInterface;
use App\SystemConfig\LegacyHandler\SystemConfigHandler;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AddEmailToQueueScheduler extends LegacyHandler implements SchedulerInterface {

    public const SCHEDULER_KEY = 'scheduler::email-to-queue';

    protected PreparedStatementHandler $preparedStatementHandler;
    protected LoggerInterface $logger;
    protected EmailManagerHandler $emailManagerHandler;
    protected RecordProviderInterface $recordProvider;
    protected SystemConfigHandler $systemConfigHandler;

    protected string $limit;

    public function __construct(
        string $projectDir,
        string $legacyDir,
        string $legacySessionName,
        string $defaultSessionName,
        LegacyScopeState $legacyScopeState,
        RequestStack $requestStack,
        PreparedStatementHandler $preparedStatementHandler,
        LoggerInterface $logger,
        EmailManagerHandler $emailManagerHandler,
        RecordProviderInterface $recordProvider,
        SystemConfigHandler $systemConfigHandler
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
        $this->logger = $logger;
        $this->emailManagerHandler = $emailManagerHandler;
        $this->recordProvider = $recordProvider;
        $this->systemConfigHandler = $systemConfigHandler;

        $this->limit = $this->getLimit();
    }

    public function getKey(): string
    {
       return self::SCHEDULER_KEY;
    }

    public function getHandlerKey(): string
    {
        return self::SCHEDULER_KEY;
    }

    /**
     * @throws \Exception
     */
    public function run(): bool
    {
        $table = $this->emailManagerHandler->getModuleTable('EmailMarketing');
        try {
            $emails = $this->preparedStatementHandler->fetchAll(
                "SELECT * FROM $table WHERE status = 'scheduled'",
                []
            );
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        $passed = true;

        foreach ($emails as $email) {
            $emailId = $email['id'];
            $campaignId = $email['campaign_id'];
            $sendDate = $email['date_start'];

            $emProspects = $this->getProspectLists($emailId);
            $emRecord = $this->recordProvider->getRecord('EmailMarketing', $emailId);
            $sentEmails = [];
            foreach ($emProspects as $prospectList) {
                $id = $prospectList['id'];

                $prospectListId = $prospectList['prospect_list_id'];

                $this->removeDeletedProspects($prospectListId);

                $prospects = $this->filterProspectsEmails($prospectListId);

                $ids = [];

                foreach ($prospects as $result) {

                    $bean = $this->emailManagerHandler->getBean($result['related_type'], $result['related_id']);

                    $isDuplicate = $this->checkForDuplicate($result['related_id'], $emailId);

                    if ($isDuplicate){
                        continue;
                    }

                    // check if duplicate
                    if (array_key_exists($bean->email1, $sentEmails)) {

                        $this->emailManagerHandler->setSentStatus(
                            $bean->email1,
                            $result['related_id'],
                            $result['related_type'],
                            true,
                            'blocked',
                            $prospectListId,
                            $campaignId,
                            $emailId,
                        );
                        continue;
                    }

                    $validated = $this->emailManagerHandler->validateEmail(
                        $bean,
                        $campaignId,
                        $emailId,
                        $prospectListId,
                        []
                    );

                    if (!$validated){
                        continue;
                    }

                    $ids[] = $result['related_id'];
                    $sentEmails[$bean->email1] = 1;
                }

                $result = $this->runInsertQuery($id, $emailId, $campaignId, $sendDate, $ids);

                if (!$result) {
                    $passed = false;
                }
            }

            $this->emailManagerHandler->updateRecordStatus($emRecord, 'in_queue');

            $this->deleteExemptEntries($emailId);
        }


        if (!$passed) {
            $this->logger->warning('was not added to the queue');
        }

        return $passed;
    }

    protected function getProspectLists(string $emId): array
    {
        $query = "SELECT email_marketing_prospect_lists.* FROM email_marketing_prospect_lists ";
        $query .= 'INNER JOIN prospect_lists on prospect_lists.id = email_marketing_prospect_lists.prospect_list_id ';
        $query .= 'WHERE prospect_lists.deleted=0 and email_marketing_id = :id and email_marketing_prospect_lists.deleted=0';

        $records = [];

        try {
            $records = $this->preparedStatementHandler->fetchAll($query, ['id' => $emId]);
        } catch (Exception $e) {
            $this->logger->warning('Could not retrieve prospect lists: ' . $e->getMessage());
        }

        return $records;
    }

    protected function runInsertQuery(
        string $prospectId,
        string $emId,
        string $campaignId,
        string $sendDate,
        array $ids,
    ): bool
    {
        $timedate = $this->emailManagerHandler->getTimeDate();
        $user = $this->getUser();

        $query = 'INSERT INTO emailman (
                      date_entered,
                      user_id,
                      campaign_id,
                      marketing_id,
                      list_id,
                      related_id,
                      related_type,
                      send_date_time
                      ) ';
        $query .= 'SELECT :date, :user_id, :campaign_id, :em_id, plp.prospect_list_id, plp.related_id,
        plp.related_type,
        :send_date ';
        $query .= 'FROM prospect_lists_prospects plp ';
        $query .= 'INNER JOIN email_marketing_prospect_lists empl ON empl.prospect_list_id = plp.prospect_list_id ';
        $query .= 'WHERE empl.id = :prospect_id AND NOT EXISTS (SELECT id FROM emailman WHERE related_id = plp.related_id AND marketing_id = :em_id) ';
        $query .= "AND plp.deleted=0 AND empl.deleted=0 AND empl.email_marketing_id= :em_id AND plp.related_id IN ('" . implode("','", $ids) . "') ";
        $query .= 'LIMIT ' . (int)$this->limit;

        try {
            $result = $this->preparedStatementHandler->update($query,
                [
                    'date' => $timedate->nowDb(),
                    'user_id' => $user?->id,
                    'em_id' => $emId,
                    'send_date' => $sendDate,
                    'prospect_id' => $prospectId,
                    'campaign_id' => $campaignId,
                ],
                [
                    ['param' => 'date', 'type' => 'string'],
                    ['param' => 'user_id', 'type' => 'string'],
                    ['param' => 'em_id', 'type' => 'string'],
                    ['param' => 'send_date', 'type' => 'string'],
                    ['param' => 'prospect_id', 'type' => 'string'],
                    ['param' => 'campaign_id', 'type' => 'string'],
                ]);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $result = '';
        }

        $this->limit -= $result;

        return !(empty($result) && $result !== 0);
    }

    protected function getUser(): ?\SugarBean {

        $this->init();

        global $current_user;

        $user = $current_user->getSystemUser();

        $this->close();

        return $user;
    }

    protected function deleteExemptEntries(string $marketingId): void
    {
        $table = $this->emailManagerHandler->getTable();
        $query = "DELETE FROM $table WHERE id IN ( SELECT em.id FROM ( SELECT emailman.id id FROM emailman ";
        $query .= 'INNER JOIN prospect_lists_prospects plp ON emailman.related_id = plp.related_id ';
        $query .= 'AND emailman.related_type = plp.related_type ';
        $query .= 'INNER JOIN prospect_lists pl ON pl.id = plp.prospect_list_id ';
        $query .= 'INNER JOIN email_marketing_prospect_lists empl ON plp.prospect_list_id = empl.prospect_list_id ';
        $query .= "WHERE plp.deleted = 0 AND empl.deleted = 0 AND pl.deleted = 0 AND pl.list_type = 'exempt' ";
        $query .= 'AND empl.email_marketing_id = :marketing_id ) em )';

        try {
            $this->preparedStatementHandler->update($query, [
                'marketing_id' => $marketingId
            ], [
                ['param' => 'marketing_id', 'type' => 'string'],
            ]);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

    }

    protected function filterProspectsEmails(string $id): array
    {
        $query = "SELECT * FROM prospect_lists_prospects plp ";
        $query .= "WHERE prospect_list_id = :id AND plp.deleted = 0";

        try {
            $results = $this->preparedStatementHandler->fetchAll($query, [
                'id' => $id,
            ]);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        if (empty($results)){
            return [];
        }

        return $results;
    }

    protected function removeDeletedProspects(string $id): void
    {
        $query = "SELECT plp.related_id FROM prospect_lists_prospects plp ";
        $query .= "WHERE plp.prospect_list_id = :list_id AND plp.deleted = 1";

        try {
            $results = $this->preparedStatementHandler->fetchAll($query, [
                'list_id' => $id
            ]);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        if (empty($results)) {
            return;
        }

        foreach ($results as $key => $result) {
            $ids[] = $result['related_id'];
        }

        $deleteQuery = "DELETE FROM emailman WHERE related_id IN ('" . implode("','", $ids) . "')";

        try {
            $this->preparedStatementHandler->update($deleteQuery, [], []);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    protected function checkForDuplicate(string $id, string $marketingId): bool
    {
        $query = "SELECT related_id FROM campaign_log WHERE related_id = :id AND marketing_id = :mkt_id AND deleted = 0";

        try {
            $results = $this->preparedStatementHandler->fetch($query,
                [
                    'id' => $id,
                    'mkt_id' => $marketingId
                ]);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        if (empty($results)){
            return false;
        }

        return true;
    }

    protected function getLimit(): string
    {
        return $this->systemConfigHandler->getSystemConfig('emails_per_run')?->getValue() ?? '50';
    }
}
