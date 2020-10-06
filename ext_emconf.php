<?php

$EM_CONF[$_EXTKEY] = [
    'title'       => 'Site Factory',
    'state'       => 'beta',
    'version'     => '0.8.3',
    'description' => 'Replicate and modify an existing website model very easily with a flexible and lean design. Read the code examples to understand and master all the TypoScript configuration, or extend the existing duplication processes. Based on freesite (created by Kasper Skårhøj) this project was originaly conceived by Cyril Wolfangel and is developped and maintained by Romain Canon. Join the project on https://github.com/romaincanon/TYPO3-Site-Factory',
    'category'    => 'module',

    'constraints' => [
        'depends'   => [
            'extbase' => '',
            'fluid'   => '',
            'typo3' => '9.0.0-9.5.99',
        ],
        'conflicts' => [],
        'suggests'  => []
    ],

    'author'         => 'Romain CANON',
    'author_email'   => 'romain.hydrocanon@gmail.com',

    'shy'              => '',
    'priority'         => '',
    'module'           => '',
    'internal'         => '',
    'uploadfolder'     => true,
    'createDirs'       => 'uploads/tx_sitefactory/_processed_/',
    'modify_tables'    => '',
    'clearCacheOnLoad' => 1,
    'lockType'         => ''
];
