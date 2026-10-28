<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Tests\Unit;

use Pixelegg\PxeComposerReport\Service\ReportConfig;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Builds a ReportConfig backed by $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'],
 * which is what ExtensionConfiguration::get() reads. Tests must call
 * resetConfig() in tearDown().
 */
trait ConfigTrait
{
    /** @param array<string,mixed> $settings */
    protected function config(array $settings): ReportConfig
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['pxe_composer_report'] = $settings;
        return new ReportConfig(new ExtensionConfiguration());
    }

    protected function resetConfig(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['pxe_composer_report']);
    }
}
