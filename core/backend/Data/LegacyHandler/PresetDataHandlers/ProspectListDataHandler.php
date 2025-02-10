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


namespace App\Data\LegacyHandler\PresetDataHandlers;

use ApiBeanMapper;
use App\Data\LegacyHandler\ListData;
use App\Data\LegacyHandler\PresetListDataHandlerInterface;
use App\Data\LegacyHandler\RecordMapper;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\Module\Service\ModuleNameMapperInterface;
use BeanFactory;
use Symfony\Component\HttpFoundation\RequestStack;

class ProspectListDataHandler extends LegacyHandler implements PresetListDataHandlerInterface
{
    public const HANDLER_KEY = 'prospectlists-data-handler';

    protected ModuleNameMapperInterface $moduleNameMapper;
    protected RecordMapper $recordMapper;

    /**
     * @param string $projectDir
     * @param string $legacyDir
     * @param string $legacySessionName
     * @param string $defaultSessionName
     * @param LegacyScopeState $legacyScopeState
     * @param RequestStack $requestStack
     * @param ModuleNameMapperInterface $moduleNameMapper
     * @param RecordMapper $recordMapper
     */
    public function __construct(
        string $projectDir,
        string $legacyDir,
        string $legacySessionName,
        string $defaultSessionName,
        LegacyScopeState $legacyScopeState,
        RequestStack $requestStack,
        ModuleNameMapperInterface $moduleNameMapper,
        RecordMapper $recordMapper,
    )
    {
        parent::__construct($projectDir, $legacyDir, $legacySessionName, $defaultSessionName, $legacyScopeState, $requestStack);
        $this->moduleNameMapper = $moduleNameMapper;
        $this->recordMapper = $recordMapper;
    }

    /**
     * @return string
     */
    public function getHandlerKey(): string
    {
        return self::HANDLER_KEY;
    }

    /**
     * @param string $module
     * @param array $criteria
     * @param int $offset
     * @param int $limit
     * @param array $sort
     * @return ListData
     */
    public function fetch(string $module, array $criteria = [], int $offset = -1, int $limit = -1, array $sort = []): ListData
    {

        $params = $criteria['preset']['params'];

        $parentId = $params['id'];
        $parentField = $params['parent_field'];
        $parentModule = $params['parent_module'];

        $this->init();

        $parentRecord = BeanFactory::getBean($parentModule, $parentId);

        $parentFieldDef = $parentRecord->field_defs[$parentField];

        $link = $parentFieldDef['link'];
        $parentRecord->load_relationship($link);

        $beans = $parentRecord->$link->getBeans();

        $records =  $this->mapBeans($beans);

        return $this->buildListData($records);
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return 'prospectlists';
    }

    /**
     * @param array $resultData
     * @return ListData
     */
    protected function buildListData(array $records): ListData
    {
        $listData = new ListData();
        $listData->setOffsets([]);
        $listData->setOrdering([]);
        $listData->setRecords($this->recordMapper->mapRecords($records));

        return $listData;
    }

    /**
     * @param $beans
     * @return array
     */
    protected function mapBeans($beans): array
    {
        $records = [];
        require_once 'include/portability/ApiBeanMapper/ApiBeanMapper.php';
        $mapper = new ApiBeanMapper();
        foreach ($beans as $bean) {
            $records[] = $mapper->toApi($bean);
        }
        return $records;
    }
}
