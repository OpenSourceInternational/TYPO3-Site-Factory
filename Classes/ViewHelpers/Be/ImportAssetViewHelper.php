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

namespace Romm\SiteFactory\ViewHelpers\Be;

use TYPO3\CMS\Fluid\ViewHelpers\Be\AbstractBackendViewHelper;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * ViewHelper to include CSS or JavaScript assets.
 */
class ImportAssetViewHelper extends AbstractBackendViewHelper
{

    /**
     * @return void
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('cssFiles', 'array', 'array of css files', false);
        $this->registerArgument('jsFiles', 'array', 'array of js files', false);
    }

    /**
     * Includes the given CSS or JavaScript files.
     */
    public function render()
    {
        $cssFiles = $this->arguments['cssFiles'];
        $jsFiles = $this->arguments['jsFiles'];
        $pageRenderer = (version_compare(TYPO3_version, '7.0', '<'))
            ? $this->getDocInstance()->getPageRenderer()
            : $this->getPageRenderer();

        if (is_array($cssFiles)) {
            foreach ($cssFiles as $value) {
                $path = $this->getFileRealPath($value);
                $pageRenderer->addCssFile($path);
            }
        }

        if (is_array($jsFiles)) {
            foreach ($jsFiles as $value) {
                $path = $this->getFileRealPath($value);
                $pageRenderer->addJsLibrary($path, $path);
            }
        }
    }

    /**
     * Returns a file path correct value by finding the 'EXT:xxx' values.
     *
     * @param    string $path The path to the file.
     * @return    string            The correct path;
     */
    private function getFileRealPath($path)
    {
        if (preg_match('/^EXT:([^\/]*)\/(.*)$/', $path, $res)) {
            $extRelPath = PathUtility::stripPathSitePrefix(ExtensionManagementUtility::extPath($res[1]));
            $path = str_replace('EXT:' . $res[1] . '/', $extRelPath, $path);
        }

        return $path;
    }

}
