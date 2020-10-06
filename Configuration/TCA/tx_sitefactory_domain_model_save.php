<?php
defined('TYPO3_MODE') or die;

$LLL = 'LLL:EXT:site_factory/Resources/Private/Language/locallang_db.xlf:tx_sitefactory_domain_model_save';

return [
    'ctrl' => [
        'title' => $LLL,
        'label' => 'root_page_uid',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'hideTable' => 1,
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:site_factory/Resources/Public/Icons/save.png',
    ],
    'interface' => [
        'showRecordFieldList' => 'root_page_uid, date, configuration',
    ],
    'columns' => [
        'root_page_uid' => [
            'exclude' => 1,
            'label' => $LLL . '.root_page_uid',
            'config' => [
                'type' => 'input',
                'eval' => 'required,trim',
                'readOnly' => 1,
            ],
        ],
        'date' => [
            'exclude' => 1,
            'label' => $LLL . '.date',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'required,trim,date',
                'readOnly' => 1,
            ],
        ],
        'configuration' => [
            'exclude' => 1,
            'label' => $LLL . '.configuration',
            'config' => [
                'type' => 'text',
                'cols' => 60,
                'rows' => 5,
                'eval' => 'required,trim',
                'readOnly' => 1,
            ],
        ],
    ],
    'types' => [
        0 => [
            'showitem' => '--palette--;;paletteCore',
        ],
    ],
    'palettes' => [
        'paletteCore' => [
            'showitem' => 'root_page_uid, --linebreak--, date, --linebreak--, configuration',
            'canNotCollapse' => true
        ],
    ],
];
