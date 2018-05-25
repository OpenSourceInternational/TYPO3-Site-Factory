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

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Error\Error;
use Romm\SiteFactory\Core\CacheManager;
use Romm\SiteFactory\Core\Core;
use Romm\SiteFactory\Duplication\AbstractDuplicationProcess;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Controller managing the duplication of sites.
 */
class DuplicationController extends AbstractController
{

    /**
     * Ajax implementation of the function "processDuplication". Will display
     * the result in JSON.
     *
     * See "processDuplication" function for more details.
     *
     * @return bool
     */
    public function ajaxProcessDuplicationAction($cacheToken = null, $index = null)
    {
        $result = [];
        if($this->request){
            // @todo: check if token is valid
            if($this->request->getArgument('duplicationToken')){
                $cacheToken = $this->request->getArgument('duplicationToken');
            }


            // @todo: check if index is valid
            if($this->request->getArgument('index')){
                $index = $this->request->getArgument('index');
            }
        }
        $result = $this->processDuplication($cacheToken, $index, true);

        $this->view = null;

        return json_encode($result);
    }

    /**
     * @todo: rewrite function doc
     * @param string $cacheToken The token of the cache file to get the current state of the duplication.
     * @param string $index      The index of the process which will be executed (e.g. "pagesDuplication" or "treeUidAssociation").
     * @param bool   $checkAjax  If true, will call the function "checkAjaxCall" of the current process class.
     * @return    array The result of the function, may contain these keys :
     *                           - "success":      "False" if error(s) occurred, "true" otherwise.
     *                           - "result":       The result of the execution function. Contains useful data for further duplication process steps.
     *                           - "errorMessage": If error(s) occurred, will contain an error message. If the current user is admin, it will get a detailed message.
     */
    private function processDuplication($cacheToken, $index, $checkAjax = false)
    {
        // Getting configuration in cache file.
        $cache = CacheManager::getCacheInstance(CacheManager::CACHE_PROCESSED);
        $cacheData = $cache->get($cacheToken);
        $cacheData = json_decode($cacheData, true);

        /** @var Result $result */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $result = $objectManager->get(Result::class);

        try {
            if (isset($cacheData['duplicationData']['modelPageUid']) && MathUtility::canBeInterpretedAsInteger($cacheData['duplicationData']['modelPageUid']) && $cacheData['duplicationData']['modelPageUid'] > 0) {
                $duplicationConfiguration = AbstractDuplicationProcess::getCleanedDuplicationConfiguration($cacheData['duplicationData']['modelPageUid']);

                if (isset($duplicationConfiguration[$index])) {
                    if (isset($duplicationConfiguration[$index]['class'])) {
                        $class = $duplicationConfiguration[$index]['class'];
                        $settings = (array_key_exists('settings', $duplicationConfiguration[$index]))
                            ? (is_array($duplicationConfiguration[$index]['settings']))
                                ? $duplicationConfiguration[$index]['settings']
                                : []
                            : [];

                        // Calling the function of the current process step.
                        /** @var AbstractDuplicationProcess $class */
                        $class = GeneralUtility::makeInstance($class, $cacheData['duplicationData'], $settings, $cacheData['fieldsValues']);
                        if ($class instanceof AbstractDuplicationProcess) {
                            // @todo : else
//                            if (!$checkAjax || ($checkAjax && $class->checkAjaxCall())) {
                                $class->run();
                                $fieldsValues = $class->getFieldsValues();
                                $result->merge($class->getResult());

                                // Saving modified data in cache.
                                $configuration = [
                                    'duplicationData' => $class->getDuplicationData(),
                                    'fieldsValues'    => $fieldsValues
                                ];
                                $cache->set($cacheToken, json_encode($configuration));
//                            }
                        } else {
                            throw new \Exception('The class "' . $class . '" must extend "' . AbstractDuplicationProcess::class . '".', 1422887215);
                        }
                    } else {
                        throw new \Exception('The class is not set for the duplication configuration named "' . $index . '".', 1422885526);
                    }
                } else {
                    throw new \Exception('Trying to get the duplication configuration named "' . $index . '" but it does not exist.', 1422885438);
                }
            } else {
                throw new \Exception('The duplication data must contain a valid index for "modelPageUid".', 1422885697);
            }
        } catch (\Exception $exception) {
            /** @var BackendUserAuthentication $backendUser */
            $backendUser = $GLOBALS['BE_USER'];

            // Setting up error message. If the user is admin, it gets a detailed message.
            if ($backendUser->isAdmin()) {
                $errorMessage = Core::translate('duplication_process.process_error_detailed') . ' ' . $exception->getMessage();
            } else {
                $errorMessage = Core::translate('duplication_process.process_error_single');
            }

            $result->addError(new Error($errorMessage, 1431985617));
        }

        return Core::convertValidationResultToArray($result);
    }
}
