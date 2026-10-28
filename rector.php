<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/Classes',
        __DIR__ . '/Configuration',
        __DIR__ . '/Tests',
    ])
    ->withSkip([
        // Compiled only when pxe_hub is installed.
        __DIR__ . '/Classes/Hub',
        __DIR__ . '/Tests/Unit/Hub',
    ])
    ->withSets([
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ]);
