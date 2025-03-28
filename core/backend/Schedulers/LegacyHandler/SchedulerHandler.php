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


    protected int $maxJobs = 10;
    protected int $maxRuntime = 60;
    protected int $minInterval = 30;

    protected int $jobTries = 5;
    protected int $timeout = 86400; // seconds
    protected int $successLifetime = 30; // days
    protected int $failureLifetime = 180; // days

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

        $this->setCronConfig();
        $this->setJobsConfig();
    }

    public function getHandlerKey(): string
    {
        return self::HANDLER_KEY;
    }

    /**
     * @return array
     * @throws \Doctrine\DBAL\Exception
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    public function runSchedulers(): array {

        if (!$this->throttle()){
            $this->logger->error('Jobs run too frequently, throttled to protect the system');
        }

        $this->cleanup(false);

        $this->clearHistoricJobs(false);

        $query = "SELECT * FROM schedulers WHERE status = 'Active' AND job LIKE '%scheduler::%' ";
        $query .= "AND NOT EXISTS(SELECT id FROM job_queue WHERE scheduler_id = schedulers.id AND status != 'done')";
        $schedulers = $this->preparedStatementHandler->fetchAll($query, []);

        if (empty($schedulers)) {
            return [];
        }

        foreach ($schedulers as $scheduler) {
            $this->init();
            $schedulerBean = \BeanFactory::getBean('Schedulers', $scheduler['id']);

            $this->close();

            $this->createJob($schedulerBean);
        }

        $response = [];
        $cutoff = time() + $this->maxRuntime;

        if (empty($this->maxJobs)){
            $this->logger->error('Ran all cron Jobs');
        }

        for ($count = 0; $count < $this->maxJobs; $count++) {

            $schedulerRow = $schedulers[$count];

            $job = $this->getNextScheduler(false);

            if (empty($job->id)) {
                $this->logger->error('Unable to get job id');
                continue;
            }

            $scheduler = $this->schedulerRegistry->get($schedulerRow['job']);

            $status = $scheduler->run();

            $this->resolveJob($job->id, $status);

            $response[] = [
                'name' => $schedulerRow['name'],
                'result' => $status,
            ];

            if (time() >= $cutoff) {
                $this->logger->info('Timeout');
                break;
            }
        }

        return $response;
    }

    public function resolveJob($id, $result, $messages = null): void
    {

        $this->init();

        $job = \BeanFactory::getBean('SchedulersJobs', $id);
        $job->resolution = 'failed';

        if ($result) {
            $job->resolution = 'success';
            $job->status = 'done';
        }

        if (!empty($messages)) {
            $job->addMessages($messages);
        }

        $job->save();

        $this->close();

    }

    public function buildJob($scheduler): \SugarBean|bool
    {
        $this->init();

        global $timedate, $current_user;

        $job = \BeanFactory::newBean('SchedulersJobs');
        $job->scheduler_id = $scheduler->id;
        $job->name = $scheduler->name ?? '';
        $job->execute_time = $timedate->nowDb();
        $job->target = $scheduler->job;

        $job->assigned_user_id = $current_user->id ?? '';
        $this->close();
        return $job;
    }

    protected function submitJob(\SugarBean|bool $job): void
    {
        $this->init();

        global $timedate;

        $job->id = create_guid();
        $job->new_with_id = true;
        $job->status = 'queued';
        $job->resolution = 'queued';

        if (empty($job->execute_time ?? '')) {
            $job->execute_time = $timedate->nowDb();
        }

        $job->save();

        $this->close();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getNextScheduler(bool $isLegacy): \SugarBean|bool|null
    {
        $this->init();

        global $timedate;

        $this->close();
        $tries = $this->jobTries;
        $cronId = $this->getCronId();

        $where = "WHERE execute_time <= :now AND status = 'queued' AND target LIKE '%scheduler::%'";

        if ($isLegacy) {
            $where = "WHERE execute_time <= :now AND status = 'queued' AND target NOT LIKE '%scheduler::%'";
        }

        $query = "SELECT id FROM job_queue $where ORDER BY date_entered ASC";


        while ($tries--){
            $result = $this->preparedStatementHandler->fetch($query, [
                'now' => $timedate->nowDb()
            ]);

            if (empty($result['id'])){
                return null;
            }

            $this->init();
            $job = \BeanFactory::getBean('SchedulersJobs');
            $job->retrieve($result['id']);

            if (empty($job->id)) {
                return null;
            }

            $job->status = 'running';
            $job->client = $cronId;

            $update = 'UPDATE job_queue SET status = :job_status, date_modified = :now, client = :client_id ';
            $update .= 'WHERE id = :job_id AND status = :status';
            $result = $this->preparedStatementHandler->update($update, [
                'job_status' => $job->status,
                'now' => $timedate->nowDb(),
                'client_id' => $cronId,
                'job_id' => $job->id,
                'status' => 'queued'
            ], [
                ['param' => 'job_status', 'type' => 'string'],
                ['param' => 'now', 'type' => 'string'],
                ['param' => 'client_id', 'type' => 'string'],
                ['param' => 'job_id', 'type' => 'string'],
                ['param' => 'status', 'type' => 'string']
            ]);

            if (empty($result)){
                continue;
            }

            $job->save();
            $this->close();

            break;
        }

        return $job;
    }


    protected function getCronId(): string
    {
        $this->init();

        global $sugar_config;

        $key = $sugar_config['unique_key'];

        $id = "CRON$key:" . getmypid();

        $this->close();

        return $id;
    }

    /**
     * @return array|null
     */
    public function getCronConfig(): ?array
    {
        return $this->systemConfigHandler->getSystemConfig('cron')?->getItems();
    }

    public function throttle(): bool
    {
        $minInterval = $this->getCronConfig()['min_cron_interval'];

        if ($minInterval === 0){
            return true;
        }
        $this->init();
        $lockfile = sugar_cached('modules/Schedulers/lastrun');

        create_cache_directory($lockfile);

        if (!file_exists($lockfile)) {
            $this->markLastRun($lockfile);
            return true;
        }

        $contents = file_get_contents($lockfile);
        $this->markLastRun($lockfile);

        $this->close();

        return time() - $contents >= $minInterval;
    }

    protected function markLastRun($lockfile = null): void
    {
        if (!file_put_contents($lockfile, time())){
            $this->logger->error('Scheduler cannot write PID file.  Please check permissions on ' . $lockfile);
        }
    }

    public function getLegacySchedulers(): ?array
    {
        $this->init();

        $schedulers = \BeanFactory::getBean('Schedulers')->get_full_list(
            '',
            "schedulers.status='Active' AND job NOT LIKE '%scheduler::%' AND NOT EXISTS(SELECT id FROM job_queue WHERE scheduler_id=schedulers.id AND status!='done')"
        );
        $this->close();

        return $schedulers;
    }

    public function createJob(\SugarBean $scheduler): void
    {
        $this->init();

        global $timedate;

        if (!$scheduler->fireQualified()) {
            $this->logger->debug('Scheduler did NOT find valid job (' . $scheduler->name . ') for time GMT (' . $timedate->now() . ')');
        }

        $job = $this->buildJob($scheduler);
        $this->submitJob($job);

        $this->close();
    }

    /**
     * @throws \DateMalformedStringException
     * @throws \Doctrine\DBAL\Exception
     */
    public function cleanup($isLegacy): void
    {
        $this->init();

        global $timedate, $app_strings;

        $date = $timedate->getNow()->modify("-$this->timeout seconds")->asDb();

        $this->close();

        $where = "WHERE status = 'running' AND target LIKE '%scheduler::%' AND date_modified <= :date";

        if ($isLegacy){
            $where = "WHERE status = 'running' AND target NOT LIKE '%scheduler::%' AND date_modified <= :date";
        }

        $query = "SELECT id from job_queue " . $where;

        $results = $this->preparedStatementHandler->fetchAll($query, ["date" => $date]);

        foreach ($results as $result) {
            $this->resolveJob($result, 'failure', $app_strings['ERR_TIMEOUT']);
        }
    }

    /**
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    public function clearHistoricJobs(bool $isLegacy): void
    {
        $this->processJobs($isLegacy, $this->successLifetime, true);
        $this->processJobs($isLegacy, $this->failureLifetime, false);
    }

    /**
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    protected function processJobs(bool $isLegacy, $days, bool $success): void
    {
        $this->init();

        global $timedate;
        $date = $timedate->getNow()->modify("-$days days")->asDb();

        $this->close();

        $resolution = "AND resolution = 'success'";
        $where = "WHERE status = 'done' AND date_modified <= :date AND target LIKE '%scheduler::%' ";

        if (!$success) {
            $resolution = "AND resolution != 'success'";
        }

        if ($isLegacy){
            $where = "WHERE status = 'done' AND date_modified <= :date AND target NOT LIKE '%scheduler::%' ";
        }

        $where = $where . $resolution;

        $query = 'SELECT id FROM job_queue ' . $where;

        $results = $this->preparedStatementHandler->fetchAll($query,  ["date" => $date]);

        foreach ($results as $result) {
            $this->deleteJob($result);
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function runLegacyJobs(): bool {

        $cutoff = time() + $this->maxRuntime;
        $passed = true;

        for ($count = 0; $count < $this->maxJobs; $count++) {

            $job = $this->getNextScheduler(true);

            if (empty($job)) {
                break;
            }

            $this->init();

            if (!$job->runJob()) {
                $passed = false;
                $this->jobFailed($job);
            }

            $this->close();

            if (time() >= $cutoff) {
                break;
            }
        }

        $this->maxJobs -= $count;

        return $passed;
    }

    protected function deleteJob(string $id): void
    {
        $this->init();

        $job = \BeanFactory::newBean('SchedulersJobs');

        if (empty($job)) {
            return;
        }

        $job->mark_deleted($id);

        $this->close();
    }

    protected function setCronConfig(): void
    {
        $cronConfig = $this->systemConfigHandler->getSystemConfig('cron')?->getItems() ?? [];

        $this->maxJobs = $cronConfig['max_cron_jobs'] ?? $this->maxJobs;
        $this->maxRuntime = $cronConfig['max_cron_runtime'] ?? $this->maxRuntime;
        $this->minInterval = $cronConfig['min_cron_interval'] ?? $this->minInterval;
    }

    protected function setJobsConfig(): void
    {
        $jobConfig = $this->systemConfigHandler->getSystemConfig('jobs')?->getItems() ?? [];

        $this->successLifetime = $jobConfig['success_lifetime'] ?? $this->successLifetime;
        $this->failureLifetime = $jobConfig['failure_lifetime'] ?? $this->failureLifetime;
        $this->timeout = $jobConfig['timeout'] ?? $this->timeout;
        $this->jobTries = $jobConfig['max_retries'] ?? $this->jobTries;
    }

    protected function jobFailed($job): void
    {
        $this->logger->error('Scheduler job failed: ' . $job->name);
    }
}
