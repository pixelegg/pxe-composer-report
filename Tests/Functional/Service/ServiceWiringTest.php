<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use Pixelegg\PxeComposerReport\Controller\ComposerReportController;
use Pixelegg\PxeComposerReport\Service\Collector\CollectorFactory;
use Pixelegg\PxeComposerReport\Service\Collector\ComposerCliCollector;
use Pixelegg\PxeComposerReport\Service\Collector\PackagistCollector;
use Pixelegg\PxeComposerReport\Service\ComposerReportService;
use Pixelegg\PxeComposerReport\Task\ComposerReportTask;
use TYPO3\CMS\Backend\Module\ModuleRegistry;
use TYPO3\CMS\Core\Console\CommandRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Smoke test of the DI wiring on the supported TYPO3 versions. Nothing here talks
 * to the network or runs composer.
 */
final class ServiceWiringTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];
    protected array $testExtensionsToLoad = ['pixelegg/pxe-composer-report'];

    #[Test]
    public function backendControllerIsResolvable(): void
    {
        self::assertInstanceOf(ComposerReportController::class, $this->get(ComposerReportController::class));
    }

    #[Test]
    public function reportServiceIsResolvableViaMakeInstance(): void
    {
        // The scheduler task obtains the service through GeneralUtility::makeInstance(),
        // which only works for public services.
        self::assertInstanceOf(ComposerReportService::class, GeneralUtility::makeInstance(ComposerReportService::class));
    }

    #[Test]
    public function backendModuleIsRegisteredBelowAdminTools(): void
    {
        $registry = $this->get(ModuleRegistry::class);
        self::assertTrue($registry->hasModule('pxe_composer_report'));
        // "tools" in TYPO3 13.4, renamed to "admin" (with alias "tools") in TYPO3 14.
        self::assertContains(
            $registry->getModule('pxe_composer_report')->getParentIdentifier(),
            ['tools', 'admin'],
        );
    }

    #[Test]
    public function schedulerTaskIsRegistered(): void
    {
        self::assertArrayHasKey(
            ComposerReportTask::class,
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'] ?? [],
        );
    }

    #[Test]
    public function consoleCommandIsRegistered(): void
    {
        $registry = $this->get(CommandRegistry::class);
        self::assertTrue($registry->has('pxe:composer-report:run'));
    }

    #[Test]
    public function collectorModeSelectsCollector(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['pxe_composer_report'] = ['collectorMode' => 'packagist'];
        self::assertInstanceOf(PackagistCollector::class, $this->get(CollectorFactory::class)->create());

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['pxe_composer_report'] = ['collectorMode' => 'composer'];
        self::assertInstanceOf(ComposerCliCollector::class, $this->get(CollectorFactory::class)->create());
    }

    #[Test]
    public function autoModeFallsBackToPackagistWithNoticeWhenBinaryIsMissing(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['pxe_composer_report'] = [
            'collectorMode' => 'auto',
            'composerBinary' => '/no/such/composer',
        ];
        $selection = $this->get(CollectorFactory::class)->select();
        self::assertInstanceOf(PackagistCollector::class, $selection['collector']);
        self::assertSame([CollectorFactory::NOTICE_FALLBACK_NO_BINARY], $selection['notices']);
    }
}
