<?php
defined('TYPO3_MODE') or die();

$associationParentField = [
    'site_factory_association_parent' => [
        'label' => 'site_factory_association_parent',
        'config' => [
            'type' => 'passthrough'
        ]
    ]
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', $associationParentField);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('pages', 'site_factory_association_parent', '','');
