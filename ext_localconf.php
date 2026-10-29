<?php

declare(strict_types=1);

use Pixelegg\PxeComposerReport\Task\ComposerReportTask;

defined('TYPO3') or die();

// FluidEmail template path for the report mail (ReportMailer::setTemplate('Report')).
$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][770]
    = 'EXT:pxe_composer_report/Resources/Private/Templates/Email/';

// Scheduler task: read-only composer report run.
// 'extension' is what the scheduler shows as group: the optgroup of the task type select
// in TYPO3 13.4, the wizard category header (ucfirst(), not translated) in TYPO3 14.
// Neither version runs it through the language service, so it is the product name
// (identical in English and German) instead of the raw extension key.
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][ComposerReportTask::class] = [
    'extension' => 'Composer Report',
    'title' => 'LLL:EXT:pxe_composer_report/Resources/Private/Language/locallang.xlf:task.title',
    'description' => 'LLL:EXT:pxe_composer_report/Resources/Private/Language/locallang.xlf:task.description',
];
