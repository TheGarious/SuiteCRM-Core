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


namespace App\Process\LegacyHandler;


use ApiPlatform\Exception\InvalidArgumentException;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\Module\ProspectLists\Service\MultiRelate\ProspectListsEmailMapper;
use App\Process\Entity\Process;
use App\Process\Service\ProcessHandlerInterface;
use BeanFactory;
use Symfony\Component\HttpFoundation\RequestStack;

class SendTestEmailHandler extends LegacyHandler implements ProcessHandlerInterface
{
    protected const MSG_OPTIONS_NOT_FOUND = 'Process options is not defined';
    protected const PROCESS_TYPE = 'record-send-test-email';

    protected ProspectListsEmailMapper $prospectListsEmailMapper;

    public function __construct(
        string                   $projectDir,
        string                   $legacyDir,
        string                   $legacySessionName,
        string                   $defaultSessionName,
        LegacyScopeState         $legacyScopeState,
        RequestStack             $requestStack,
        ProspectListsEmailMapper $prospectListsEmailMapper,
    )
    {
        parent::__construct($projectDir, $legacyDir, $legacySessionName, $defaultSessionName, $legacyScopeState, $requestStack);
        $this->prospectListsEmailMapper = $prospectListsEmailMapper;
    }

    /**
     * @inheritDoc
     */
    public function getHandlerKey(): string
    {
        return self::PROCESS_TYPE;
    }

    /**
     * @inheritDoc
     */
    public function getProcessType(): string
    {
        return self::PROCESS_TYPE;
    }

    /**
     * @inheritDoc
     */
    public function requiredAuthRole(): string
    {
        return 'ROLE_USER';
    }

    /**
     * @inheritDoc
     */
    public function getRequiredACLs(Process $process): array
    {
        return [];
    }


    /**
     * @inheritDoc
     */
    public function configure(Process $process): void
    {
        $process->setId(self::PROCESS_TYPE);
        $process->setAsync(false);
    }

    /**
     * @inheritDoc
     */
    public function validate(Process $process): void
    {
        if (empty($process->getOptions())) {
            throw new InvalidArgumentException(self::MSG_OPTIONS_NOT_FOUND);
        }
    }

    /**
     * @inheritDoc
     */
    public function run(Process $process): void
    {
        $options = $process->getOptions();

        $fields = $options['params']['fields'];

        $this->init();

        $emails = [];

        foreach ($fields as $field) {
            $module = $field['module'];
            $value = $field['value'];

            if ($value === null) {
                continue;
            }

            if ($module === 'ProspectLists') {
                $this->prospectListsEmailMapper->getEmailFromMultiRelate($emails, $module, $value);
                continue;
            }

            if ($module === 'Users') {
                foreach ($value as $key => $item) {
                    $id = $item['id'];
                    $bean = BeanFactory::getBean($module, $id);
                    $emails[$bean->email1] = $bean->email1;
                }
                continue;
            }

            foreach ($value as $key => $item) {
                $emails[$item] = $item;
            }
        }

    }

}
