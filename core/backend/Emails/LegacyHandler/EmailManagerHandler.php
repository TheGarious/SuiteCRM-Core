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

namespace App\Emails\LegacyHandler;

use App\Data\Entity\Record;
use App\Data\LegacyHandler\PreparedStatementHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use Configurator;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use SugarBean;
use App\Engine\LegacyHandler\LegacyHandler;
use Symfony\Component\HttpFoundation\RequestStack;

class EmailManagerHandler extends LegacyHandler {

    protected PreparedStatementHandler $preparedStatementHandler;
    protected LoggerInterface $logger;

    public function __construct(
        string $projectDir,
        string $legacyDir,
        string $legacySessionName,
        string $defaultSessionName,
        LegacyScopeState $legacyScopeState,
        RequestStack $requestStack,
        PreparedStatementHandler $preparedStatementHandler,
        LoggerInterface $logger
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
    }

    public function getHandlerKey(): string
    {
        return 'email-manager-handler';
    }

    /**
     * @param Record $emailRecord
     * @param Record $emailMarketing
     * @param SugarBean $moduleBean
     * @param SugarBean $emailMan
     * @param string $prospectListId
     * @param array $suppressedEmails
     * @return bool
     * @throws Exception
     */
    public function validateEmail(
        Record $emailRecord,
        Record $emailMarketing,
        SugarBean $moduleBean,
        SugarBean $emailMan,
        string $prospectListId,
        array $suppressedEmails
    ): bool
    {
        $email = $moduleBean->email1 ?? '';
        $emailManId = $this->getEmailManId($emailRecord->getAttributes()['parent_id'], $emailMarketing->getId());

        $isPrimary = $emailMan->is_primary_email_address($moduleBean) ?? false;
        $isValid = $emailMan->valid_email_address($email) ?? false;
        $shouldBlock = $emailMan->shouldBlockEmail($moduleBean) ?? true;

        if (!$isPrimary) {
            $this->setAsSent($email, $emailRecord, $emailMarketing, true, 'send error', $prospectListId);
            $this->logger->error("Email Address provided is not Primary Address for email with id $email and EmailMan id $emailManId");

            return false;
        }

        if (!$isValid) {
            $this->setAsSent($email, $emailRecord, $emailMarketing, true, 'invalid email', $prospectListId);
            $this->logger->error("Email Address provided is not Primary Address for email $email and EmailMan id $emailManId");

            return false;
        }

        if ($shouldBlock) {
            $this->setAsSent($email, $emailRecord, $emailMarketing, true, 'blocked', $prospectListId);
            $this->logger->warning("Email Address was sent due to not being confirm opt in for email $email and EmailMan id $emailManId");

            return false;
        }

        if ($this->isOptOut($moduleBean)) {
            $this->setAsSent($email, $emailRecord, $emailMarketing, true, 'blocked', $prospectListId);
            $this->logger->error("Email Address provided is Opted out with id $email and EmailMan id $emailManId");

            return false;
        }

        if ($this->isRestrictedDomains($moduleBean, $suppressedEmails['domains'])) {
            $this->setAsSent($email, $emailRecord, $emailMarketing, true, 'blocked', $prospectListId);
            $this->logger->error("Email Address provided is restricted: $email and EmailMan id $emailManId");

            return false;
        }

        if ($this->isRestrictedAddress($moduleBean, $suppressedEmails['addresses'])) {
            $this->setAsSent($email, $emailRecord, $emailMarketing, true, 'blocked', $prospectListId);
            $this->logger->error("Email Address provided is restricted: $email and EmailMan id $emailManId");

            return false;
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function setAsSent(
        string $email,
        ?Record $emailRecord,
        ?Record $emailMarketing,
        $delete,
        $activityType,
        $prospectListId
    ): void {
        $emAttributes = $emailMarketing?->getAttributes() ?? [];
        $emailAttributes = $emailRecord?->getAttributes() ?? [];

        $this->init();

        global $timedate;

        $id = $this->getEmailManId($emailAttributes['parent_id'], $emAttributes['id']);

        if (!$delete){
            $this->close();
            $query = "UPDATE emailman SET in_queue = '1', send_attempts = send_attempts + 1, in_queue_date = :now ";
            $query .= "WHERE id = :id";

            try {
                $this->preparedStatementHandler->update($query, [
                    'now' => $timedate->now(),
                    'id' => $id
                ], [
                    ['param' => 'now', 'type' => 'datetime'],
                    ['param' => 'id', 'type' => 'string']
                ]);
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }

            return;
        }

        $campaignLog = \BeanFactory::newBean('CampaignLog');
        $campaignLog->campaign_id = $emAttributes['campaign_id'] ?? '';
        $campaignLog->marketing_id = $emAttributes['id'] ?? '';
        $campaignLog->more_information = $email;
        $campaignLog->activity_type = $activityType;
        $campaignLog->activity_date = $timedate->nowDb();
        $campaignLog->list_id = $prospectListId ?? null;
        $campaignLog->related_id = $emailAttributes['parent_id'] ?? null;
        $campaignLog->related_type = $emailAttributes['parent_type'] ?? null;
        $campaignLog->resend_type = null;
        $campaignLog->save();

        $this->close();

        $query = "DELETE FROM emailman WHERE id = :id ";
        try {
            $this->preparedStatementHandler->update($query, [
                'id' => $id
            ], [
                ['param' => 'id', 'type' => 'string'],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Unable to Delete Record from Email Man with the id' . $id);
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * @return string|null
     */
    public function getTable(): ?string
    {
        $this->init();

        $emailMan = \BeanFactory::getBean('EmailMan');
        $table = $emailMan->db->quote($emailMan->getTableName());

        $this->close();

        return $table;
    }


    /**
     * @param string $module
     * @return string|null
     */
    public function getModuleTable(string $module): ?string
    {
        $this->init();

        $bean = \BeanFactory::getBean($module);
        $table = $bean->db->quote($bean->getTableName());

        $this->close();

        return $table;
    }

    /**
     * @param $relatedId
     * @param $marketingId
     * @return mixed
     * @throws Exception
     */
    public function getEmailManId($relatedId, $marketingId): mixed
    {

        $query = "SELECT id FROM emailman WHERE related_id = :related_id AND marketing_id = :marketing_id AND deleted = 0";

        $result = $this->preparedStatementHandler->fetch($query, [
           'related_id' => $relatedId,
           'marketing_id' => $marketingId
        ]);

        if (empty($result)){
            $this->logger->warning('Unable to find EmailMan id');
        }

        return $result['id'];
    }

    /**
     * @param SugarBean $moduleBean
     * @return bool
     */
    protected function isOptOut(SugarBean $moduleBean): bool
    {
        return isset($moduleBean->email_opt_out) && (
                $moduleBean->email_opt_out === 'on' ||
                $moduleBean->email_opt_out === '1' ||
                $moduleBean->email_opt_out === 1
        );
    }

    /**
     * @param SugarBean $moduleBean
     * @param array $domains
     * @return bool
     */
    protected function isRestrictedDomains(SugarBean $moduleBean, array $domains): bool
    {
        $email = strtolower($moduleBean->email1 ?? '');

        $check = strrpos($email, '@');

        if ($check === false){
            return false;
        }

        foreach ($domains as $domain => $value) {
            $pos = strrpos($email, (string) $domain);
            if ($pos === false){
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param SugarBean $moduleBean
     * @param array $addresses
     * @return bool
     */
    protected function isRestrictedAddress(SugarBean $moduleBean, array $addresses): bool
    {
        $email = $moduleBean->email1 ?? '';

        return $addresses[$email] ?? false;
    }


    /**
     * @param string $marketingId
     * @return array
     * @throws Exception
     */
    public function getSuppressedEmails(string $marketingId): array
    {
        $query = 'SELECT prospect_list_id, prospect_lists.list_type,prospect_lists.domain_name ';
        $query .= 'FROM email_marketing_prospect_lists ';
        $query .= 'LEFT JOIN prospect_lists on prospect_lists.id = email_marketing_prospect_lists.prospect_list_id ';
        $query .= 'WHERE email_marketing_id = :marketing_id ';
        $query .= "AND prospect_lists.list_type in ('exempt_address','exempt_domain') ";
        $query .= "AND email_marketing_prospect_lists.deleted = 0 AND prospect_lists.deleted = 0 ";

        $domains = [];
        $addresses = [];

        $results = $this->preparedStatementHandler->fetchAll($query, [
           'marketing_id' => $marketingId
        ]);

        foreach ($results as $row) {
            if ($row['list_type'] === 'exempt_domain') {
                $domains[$row['domain_name']] = 1;
                continue;
            }

            $addresses = $this->getInvalidEmails($row['prospect_list_id']);
        }
        return [
          'domains' => $domains,
          'addresses' => $addresses
        ];
    }

    /**
     * @param $id
     * @return array
     * @throws Exception
     */
    protected function getInvalidEmails($id): array
    {
        $addresses = [];

        $query = 'SELECT email_address FROM email_addresses ea ';
        $query .= 'JOIN email_addr_bean_rel eabr ON ea.id = eabr.email_address_id ';
        $query .= 'JOIN prospect_lists_prospects plp ON eabr.bean_id = plp.related_id ';
        $query .= 'AND eabr.bean_module = plp.related_type AND plp.prospect_list_id = :id AND plp.deleted = 0';

        $results = $this->preparedStatementHandler->fetchAll($query, [
            'id' => $id
        ]);

        foreach ($results as $row) {
            if (empty($row['email_address'])) {
                continue;
            }

            $addresses[strtolower($row['email_address'])] = 1;
        }

        return $addresses;
    }

    /**
     * @return Configurator
     */
    public function getConfigurator(): Configurator
    {
        $this->init();

        $configurator = new Configurator();

        $this->close();

        return $configurator;
    }

    public function getTimeDate(): \TimeDate
    {
        $this->init();

        global $timedate;

        $this->close();

        return $timedate;
    }
}
