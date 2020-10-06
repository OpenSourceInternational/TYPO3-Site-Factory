<?php
declare(strict_types=1);

namespace Romm\SiteFactory\Duplication\Raw;

use Stratis\StratisServices\Service\QueryBuilderService;

/**
 * Class PageRawDuplicator
 *
 * @author Vladimir Cherednichenko <vovacherednichenko@o-s-i.org>
 */
class PageRawDuplicator
{
    /**
     * @var QueryBuilderService
     */
    protected $qbService;

    /**
     * @var array
     */
    protected $tables = [];

    /**
     * @var array
     */
    protected $idsRelations = [];

    /**
     * @var array
     */
    protected $tablesToSkip = [
        'pages',
        'sys_file',
        'sys_filemounts',
        'sys_file_metadata',
        'sys_file_processedfile',
        'sys_file_storage',
        'sys_history',
        'sys_log',
    ];

    /**
     * @var array
     */
    protected $mmTablesToUpdate = [];

    /**
     * @var array
     */
    protected $fieldRelationsToUpdate = [];

    /**
     * @var ContentElementRawUpdater
     */
    protected $contentElementRawUpdater;

    /**
     * @var array
     */
    protected $stats = [
        'copied' => [],
        'created' => [],
        'updated' => [],
    ];

    /**
     * PageRawDuplicator constructor.
     *
     * @param QueryBuilderService      $qbService
     * @param ContentElementRawUpdater $contentElementRawUpdater
     */
    public function __construct(QueryBuilderService $qbService, ContentElementRawUpdater $contentElementRawUpdater)
    {
        $this->qbService = $qbService;
        $this->contentElementRawUpdater = $contentElementRawUpdater;

        $this->tables = array_keys($GLOBALS['TCA']);
        $this->buildRelationUpdateConfig();
    }

    protected function buildRelationUpdateConfig()
    {
        foreach ($GLOBALS['TCA'] as $table => $config) {
            foreach ($config['columns'] as $field => $fieldConfig) {
                // "Many to many" relations
                if (array_key_exists('MM', $fieldConfig['config'])) {
                    if (!$fieldConfig['config']['foreign_table']) {
                        continue;
                    }

                    $this->mmTablesToUpdate[$fieldConfig['config']['MM']][$table] = [
                        'foreign_table' => $fieldConfig['config']['foreign_table'],
                        'match_fields' => $fieldConfig['config']['MM_match_fields'] ?? []
                    ];

                    continue;
                }

                // "One to many" or "Many to one" relations
                if (array_key_exists('foreign_table', $fieldConfig['config'])) {
                    $matchFields = $fieldConfig['config']['foreign_match_fields'] ?? [];

                    if (array_key_exists('foreign_table_field', $fieldConfig['config'])) {
                        $matchFields[$fieldConfig['config']['foreign_table_field']] = $table;
                    }

                    $this->fieldRelationsToUpdate[$table][$field] = [
                        'foreign_table' => $fieldConfig['config']['foreign_table'],
                        'foreign_field' => $fieldConfig['config']['foreign_field'],
                        'match_fields' => $matchFields,
                    ];
                }
            }
        }
    }

    /**
     * @param int $sourceUid
     * @param int $destinationPid
     *
     * @return int
     */
    public function copy(int $sourceUid, int $destinationPid)
    {
        $pageUid = $this->copyPageTree($sourceUid, $destinationPid);

        $this->copyMMRelations();
        $this->copyFieldRelations();
        $this->contentElementRawUpdater->update($pageUid, $this->idsRelations);

        return $pageUid;
    }

    /**
     * @param $sourceUid
     * @param $destinationPid
     *
     * @return int
     */
    protected function copyPageTree(int $sourceUid, int $destinationPid): int
    {
        $pageUid = $this->copyPageRecord($sourceUid, $destinationPid);

        $this->copyPageChildes($sourceUid, $pageUid);
        $this->copyRecordsListForPage($sourceUid, $pageUid);

        return $pageUid;
    }

    /**
     * @param int $sourceUid
     * @param int $destUid
     */
    protected function copyPageChildes(int $sourceUid, int $destUid)
    {
        $childes = $this->qbService->setTable('pages')
            ->fetchAllByParams(['pid' => $sourceUid, 'deleted' => 0]);

        foreach ($childes as $child) {
            $this->copyPageTree($child['uid'], $destUid);
        }
    }

    /**
     * @param int $sourceUid
     * @param int $destinationPid
     *
     * @return int
     */
    protected function copyPageRecord(int $sourceUid, int $destinationPid): int
    {
        $page = $this->qbService->setTable('pages')
            ->fetchOneByParams(['uid' => $sourceUid]);

        // TODO: clear possible sensitive data in pages
        $page['pid'] = $destinationPid;
        unset($page['uid']);

        $this->qbService->create($page);
        $this->stats['copied']['pages']++;

        $newUid = $this->qbService->lastInsertUid();
        $this->idsRelations['pages'][$sourceUid] = $newUid;

        return $newUid;
    }

    /**
     * @param string $table
     * @param int    $sourceUid
     * @param int    $destUid
     */
    protected function copyRecordsListFromSpecificTable(string $table, int $sourceUid, int $destUid)
    {
        $params = ['pid' => $sourceUid];

        if ($GLOBALS['TCA'][$table]['ctrl']['delete']) {
            $params[$GLOBALS['TCA'][$table]['ctrl']['delete']] = 0;
        }

        $records = $this->qbService->setTable($table)
            ->fetchAllByParams($params);

        foreach ($records as $record) {
            // TODO: clear possible sensitive data
            $record['pid'] = $destUid;

            $srcRecordUid = $record['uid'];
            unset($record['uid']);

            $this->qbService->create($record);
            $this->stats['copied'][$table]++;
            $newRecordUid = $this->qbService->lastInsertUid();

            // This will be used in content processing and relations updates
            $this->idsRelations[$table][$srcRecordUid] = $newRecordUid;
        }
    }

    /**
     * @param int $sourceUid
     * @param int $destUid
     */
    protected function copyRecordsListForPage(int $sourceUid, int $destUid)
    {
        foreach ($this->tables as $table) {
            if (in_array($table, $this->tablesToSkip)) {
                continue;
            }

            $this->copyRecordsListFromSpecificTable($table, $sourceUid, $destUid);
        }
    }

    protected function copyMMRelations()
    {
        foreach ($this->mmTablesToUpdate as $mmTable => $tables) {
            foreach ($tables as $table => $config) {
                if (!array_key_exists($table, $this->idsRelations)) {
                    continue;
                }

                $sourceIds = array_keys($this->idsRelations[$table] ?? []);
                $foreignTable = $config['foreign_table'];
                $rows = $this->qbService->setTable($mmTable)
                    ->fetchAllByParams(array_merge(['uid_foreign' => $sourceIds], $config['match_fields']));

                foreach ($rows as $row) {
                    unset($row['uid']);
                    $row['uid_foreign'] = $this->idsRelations[$table][$row['uid_foreign']];
                    $row['uid_local'] = $this->idsRelations[$foreignTable][$row['uid_local']] ?? $row['uid_local'];

                    // TODO: possible performance improvement - join inserts for one table
                    $this->qbService->setTable($mmTable)->create($row);
                    $this->stats['created'][$mmTable]++;
                }
            }
        }
    }

    protected function copyFieldRelations()
    {
        foreach ($this->fieldRelationsToUpdate as $table => $fields) {
            // skip tables processing what have not been updated
            if (!array_key_exists($table, $this->idsRelations)) {
                continue;
            }

            $newRecordIds = array_values($this->idsRelations[$table]);

            // Fetch all migrated rows for the table
            $rows = $this->qbService->setTable($table)
                ->select('uid,' . implode(',', array_keys($fields)))
                ->fetchAllByParams(['uid' => $newRecordIds]);

            $this->copyForeignFieldRelation($table, $fields);
            $this->copyLocalFieldRelation($fields, $table, $rows);
        }
    }

    /**
     * @param array  $fields
     * @param string $table
     * @param array  $rows
     */
    protected function copyLocalFieldRelation(array $fields, string $table, array $rows)
    {
        foreach ($rows as $row) {
            $newValues = [];
            $updateRequired = false;

            foreach ($fields as $field => $config) {
                $foreignTable = $config['foreign_table'];

                if ($config['foreign_field'] || !array_key_exists($foreignTable, $this->idsRelations)) {
                    continue;
                }

                $ids = explode(',', (string) ($row[$field] ?? ''));
                $resultIds = [];

                foreach ($ids as $id) {
                    $id = (int) $id;

                    if (array_key_exists($id, $this->idsRelations[$foreignTable])) {
                        $updateRequired = true;
                        $resultIds[] = $this->idsRelations[$foreignTable][$id];
                        continue;
                    }

                    $resultIds[] = $id;
                }

                $newValues[$field] = implode(',', $resultIds);
            }

            if ($updateRequired) {
                $this->qbService->setTable($table)
                    ->updateFromArray($row['uid'], $newValues);

                $this->stats['updated'][$table]++;
            }
        }
    }

    /**
     * @param string $table
     * @param array  $fields
     */
    protected function copyForeignFieldRelation(string $table, array $fields)
    {
        foreach ($fields as $field => $config) {
            // Relation stored on foreign side - "One to many" relation
            if (!$config['foreign_field']) {
                continue;
            }

            $foreignTable = $config['foreign_table'];
            $newForeignRecordIds = array_values($this->idsRelations[$foreignTable] ?? []);

            // Skip if there was not copied any record from foreign table
            if (empty($newForeignRecordIds)) {
                return;
            }

            // We need to update field value on foreign side
            // Select records from foreign table what was copied and have old values in foreign field
            $foreignRows = $this->qbService->setTable($foreignTable)
                ->select('uid,' . $config['foreign_field'])
                ->fetchAllByParams(array_merge([
                    'uid' => $newForeignRecordIds
                ], $config['match_fields']));

            foreach ($foreignRows as $foreignRow) {
                $oldValue = $foreignRow[$config['foreign_field']];

                if (!array_key_exists($oldValue, $this->idsRelations[$table])) {
                    continue;
                }

                $this->qbService->setTable($foreignTable)
                    ->updateFromArray($foreignRow['uid'], [
                        $config['foreign_field'] => $this->idsRelations[$table][$oldValue]
                    ]);

                $this->stats['updated_foreign'][$foreignTable]++;
            }
        }
    }
}
