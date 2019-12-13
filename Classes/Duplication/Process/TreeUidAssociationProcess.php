<?php
/*
 * 2016 Romain CANON <romain.hydrocanon@gmail.com>
 *
 * This file is part of the TYPO3 Site Factory project.
 * It is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License, either
 * version 3 of the License, or any later version.
 *
 * For the full copyright and license information, see:
 * http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Romm\SiteFactory\Duplication\Process;

use Romm\SiteFactory\Duplication\AbstractDuplicationProcess;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class containing functions called when a site is being duplicated.
 * See function "run" for more information.
 */
class TreeUidAssociationProcess extends AbstractDuplicationProcess
{
    /**
     * After a node duplication, by knowing the source node's uid, and the
     * duplicated node's uid, this function will be able to associate every old
     * page of the node's tree with the new duplicated node's uid.
     */
    public function run()
    {
        $modelPageUid = $this->getModelPageUid();
        if (!$modelPageUid) {
            return;
        }
        $duplicatedPageUid = $this->getDuplicatedPageUid();
        if (!$duplicatedPageUid) {
            return;
        }

        // Processing the pages uid association.
        $treeUidAssociation = $this->getTreeUidAssociationRecursive($modelPageUid, $duplicatedPageUid);

        $this->addNotice(
            'duplication_process.pages_association.notice.amount_pages',
            1431985778,
            ['d' => count($treeUidAssociation)]
        );

        $this->setDuplicationDataValue('pagesUidAssociation', $treeUidAssociation);
    }

    /**
     * Recursive function for processing the function "getTreeUidAssociation".
     * Will check the sub pages of the old and new page.
     *
     * @param    integer $oldUid The uid of the model page.
     * @param    integer $newUid The uid of the duplicated page.
     * @return    array    An array containing association between the pages.
     */
    private function getTreeUidAssociationRecursive($oldUid, $newUid)
    {
        $uidAssociation = [$oldUid => $newUid];
        
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('pages');
        $query = $connection->createQueryBuilder();
        $query->getRestrictions()->removeAll();
        $query->getRestrictions()->removeAll();
        $query->addSelectLiteral('uid', 'site_factory_association_parent')
            ->from('pages')
            ->where( 'deleted=0 AND pid=' . $newUid)
            ->orderBy('sorting');
        $newChildren = $query->execute()->fetchAll();
        
        foreach ($newChildren as $newChild) {
            $childrenAssociation = $this->getTreeUidAssociationRecursive($newChild['site_factory_association_parent'] , $newChild['uid']);
            foreach ($childrenAssociation as $childrenAssociationOldUid => $childrenAssociationNewUid) {
                $uidAssociation[$childrenAssociationOldUid] = $childrenAssociationNewUid;
            }
        }

        return $uidAssociation;
    }
}
