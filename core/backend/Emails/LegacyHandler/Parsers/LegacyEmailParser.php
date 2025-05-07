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

use App\DateTime\LegacyHandler\DateTimeHandler;
use App\Emails\Service\EmailParserHandler\EmailParserInterface;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\SystemConfig\LegacyHandler\SystemConfigHandler;
use Symfony\Component\HttpFoundation\RequestStack;

class LegacyEmailParser extends LegacyHandler implements EmailParserInterface
{
    public const KEY = 'legacy-email-parser';

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

    public function getKey(): string
    {
        return self::KEY;
    }

    public function applies(): bool
    {
        return true;
    }

    public function getModule(): string
    {
        return 'default';
    }

    public function getOrder(): int
    {
        return 0;
    }

    public function getHandlerKey(): string
    {
        return self::KEY;
    }

    public function parse($string, $bean): string
    {
        $siteUrl = $this->getSiteUrl();

        if ($bean->module_name === 'Surveys') {
            $string = $this->buildSurveyUrl($string, $bean);
        }

        $string =  str_replace([
            '$config_site_url',
            '$sugarurl',
            '$contact_user_user_name',
            '$contact_user_pwd_last_changed',
        ], [
            $siteUrl,
            $siteUrl,
            $bean->name ?? '',
            $this->dateTimeHandler->getDateTime()->nowDb(),
        ], $string);

        require_once $this->legacyDir . '/modules/EmailTemplates/EmailTemplateParser.php';

        return $this->useTemplateParser($bean, $siteUrl, $string);
    }

    protected function getSiteUrl()
    {
        return $this->systemConfigHandler->getSystemConfig('site_url')?->getValue() ?? '';
    }

    /**
     * @param \SugarBean|bool $bean
     * @param string $siteUrl
     * @param $description_html
     * @return string
     */
    public function useTemplateParser(\SugarBean|bool $bean, string $siteUrl, $description_html): string
    {
        return (new \EmailTemplateParser(
            null,
            null,
            $bean,
            $siteUrl,
            null
        ))->getParsedValue($description_html);
    }

    protected function buildSurveyUrl($string, $bean): string {
        if ($bean->status !== 'Public') {
            return $string;
        }

        $url = $this->getSiteUrl() . '/index.php?entryPoint=survey&id=' . $bean->id;

        return str_replace('$surveys_survey_url_display', $url, $string);
    }

}
