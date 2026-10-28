<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Tests\Unit\Hub;

use PHPUnit\Framework\TestCase;
use Pixelegg\PxeComposerReport\Hub\ComposerReportPanelProvider;
use Pixelegg\PxeComposerReport\Service\RunRepository;
use Pixelegg\PxeHub\Panel\PanelProvider;
use Pixelegg\PxeHub\Panel\PanelState;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * The hub integration is optional: these tests only run when pxe_hub is installed.
 */
final class ComposerReportPanelProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!interface_exists(PanelProvider::class)) {
            self::markTestSkipped('Optional integration: pxe_hub is not installed.');
        }
        // The provider translates through the backend language service; resolve keys to their last segment.
        $lang = self::createStub(LanguageService::class);
        $lang->method('sL')->willReturnCallback(static fn(string $key): string => ucfirst((string)substr($key, (int)strrpos($key, '.') + 1)));
        $GLOBALS['LANG'] = $lang;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['LANG']);
        parent::tearDown();
    }

    private function provider(?array $latest): ComposerReportPanelProvider
    {
        $repo = self::createStub(RunRepository::class);
        $repo->method('latest')->willReturn($latest);
        $uriBuilder = self::createStub(UriBuilder::class);
        $uriBuilder->method('buildUriFromRoute')->willReturn(new \TYPO3\CMS\Core\Http\Uri('/module/tools/pxe-composer-report'));
        return new ComposerReportPanelProvider($repo, $uriBuilder);
    }

    public function testIdentity(): void
    {
        $p = $this->provider(null);
        self::assertSame('pxe_composer_report', $p->getExtensionKey());
        self::assertSame('module-pxe-composer-report', $p->getIcon());
        self::assertSame('/module/tools/pxe-composer-report', $p->getModuleLink());
    }

    public function testStatusUnknownWhenNoRun(): void
    {
        self::assertSame(PanelState::Unknown, $this->provider(null)->getStatus()->state);
    }

    public function testStatusErrorWhenSecurityFindings(): void
    {
        $p = $this->provider(['total_outdated' => 5, 'security_count' => 2, 'major' => 1]);
        self::assertSame(PanelState::Error, $p->getStatus()->state);
    }

    public function testStatusWarningWhenOutdatedButNoSecurity(): void
    {
        $p = $this->provider(['total_outdated' => 5, 'security_count' => 0, 'major' => 1]);
        self::assertSame(PanelState::Warning, $p->getStatus()->state);
    }

    public function testStatusOkWhenNothingOutdated(): void
    {
        $p = $this->provider(['total_outdated' => 0, 'security_count' => 0, 'major' => 0]);
        self::assertSame(PanelState::Ok, $p->getStatus()->state);
    }

    public function testMetricsExposeCounts(): void
    {
        $p = $this->provider(['total_outdated' => 5, 'security_count' => 2, 'major' => 1]);
        $labels = array_map(fn($m) => $m->label, $p->getMetrics());
        self::assertContains('MetricOutdated', $labels);
        self::assertContains('MetricSecurity', $labels);
    }
}
