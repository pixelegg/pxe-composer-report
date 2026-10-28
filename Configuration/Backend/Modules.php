<?php

declare(strict_types=1);

use Pixelegg\PxeComposerReport\Controller\ComposerReportController;

return [
    'pxe_composer_report' => [
        'parent' => 'tools',
        'position' => ['after' => 'tools_toolssettings'],
        'access' => 'admin',
        'path' => '/module/tools/pxe-composer-report',
        'iconIdentifier' => 'module-pxe-composer-report',
        'labels' => 'LLL:EXT:pxe_composer_report/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'PxeComposerReport',
        'routes' => [
            '_default' => ['target' => ComposerReportController::class . '::indexAction'],
            // State-changing actions are POST only; all backend routes carry a CSRF token.
            'refresh' => ['target' => ComposerReportController::class . '::refreshAction', 'methods' => ['POST']],
            'testMail' => ['target' => ComposerReportController::class . '::testMailAction', 'methods' => ['POST']],
        ],
    ],
];
