<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Service;

use Pixelegg\PxeComposerReport\Service\Collector\CollectorException;
use Pixelegg\PxeComposerReport\Service\Collector\CollectorFactory;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates one report run: collect → build → [AI] → persist → prune → [email].
 * Read-only end to end: composer is only ever called with `outdated`/`audit`
 * (see ComposerCliCollector). Never throws: a failed run is stored with
 * status "error" and a machine-readable error code.
 */
final class ComposerReportService
{
    public const ERROR_UNEXPECTED = 'unexpected';

    public function __construct(
        private readonly CollectorFactory $collectorFactory,
        private readonly ReportBuilder $builder,
        private readonly RunRepository $runRepository,
        private readonly AiSummaryService $ai,
        private readonly ReportMailer $mailer,
        private readonly ReportConfig $config,
        private readonly LatestVersionResolver $resolver,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param string $triggeredBy 'scheduler' | 'manual' | 'cli'
     * @param bool $forceMail send the email even if emailEnabled is off / nothing was found
     */
    public function run(string $triggeredBy, bool $forceMail = false): RunResult
    {
        try {
            $selection = $this->collectorFactory->select();
            $raw = $selection['collector']->collect();
            $outdated = $this->enrichWithinMajor($raw['outdated']);
            $report = $this->builder->build($outdated, $raw['advisories'], $raw['collector'], $raw['private'], $selection['notices']);
            $aiSummary = $this->ai->summarize($report);

            $counts = is_array($report['counts']) ? $report['counts'] : [];
            $uid = $this->runRepository->insert([
                'collector' => $raw['collector'],
                'status' => RunResult::STATUS_OK,
                'total_outdated' => (int)($counts['total'] ?? 0),
                'major' => (int)($counts['major'] ?? 0),
                'minor' => (int)($counts['minor'] ?? 0),
                'patch' => (int)($counts['patch'] ?? 0),
                'security_count' => (int)($counts['security'] ?? 0),
                'typo3_count' => (int)($counts['typo3'] ?? 0),
                'report_json' => json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ai_summary' => $aiSummary,
                'triggered_by' => $triggeredBy,
            ]);
            $this->prune();

            $mailed = $this->maybeEmail($report, $aiSummary, $forceMail);
            return RunResult::ok($uid, $report, $aiSummary, $mailed);
        } catch (\Throwable $e) {
            $code = $e instanceof CollectorException ? $e->reason : self::ERROR_UNEXPECTED;
            $message = $e->getMessage();
            $this->logger->error('pxe_composer_report run failed', ['code' => $code, 'error' => $message]);
            $uid = $this->runRepository->insert([
                'collector' => '',
                'status' => RunResult::STATUS_ERROR,
                'triggered_by' => $triggeredBy,
                'error_log' => $message,
                'report_json' => json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $this->prune();
            return RunResult::error($uid, $code, $message);
        }
    }

    private function prune(): void
    {
        try {
            $this->runRepository->prune($this->config->int('keepRuns', ReportConfig::DEFAULT_KEEP_RUNS));
        } catch (\Throwable $e) {
            $this->logger->warning('pxe_composer_report prune failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Ensure every outdated row carries `latestInMajor` / `latestOverall`. The
     * collectors normally fill both; rows that only know an absolute `latest`
     * are resolved via Packagist when allowed, otherwise that value is kept.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function enrichWithinMajor(array $rows): array
    {
        foreach ($rows as &$row) {
            if (isset($row['latestInMajor'])) {
                continue;
            }
            $fallback = (string)($row['latest'] ?? '');
            $res = $this->resolver->resolve((string)($row['name'] ?? ''), (string)($row['version'] ?? ''));
            $row['latestInMajor'] = $res['latestInMajor'] !== '' ? $res['latestInMajor'] : $fallback;
            $row['latestOverall'] = $res['latestOverall'] !== '' ? $res['latestOverall'] : $fallback;
        }
        unset($row);
        return $rows;
    }

    /** @param array<string,mixed> $report */
    private function maybeEmail(array $report, string $aiSummary, bool $force): bool
    {
        if (!$force) {
            if (!$this->config->bool('emailEnabled')) {
                return false;
            }
            if ($this->config->bool('emailOnlyOnFindings', true) && !ReportBuilder::shouldNotify($report, 'any')) {
                return false;
            }
        }
        return $this->mailer->send($report, $aiSummary, $this->config->projectLabel());
    }
}
