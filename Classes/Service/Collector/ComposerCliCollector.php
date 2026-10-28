<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Service\Collector;

use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Read-only collector: runs ONLY `composer outdated` and `composer audit` as fixed
 * argument arrays, never `update`/`install`/`require` or anything else that changes the
 * project. This invariant is enforced by ComposerCliCollectorSafetyTest.
 *
 * `outdated` is run twice: once plain (absolute latest) and once with
 * `--minor-only` (latest within the installed major, i.e. `^installed`). That
 * way the collector needs no Packagist lookup of its own; composer talks to the
 * repositories configured in the project, with the project's own credentials.
 */
final class ComposerCliCollector implements CollectorInterface
{
    /** The ONLY commands this extension may execute. Fixed arrays, never built from input. */
    public const COMMANDS = [
        'outdated' => ['composer', 'outdated', '--direct', '--format=json', '--no-interaction'],
        'outdatedMinor' => ['composer', 'outdated', '--direct', '--minor-only', '--format=json', '--no-interaction'],
        'audit' => ['composer', 'audit', '--format=json', '--no-interaction'],
    ];

    private const TIMEOUT_SECONDS = 300.0;
    private const MAX_ERROR_LENGTH = 2000;

    public function __construct(private readonly ComposerBinary $binary) {}

    public function collect(): array
    {
        $root = Environment::getProjectPath();
        self::assertLockFile($root);
        if (!$this->binary->isProcOpenAvailable()) {
            throw new CollectorException(CollectorException::PROC_OPEN_DISABLED, 'proc_open() is disabled in php.ini (disable_functions).');
        }
        $prefix = $this->binary->resolve();

        $all = $this->runJson(self::COMMANDS['outdated'], $prefix, $root);
        $minor = $this->runJson(self::COMMANDS['outdatedMinor'], $prefix, $root);
        // `composer audit` exits non-zero when advisories are found; that is normal.
        $audit = $this->runJson(self::COMMANDS['audit'], $prefix, $root, allowFailure: true);

        $advisories = [];
        $rawAdvisories = $audit['advisories'] ?? [];
        if (is_array($rawAdvisories)) {
            foreach ($rawAdvisories as $name => $list) {
                if (!is_array($list)) {
                    continue;
                }
                foreach ($list as $a) {
                    if (is_array($a)) {
                        $advisories[(string)$name][] = $a;
                    }
                }
            }
        }

        return [
            'outdated' => $this->mergeOutdated($all, $minor),
            'advisories' => $advisories,
            'collector' => 'composer',
            'private' => [],
        ];
    }

    /**
     * @param array<string,mixed> $all   result of `outdated` (latest = absolute)
     * @param array<string,mixed> $minor result of `outdated --minor-only` (latest = within ^installed)
     * @return list<array<string,mixed>>
     */
    private function mergeOutdated(array $all, array $minor): array
    {
        $rows = [];
        foreach ($this->installedRows($all) as $p) {
            $name = (string)($p['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $rows[$name] = [
                'name' => $name,
                'version' => self::normalize((string)($p['version'] ?? '')),
                'latestOverall' => self::normalize((string)($p['latest'] ?? '')),
                'latestInMajor' => '',
                'latestStatus' => (string)($p['latest-status'] ?? ''),
                'description' => (string)($p['description'] ?? ''),
                'abandoned' => $p['abandoned'] ?? false,
            ];
        }
        foreach ($this->installedRows($minor) as $p) {
            $name = (string)($p['name'] ?? '');
            if ($name !== '' && isset($rows[$name])) {
                $rows[$name]['latestInMajor'] = self::normalize((string)($p['latest'] ?? ''));
            }
        }
        foreach ($rows as &$row) {
            if ($row['latestInMajor'] === '') {
                // Not in the minor-only list: either the absolute latest is already
                // semver-compatible, or only a new major exists (nothing to do within the major).
                $row['latestInMajor'] = $row['latestStatus'] === 'semver-safe-update'
                    ? $row['latestOverall']
                    : $row['version'];
            }
            unset($row['latestStatus']);
        }
        unset($row);
        return array_values($rows);
    }

    /**
     * @param array<string,mixed> $decoded
     * @return list<array<string,mixed>>
     */
    private function installedRows(array $decoded): array
    {
        $installed = $decoded['installed'] ?? [];
        if (!is_array($installed)) {
            return [];
        }
        return array_values(array_filter($installed, 'is_array'));
    }

    /**
     * The report needs a composer-managed project. Without a lock file in the
     * project root there is nothing to compare; a classic (non-composer)
     * installation gets its own, clearer message.
     *
     * @throws CollectorException
     */
    public static function assertLockFile(string $projectPath): void
    {
        if (is_file($projectPath . '/composer.lock')) {
            return;
        }
        if (!Environment::isComposerMode()) {
            throw new CollectorException(CollectorException::CLASSIC_MODE, 'TYPO3 is not installed with composer; no composer.lock in ' . $projectPath);
        }
        throw new CollectorException(CollectorException::LOCK_MISSING, $projectPath . '/composer.lock');
    }

    private static function normalize(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }

    /**
     * @param list<string> $argv   one of self::COMMANDS
     * @param list<string> $prefix replacement for the `composer` placeholder at index 0
     * @return array<string,mixed>
     */
    private function runJson(array $argv, array $prefix, string $cwd, bool $allowFailure = false): array
    {
        $command = array_merge($prefix, array_slice($argv, 1));
        $process = new Process($command, $cwd, $this->environment(), null, self::TIMEOUT_SECONDS);
        $process->run();

        $decoded = json_decode($process->getOutput(), true);
        $isJson = is_array($decoded);

        if (!$process->isSuccessful() && !($allowFailure && $isJson)) {
            $error = trim($process->getErrorOutput()) !== '' ? trim($process->getErrorOutput()) : trim($process->getOutput());
            throw new CollectorException(
                CollectorException::COMPOSER_FAILED,
                sprintf('`%s` exited with %d: %s', implode(' ', array_slice($argv, 1)), (int)$process->getExitCode(), mb_substr($error, 0, self::MAX_ERROR_LENGTH)),
            );
        }
        if (!$isJson) {
            throw new CollectorException(
                CollectorException::COMPOSER_FAILED,
                sprintf('`%s` returned no JSON: %s', implode(' ', array_slice($argv, 1)), mb_substr(trim($process->getOutput() . "\n" . $process->getErrorOutput()), 0, self::MAX_ERROR_LENGTH)),
            );
        }
        return $decoded;
    }

    /**
     * Composer refuses to run without HOME or COMPOSER_HOME (typical for php-fpm
     * users). When neither is set, point COMPOSER_HOME into var/ so caches land
     * in a writable place. Everything else is inherited from the PHP process.
     *
     * @return array<string,string>
     */
    private function environment(): array
    {
        $env = ['COMPOSER_NO_INTERACTION' => '1'];
        $home = getenv('HOME');
        $composerHome = getenv('COMPOSER_HOME');
        if (($home === false || $home === '') && ($composerHome === false || $composerHome === '')) {
            $dir = Environment::getVarPath() . '/pxe_composer_report/composer-home';
            GeneralUtility::mkdir_deep($dir);
            $env['COMPOSER_HOME'] = $dir;
        }
        return $env;
    }
}
