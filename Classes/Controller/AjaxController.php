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

use Romm\SiteFactory\Utility\AjaxInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Http\HtmlResponse;

class AjaxController extends ActionController
{
    public function dispatchAction()
    {
        $response = new HtmlResponse('');
        $requestArguments = GeneralUtility::_GP('request');
        $result = [];

        if (null !== $requestArguments
            && is_array($requestArguments)
        ) {
            if (true === isset($requestArguments['function'])) {
                $result = $this->dispatchUserFunction($requestArguments);
            } elseif (true === isset($requestArguments['mvc'])
                && is_array($requestArguments['mvc'])
            ) {
                $result = $this->dispatchControllerAction($requestArguments);
            }
        }

        $response->getBody()->write($result);
        return $response;
    }

    public function dispatchUserFunction($requestArguments)
    {
        $result = [];
        list($className) = GeneralUtility::trimExplode('->', $requestArguments['function']);

        if (class_exists($className)
            && in_array(AjaxInterface::class, class_implements($className))
        ) {
            $parameters = (true === isset($requestArguments['arguments']))
                ? $requestArguments['arguments']
                : [];

            $result = GeneralUtility::callUserFunction($requestArguments['function'], $parameters, $this);
        }

        return $result;
    }

    public function dispatchControllerAction($requestArguments) {

        $extensionName = (true === isset($requestArguments['mvc']['extensionName']))
            ? $requestArguments['mvc']['extensionName']
            : null;
        $controllerName = (true === isset($requestArguments['mvc']['controller']))
            ? $requestArguments['mvc']['controller']
            : null;
        $vendorName = (true === isset($requestArguments['mvc']['vendor']))
            ? $requestArguments['mvc']['vendor']
            : null;
        $actionName = (true === isset($requestArguments['mvc']['action']))
            ? $requestArguments['mvc']['action']
            : null;
        $arguments = (true === isset($requestArguments['arguments']))
            ? $requestArguments['arguments']
            : [];

        $controller = GeneralUtility::makeInstance($vendorName . '\\' . $extensionName .'\\Controller\\' . $controllerName.'Controller');

        $result = call_user_func(array($controller, $actionName . 'Action'), $arguments['duplicationToken'], $arguments['index']);

        return $result;

    }
}
