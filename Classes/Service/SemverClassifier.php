<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Service;

/**
 * Pure semver impact comparison: strip a leading "v", read major.minor.patch,
 * ignore pre-release/build suffixes.
 */
final class SemverClassifier
{
    /** @return 'major'|'minor'|'patch'|'none'|'unknown' */
    public function classify(string $current, string $latest): string
    {
        $a = $this->parts($current);
        $b = $this->parts($latest);
        if ($a === null || $b === null) {
            return 'unknown';
        }
        if ($b[0] > $a[0]) {
            return 'major';
        }
        if ($b[0] < $a[0]) {
            return 'none';
        }
        if ($b[1] > $a[1]) {
            return 'minor';
        }
        if ($b[1] < $a[1]) {
            return 'none';
        }
        if ($b[2] > $a[2]) {
            return 'patch';
        }
        return 'none';
    }

    /** Major version number, or null when unparseable. */
    public function major(string $v): ?int
    {
        $p = $this->parts($v);
        return $p === null ? null : $p[0];
    }

    /** @return array{0:int,1:int,2:int}|null */
    private function parts(string $v): ?array
    {
        $s = preg_replace('/^v/i', '', trim($v)) ?? '';
        if ($s === '' || !preg_match('/^(\d+)\.(\d+)\.(\d+)(?:[.\-+].*)?$/', $s, $m)) {
            return null;
        }
        return [(int)$m[1], (int)$m[2], (int)$m[3]];
    }
}
