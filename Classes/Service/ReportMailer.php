<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;

/**
 * Optional report email. Best effort: a mail failure never breaks a run.
 * All texts come from locallang.xlf in the configured `emailLanguage`.
 */
final class ReportMailer
{
    private const LLL = 'LLL:EXT:pxe_composer_report/Resources/Private/Language/locallang.xlf:';

    private const LABEL_KEYS = [
        'eyebrow', 'title', 'checkedAt', 'collector', 'summary', 'outdated', 'security', 'major', 'minor', 'patch',
        'nextMajorAvailable', 'latestHint', 'securityHeading', 'typo3Heading', 'otherHeading', 'majorOnlyHeading', 'majorOnlyHint', 'privateHeading',
        'privateHint', 'abandoned', 'majorPrefix', 'nothingOutdated', 'footer',
    ];

    public function __construct(
        private readonly ReportConfig $config,
        private readonly LanguageServiceFactory $languageServiceFactory,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array<string,mixed> $report
     * @param list<string>|null $overrideRecipients when given (non-empty), used instead of the configured list
     */
    public function send(array $report, string $aiSummary, string $projectLabel, ?array $overrideRecipients = null): bool
    {
        $recipients = ($overrideRecipients !== null && $overrideRecipients !== [])
            ? array_values(array_filter(array_map('trim', $overrideRecipients)))
            : $this->config->list('emailRecipients');
        $recipients = array_values(array_filter($recipients, static fn(string $r): bool => GeneralUtility::validEmail($r)));
        if ($recipients === []) {
            return false;
        }

        $language = $this->config->language();
        $lang = $this->languageServiceFactory->create($language === 'de' ? 'de' : 'default');
        $counts = is_array($report['counts'] ?? null) ? $report['counts'] : [];
        $subject = sprintf(
            $this->label($lang, 'email.subject'),
            $projectLabel,
            (int)($counts['total'] ?? 0),
            (int)($counts['security'] ?? 0),
        );

        try {
            $email = GeneralUtility::makeInstance(FluidEmail::class);
            $email->from(MailUtility::getSystemFromAddress())
                ->to(...$recipients)
                ->subject($subject)
                ->format(FluidEmail::FORMAT_BOTH)
                ->setTemplate('Report')
                ->assignMultiple([
                    'report' => $report,
                    'aiSummary' => $aiSummary,
                    'projectLabel' => $projectLabel,
                    'date' => date($this->label($lang, 'email.dateFormat')),
                    'labels' => $this->labels($lang),
                    'language' => $language,
                ]);
            $this->mailer->send($email);
            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('pxe_composer_report mail failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /** @return array<string,string> */
    private function labels(LanguageService $lang): array
    {
        $labels = [];
        foreach (self::LABEL_KEYS as $key) {
            $labels[$key] = $this->label($lang, 'email.' . $key);
        }
        return $labels;
    }

    private function label(LanguageService $lang, string $key): string
    {
        $value = $lang->sL(self::LLL . $key);
        return $value !== '' ? $value : $key;
    }
}
