<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pixelegg\PxeComposerReport\Service\ReportConfig;
use Pixelegg\PxeComposerReport\Tests\Unit\ConfigTrait;

final class ReportConfigTest extends TestCase
{
    use ConfigTrait;

    protected function tearDown(): void
    {
        $this->resetConfig();
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']);
        parent::tearDown();
    }

    #[Test]
    public function defaultsApplyWhenExtensionWasNeverConfigured(): void
    {
        $c = $this->config([]);
        self::assertSame('composer', $c->get('composerBinary', 'composer'));
        self::assertTrue($c->bool('resolveLatestViaPackagist', true));
        self::assertFalse($c->bool('emailEnabled'));
        self::assertSame(ReportConfig::DEFAULT_KEEP_RUNS, $c->int('keepRuns', ReportConfig::DEFAULT_KEEP_RUNS));
        self::assertSame([], $c->list('privateVendors'));
        self::assertSame('en', $c->language());
    }

    #[Test]
    public function emptyStringsFallBackToDefaults(): void
    {
        $c = $this->config(['composerBinary' => '', 'keepRuns' => '', 'resolveLatestViaPackagist' => '']);
        self::assertSame('composer', $c->get('composerBinary', 'composer'));
        self::assertSame(50, $c->int('keepRuns', 50));
        self::assertTrue($c->bool('resolveLatestViaPackagist', true));
    }

    #[Test]
    public function boolAcceptsStringAndIntegerFlags(): void
    {
        self::assertTrue($this->config(['emailEnabled' => '1'])->bool('emailEnabled'));
        self::assertTrue($this->config(['emailEnabled' => 1])->bool('emailEnabled'));
        self::assertFalse($this->config(['emailEnabled' => '0'])->bool('emailEnabled'));
        self::assertFalse($this->config(['resolveLatestViaPackagist' => '0'])->bool('resolveLatestViaPackagist', true));
    }

    #[Test]
    public function listSplitsAndTrims(): void
    {
        $c = $this->config(['emailRecipients' => ' a@example.com, b@example.com ,, ']);
        self::assertSame(['a@example.com', 'b@example.com'], $c->list('emailRecipients'));
    }

    #[Test]
    public function languageOnlyKnowsEnglishAndGerman(): void
    {
        self::assertSame('de', $this->config(['emailLanguage' => 'DE'])->language());
        self::assertSame('en', $this->config(['emailLanguage' => 'fr'])->language());
    }

    #[Test]
    public function projectLabelFallsBackToSiteName(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] = 'Example Site';
        self::assertSame('Example Site', $this->config([])->projectLabel());
        self::assertSame('Custom', $this->config(['projectLabel' => 'Custom'])->projectLabel());
    }
}
