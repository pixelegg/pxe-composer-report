<?php

declare(strict_types=1);

$lll = 'LLL:EXT:pxe_composer_report/Resources/Private/Language/locallang.xlf:tca.';

return [
    'ctrl' => [
        'title' => $lll . 'run',
        'label' => 'crdate',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'crdate DESC',
        'iconfile' => 'EXT:pxe_composer_report/Resources/Public/Icons/module-composer.svg',
        'hideTable' => true,
        'security' => ['ignorePageTypeRestriction' => true],
    ],
    'columns' => [
        'collector' => ['label' => $lll . 'collector', 'config' => ['type' => 'input', 'readOnly' => true]],
        'status' => ['label' => $lll . 'status', 'config' => ['type' => 'input', 'readOnly' => true]],
        'total_outdated' => ['label' => $lll . 'total_outdated', 'config' => ['type' => 'number', 'readOnly' => true]],
        'security_count' => ['label' => $lll . 'security_count', 'config' => ['type' => 'number', 'readOnly' => true]],
        'report_json' => ['label' => $lll . 'report_json', 'config' => ['type' => 'text', 'readOnly' => true]],
        'ai_summary' => ['label' => $lll . 'ai_summary', 'config' => ['type' => 'text', 'readOnly' => true]],
        'error_log' => ['label' => $lll . 'error_log', 'config' => ['type' => 'text', 'readOnly' => true]],
        'triggered_by' => ['label' => $lll . 'triggered_by', 'config' => ['type' => 'input', 'readOnly' => true]],
    ],
    'types' => ['1' => ['showitem' => 'collector,status,total_outdated,security_count,triggered_by,ai_summary,report_json,error_log']],
];
