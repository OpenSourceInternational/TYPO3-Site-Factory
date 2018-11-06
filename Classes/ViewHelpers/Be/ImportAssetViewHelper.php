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

        $pageRenderer = $this->getPageRenderer();

        if (is_array($cssFiles)) {
            foreach ($cssFiles as $value) {
                $pageRenderer->addCssFile($value);
            }
        }

        if (is_array($jsFiles)) {
            foreach ($jsFiles as $value) {
                $pageRenderer->addJsLibrary($value, $value);
            }
        }
    }
}
