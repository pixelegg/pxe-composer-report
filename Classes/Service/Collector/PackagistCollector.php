<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Service\Collector;

use Pixelegg\PxeComposerReport\Service\LatestVersionResolver;
use Pixelegg\PxeComposerReport\Service\PackagePrivacy;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Fallback collector for hosts without composer/proc_open: reads installed versions
 * from composer.lock and asks Packagist for the latest stable within the current
 * major (and overall) plus security advisories. Pure read-only HTTP GET.
 *
 * Packages of private vendors (PackagePrivacy) are never sent anywhere; they are
 * reported under `private` as "not checked".
 */
final class PackagistCollector implements CollectorInterface
{
    private const ADVISORIES_URL = 'https://packagist.org/api/security-advisories/';

    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly LatestVersionResolver $resolver,
        private readonly PackagePrivacy $privacy,
    ) {}

    public function collect(): array
    {
        if (!$this->privacy->packagistAllowed()) {
            throw new CollectorException(
                CollectorException::PACKAGIST_DISABLED,
                'The Packagist collector needs resolveLatestViaPackagist = 1, or the composer binary must be available.',
            );
        }
        $root = Environment::getProjectPath();
        ComposerCliCollector::assertLockFile($root);
        $lockPath = $root . '/composer.lock';

        $lock = $this->readJson($lockPath);
        $packages = is_array($lock['packages'] ?? null) ? $lock['packages'] : [];
        $rootJson = $this->readJson($root . '/composer.json');
        $direct = array_map('strval', array_keys(is_array($rootJson['require'] ?? null) ? $rootJson['require'] : []));

        $outdated = [];
        $private = [];
        $checked = [];
        foreach ($packages as $p) {
            if (!is_array($p)) {
                continue;
            }
            $name = (string)($p['name'] ?? '');
            if ($name === '' || !in_array($name, $direct, true)) {
                continue;
            }
            if ($this->privacy->isPrivate($name)) {
                $private[] = $name;
                continue;
            }
            $checked[] = $name;
            $current = ltrim((string)($p['version'] ?? ''), 'vV');
            $res = $this->resolver->resolve($name, $current);
            $inMajor = $res['latestInMajor'];
            $overall = $res['latestOverall'];
            $hasUpdate = ($inMajor !== '' && version_compare($inMajor, $current, '>'))
                || ($overall !== '' && version_compare($overall, $current, '>'));
            if (!$hasUpdate) {
                continue;
            }
            $outdated[] = [
                'name' => $name,
                'version' => $current,
                'latestInMajor' => $inMajor !== '' ? $inMajor : $current,
                'latestOverall' => $overall !== '' ? $overall : $current,
                'description' => (string)($p['description'] ?? ''),
                'abandoned' => $p['abandoned'] ?? false,
            ];
        }

        return [
            'outdated' => $outdated,
            'advisories' => $this->advisories($checked),
            'collector' => 'packagist',
            'private' => $private,
        ];
    }

    /** @return array<string,mixed> */
    private function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Advisories for all checked (public, direct) packages: an advisory may exist
     * even when no newer version is available yet.
     *
     * @param list<string> $names
     * @return array<string,list<array<string,mixed>>>
     *
     * @throws CollectorException
     */
    private function advisories(array $names): array
    {
        if ($names === []) {
            return [];
        }
        try {
            $query = http_build_query(['packages' => $names]);
            $res = $this->requestFactory->request(self::ADVISORIES_URL . '?' . $query, 'GET', ['timeout' => 20, 'connect_timeout' => 10]);
            $data = json_decode((string)$res->getBody(), true);
        } catch (\Throwable $e) {
            throw new CollectorException(CollectorException::PACKAGIST_FAILED, 'Security advisories lookup failed: ' . $e->getMessage(), $e);
        }
        $out = [];
        $advs = is_array($data) && is_array($data['advisories'] ?? null) ? $data['advisories'] : [];
        foreach ($advs as $name => $list) {
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $a) {
                if (!is_array($a)) {
                    continue;
                }
                $out[(string)$name][] = [
                    'title' => (string)($a['title'] ?? ''),
                    'cve' => (string)($a['cve'] ?? ''),
                    'severity' => (string)($a['severity'] ?? ''),
                    'link' => (string)($a['link'] ?? ''),
                ];
            }
        }
        return $out;
    }
}
