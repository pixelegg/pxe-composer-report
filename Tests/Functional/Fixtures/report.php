<?php

declare(strict_types=1);

/**
 * A complete report as ReportBuilder::build() produces it: used to render the
 * module template and the email without running a collector.
 */
return [
    'collector' => 'composer',
    'typo3' => [
        [
            'name' => 'typo3/cms-core', 'current' => '13.4.1', 'latest' => '13.4.37', 'nextMajor' => '14.3.10',
            'impact' => 'patch', 'description' => 'TYPO3 CMS Core', 'abandoned' => false, 'advisories' => [],
        ],
    ],
    'other' => [
        [
            'name' => 'acme/legacy', 'current' => '1.0.0', 'latest' => '1.0.1', 'nextMajor' => '',
            'impact' => 'patch', 'description' => 'Old', 'abandoned' => 'acme/modern', 'advisories' => [],
        ],
        [
            'name' => 'guzzlehttp/guzzle', 'current' => '7.8.0', 'latest' => '7.9.2', 'nextMajor' => '',
            'impact' => 'patch', 'description' => 'HTTP client', 'abandoned' => false, 'advisories' => [],
        ],
    ],
    'security' => [
        [
            'name' => 'guzzlehttp/guzzle', 'title' => 'Example advisory', 'cve' => 'CVE-2026-0001',
            'severity' => 'high', 'link' => 'https://example.org/advisory',
        ],
    ],
    'majorOnly' => [
        ['name' => 'georgringer/news', 'current' => '11.4.0', 'nextMajor' => '12.3.0'],
    ],
    'private' => ['mycorp/intranet'],
    'notices' => ['fallback_no_binary'],
    'counts' => [
        'total' => 3, 'major' => 0, 'minor' => 0, 'patch' => 3, 'security' => 1, 'typo3' => 1,
        'nextMajor' => 2, 'abandoned' => 1, 'private' => 1,
    ],
];
