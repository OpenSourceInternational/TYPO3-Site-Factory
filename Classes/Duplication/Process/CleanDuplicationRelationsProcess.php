<?php

namespace Romm\SiteFactory\Duplication\Process;

use Romm\SiteFactory\Duplication\AbstractDuplicationProcess;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\QueryGenerator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Class CleanDuplicationRelationsProcess
 * @package Romm\SiteFactory\Duplication\Process
 */
class CleanDuplicationRelationsProcess extends AbstractDuplicationProcess
{
    /**
     * Сlear duplicate recording information to avoid duplicate copying.
     */
    public function run()
    {
        $modelPageUid = $this->getModelPageUid();
        $this->cleanDuplicationRelations($modelPageUid);
        $duplicatedPageUid = $this->getDuplicationData('duplicatedPageUid');
        if ($duplicatedPageUid) {
            $this->cleanDuplicationRelations($duplicatedPageUid);
        }
    }
    
    /**
     * @param $rootPageUid
     */
    protected function cleanDuplicationRelations($rootPageUid)
    {
        $settings = $this->getProcessSettings();
        $pageTree = $this->getPageTree((int)$rootPageUid);
        foreach ($settings['tables'] as $table => $columns) {
            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $queryBuilder->getRestrictions()->removeAll();
            foreach ($columns as $column) {
                try {
                    $queryBuilder
                        ->update($table)
                        ->where(
                            $queryBuilder->expr()->in(
                                'pid',
                                $queryBuilder->createNamedParameter(
                                    GeneralUtility::intExplode(',', $pageTree),
                                    Connection::PARAM_INT_ARRAY
                                )
                            )
                        )
                        ->set($column, 0)
                        ->execute();
                
                } catch (\Exception $exception) {
                    // column not exists, do nothing
                }
            }
            $this->addNotice(
                $rootPageUid . ' : ' . $table,
                1570184531
            );
        }
    }
    
    /**
     * @param int $parent
     * @param int $depth
     * @return string
     */
    protected function getPageTree(int $parent = 0, $depth = 250): string
    {
        /** @var QueryGenerator $queryGenerator */
        $queryGenerator = GeneralUtility::makeInstance(QueryGenerator::class);
        $childPids = $queryGenerator->getTreeList($parent, $depth, 0, 1);
        return $childPids;
    }
}
