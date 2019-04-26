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

namespace Romm\SiteFactory\Controller;

use Romm\SiteFactory\Domain\Model\Pages;
use Romm\SiteFactory\Domain\Model\Save;
use Romm\SiteFactory\Domain\Repository\PagesRepository;
use Romm\SiteFactory\Domain\Repository\SaveRepository;
use Romm\SiteFactory\Form\Fields\AbstractField;
use Romm\SiteFactory\Core\CacheManager;
use Romm\SiteFactory\Core\Core;
use Romm\SiteFactory\Duplication\AbstractDuplicationProcess;
use Romm\SiteFactory\Form\Fields\Field;
use Romm\SiteFactory\Form\FieldsConfigurationPresets;
use Romm\SiteFactory\Utility\ConstantManagerUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;

/**
 * Administration controller. Manages the following actions :
 *
 * - Index:
 *        The homepage of the module, introducing the extension and giving some
 *        information about it.
 * - New:
 *        The page containing a form which allows someone to create a new site.
 * - processCopy:
 *        The page where you go after submitting the "New" form. It will manage
 *        all the duplication processes (pages duplication, constants assignation,
 *        etc.).
 * - saveSiteConfiguration:
 *        When a site is created/modified, the information of the form is encoded
 *        and stored in database for future usage.
 */
class AdministrationController extends AbstractController
{

    /**
     * @var SaveRepository
     */
    protected $saveRepository = null;

    /**
     * @var PagesRepository
     */
    protected $pagesRepository = null;

    /**
     * @var array $fieldsConfiguration
     */
    public $fieldsConfiguration = null;

    /**
     * @param SaveRepository $saveRepository
     */
    public function injectSaveRepository(SaveRepository $saveRepository)
    {
        $this->saveRepository = $saveRepository;
    }

    /**
     * @param PagesRepository $pagesRepository
     */
    public function injectPagesRepository(PagesRepository $pagesRepository)
    {
        $this->pagesRepository = $pagesRepository;
    }

    /**
     * Homepage of the module.
     *
     * Displays a list of the model sites which can be duplicated, and a list of
     * the already duplicated sites with their custom values.
     */
    public function indexAction()
    {
        // Models sites which can be duplicated.
        $this->view->assign('modelSitesList', FieldsConfigurationPresets::getModelSitesList());

        // Managing the already duplicated sites list.
        /** @var Save[] $savedSites */
        $savedSitessPid = Core::getExtensionConfiguration('copyDestination');
        $defaultQuerySettings = Core::getObjectManager()->get(QuerySettingsInterface::class);
        $defaultQuerySettings->setRespectStoragePage(true);
        $defaultQuerySettings->setStoragePageIds([$savedSitessPid]);
        $this->saveRepository->setDefaultQuerySettings($defaultQuerySettings);
        $savedSites = $this->saveRepository->findAll();
        // Adding root page of the site in the configuration.
        /** @var QuerySettingsInterface $defaultQuerySettings */
        $defaultQuerySettings = Core::getObjectManager()->get(QuerySettingsInterface::class);
        $defaultQuerySettings->setRespectStoragePage(false);
        $defaultQuerySettings->setIgnoreEnableFields(true);

        $finalSavedSites = [];
        foreach ($savedSites as $key => $site) {

            $this->pagesRepository->setDefaultQuerySettings($defaultQuerySettings);

            /** @var Pages $page */
            $page = $this->pagesRepository->findByUid($site->getRootPageUid());

            // The page may have been deleted.
            if (!$page) {
                continue;
            }

            $siteConfiguration['page'] = $page;
            $siteConfiguration['fields'] = $this->fillFieldsValuesFromSavedSite($site);

            $site->setConfiguration($siteConfiguration);
            $finalSavedSites[] = $site;
        }

        $this->view->assign('savedSites', $finalSavedSites);
    }

    /**
     * Get the values of a site that has already been duplicated.
     * Useful when you want to list a already duplicated  site's properties, or
     * when you are editing a site.
     *
     * @param Save $site                   The saved site.
     * @param bool $onlyModificationFields True if you want only the fields that are accessible when editing, false otherwise.
     * @return AbstractField[]
     * @throws \Exception
     */
    private function fillFieldsValuesFromSavedSite(Save $site, $onlyModificationFields = true)
    {
        $siteConfiguration = $site->getConfiguration();

        // Settings fields configuration for the site.
        $fields = Field::getFields($site->getRootPageUid(), $onlyModificationFields);

        if (isset($siteConfiguration['fieldsValues']) && is_array($siteConfiguration['fieldsValues'])) {
            foreach ($siteConfiguration['fieldsValues'] as $fieldName => $fieldValue) {
                if (array_key_exists($fieldName, $fields)) {
                    $fields[$fieldName]->setValue($fieldValue);
                }
            }
        }

        $constantsValues = ConstantManagerUtility::getTemplateConstantsValues($site->getRootPageUid(), array_keys($fields));
        foreach ($constantsValues as $fieldName => $fieldValue) {
            $fields[$fieldName]->setValue($fieldValue);
        }

        return $fields;
    }

    /**
     * Contains the main form for the creation of a new site.
     *
     * If the form has been submitted, fields' values will be checked so they
     * must match their rules declared in the main configuration. If no error
     * occurs, the site duplication process will start.
     *
     * If the argument "modifySite" is sent, the site has already been
     * duplicated and saved, and the form will be filled with the already saved
     * configuration of this site.
     *
     * @throws \Exception
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function newAction()
    {
        // Models sites which can be duplicated.
        $modelSites = FieldsConfigurationPresets::getModelSitesList();
        if (empty($modelSites)) {
            $this->view->assign('noModelSite', true);
        } else {
            // Getting the selected model site. If none has been submitted, the default one (first one in the list) is used.
            $selectedModelSite = ($this->request->hasArgument('fields') && isset($this->request->getArgument('fields')['modelSite'])) ?
                $this->request->getArgument('fields')['modelSite'] :
                key($modelSites);

            if (!$selectedModelSite) {
                throw new \Exception('Fatal error: the module should have a model site value.', 1423830741);
            }

            if (!Core::checkUidIsModelSite($selectedModelSite)) {
                throw new \Exception('Fatal error: the model site "' . $selectedModelSite . '" is not a correct value.', 1423830834);
            }

            $checkHideInSiteModification = false;

            // Checking if a site is being edited, and if the uid of this site is correct.
            $modifySite = null;
            if ($this->request->hasArgument('modifySite')) {
                $modifySite = intval($this->request->getArgument('modifySite'));
                Core::checkUidIsSavedSite($modifySite);
                $checkHideInSiteModification = true;
            }

            if ($modifySite !== null) {
                $pageUid = $this->request->getArgument('modifySite');
                $this->view->assign('modifySite', $pageUid);

                /** @var Save $savedSite */
                $savedSite = $this->saveRepository->findOneByRootPageUid($pageUid);
                $fields = $this->fillFieldsValuesFromSavedSite($savedSite, false);
            } else // Getting the fields for the selected model site.
            {
                $fields = Field::getFields($selectedModelSite, $checkHideInSiteModification);
            }

            // The form has been submitted, the site might be duplicated.
            if ($this->request->hasArgument('submitted')) {
                $this->processFormSubmit($fields);
            } else {
                $this->view->assign('refreshForm', true);

                if ($modifySite === null) {
                    $constantsValues = ConstantManagerUtility::getTemplateConstantsValues($selectedModelSite, array_keys($fields));
                    foreach ($constantsValues as $fieldName => $fieldValue) {
                        $fields[$fieldName]->setValue($fieldValue);
                    }
                }
            }

            // Creating a unique id for the generated form.
            $this->view->assign('formId', 'SiteFactoryForm_' . uniqid());

            $this->view->assign('fieldsConfiguration', $fields);
        }
    }

    /**
     *
     * Process fields checks.
     *
     * If errors occur, information will be assigned to the view. If all fields
     * are correctly filled, a redirection is sent to "processCopyAction".
     *
     * @param $fields
     * @throws \Exception
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function processFormSubmit($fields)
    {
        $errorsCounter = 0;
        $requestFields = $this->request->getArgument('fields');

        foreach ($fields as $fieldName => $field) {
            // Check if a field has been submitted.
            if (array_key_exists($fieldName, $requestFields)) {
                $field->setValue($requestFields[$fieldName]);

                // Validating the field.
                $validationResult = $field->validate()->getMergedValidationResult();

                if (!empty($validationResult)) {
                    if ($validationResult->hasErrors()) {
                        $errorsCounter += count($validationResult->getErrors());
                    }
                }
            }
        }

        $changeModelSiteId = false;
        if ($this->request->hasArgument('changeModelSiteId')) {
            $changeModelSiteId = (bool)$this->request->getArgument('changeModelSiteId');
        }

        // No error occurred: the site duplication can start.
        if ($errorsCounter == 0 && !$changeModelSiteId) {
            $redirectParameters = [];
            $duplicationData = [];

            if ($this->request->hasArgument('modifySite')) {
                if (Core::checkUidIsSavedSite($this->request->getArgument('modifySite'))) {
                    $redirectParameters['modifySite'] = $this->request->getArgument('modifySite');

                    // We get back the last saved configuration. It prevents missing values as some fields may not be in the modification form (e.g. the "model site").
                    /** @var Save $savedSite */
                    $savedSite = $this->saveRepository->findOneByRootPageUid($redirectParameters['modifySite']);
                    $duplicationData = $savedSite->getConfiguration();
                }
            }

            // Saving configuration in a cache file.
            $fieldsValues = [];
            foreach ($fields as $field) {
                $fieldsValues[$field->getName()] = $field->getValue();
            }

            $dataFileCacheToken = md5(uniqid(rand(), true));
            $dataFileValue = json_encode([
                'fieldsValues'    => $fieldsValues,
                'duplicationData' => $duplicationData
            ]);

            $cache = CacheManager::getCacheInstance(CacheManager::CACHE_PROCESSED);
            $cache->set(
                $dataFileCacheToken,
                $dataFileValue,
                [],
                60 * 60 * 24 // 1 day.
            );
            $redirectParameters['duplicationToken'] = $dataFileCacheToken;

            $this->redirect('processCopy', null, null, $redirectParameters);
        } else {
            $this->view->assign('errorsCounter', $errorsCounter);
        }
    }

    /**
     * This action is called when a form has been submitted to create a new
     * site.
     *
     * It will get all the information needed to duplicate the model site, and
     * further: management of uploaded files, constants management, etc.
     *
     * @throws \Exception
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception\InvalidNumberOfConstraintsException
     */
    public function processCopyAction()
    {
        $cacheToken = $this->request->getArgument('duplicationToken');

        $cache = CacheManager::getCacheInstance(CacheManager::CACHE_PROCESSED);
        $cacheData = $cache->get($cacheToken);
        // @todo: manage wrong token or wrong cacheData
        $cacheData = json_decode($cacheData, true);

        // Check if the process is a modification of an already duplicated site.
        $modifySite = null;
        if ($this->request->hasArgument('modifySite') && Core::checkUidIsSavedSite($this->request->getArgument('modifySite'))) {
            $modifySite = $this->request->getArgument('modifySite');

            $cacheData['duplicationData']['modelPageUid'] = $cacheData['fieldsValues']['modelSite'];
            $cacheData['duplicationData']['modifySite'] = $modifySite;
            $cacheData['duplicationData']['duplicatedPageUid'] = $modifySite;

            /** @var Save $savedSite */
            $savedSite = $this->saveRepository->findLastByRootPageUid($modifySite);
            $cacheData['savedSite'] = $savedSite->getConfiguration();
        } else {
            $cacheData['duplicationData']['modelPageUid'] = $cacheData['fieldsValues']['modelSite'];
            $cacheData['duplicationData']['copyDestination'] = Core::getExtensionConfiguration('copyDestination');
        }

        // Saving modified data in cache.
        $cache->set($cacheToken, json_encode($cacheData));

        $this->view->assign('duplicationToken', $cacheToken);

        $siteModificationToken = ($modifySite) ? true : false;
        $duplicationConfiguration = AbstractDuplicationProcess::getCleanedDuplicationConfiguration($cacheData['duplicationData']['modelPageUid'], $siteModificationToken);
        $this->view->assign('duplicationConfiguration', $duplicationConfiguration);

        $this->view->assign('duplicationConfigurationJSON', addslashes(json_encode(array_keys($duplicationConfiguration))));
    }

    public function helpAction()
    {
    }
}
