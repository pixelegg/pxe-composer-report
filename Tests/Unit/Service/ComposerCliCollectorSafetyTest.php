<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pixelegg\PxeComposerReport\Service\Collector\CollectorException;
use Pixelegg\PxeComposerReport\Service\Collector\ComposerBinary;
use Pixelegg\PxeComposerReport\Service\Collector\ComposerCliCollector;
use Pixelegg\PxeComposerReport\Tests\Unit\ConfigTrait;

/**
 * The extension must never be able to change a project: only `outdated` and
 * `audit` with fixed, read-only argument lists.
 */
final class ComposerCliCollectorSafetyTest extends TestCase
{
    use ConfigTrait;

    protected function tearDown(): void
    {
        $this->resetConfig();
        parent::tearDown();
    }

    #[Test]
    public function onlyReadOnlySubcommandsAllowed(): void
    {
        $forbidden = ['update', 'require', 'install', 'remove', 'create-project', 'dump-autoload', 'exec', 'run-script', 'bump', 'reinstall', 'remove', 'global', 'config', 'self-update'];
        foreach (ComposerCliCollector::COMMANDS as $argv) {
            self::assertContains($argv[1], ['outdated', 'audit'], 'Only outdated/audit allowed');
            foreach ($argv as $arg) {
                self::assertNotContains($arg, $forbidden, 'Forbidden mutating token in command');
                self::assertDoesNotMatchRegularExpression('/[;&|`$<>]/', $arg, 'No shell metacharacters');
            }
        }
    }

    #[Test]
    public function commandsAreFixedArraysWithComposerPlaceholderAndJsonOutput(): void
    {
        foreach (ComposerCliCollector::COMMANDS as $argv) {
            self::assertSame('composer', $argv[0]);
            self::assertContains('--format=json', $argv);
            self::assertContains('--no-interaction', $argv);
        }
        self::assertSame(['outdated', 'outdatedMinor', 'audit'], array_keys(ComposerCliCollector::COMMANDS));
    }

    #[Test]
    public function configuredBinaryPathMustExist(): void
    {
        $binary = new ComposerBinary($this->config(['composerBinary' => '/definitely/not/here/composer']));
        $this->expectException(CollectorException::class);
        try {
            $binary->resolve();
        } catch (CollectorException $e) {
            self::assertSame(CollectorException::BINARY_NOT_FOUND, $e->reason);
            throw $e;
        }
    }

    #[Test]
    public function missingBinaryInPathIsReportedNotThrownByIsAvailable(): void
    {
        $binary = new ComposerBinary($this->config(['composerBinary' => 'pxe-composer-report-no-such-binary']));
        self::assertFalse($binary->isAvailable());
    }

    #[Test]
    public function executableFileIsUsedVerbatim(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pxecr');
        self::assertIsString($file);
        chmod($file, 0755);
        try {
            $binary = new ComposerBinary($this->config(['composerBinary' => $file]));
            self::assertSame([$file], $binary->resolve());
        } finally {
            unlink($file);
        }
    }
}
