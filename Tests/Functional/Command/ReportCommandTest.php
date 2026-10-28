<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Pixelegg\PxeComposerReport\Command\ReportCommand;
use Pixelegg\PxeComposerReport\Service\Collector\CollectorException;
use Pixelegg\PxeComposerReport\Service\ComposerReportService;
use Pixelegg\PxeComposerReport\Service\RunRepository;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * End-to-end run of the report through the CLI command, with the composer
 * binary replaced by a shell script that prints canned JSON. No network.
 */
final class ReportCommandTest extends FunctionalTestCase
{
    private const FIXTURES = __DIR__ . '/../Fixtures/composer';
    protected array $coreExtensionsToLoad = ['scheduler'];
    protected array $testExtensionsToLoad = ['pixelegg/pxe-composer-report'];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['pxe_composer_report'] = [
            'collectorMode' => 'composer',
            'composerBinary' => self::FIXTURES . '/fake-composer.sh',
            'resolveLatestViaPackagist' => '0',
            'keepRuns' => '2',
            'emailEnabled' => '0',
            'aiEnabled' => '0',
        ];
    }

    private function writeProjectFiles(): void
    {
        copy(self::FIXTURES . '/root-composer.json', Environment::getProjectPath() . '/composer.json');
        copy(self::FIXTURES . '/lock-with-direct.json', Environment::getProjectPath() . '/composer.lock');
    }

    /** @param array<string,mixed> $input */
    private function runCommand(array $input = []): CommandTester
    {
        $tester = new CommandTester(new ReportCommand($this->get(ComposerReportService::class)));
        $tester->execute($input);
        return $tester;
    }

    #[Test]
    public function reportsAdvisoriesWithExitCodeOne(): void
    {
        $this->writeProjectFiles();

        $tester = $this->runCommand();

        self::assertSame(ReportCommand::EXIT_ADVISORIES, $tester->getStatusCode(), $tester->getDisplay());
        $display = $tester->getDisplay();
        self::assertStringContainsString('CVE-2026-0001', $display);
        self::assertStringContainsString('typo3/cms-core', $display);

        $latest = $this->get(RunRepository::class)->latest();
        self::assertIsArray($latest);
        self::assertSame('ok', $latest['status']);
        self::assertSame('composer', $latest['collector']);
        self::assertSame('cli', $latest['triggered_by']);
        self::assertSame(1, (int)$latest['security_count']);

        $report = json_decode((string)$latest['report_json'], true);
        self::assertIsArray($report);
        $core = $report['typo3'][0];
        self::assertSame('typo3/cms-core', $core['name']);
        self::assertSame('13.4.1', $core['current']);
        self::assertSame('13.4.37', $core['latest'], 'latest within major comes from --minor-only');
        self::assertSame('14.3.10', $core['nextMajor'], 'newer major from the plain outdated call');
        self::assertSame('patch', $core['impact']);

        $names = array_column($report['other'], 'name');
        self::assertSame(['guzzlehttp/guzzle', 'acme/legacy'], $names, 'minor (7.8 -> 7.9) before patch');
        self::assertSame('acme/modern', $report['other'][1]['abandoned']);
        self::assertSame(1, $report['counts']['minor']);
        self::assertSame(2, $report['counts']['patch']);
        // news 11.4.0 is up to date within 11.x; the newer major is a hint, not an outdated row.
        self::assertSame(['georgringer/news'], array_column($report['majorOnly'], 'name'));
        self::assertSame(2, $report['counts']['nextMajor']);
        self::assertSame(3, $report['counts']['total']);
        self::assertSame([], $report['private'], 'composer collector checks private vendors itself');
        self::assertSame([], $report['notices']);
    }

    #[Test]
    public function jsonOutputIsMachineReadable(): void
    {
        $this->writeProjectFiles();

        $tester = $this->runCommand(['--json' => true]);

        self::assertSame(ReportCommand::EXIT_ADVISORIES, $tester->getStatusCode());
        $data = json_decode($tester->getDisplay(), true);
        self::assertIsArray($data);
        self::assertSame('ok', $data['status']);
        self::assertSame(1, $data['report']['counts']['security']);
    }

    #[Test]
    public function missingLockFileFailsGracefullyWithExitCodeTwo(): void
    {
        @unlink(Environment::getProjectPath() . '/composer.lock');

        $tester = $this->runCommand();

        self::assertSame(ReportCommand::EXIT_FAILED, $tester->getStatusCode());
        // Functional test instances are classic (non-composer) installations, so the clearer code is used.
        $expected = Environment::isComposerMode() ? CollectorException::LOCK_MISSING : CollectorException::CLASSIC_MODE;
        self::assertStringContainsString($expected, $tester->getDisplay());

        $latest = $this->get(RunRepository::class)->latest();
        self::assertIsArray($latest);
        self::assertSame('error', $latest['status']);
        $report = json_decode((string)$latest['report_json'], true);
        self::assertSame($expected, $report['error']['code'] ?? null);
    }

    #[Test]
    public function missingBinaryIsReportedAsErrorCode(): void
    {
        $this->writeProjectFiles();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['pxe_composer_report']['composerBinary'] = '/no/such/composer';

        $tester = $this->runCommand();

        self::assertSame(ReportCommand::EXIT_FAILED, $tester->getStatusCode());
        self::assertStringContainsString(CollectorException::BINARY_NOT_FOUND, $tester->getDisplay());
    }

    #[Test]
    public function autoModeFallsBackToPackagistWithNoticeWhenBinaryIsMissing(): void
    {
        $this->writeProjectFiles();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['pxe_composer_report']['collectorMode'] = 'auto';
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['pxe_composer_report']['composerBinary'] = '/no/such/composer';

        // Packagist is disabled in this test setup, so the fallback collector stops with a clear code
        // instead of touching the network.
        $tester = $this->runCommand();

        self::assertSame(ReportCommand::EXIT_FAILED, $tester->getStatusCode());
        self::assertStringContainsString(CollectorException::PACKAGIST_DISABLED, $tester->getDisplay());
    }

    #[Test]
    public function oldRunsArePrunedToKeepRuns(): void
    {
        $this->writeProjectFiles();
        $this->runCommand();
        $this->runCommand();
        $this->runCommand();

        self::assertSame(2, $this->get(RunRepository::class)->count());
    }
}
