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

namespace App\FieldDefinitions\LegacyHandler;

use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\FieldDefinitions\Entity\FieldDefinition;
use App\FieldDefinitions\Service\VardefConfigMapperInterface;
use App\UserPreferences\Service\UserPreferencesProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class OutboundEmailDefaultMapper extends LegacyHandler implements VardefConfigMapperInterface
{

    public function __construct(
        string $projectDir,
        string $legacyDir,
        string $legacySessionName,
        string $defaultSessionName,
        LegacyScopeState $legacyScopeState,
        RequestStack $requestStack,
        protected UserPreferencesProviderInterface $userPreferenceService,
    )
    {
        parent::__construct($projectDir, $legacyDir, $legacySessionName, $defaultSessionName, $legacyScopeState, $requestStack);
    }

    public function getHandlerKey(): string
    {
        return 'outbound-email-vardef-mapper';
    }

        /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return 'outbound-email-vardef-mapper';
    }

    /**
     * @inheritDoc
     */
    public function getModule(): string
    {
        return 'default';
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function map(array $vardefs): array
    {

        foreach ($vardefs as $fieldName => $fieldDefinition) {

            $type = $fieldDefinition['type'] ?? '';
            $module = $fieldDefinition['module'] ?? '';

            if (empty($module)){
                continue;
            }

            if ($type !== 'relate' || $module !== 'OutboundEmailAccounts') {
                continue;
            }

            $preferences = $this->userPreferenceService->getUserPreference('Emails')?->getItems() ?? [];

            if ($preferences === []) {
                continue;
            }

            $id = $preferences['defaultOEAccount'] ?? '';

            if ($id === '') {
                continue;
            }

            $bean = $this->getBean('OutboundEmailAccounts', $id);


            $fieldDefinition['defaultValueObject'] = [
                'id' => $id,
                'smtp_from_name' => $bean->smtp_from_name,
                'smtp_from_addr' => $bean->smtp_from_addr,
                'from_addr' => $bean->smtp_from_name . ' ' . $bean->smtp_from_addr,
            ];

            $vardefs[$fieldName] = $fieldDefinition;
        }

        return $vardefs;
    }

    protected function getBean(string $module, string $id): \SugarBean|bool
    {
        $this->init();

        $bean = \BeanFactory::getBean($module, $id);

        $this->close();

        return $bean;
    }
}
