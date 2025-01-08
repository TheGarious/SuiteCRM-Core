<?php
/**
 * SuiteCRM is a customer relationship management program developed by SalesAgility Ltd.
 * Copyright (C) 2024 SalesAgility Ltd.
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

namespace App\Module\Campaigns\Statistics;

use App\Data\LegacyHandler\ListDataQueryHandler;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\Module\Service\ModuleNameMapperInterface;
use App\Statistics\Entity\Statistic;
use App\Statistics\Model\ChartOptions;
use App\Statistics\Service\StatisticsProviderInterface;
use App\Statistics\StatisticsHandlingTrait;
use BeanFactory;
use SugarBean;
use Symfony\Component\HttpFoundation\RequestStack;

class CampaignSendStatus extends LegacyHandler implements StatisticsProviderInterface
{
    use StatisticsHandlingTrait;

    public const KEY = 'campaign-send-status';

    private ListDataQueryHandler $queryHandler;
    private ModuleNameMapperInterface$moduleNameMapper;


    /**
     * CampaignSendStatus constructor.
     * @param string $projectDir
     * @param string $legacyDir
     * @param string $legacySessionName
     * @param string $defaultSessionName
     * @param LegacyScopeState $legacyScopeState
     * @param ListDataQueryHandler $queryHandler
     * @param ModuleNameMapperInterface $moduleNameMapper
     * @param RequestStack $session
     */
    public function __construct(
        string $projectDir,
        string $legacyDir,
        string $legacySessionName,
        string $defaultSessionName,
        LegacyScopeState $legacyScopeState,
        ListDataQueryHandler $queryHandler,
        ModuleNameMapperInterface $moduleNameMapper,
        RequestStack $session,
    ) {
        parent::__construct($projectDir, $legacyDir, $legacySessionName, $defaultSessionName, $legacyScopeState, $session);
        $this->queryHandler = $queryHandler;
        $this->moduleNameMapper = $moduleNameMapper;
    }

    public function getHandlerKey(): string
    {
        return self::KEY;
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getData(array $query): Statistic
    {
        [$module, $id, $criteria, $sort] = $this->extractContext($query);

        if (empty($module) || $module !== 'campaigns') {
            return $this->getEmptySeriesResponse(self::KEY);
        }

        $activities = [
            'targeted' => 'LBL_LOG_ENTRIES_TARGETED_TITLE',
            'sent error' => 'LBL_LOG_ENTRIES_SEND_ERROR_TITLE',
            'invalid email' => 'LBL_LOG_ENTRIES_INVALID_EMAIL_TITLE',
            'blocked' => 'LBL_LOG_ENTRIES_BLOCKED_TITLE',
            'viewed' => 'LBL_LOG_ENTRIES_VIEWED_TITLE',
        ];

        $this->init();
        $this->startLegacyApp();

        $legacyName = $this->moduleNameMapper->toLegacy($module);
        $bean = BeanFactory::newBean($legacyName);

        if (!$bean instanceof SugarBean) {
            return $this->getEmptySeriesResponse(self::KEY);
        }

        $query = $this->queryHandler->getQuery($bean, $criteria, $sort);

        $query = $this->generateQuery($query, $id, $activities);

        $result = $this->runQuery($query, $bean);

        $nameField = 'activity_type';
        $valueField = 'hits';

        $series = $this->buildSingleSeries($result, $nameField, $valueField, $activities);

        $chartOptions = new ChartOptions();

        $statistic = $this->buildSeriesResponse(self::KEY, 'int', $series, $chartOptions);

        $this->close();

        return $statistic;
    }


    public function generateQuery(array $query, string $id, array $activities): array
    {
        $query['select'] = "SELECT activity_type,target_type, count(*) hits ";
        $query['from'] = " FROM campaign_log ";
        $query['where'] = " WHERE campaign_id = '$id' AND archived=0 AND deleted=0 AND activity_type in ('" . implode("','", array_keys($activities)) . "')";
        $query['group_by'] = " GROUP BY  activity_type, target_type";
        $query['order_by'] = " ORDER BY  activity_type, target_type";

        return $query;
    }

    /**
     * @param array $query
     * @param $bean
     * @return array
     */
    protected function runQuery(array $query, $bean): array
    {
        // send limit -2 to not add a limit
        return $this->queryHandler->runQuery($bean, $query, -1, -2);
    }

}
