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

/**
 * ViewHelper to convert an array containing error messages into a human
 * readable string.
 */
class ErrorMessageViewHelper extends AbstractBackendViewHelper
{

    /**
     * @return void
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('errors', 'array', ' Error messages.', false);
    }

    /**
     * @return    string
     */
    public function render()
    {
        $errors = $this->arguments['errors'];
        $result = '';

        if (is_array($errors)) {
            $result = implode("\n\r", $errors);
        }

        return $result;
    }

}
