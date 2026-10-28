<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Command;

use Pixelegg\PxeComposerReport\Service\ComposerReportService;
use Pixelegg\PxeComposerReport\Service\RunResult;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `vendor/bin/typo3 pxe:composer-report:run [--mail] [--json]`
 *
 * Runs the same read-only report as the scheduler task and stores it.
 * Exit codes: 0 = no security advisories, 1 = advisories found, 2 = the run failed.
 */
final class ReportCommand extends Command
{
    public const EXIT_OK = 0;
    public const EXIT_ADVISORIES = 1;
    public const EXIT_FAILED = 2;

    public function __construct(private readonly ComposerReportService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Runs composer outdated / composer audit (read-only), stores the report and optionally emails it. '
                . 'Exit code 0: no advisories, 1: security advisories found, 2: run failed.')
            ->addOption('mail', 'm', InputOption::VALUE_NONE, 'Send the report email to the configured recipients, even if emailEnabled is off')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the stored report as JSON instead of a summary');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->service->run('cli', (bool)$input->getOption('mail'));

        if ((bool)$input->getOption('json')) {
            $output->writeln((string)json_encode([
                'uid' => $result->uid,
                'status' => $result->status,
                'errorCode' => $result->errorCode,
                'errorMessage' => $result->errorMessage,
                'mailed' => $result->mailed,
                'report' => $result->report,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $this->exitCode($result);
        }

        if (!$result->isOk()) {
            $io->error(sprintf('Composer report failed (%s): %s', $result->errorCode, $result->errorMessage));
            return self::EXIT_FAILED;
        }

        $io->title('Composer report #' . $result->uid . ' (' . (string)($result->report['collector'] ?? '') . ')');
        $io->definitionList(
            ['Outdated' => $result->count('total')],
            ['  major' => $result->count('major')],
            ['  minor' => $result->count('minor')],
            ['  patch' => $result->count('patch')],
            ['Security advisories' => $result->count('security')],
            ['TYPO3 packages outdated' => $result->count('typo3')],
            ['Newer major available' => $result->count('nextMajor')],
            ['Private (not checked)' => $result->count('private')],
            ['Mail sent' => $result->mailed ? 'yes' : 'no'],
        );

        $security = is_array($result->report['security'] ?? null) ? $result->report['security'] : [];
        if ($security !== []) {
            $rows = [];
            foreach ($security as $a) {
                $rows[] = [(string)($a['name'] ?? ''), (string)($a['severity'] ?? ''), (string)($a['cve'] ?? ''), (string)($a['title'] ?? '')];
            }
            $io->table(['Package', 'Severity', 'CVE', 'Title'], $rows);
        }
        foreach (['typo3' => 'TYPO3 packages', 'other' => 'Other packages'] as $key => $title) {
            $list = is_array($result->report[$key] ?? null) ? $result->report[$key] : [];
            if ($list === []) {
                continue;
            }
            $rows = [];
            foreach ($list as $p) {
                $rows[] = [
                    (string)($p['name'] ?? ''),
                    (string)($p['current'] ?? ''),
                    (string)($p['latest'] ?? ''),
                    (string)($p['impact'] ?? ''),
                    (string)($p['nextMajor'] ?? ''),
                ];
            }
            $io->section($title);
            $io->table(['Package', 'Installed', 'Latest (in major)', 'Impact', 'Next major'], $rows);
        }
        $majorOnly = is_array($result->report['majorOnly'] ?? null) ? $result->report['majorOnly'] : [];
        if ($majorOnly !== []) {
            $rows = [];
            foreach ($majorOnly as $m) {
                $rows[] = [(string)($m['name'] ?? ''), (string)($m['current'] ?? ''), (string)($m['nextMajor'] ?? '')];
            }
            $io->section('Newer major available (up to date within the installed major)');
            $io->table(['Package', 'Installed', 'Next major'], $rows);
        }
        if ($result->aiSummary !== '') {
            $io->section('Summary');
            $io->text($result->aiSummary);
        }

        return $this->exitCode($result);
    }

    private function exitCode(RunResult $result): int
    {
        if (!$result->isOk()) {
            return self::EXIT_FAILED;
        }
        return $result->hasAdvisories() ? self::EXIT_ADVISORIES : self::EXIT_OK;
    }
}
