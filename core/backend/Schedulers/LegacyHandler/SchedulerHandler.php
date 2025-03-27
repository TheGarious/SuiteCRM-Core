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

namespace App\Schedulers\LegacyHandler;

use App\Data\LegacyHandler\PreparedStatementHandler;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\Schedulers\Service\SchedulerRegistry;
use App\SystemConfig\LegacyHandler\SystemConfigHandler;
use Psr\Log\LoggerInterface;
use SugarCronJobs;
use SuiteCRM\Exception\Exception;
use Symfony\Component\HttpFoundation\RequestStack;

class SchedulerHandler extends LegacyHandler {

    public const HANDLER_KEY = 'scheduler-handler';

    protected SchedulerRegistry $schedulerRegistry;
    protected SystemConfigHandler $systemConfigHandler;
    protected PreparedStatementHandler $preparedStatementHandler;
    protected LoggerInterface $logger;

    public function __construct(
        string $projectDir,
        string $legacyDir,
        string $legacySessionName,
        string $defaultSessionName,
        LegacyScopeState $legacyScopeState,
        RequestStack $requestStack,
        SchedulerRegistry $schedulerRegistry,
        SystemConfigHandler $systemConfigHandler,
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
        $this->schedulerRegistry = $schedulerRegistry;
        $this->systemConfigHandler = $systemConfigHandler;
        $this->preparedStatementHandler = $preparedStatementHandler;
        $this->logger = $logger;
    }

    public function getHandlerKey(): string
    {
        return self::HANDLER_KEY;
    }

    /**
     * @return bool
     */
    public function runLegacySchedulers(): bool {

        $this->init();

        global $sugar_config;

        $cronClass = $sugar_config['cron_class'] ?? 'SugarCronJobs';

        $file = "include/SugarQueue/$cronClass.php";

        if (file_exists("custom/$file")) {
            require_once "custom/$file";
        } else {
            require_once $file;
        }

        $passed = true;
        $jobQueue = new SugarCronJobs();

        try {
            $jobQueue->runCycle();
        } catch (Exception $exception) {
            $passed = false;
            $this->logger->error($exception->getMessage());
        }

        $this->close();

        return $passed;
    }

    /**
     * @return array
     */
    public function runSchedulers(): array {

        $keys = $this->schedulerRegistry->getAllKeys();
        $response = [];

        foreach ($keys as $key) {
            if ($this->getStatus($key) === 'Inactive') {
                continue;
            }

            $scheduler = $this->schedulerRegistry->get($key);
            $status = $scheduler->run();

            $response[] = [
                'name' => $key,
                'result' => $status,
            ];
        }

        return $response;
    }

    /**
     * @param $key
     * @return string
     */
    public function getStatus($key): string {

        try {
            $record = $this->preparedStatementHandler->fetch(
                "SELECT * FROM schedulers WHERE job = :key",
                ['key' => $key]
            );
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return $record['status'] ?? 'Inactive';
    }

}
