<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Service;

/**
 * Pure transform: raw collector data → structured report.
 * No I/O. Output is a plain array (stored as report_json, rendered by Fluid).
 */
final class ReportBuilder
{
    public function __construct(private readonly SemverClassifier $semver) {}

    /**
     * @param list<array<string,mixed>> $outdated rows from a collector (see CollectorInterface)
     * @param array<string,list<array<string,mixed>>> $advisories package name => advisories
     * @param list<string> $private direct dependencies that were deliberately not checked
     * @param list<string> $notices machine-readable notices (CollectorFactory::NOTICE_*)
     * @return array<string,mixed> keys: collector, typo3, other, security, majorOnly, private, notices, counts
     */
    public function build(array $outdated, array $advisories, string $collector, array $private = [], array $notices = []): array
    {
        $typo3 = [];
        $other = [];
        $majorOnly = [];
        $private = array_values(array_unique($private));
        sort($private);
        $counts = [
            'total' => 0, 'major' => 0, 'minor' => 0, 'patch' => 0,
            'security' => 0, 'typo3' => 0, 'nextMajor' => 0, 'abandoned' => 0, 'private' => count($private),
        ];

        foreach ($outdated as $pkg) {
            $name = (string)($pkg['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $current = (string)($pkg['version'] ?? '');
            // "Latest" = latest within the installed major. Falls back to the absolute
            // `latest` for rows that were not enriched (keeps older callers working).
            $latestInMajor = (string)($pkg['latestInMajor'] ?? $pkg['latest'] ?? '');
            $latestOverall = (string)($pkg['latestOverall'] ?? $latestInMajor);
            $abandoned = $pkg['abandoned'] ?? false;
            $abandoned = $abandoned === true ? true : (is_string($abandoned) && $abandoned !== '' ? $abandoned : false);

            $impact = $this->semver->classify($current, $latestInMajor);

            // A newer major exists when the overall latest jumps the major number.
            $majorCurrent = $this->semver->major($current);
            $majorOverall = $this->semver->major($latestOverall);
            $nextMajor = ($majorOverall !== null && $majorCurrent !== null && $majorOverall > $majorCurrent)
                ? $latestOverall
                : '';

            if ($impact === 'none') {
                // Up to date within the installed major. A newer major is listed as a
                // hint only and does not count as "outdated".
                if ($nextMajor !== '') {
                    $majorOnly[] = ['name' => $name, 'current' => $current, 'nextMajor' => $nextMajor];
                    $counts['nextMajor']++;
                }
                continue;
            }

            $row = [
                'name' => $name,
                'current' => $current,
                'latest' => $latestInMajor,
                'nextMajor' => $nextMajor,
                'impact' => $impact,
                'description' => (string)($pkg['description'] ?? ''),
                'abandoned' => $abandoned,
                'advisories' => $advisories[$name] ?? [],
            ];

            $counts['total']++;
            if (isset($counts[$impact])) {
                $counts[$impact]++;
            }
            if ($nextMajor !== '') {
                $counts['nextMajor']++;
            }
            if ($abandoned !== false) {
                $counts['abandoned']++;
            }
            if ($this->isTypo3($name)) {
                $counts['typo3']++;
                $typo3[] = $row;
            } else {
                $other[] = $row;
            }
        }

        $this->sortByImpact($typo3);
        $this->sortByImpact($other);
        usort($majorOnly, static fn(array $a, array $b): int => strcmp((string)$a['name'], (string)$b['name']));

        $security = [];
        foreach ($advisories as $name => $list) {
            foreach ($list as $a) {
                $security[] = [
                    'name' => (string)$name,
                    'title' => (string)($a['title'] ?? ''),
                    'cve' => (string)($a['cve'] ?? ''),
                    'severity' => (string)($a['severity'] ?? ''),
                    'link' => (string)($a['link'] ?? ''),
                ];
            }
        }
        $counts['security'] = count($security);
        $this->sortBySeverity($security);

        return [
            'collector' => $collector,
            'typo3' => $typo3,
            'other' => $other,
            'security' => $security,
            'majorOnly' => $majorOnly,
            'private' => $private,
            'notices' => array_values($notices),
            'counts' => $counts,
        ];
    }

    /** @param array<string,mixed> $report */
    public static function shouldNotify(array $report, string $policy): bool
    {
        $c = is_array($report['counts'] ?? null) ? $report['counts'] : [];
        $security = (int)($c['security'] ?? 0);
        $major = (int)($c['major'] ?? 0);
        $total = (int)($c['total'] ?? 0);
        return match ($policy) {
            'security' => $security > 0,
            'security_or_major' => $security > 0 || $major > 0,
            default => $total > 0 || $security > 0, // 'any'
        };
    }

    private function isTypo3(string $name): bool
    {
        return str_starts_with($name, 'typo3/');
    }

    /**
     * Order rows by severity of the update: major → minor → patch → none/unknown,
     * package name as a stable tie-breaker.
     *
     * @param list<array<string,mixed>> $rows
     */
    private function sortByImpact(array &$rows): void
    {
        $rank = ['major' => 0, 'minor' => 1, 'patch' => 2, 'unknown' => 3, 'none' => 4];
        usort($rows, static function (array $a, array $b) use ($rank): int {
            $ra = $rank[(string)$a['impact']] ?? 9;
            $rb = $rank[(string)$b['impact']] ?? 9;
            return $ra <=> $rb ?: strcmp((string)$a['name'], (string)$b['name']);
        });
    }

    /**
     * Order advisories by severity, most severe first (critical → high → medium → low → unknown).
     *
     * @param list<array<string,mixed>> $advisories
     */
    private function sortBySeverity(array &$advisories): void
    {
        usort($advisories, function (array $a, array $b): int {
            return $this->severityRank((string)($b['severity'] ?? '')) <=> $this->severityRank((string)($a['severity'] ?? ''))
                ?: strcmp((string)$a['name'], (string)$b['name']);
        });
    }

    private function severityRank(string $severity): int
    {
        return match (strtolower(trim($severity))) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }
}
