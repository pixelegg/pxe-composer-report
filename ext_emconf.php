<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'pxe Composer Report',
    'description' => 'Per-project, read-only composer update report: backend module + optional scheduled email.',
    'category' => 'module',
    'state' => 'beta',
    'version' => '1.2.0',
    'author' => 'pixelegg',
    'author_email' => 'info@pixelegg.de',
    'author_company' => 'pixelegg Informatik & Design GmbH',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
            'scheduler' => '13.4.0-14.99.99',
            'php' => '8.2.0-8.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => ['psr-4' => ['Pixelegg\\PxeComposerReport\\' => 'Classes/']],
];
