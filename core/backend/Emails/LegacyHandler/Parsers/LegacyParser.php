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

namespace App\Emails\LegacyHandler\Parsers;

use App\Data\Entity\Record;
use App\DateTime\LegacyHandler\DateTimeHandler;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\SystemConfig\LegacyHandler\SystemConfigHandler;
use Symfony\Component\HttpFoundation\RequestStack;

class LegacyParser extends LegacyHandler
{

    public function __construct(
        string                        $projectDir,
        string                        $legacyDir,
        string                        $legacySessionName,
        string                        $defaultSessionName,
        LegacyScopeState              $legacyScopeState,
        RequestStack                  $requestStack,
        protected SystemConfigHandler $systemConfigHandler,
        protected DateTimeHandler     $dateTimeHandler
    )
    {
        parent::__construct(
            $projectDir,
            $legacyDir,
            $legacySessionName,
            $defaultSessionName,
            $legacyScopeState, $requestStack
        );
    }

    public function getHandlerKey(): string
    {
        return 'legacy-parser';
    }

    public function parseEmail(Record $email): Record
    {
        $attributes = $email->getAttributes();

        $this->init();
        $this->startLegacyApp();

        $parentType = $attributes['parent_type'] ?? '';
        $parentId = $attributes['parent_type'] ?? '';

        if (empty($parentId)) {
            return $email;
        }

        $bean = \BeanFactory::getBean($attributes['parent_type'], $attributes['parent_id']);

        $attributes['description_html'] = $this->parse($attributes['description_html']);
        $attributes['name'] = $this->parse($attributes['name']);

        $attributes = $this->parseModule($attributes, $parentType, $bean);

        if ($parentType === 'Prospects' || $parentType === 'Leads') {
            $attributes = $this->parseModule($attributes, 'Contacts', $bean);
        }

        $email->setAttributes($attributes);

        $this->close();

        return $email;
    }

    protected function parse(string $string): string
    {
        $siteUrl = $this->systemConfigHandler->getSystemConfig('site_url')?->getValue() ?? '';

        return str_replace([
            '$config_site_url',
            '$sugarurl',
            '$contact_user_user_name',
            '$contact_user_pwd_last_changed',
        ], [
            $siteUrl,
            $siteUrl,
            $bean->name ?? '',
            $this->dateTimeHandler->getDateTime()->nowDb()
        ], $string);
    }

    protected function parseModule($attributes, string $module, \SugarBean|bool $bean)
    {
        $template = \BeanFactory::getBean('EmailTemplates');
        $arr = [];

        $template = $template->parse_email_template([
            'subject' => $attributes['name'],
            'body' => $attributes['description_html'],
        ], $module, $bean, $arr);


        $attributes['name'] = $template['subject'];
        $attributes['description_html'] = $template['body'];

        return $attributes;
    }

}
