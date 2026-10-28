<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Service;

/**
 * Decides which package names may leave the server.
 *
 * - `privateVendors`: packages of these vendors (or exact package names) are
 *   never sent to Packagist, the advisories API or the AI summary.
 * - `resolveLatestViaPackagist`: when off, the extension itself never contacts
 *   repo.packagist.org / packagist.org (composer may still do so on its own).
 */
final class PackagePrivacy
{
    public function __construct(private readonly ReportConfig $config) {}

    /** @return list<string> lower-cased vendor prefixes or full package names */
    public function privateVendors(): array
    {
        $out = [];
        foreach ($this->config->list('privateVendors') as $entry) {
            $entry = strtolower(trim($entry, " /\t"));
            if ($entry !== '') {
                $out[] = $entry;
            }
        }
        return array_values(array_unique($out));
    }

    public function isPrivate(string $packageName): bool
    {
        $name = strtolower(trim($packageName));
        if ($name === '') {
            return false;
        }
        $vendor = str_contains($name, '/') ? substr($name, 0, (int)strpos($name, '/')) : $name;
        foreach ($this->privateVendors() as $entry) {
            if ($entry === $name || $entry === $vendor) {
                return true;
            }
        }
        return false;
    }

    public function packagistAllowed(): bool
    {
        return $this->config->bool('resolveLatestViaPackagist', true);
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    public function filterPublic(array $names): array
    {
        return array_values(array_filter($names, fn(string $n): bool => !$this->isPrivate($n)));
    }
}
