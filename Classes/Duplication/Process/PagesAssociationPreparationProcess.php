<?php

namespace Romm\SiteFactory\Duplication\Process;

use Romm\SiteFactory\Duplication\AbstractDuplicationProcess;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class PagesAssociationPreparationProcess
 * @package Romm\SiteFactory\Duplication\Process
 */
class PagesAssociationPreparationProcess extends AbstractDuplicationProcess
{
    /**
     * update site_factory_association_parent for all pages on model
     */
    public function run()
    {
        $modelPageUid = $this->getModelPageUid();
        $this->preparePagesAssociationRecursive($modelPageUid);
    }
    
    /**
     * @param $parentUid
     */
    protected function  preparePagesAssociationRecursive($parentUid)
    {
        // update site_factory_association_parent
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('pages');
        $query = $connection->createQueryBuilder();
        $query->getRestrictions()->removeAll();
        $query->update('pages')
            ->where('uid=' . $parentUid)
            ->set('site_factory_association_parent',  $parentUid)
            ->execute();
    
        // find children
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('pages');
        $query = $connection->createQueryBuilder();
        $query->getRestrictions()->removeAll();
        $query->addSelectLiteral('uid')
            ->from('pages')
            ->where( 'deleted=0 AND pid=' . $parentUid)
            ->orderBy('sorting');
        $children = $query->execute()->fetchAll();
        
        foreach ($children as $child) {
            $this->preparePagesAssociationRecursive($child['uid']);
        }
    }
}
