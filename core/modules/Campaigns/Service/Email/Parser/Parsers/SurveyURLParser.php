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

namespace App\Module\Campaigns\Service\Email\Parser\Parsers;

use App\Data\Entity\Record;
use App\Data\Service\RecordTemplate\RecordTemplateManagerInterface;
use App\Module\Campaigns\Service\Email\Parser\CampaignEmailParserInterface;
use App\Module\Campaigns\Service\Email\Trackers\EmailTrackerManagerInterface;
use Psr\Log\LoggerInterface;

class SurveyURLParser implements CampaignEmailParserInterface
{

    public function __construct(
        protected EmailTrackerManagerInterface $trackerManager,
        protected RecordTemplateManagerInterface $recordTemplateManager,
        protected LoggerInterface $logger
    ) {
    }

    public function getKey(): string
    {
        return 'survey-url-parser';
    }

    public function getOrder(): int
    {
        return 2;
    }

    public function applies(Record $record, array $context): bool
    {
        /** @var Record $emailMarketing */
        $emailMarketing = $context['emailMarketingRecord'] ?? null;

        if ($emailMarketing === null) {
            return false;
        }

        $type = $emailMarketing->getAttributes()['type'] ?? null;

        return $type === 'survey';
    }

    public function parse(Record $record, array $context): void
    {
        $targetRecord = $context['targetRecord'] ?? null;
        if ($targetRecord === null) {
            $this->logger->debug(
                'Campaigns:SurveyURLParser::parse - missing targetRecord', [
                    'context' => $context,
                ]
            );
            return;
        }

        if (!$this->recordTemplateManager->isType($targetRecord, 'person')) {
            $this->logger->debug(
                'Campaigns:SurveyURLParser::parse - targetRecord is not a person', [
                    'targetRecord' => $targetRecord,
                    'context' => $context,
                    'record' => $record,
                ]
            );
            return;
        }

        $targetId = $targetRecord->getId() ?? '';

        if (empty($targetId)) {
            $this->logger->debug(
                'Campaigns:SurveyURLParser::parse - target id is empty', [
                    'targetRecord' => $targetRecord,
                    'context' => $context,
                    'record' => $record,
                ]
            );
            return;
        }

        $trackerId = $context['trackerId'] ?? '';
        if (empty($trackerId)) {
            $this->logger->debug(
                'Campaigns:SurveyURLParser::parse - missing trackerId - id: ' . $trackerId, [
                    'trackerId' => $trackerId,
                ]
            );
            return;
        }


        $marketingRecord = $context['emailMarketingRecord'] ?? null;

        if ($marketingRecord === null) {
            $this->logger->debug(
                'Campaigns:SurveyURLParser::parse - missing emailMarketingRecord', [
                    'context' => $context,
                ]
            );
            return;
        }

        $surveyId = $marketingRecord->getAttributes()['survey_id'] ?? '';
        if (empty($surveyId)) {
            $this->logger->debug(
                'Campaigns:SurveyURLParser::parse - missing surveyId', [
                    'context' => $context
                ]
            );
            return;
        }

        $attributes = $record->getAttributes() ?? [];

        $attributes['description'] = $this->trackerManager->addSurveyLink($surveyId, $trackerId, $targetId, $attributes['description'] ?? '', $context);
        $attributes['description_html'] = $this->trackerManager->addSurveyLink($surveyId, $trackerId, $targetId, $attributes['description_html'] ?? '', $context);

        $record->setAttributes($attributes);

        $this->logger->debug(
            'Campaigns:SurveyURLParser::parse - added unsubscribe link - id: ' . $trackerId, [
                'trackerId' => $trackerId,
                'recordAttributes' => $attributes,
            ]
        );
    }


}
