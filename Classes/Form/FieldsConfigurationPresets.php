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

namespace Romm\SiteFactory\Form;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Romm\SiteFactory\Core\Core;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Class containing custom user functions for fields' TypoScript configuration.
 */
class FieldsConfigurationPresets
{

    /**
     * Gets an ordered list of the pages with the "Model site" flag set.
     *
     * @return    array    The model sites in an array. Empty array if no model was found.
     */
    public static function getModelSitesList()
    {
        $modelSitesPid = Core::getExtensionConfiguration('modelSitesPid');

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('pages');
        $query = $connection->createQueryBuilder();
        $query->getRestrictions()->removeAll();
        $query->addSelectLiteral('uid, title')
            ->from('pages')
            ->where('deleted=0 AND pid=' . $modelSitesPid);
        $aModelSites = $query->execute()->fetchAll();


        $orderedModelSites = [];

        if (is_array($aModelSites)) {
            foreach ($aModelSites as $modelSite) {
                $orderedModelSites[$modelSite['uid']] = $modelSite['title'] . ' (' . $modelSite['uid'] . ')';
            }
        }

        return $orderedModelSites;
    }

    /**
     * Gets an ordered list of the backend layouts.
     *
     * @return    array    The backend layouts in an array. Empty array if none was found.
     */
    public static function getBackendLayoutsList()
    {
        /** @var BackendLayoutView $backendLayoutView */
        $backendLayoutView = GeneralUtility::makeInstance(BackendLayoutView::class);

        $items = [];
        $params = [
            'table' => 'pages',
            'field' => 'backend_layout',
            'row'   => BackendUtility::getRecord('pages', 1, '*'), // @todo: manage "page" uid!
            'items' => &$items
        ];
        $backendLayoutView->addBackendLayoutItems($params);

        $result = [];
        foreach ($GLOBALS['TCA']['pages']['columns']['backend_layout']['config']['items'] as $item) {
            $result[$item[1]] = Core::translate($item[0]);
        }
        foreach ($params['items'] as $item) {
            $result[$item[1]] = $item[0];
        }

        return $result;
    }
}
