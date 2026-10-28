<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pixelegg\PxeComposerReport\Service\ReportBuilder;
use Pixelegg\PxeComposerReport\Service\SemverClassifier;

final class ReportBuilderTest extends TestCase
{
    private ReportBuilder $b;

    protected function setUp(): void
    {
        $this->b = new ReportBuilder(new SemverClassifier());
    }

    /** @return list<array<string,mixed>> */
    private function sampleOutdated(): array
    {
        return [
            ['name' => 'typo3/cms-core', 'version' => '13.4.1', 'latest' => '13.4.7', 'description' => 'TYPO3 core'],
            ['name' => 'georgringer/news', 'version' => '11.0.0', 'latest' => '12.0.0', 'description' => 'News'],
            ['name' => 'guzzlehttp/guzzle', 'version' => '7.8.0', 'latest' => '7.8.1', 'description' => 'HTTP'],
        ];
    }

    #[Test]
    public function splitsTypo3FromOther(): void
    {
        $r = $this->b->build($this->sampleOutdated(), [], 'composer');
        self::assertSame(['typo3/cms-core'], array_column($r['typo3'], 'name'));
        self::assertEqualsCanonicalizing(['georgringer/news', 'guzzlehttp/guzzle'], array_column($r['other'], 'name'));
    }

    #[Test]
    public function countsByImpact(): void
    {
        $r = $this->b->build($this->sampleOutdated(), [], 'composer');
        self::assertSame(3, $r['counts']['total']);
        self::assertSame(1, $r['counts']['major']);   // news 11 -> 12
        self::assertSame(0, $r['counts']['minor']);
        self::assertSame(2, $r['counts']['patch']);   // core 13.4.1->13.4.7 + guzzle 7.8.0->7.8.1
        self::assertSame(1, $r['counts']['typo3']);
        self::assertSame(0, $r['counts']['private']);
    }

    #[Test]
    public function attachesAdvisoriesMostSevereFirst(): void
    {
        $adv = [
            'guzzlehttp/guzzle' => [['title' => 'XSS', 'cve' => 'CVE-2024-0001', 'severity' => 'medium', 'link' => 'https://x']],
            'typo3/cms-core' => [['title' => 'RCE', 'cve' => 'CVE-2024-0002', 'severity' => 'critical', 'link' => 'https://y']],
        ];
        $r = $this->b->build($this->sampleOutdated(), $adv, 'composer');
        self::assertSame(2, $r['counts']['security']);
        self::assertSame('typo3/cms-core', $r['security'][0]['name']);
        self::assertSame('CVE-2024-0001', $r['security'][1]['cve']);
    }

    #[Test]
    public function shouldNotifyPolicies(): void
    {
        $r = $this->b->build($this->sampleOutdated(), [], 'composer');
        self::assertTrue(ReportBuilder::shouldNotify($r, 'any'));
        self::assertTrue(ReportBuilder::shouldNotify($r, 'security_or_major'));
        self::assertFalse(ReportBuilder::shouldNotify($r, 'security'));
        $empty = $this->b->build([], [], 'composer');
        self::assertFalse(ReportBuilder::shouldNotify($empty, 'any'));
        self::assertFalse(ReportBuilder::shouldNotify($empty, 'security_or_major'));
    }

    #[Test]
    public function ignoresNoneImpactRows(): void
    {
        $r = $this->b->build([['name' => 'a/b', 'version' => '2.0.0', 'latest' => '2.0.0']], [], 'composer');
        self::assertSame(0, $r['counts']['total']);
    }

    #[Test]
    public function usesLatestInMajorAndFlagsNextMajor(): void
    {
        $rows = [[
            'name' => 'typo3/cms-core', 'version' => '13.4.27',
            'latestInMajor' => '13.4.28', 'latestOverall' => '14.3.4', 'description' => 'core',
        ]];
        $r = $this->b->build($rows, [], 'composer');
        $row = $r['typo3'][0];
        self::assertSame('13.4.28', $row['latest']);   // "latest" stays within the major
        self::assertSame('14.3.4', $row['nextMajor']); // newer major surfaced separately
        self::assertSame('patch', $row['impact']);     // 13.4.27 -> 13.4.28
        self::assertSame(1, $r['counts']['nextMajor']);
    }

    #[Test]
    public function noNextMajorWhenOverallSameMajor(): void
    {
        $rows = [[
            'name' => 'a/b', 'version' => '2.1.0',
            'latestInMajor' => '2.4.0', 'latestOverall' => '2.4.0',
        ]];
        $r = $this->b->build($rows, [], 'composer');
        self::assertSame('', $r['other'][0]['nextMajor']);
        self::assertSame(0, $r['counts']['nextMajor']);
    }

    #[Test]
    public function dropsRowWhenCurrentIsLatestInMajor(): void
    {
        // Latest within the major already installed; only a new major exists -> not "behind" within-major.
        $rows = [[
            'name' => 'a/b', 'version' => '13.4.27',
            'latestInMajor' => '13.4.27', 'latestOverall' => '14.0.0',
        ]];
        $r = $this->b->build($rows, [], 'composer');
        self::assertSame(0, $r['counts']['total']);
        self::assertSame([['name' => 'a/b', 'current' => '13.4.27', 'nextMajor' => '14.0.0']], $r['majorOnly']);
        self::assertSame(1, $r['counts']['nextMajor']);
    }

    #[Test]
    public function sortsByImpactSeverity(): void
    {
        $rows = [
            ['name' => 'p/patch', 'version' => '1.0.0', 'latestInMajor' => '1.0.1', 'latestOverall' => '1.0.1'],
            ['name' => 'p/minor', 'version' => '1.0.0', 'latestInMajor' => '1.1.0', 'latestOverall' => '1.1.0'],
        ];
        $r = $this->b->build($rows, [], 'composer');
        self::assertSame(['p/minor', 'p/patch'], array_column($r['other'], 'name')); // minor before patch
    }

    #[Test]
    public function carriesPrivatePackagesAndNotices(): void
    {
        $r = $this->b->build([], [], 'packagist', ['zeta/one', 'acme/two', 'acme/two'], ['fallback_no_binary']);
        self::assertSame(['acme/two', 'zeta/one'], $r['private']);
        self::assertSame(2, $r['counts']['private']);
        self::assertSame(['fallback_no_binary'], $r['notices']);
        self::assertSame('packagist', $r['collector']);
    }

    #[Test]
    public function keepsAbandonedFlagAndReplacement(): void
    {
        $rows = [
            ['name' => 'a/old', 'version' => '1.0.0', 'latestInMajor' => '1.0.1', 'latestOverall' => '1.0.1', 'abandoned' => 'a/new'],
            ['name' => 'b/old', 'version' => '1.0.0', 'latestInMajor' => '1.0.1', 'latestOverall' => '1.0.1', 'abandoned' => true],
            ['name' => 'c/ok', 'version' => '1.0.0', 'latestInMajor' => '1.0.1', 'latestOverall' => '1.0.1', 'abandoned' => false],
        ];
        $r = $this->b->build($rows, [], 'composer');
        self::assertSame('a/new', $r['other'][0]['abandoned']);
        self::assertTrue($r['other'][1]['abandoned']);
        self::assertFalse($r['other'][2]['abandoned']);
        self::assertSame(2, $r['counts']['abandoned']);
    }
}
