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

namespace App\Schedulers\Command;

use App\Authentication\LegacyHandler\Authentication;
use App\Engine\LegacyHandler\DefaultLegacyHandler;
use App\Install\Command\BaseCommand;
use App\Schedulers\LegacyHandler\SchedulerHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'schedulers:run-all')]
class RunSchedulersCommand extends BaseCommand
{
    protected SchedulerHandler $schedulerHandler;
    protected Authentication $authentication;

    public function __construct(
        SchedulerHandler $schedulerHandler,
        Authentication $authentication,
        DefaultLegacyHandler $legacyHandler,
        ?string          $name = null
    )
    {

        $this->schedulerHandler = $schedulerHandler;
        $this->initSession = true;
        $this->authentication = $authentication;
        $this->legacyHandler = $legacyHandler;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run all Schedulers');
    }


    protected function executeCommand(InputInterface $input, OutputInterface $output, array $inputs): int
    {
        $this->authentication->initLegacySystemSession();

        $appStrings = $this->getAppStrings();

        $this->writeHeader($output, $appStrings['LBL_RUN_LEGACY_SCHEDULERS']);

        $passed = $this->schedulerHandler->runLegacySchedulers();
        $color = 'green';
        $label = $appStrings['LBL_LEGACY_SCHEDULERS_RUN_SUCCESSFULLY'];

        if (!$passed) {
            $color = 'red';
            $label = $appStrings['LBL_LEGACY_SCHEDULER_FAILED'];
        }

        $output->writeln([
            $this->colorText($color, $label),
            ''
        ]);

        $this->writeHeader($output, $appStrings['LBL_RUN_SCHEDULERS']);

        $this->runSchedulers($appStrings, $output);

        return 0;
    }


    /**
     * @param array|null $appStrings
     * @param OutputInterface $output
     * @return void
     */
    public function runSchedulers(?array $appStrings, OutputInterface $output): void
    {
        $results = $this->schedulerHandler->runSchedulers();
        $color = 'green';
        $label = $appStrings['LBL_PASSED'];

        foreach ($results as $result) {

            if ($result['result'] === false) {
                $label = $appStrings['LBL_NEW'];
                $color = 'red';
            }

            $output->writeln([
                $result['name'] . ' ' . $this->colorText($color, $label),
            ]);
        }
    }

    /**
     * @param $output
     * @param $header
     * @return string|null
     */
    protected function writeHeader($output, $header): ?string
    {
        return $output->writeln([
            '',
            $header,
            '=========================',
            ''
        ]);
    }


}
