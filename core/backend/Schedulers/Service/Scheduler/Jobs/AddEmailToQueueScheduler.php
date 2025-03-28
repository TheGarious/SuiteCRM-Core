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
use App\Emails\LegacyHandler\EmailManagerHandler;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\Schedulers\Service\SchedulerInterface;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AddEmailToQueueScheduler extends LegacyHandler implements SchedulerInterface {

    public const SCHEDULER_KEY = 'scheduler::email-to-queue';

    protected PreparedStatementHandler $preparedStatementHandler;
    protected LoggerInterface $logger;
    protected EmailManagerHandler $emailManagerHandler;

    public function __construct(
        string $projectDir,
        string $legacyDir,
        string $legacySessionName,
        string $defaultSessionName,
        LegacyScopeState $legacyScopeState,
        RequestStack $requestStack,
        PreparedStatementHandler $preparedStatementHandler,
        LoggerInterface $logger,
        EmailManagerHandler $emailManagerHandler
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
     * @throws Exception
     */
    public function run(): bool
    {
        $table = $this->emailManagerHandler->getModuleTable('EmailMarketing');
        $emails = $this->preparedStatementHandler->fetchAll(
            "SELECT * FROM $table WHERE status = 'scheduled'",
            []
        );

        $passed = true;

        foreach ($emails as $email) {
            $id = $email['id'];
            $campaignId = $email['campaign_id'];
            $sendDate = $email['date_start'];

            $prospects = $this->getProspectLists($email['id']);

            foreach ($prospects as $prospect) {
                $prospectId = $prospect['id'];

                $this->runDeleteQuery($campaignId, $id, $prospect['prospect_list_id']);

                $result = $this->runInsertQuery($prospectId, $id, $campaignId, $sendDate);

                if (!$result) {
                    $passed = false;
                }
            }

            $this->deleteExemptEntries($id);
        }


        if (!$passed) {
            $this->logger->warning('was not added to the queue');
        }

        return $passed;
    }

    /**
     * @param $emId
     * @return false|mixed
     */
    protected function getProspectLists($emId): mixed
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

    /**
     * @param $prospectId
     * @param $emId
     * @param $campaignId
     * @param $sendDate
     * @return bool
     */
    protected function runInsertQuery($prospectId, $emId, $campaignId, $sendDate): bool
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
        $query .= 'WHERE empl.id = :prospect_id ';
        $query .= 'AND plp.deleted=0 AND empl.deleted=0 AND empl.email_marketing_id= :em_id';

        try {
            $result = $this->preparedStatementHandler->update($query,
                [
                    'date' => $timedate->nowDb(),
                    'user_id' => $user?->id,
                    'em_id' => $emId,
                    'send_date' => $sendDate,
                    'prospect_id' => $prospectId,
                    'campaign_id' => $campaignId
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

        if (empty($result)) {
            return false;
        }

        return true;
    }

    /**
     * @return \SugarBean|null
     */
    protected function getUser(): ?\SugarBean {

        $this->init();

        global $current_user;

        $user = $current_user->getSystemUser();

        $this->close();

        return $user;
    }


    /**
     * @param $campaignId
     * @param $marketingId
     * @param $listId
     * @return void
     */
    protected function runDeleteQuery($campaignId, $marketingId, $listId): void
    {
        $table = $this->emailManagerHandler->getTable();
        $query = "DELETE FROM $table WHERE campaign_id = :campaign_id ";
        $query .= 'AND marketing_id = :marketing_id AND list_id = :list_id';

        try {
            $this->preparedStatementHandler->update($query, [
                'campaign_id' => $campaignId,
                'marketing_id' => $marketingId,
                'list_id' => $listId,
            ], [
                ['param' => 'campaign_id', 'type' => 'string'],
                ['param' => 'marketing_id', 'type' => 'string'],
                ['param' => 'list_id', 'type' => 'string'],
            ]);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

    }

    /**
     * @param $marketingId
     * @return void
     */
    protected function deleteExemptEntries($marketingId): void
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
}
