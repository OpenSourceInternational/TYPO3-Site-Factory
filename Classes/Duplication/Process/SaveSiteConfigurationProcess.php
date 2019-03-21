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

use Romm\SiteFactory\Core\Core;
use Romm\SiteFactory\Domain\Model\Save;
use Romm\SiteFactory\Domain\Repository\SaveRepository;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Romm\SiteFactory\Duplication\AbstractDuplicationProcess;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;

/**
 * Class containing functions called when a site is being duplicated.
 * See function "run" for more information.
 */
class SaveSiteConfigurationProcess extends AbstractDuplicationProcess
{

    /**
     * When a site is being saved, this function will save all the fields values
     * in the DataBase, for further usage.
     */
    public function run()
    {
        $objectManager = Core::getObjectManager();

        /** @var SaveRepository $saveRepository */
        $saveRepository = $objectManager->get(SaveRepository::class);

        $saveObject = $saveRepository->findOneByRootPageUid($this->getDuplicatedPageUid());

        $newObject = false;
        if (empty($saveObject)) {
            $newObject = true;
            /** @var Save $saveObject */
            $saveObject = GeneralUtility::makeInstance(Save::class);
            $saveObject->setRootPageUid($this->getDuplicatedPageUid());
        }

        $configuration = $this->getDuplicationData();
        ArrayUtility::mergeRecursiveWithOverrule(
            $configuration,
            ['fieldsValues' => $this->getFieldsValues()]
        );
        $saveObject->setConfiguration(json_encode($configuration));

        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
        if ($newObject) {
            $saveRepository->add($saveObject);
        } else {
            $saveRepository->update($saveObject);
        }
        $persistenceManager->persistAll();

        // save site configuration in yaml
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        $duplicatedPageUid = $this->getDuplicatedPageUid();
        $modelPageUid = $this->getModelPageUid();

        $modelSite = $siteFinder->getSiteByRootPageId($modelPageUid);
        $modelSiteConfiguration = $modelSite->getConfiguration();

        try {
            $duplicatedSite = $siteFinder->getSiteByRootPageId($duplicatedPageUid);
            $duplicatedSiteConfiguration = $duplicatedSite->getConfiguration();
        } catch (SiteNotFoundException $e) {
            $duplicatedSiteConfiguration = $modelSiteConfiguration;
        }

        $duplicatedSiteConfiguration['rootPageId'] = $duplicatedPageUid;

        $siteConfigurationManager = GeneralUtility::makeInstance(
            SiteConfiguration::class,
            Environment::getConfigPath() . '/sites'
        );

        // error Handling pages association
        $pagesUidAssociation = $this->getDuplicationData('pagesUidAssociation');
        $errorHandling = $duplicatedSiteConfiguration['errorHandling'];
        foreach ($errorHandling as $index => $value) {
            if (isset($value['errorContentSource']) && isset($pagesUidAssociation[$value['errorContentSource']])) {
                $value['errorContentSource'] = $pagesUidAssociation[$value['errorContentSource']];
                $errorHandling[$index] = $value;
            }
        }
        $duplicatedSiteConfiguration['errorHandling'] = $errorHandling;

        $siteConfigurationManager->write($duplicatedPageUid, $duplicatedSiteConfiguration);

    }
}
