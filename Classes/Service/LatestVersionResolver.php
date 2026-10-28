<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Resolves, per package, the latest STABLE version *within the currently installed
 * major* and the latest stable version *overall* (which may be a newer major).
 *
 * "Latest" in the report means: the newest version you can adopt without a major
 * upgrade. A newer major (e.g. v14 while you run v13) is surfaced separately as a
 * hint, not as the recommended target.
 *
 * Pure read-only HTTP GET against the Packagist p2 metadata. Best-effort: any
 * failure yields empty strings so the run still succeeds. Private packages and
 * a disabled Packagist lookup (PackagePrivacy) yield empty strings without any
 * request.
 */
final class LatestVersionResolver
{
    private const P2_URL = 'https://repo.packagist.org/p2/';

    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly PackagePrivacy $privacy,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array{latestInMajor:string, latestOverall:string}
     */
    public function resolve(string $name, string $current): array
    {
        $empty = ['latestInMajor' => '', 'latestOverall' => ''];
        if ($name === '' || !$this->privacy->packagistAllowed() || $this->privacy->isPrivate($name)) {
            return $empty;
        }
        try {
            $res = $this->requestFactory->request(self::P2_URL . $name . '.json', 'GET', ['timeout' => 15, 'connect_timeout' => 10]);
            $data = json_decode((string)$res->getBody(), true);
            $versions = is_array($data) && is_array($data['packages'][$name] ?? null) ? $data['packages'][$name] : [];
            $strings = [];
            foreach ($versions as $v) {
                $strings[] = is_array($v) ? (string)($v['version'] ?? '') : '';
            }
            return self::pickVersions($strings, $current);
        } catch (\Throwable $e) {
            $this->logger->warning('pxe_composer_report version resolve failed', ['package' => $name, 'error' => $e->getMessage()]);
            return $empty;
        }
    }

    /**
     * Pure version picking without I/O, unit-testable.
     *
     * @param list<string> $versions raw version strings (may carry a leading "v")
     * @return array{latestInMajor:string, latestOverall:string}
     */
    public static function pickVersions(array $versions, string $current): array
    {
        $currentMajor = self::major($current);
        $latestInMajor = '';
        $latestOverall = '';

        foreach ($versions as $raw) {
            $v = self::normalize($raw);
            if ($v === '' || !self::isStable($v)) {
                continue;
            }
            if ($latestOverall === '' || version_compare($v, $latestOverall, '>')) {
                $latestOverall = $v;
            }
            if ($currentMajor !== null && self::major($v) === $currentMajor) {
                if ($latestInMajor === '' || version_compare($v, $latestInMajor, '>')) {
                    $latestInMajor = $v;
                }
            }
        }

        return ['latestInMajor' => $latestInMajor, 'latestOverall' => $latestOverall];
    }

    private static function normalize(string $v): string
    {
        return ltrim(trim($v), 'vV');
    }

    /** Stable = no dev/alpha/beta/RC and no pre-release/build suffix. */
    private static function isStable(string $normalized): bool
    {
        return $normalized !== '' && !preg_match('/(dev|alpha|beta|rc|-)/i', $normalized);
    }

    private static function major(string $v): ?int
    {
        $n = self::normalize($v);
        if (!preg_match('/^(\d+)\./', $n, $m)) {
            return null;
        }
        return (int)$m[1];
    }
}
