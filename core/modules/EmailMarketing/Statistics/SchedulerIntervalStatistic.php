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


namespace App\Module\EmailMarketing\Statistics;

use App\Data\LegacyHandler\PreparedStatementHandler;
use App\Data\Service\RecordProviderInterface;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Statistics\Entity\Statistic;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\Statistics\Service\StatisticsProviderInterface;
use App\Statistics\StatisticsHandlingTrait;
use Exception;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class SchedulerIntervalStatistic
 * @package App\Legacy\Statistics
 */
class SchedulerIntervalStatistic extends LegacyHandler implements StatisticsProviderInterface
{

    use StatisticsHandlingTrait;

    public const KEY = 'scheduler-interval';

    /**
     * @param string $projectDir
     * @param string $legacyDir
     * @param string $legacySessionName
     * @param string $defaultSessionName
     * @param LegacyScopeState $legacyScopeState
     * @param RequestStack $session
     * @param PreparedStatementHandler $preparedStatementHandler
     * @param RecordProviderInterface $recordProvider
     */
    public function __construct(
        string $projectDir,
        string $legacyDir,
        string $legacySessionName,
        string $defaultSessionName,
        LegacyScopeState $legacyScopeState,
        RequestStack $session,
        protected PreparedStatementHandler $preparedStatementHandler,
        protected RecordProviderInterface $recordProvider
    ) {
        parent::__construct($projectDir, $legacyDir, $legacySessionName, $defaultSessionName, $legacyScopeState, $session);
    }

    /**
     * @inheritDoc
     */
    public function getHandlerKey(): string
    {
        return self::KEY;
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return self::KEY;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getData(array $query): Statistic
    {
        [$module, $id] = $this->extractContext($query);

        if (empty($module) || empty($id)) {
            return $this->getEmptyResponse(self::KEY);
        }

        $params = $query['params'] ?? [];

        $this->init();
        $this->startLegacyApp();

        $jobs = $params['jobs'] ?? null;

        if ($jobs === null) {
            return $this->getEmptyResponse(self::KEY);
        }

        $result = [
            'fields' => []
        ];

        foreach ($jobs as $job) {
            $bean = $this->getSchedulerBean($job);

            $key = explode('::', $job);
            $key = $key[1];

            if ($bean->status === 'Inactive') {
                $this->close();
                $result['fields'][$key] = [
                    'value' => $bean->status,
                ];
                continue;
            }

            $bean->setIntervalHumanReadable();

            $result['fields'][$key] = [
                'value' => $bean->intervalHumanReadable
            ];

        }
        $statistic = $this->buildSingleValueResponse(self::KEY, 'string', $result);

        $this->close();

        return $statistic;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    protected function getSchedulerBean(string $job): \SugarBean
    {
        $queryBuilder = $this->preparedStatementHandler->createQueryBuilder();

        $queryBuilder->select('id')
            ->from('schedulers')
            ->where('job = :job')
            ->setParameter('job', $job);

        $result = $queryBuilder->fetchAssociative();

        return \BeanFactory::getBean('Schedulers', $result['id']);
    }

}
