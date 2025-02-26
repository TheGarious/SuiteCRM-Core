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

use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\Module\ProspectLists\Service\MultiRelate\ProspectListsEmailMapper;
use BeanFactory;
use Symfony\Component\HttpFoundation\RequestStack;

class FilterEmailListHandler extends LegacyHandler
{
    protected const HANDLER_KEY = 'record-filter-email-list';

    protected ProspectListsEmailMapper $prospectListsEmailMapper;
    protected EmailToQueueHandler $emailToQueueHandler;

    public function __construct(
        string                   $projectDir,
        string                   $legacyDir,
        string                   $legacySessionName,
        string                   $defaultSessionName,
        LegacyScopeState         $legacyScopeState,
        RequestStack             $requestStack,
        ProspectListsEmailMapper $prospectListsEmailMapper,
        EmailToQueueHandler $emailToQueueHandler
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
        $this->prospectListsEmailMapper = $prospectListsEmailMapper;
        $this->emailToQueueHandler = $emailToQueueHandler;
    }

    public function getHandlerKey(): string
    {
        return self::HANDLER_KEY;
    }

    /**
     * @param array $fields
     * @param $max
     * @param bool $isTest
     * @return array|null
     */
    public function getEmails(array $fields, $max, bool $isTest = false): ?array
    {
        $emails = [];
        $count = 0;

        foreach ($fields as $field) {

            if ($isTest && $count > $max) {
                return null;
            }

            $module = $field['module'];
            $value = $field['value'] ?? [];

            if ($value === null) {
                continue;
            }

            if ($module === 'ProspectLists') {
                $this->prospectListsEmailMapper->getEmailFromMultiRelate($emails, $count, $module, $value, $max, $isTest);
                if ($isTest && $count > $max){
                    return null;
                }

                continue;
            }

            if ($module === 'Users') {
                $this->getUserEmails($emails, $count, $module, $value, $isTest);
                if ($isTest && $count > $max){
                    return null;
                }

                continue;
            }

            foreach ($value as $key => $item) {
                if ($isTest && $count > $max){
                    return null;
                }

                $emails[$item] = $item;

                if (!$isTest) {
                    $this->emailToQueueHandler->sendToQueue();
                    continue;
                }

                $count++;
            }
        }

        return $emails;
    }

    /**
     * @param array $emails
     * @param $count
     * @param string $module
     * @param mixed $value
     * @param $isTest
     * @return void
     */
    public function getUserEmails(array &$emails, &$count, string $module, mixed $value, $isTest): void
    {
        foreach ($value as $key => $item) {
            $id = $item['id'];
            $bean = BeanFactory::getBean($module, $id);
            $emails[$bean->email1] = $bean->email1;

            if (!$isTest) {
                $this->emailToQueueHandler->sendToQueue();
                return;
            }

            $count++;
        }
    }
}
