<?php

/**
 * Definitions for routes provided by EXT:site_factory
 */
return [
    // Dispatch the permissions actions
    'site_factory' => [
        'path' => '/tree/copy',
        'target' => \Romm\SiteFactory\Controller\AjaxController::class . '::dispatchAction'
    ]
];
