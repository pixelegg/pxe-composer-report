<?php

declare(strict_types=1);

use Pixelegg\PxeComposerReport\Task\ComposerReportTask;

defined('TYPO3') or die();

// FluidEmail template path for the report mail (ReportMailer::setTemplate('Report')).
$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][770]
    = 'EXT:pxe_composer_report/Resources/Private/Templates/Email/';

// Scheduler task: read-only composer report run.
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][ComposerReportTask::class] = [
    'extension' => 'pxe_composer_report',
    'title' => 'LLL:EXT:pxe_composer_report/Resources/Private/Language/locallang.xlf:task.title',
    'description' => 'LLL:EXT:pxe_composer_report/Resources/Private/Language/locallang.xlf:task.description',
];
