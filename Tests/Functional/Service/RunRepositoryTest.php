<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use Pixelegg\PxeComposerReport\Service\RunRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class RunRepositoryTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];
    protected array $testExtensionsToLoad = ['pixelegg/pxe-composer-report'];

    private function subject(): RunRepository
    {
        return $this->get(RunRepository::class);
    }

    #[Test]
    public function latestReturnsNullWhenNoRunExists(): void
    {
        self::assertNull($this->subject()->latest());
        self::assertSame([], $this->subject()->history());
        self::assertSame(0, $this->subject()->count());
    }

    #[Test]
    public function insertedRunIsReturnedAsLatestAndInHistory(): void
    {
        $uid = $this->subject()->insert([
            'collector' => 'composer',
            'status' => 'ok',
            'total_outdated' => 3,
            'major' => 1,
            'minor' => 0,
            'patch' => 2,
            'security_count' => 1,
            'typo3_count' => 1,
            'report_json' => '{"counts":{"total":3}}',
            'ai_summary' => '',
            'triggered_by' => 'manual',
        ]);

        self::assertGreaterThan(0, $uid);
        $latest = $this->subject()->latest();
        self::assertIsArray($latest);
        self::assertSame($uid, (int)$latest['uid']);
        self::assertSame(3, (int)$latest['total_outdated']);
        self::assertSame('{"counts":{"total":3}}', $latest['report_json']);
        self::assertGreaterThan(0, (int)$latest['crdate']);
        self::assertCount(1, $this->subject()->history());
    }

    #[Test]
    public function historyIsNewestFirstAndLimited(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/runs.csv');

        $history = $this->subject()->history(2);

        self::assertCount(2, $history);
        self::assertSame(30, (int)$history[0]['total_outdated']);
        self::assertSame(20, (int)$history[1]['total_outdated']);
        $latest = $this->subject()->latest();
        self::assertIsArray($latest);
        self::assertSame(30, (int)$latest['total_outdated']);
    }

    #[Test]
    public function pruneKeepsOnlyTheNewestRuns(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/runs.csv');
        self::assertSame(3, $this->subject()->count());

        self::assertSame(1, $this->subject()->prune(2));

        self::assertSame(2, $this->subject()->count());
        $remaining = array_map('intval', array_column($this->subject()->history(), 'total_outdated'));
        self::assertSame([30, 20], $remaining);
        self::assertSame(0, $this->subject()->prune(2), 'second prune deletes nothing');
    }

    #[Test]
    public function pruneWithZeroKeepsEverything(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/runs.csv');
        self::assertSame(0, $this->subject()->prune(0));
        self::assertSame(3, $this->subject()->count());
    }
}
