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

use TYPO3\CMS\Backend\Tree\TreeNode;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Romm\SiteFactory\Utility\PaetreeCommandsUtility;
use Romm\SiteFactory\Duplication\AbstractDuplicationProcess;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Class containing functions called when a site is being duplicated.
 * See function "run" for more information.
 */
class PagesDuplicationProcess extends AbstractDuplicationProcess
{

    /**
     * Will duplicate the model site's page and its sub-pages in the configured
     * destination.
     */
    public function run()
    {
        $modelPageUid = $this->getModelPageUid();
        if (!$modelPageUid) {
            return;
        }

        $copyDestination = intval($this->getDuplicationData('copyDestination'));

        // Testing if the values and $copyDestination is valid.

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('pages');
        $query = $connection->createQueryBuilder();
        $query->getRestrictions()->removeAll();
        $query->addSelectLiteral('uid')
            ->from('pages')
            ->where('deleted=0 AND uid=' . intval($copyDestination));
        $testCopyDestination = $query->execute()->fetchAll();

        if ($testCopyDestination === false) {
            $this->addError(
                'duplication_process.pages_duplication.error.wrong_destination_uid',
                1431372959,
                ['d' => $copyDestination]
            );

            return;
        }

        // Calling duplication process.
        $duplicatedPageUid = $this->copyNodeToDestination($modelPageUid, $copyDestination);
        $this->setDuplicationDataValue('duplicatedPageUid', $duplicatedPageUid);

        // Updating the new page's title with the given one.
        $siteTitle = $this->getField('siteTitle');
        if ($siteTitle) {
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('pages');
            $query = $connection->createQueryBuilder();
            $query->getRestrictions()->removeAll();
            $query->update('pages')
                ->where('uid=' . $duplicatedPageUid)
                ->set('title',  $siteTitle->getValue())
                ->execute();
        }
    }

    /**
     * Copies the source node directly as the first child of the destination
     * node and returns the created node.
     *
     * @param    integer $nodeUid        The node which will be duplicated.
     * @param    integer $destinationUid The uid of the new node's parent.
     * @return    integer    The uid of the new node's first page.
     */
    private function copyNodeToDestination($nodeUid, $destinationUid)
    {
        $beUserSave = $GLOBALS['BE_USER'];

        $GLOBALS['BE_USER']->uc['copyLevels'] = 100;
        $GLOBALS['BE_USER']->workspace = 0;

        $nodeData = new \stdClass();
        $nodeData->serializeClassName = TreeNode::class;
        $nodeData->id = $nodeUid;
        $nodeData->type = 'pages';

        /** @var TreeNode $node */
        $node = GeneralUtility::makeInstance(TreeNode::class, (array)$nodeData);

        $duplicatedPageUid = PaetreeCommandsUtility::copyNode($node, $destinationUid);

        $GLOBALS['BE_USER'] = $beUserSave;

        return $duplicatedPageUid;
    }
}
