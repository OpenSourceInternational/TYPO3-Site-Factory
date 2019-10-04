<?php


namespace Romm\SiteFactory\Duplication\Process;

use Romm\SiteFactory\Duplication\AbstractDuplicationProcess;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\QueryGenerator;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class CreanExternalRelationsProcess
 * @package Romm\SiteFactory\Duplication\Process
 */
class CleanExternalRelationsProcess extends AbstractDuplicationProcess
{
    /**
     * Crean External MM Relations
     */
    public function run()
    {
        $modelPageUid = $this->getModelPageUid();
        $this->cleanExternalRelations($modelPageUid);
        $duplicatedPageUid = $this->getDuplicationData('duplicatedPageUid');
        if ($duplicatedPageUid) {
            $this->cleanExternalRelations($duplicatedPageUid);
        }
    }
    
    /**
     * @param $rootPageUid
     */
    protected function cleanExternalRelations($rootPageUid)
    {
        $settings = $this->getProcessSettings();
        $pageTree = $this->getPageTree((int)$rootPageUid);
        foreach ($settings['tables'] as $table => $relationSettings) {
            // select local records
            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($relationSettings['internal']['table']);
            $queryBuilder->getRestrictions()->removeAll();
            $internalRecords = $queryBuilder
                ->select('uid')
                ->from($relationSettings['internal']['table'])
                ->where(
                    $queryBuilder->expr()->in(
                        'pid',
                        $queryBuilder->createNamedParameter(
                            GeneralUtility::intExplode(',', $pageTree),
                            Connection::PARAM_INT_ARRAY
                        )
                    ))
                ->execute()
                ->fetchAll();
            if ($internalRecords) {
                $internalRecords = array_column($internalRecords, 'uid');
            }
            // select external records
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($relationSettings['internal']['table']);
            $queryBuilder->getRestrictions()->removeAll();
            $externalRecords = $queryBuilder
                ->select('uid')
                ->from($relationSettings['internal']['table'])
                ->where(
                    $queryBuilder->expr()->notIn(
                        'pid',
                        $queryBuilder->createNamedParameter(
                            GeneralUtility::intExplode(',', $pageTree),
                            Connection::PARAM_INT_ARRAY
                        )
                    ))
                ->execute()
                ->fetchAll();
            if ($externalRecords) {
                $externalRecords = array_column($externalRecords, 'uid');
            }
            if ($externalRecords && $internalRecords) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
                $queryBuilder->getRestrictions()->removeAll();
                $queryBuilder
                    ->delete($table)
                    ->where(
                        $queryBuilder->expr()->in(
                            $relationSettings['internal']['field'],
                            $queryBuilder->createNamedParameter(
                                $internalRecords,
                                Connection::PARAM_INT_ARRAY
                            )
                        ),
                        $queryBuilder->expr()->in(
                            $relationSettings['external']['field'],
                            $queryBuilder->createNamedParameter(
                                $externalRecords,
                                Connection::PARAM_INT_ARRAY
                            )
                        )
                    )->execute();
            }
            $this->addNotice(
                $rootPageUid . ' : ' . $table,
                1570193171
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
