<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Service;

/**
 * Outcome of one ComposerReportService::run().
 */
final class RunResult
{
    public const STATUS_OK = 'ok';
    public const STATUS_ERROR = 'error';

    /**
     * @param array<string,mixed> $report
     */
    private function __construct(
        public readonly int $uid,
        public readonly string $status,
        public readonly array $report,
        public readonly string $aiSummary,
        public readonly bool $mailed,
        public readonly string $errorCode,
        public readonly string $errorMessage,
    ) {}

    /** @param array<string,mixed> $report */
    public static function ok(int $uid, array $report, string $aiSummary, bool $mailed): self
    {
        return new self($uid, self::STATUS_OK, $report, $aiSummary, $mailed, '', '');
    }

    public static function error(int $uid, string $code, string $message): self
    {
        return new self($uid, self::STATUS_ERROR, [], '', false, $code, $message);
    }

    public function isOk(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    public function count(string $key): int
    {
        $counts = $this->report['counts'] ?? null;
        return is_array($counts) ? (int)($counts[$key] ?? 0) : 0;
    }

    public function hasAdvisories(): bool
    {
        return $this->count('security') > 0;
    }
}
