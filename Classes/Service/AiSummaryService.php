<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Optional: a short, client-friendly summary of the report via the Anthropic
 * Messages API (https://platform.claude.com/docs). Best effort: returns '' on
 * any failure (network, HTTP error, refusal, unexpected body) so the run is
 * still saved. Only package counts, advisories and outdated TYPO3 packages are
 * sent; packages of private vendors (PackagePrivacy) are stripped first.
 */
final class AiSummaryService
{
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    public const API_VERSION = '2023-06-01';

    private const MAX_TOKENS = 2048;
    private const MAX_SUMMARY_LENGTH = 2000;

    public function __construct(
        private readonly ReportConfig $config,
        private readonly PackagePrivacy $privacy,
        private readonly RequestFactory $requestFactory,
        private readonly LoggerInterface $logger,
    ) {}

    /** @param array<string,mixed> $report */
    public function summarize(array $report): string
    {
        if (!$this->config->bool('aiEnabled')) {
            return '';
        }
        $key = $this->config->get('anthropicApiKey');
        if ($key === '') {
            $this->logger->warning('pxe_composer_report: aiEnabled but no anthropicApiKey configured');
            return '';
        }

        $payload = $this->buildPayload($report);

        try {
            $response = $this->requestFactory->request(self::ENDPOINT, 'POST', [
                'headers' => [
                    'x-api-key' => $key,
                    'anthropic-version' => self::API_VERSION,
                    'content-type' => 'application/json',
                    'accept' => 'application/json',
                ],
                'body' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'timeout' => 90,
                'connect_timeout' => 10,
            ]);
            $data = json_decode((string)$response->getBody(), true);
            if (!is_array($data)) {
                $this->logger->warning('pxe_composer_report AI summary: response is not JSON');
                return '';
            }
            if (($data['stop_reason'] ?? '') === 'refusal') {
                $this->logger->warning('pxe_composer_report AI summary: request was declined by the API (stop_reason=refusal)');
                return '';
            }
            return $this->extractText($data);
        } catch (\Throwable $e) {
            // Never leak the key: Guzzle messages may quote the request, but never headers.
            $this->logger->warning('pxe_composer_report AI summary failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Exactly what is sent, minus the API key. Public for the unit test and the README.
     *
     * @param array<string,mixed> $report
     * @return array<string,mixed>
     */
    public function buildPayload(array $report): array
    {
        $language = $this->config->language();
        $data = [
            'counts' => is_array($report['counts'] ?? null) ? $report['counts'] : [],
            'security' => $this->stripPrivate(array_values(is_array($report['security'] ?? null) ? $report['security'] : []), ['name', 'severity', 'cve', 'title']),
            'typo3' => $this->stripPrivate(array_values(is_array($report['typo3'] ?? null) ? $report['typo3'] : []), ['name', 'current', 'latest', 'nextMajor', 'impact']),
        ];

        $system = $language === 'de'
            ? 'Du fasst einen technischen Composer-Update-Report einer TYPO3-Website fuer einen Kunden ohne '
                . 'Technikkenntnisse auf Deutsch zusammen: 2 bis 4 Saetze, kein Fachjargon, Sicherheitsupdates zuerst, '
                . 'keine Aufzaehlungen, keine Ueberschriften. Antworte nur mit dem Fliesstext.'
            : 'You summarize a technical composer update report of a TYPO3 website for a non-technical client in '
                . 'English: 2 to 4 sentences, no jargon, security updates first, no lists, no headings. '
                . 'Reply with the plain text only.';

        return [
            'model' => $this->config->get('anthropicModel', ReportConfig::DEFAULT_MODEL),
            'max_tokens' => self::MAX_TOKENS,
            'system' => $system,
            'messages' => [[
                'role' => 'user',
                'content' => 'Report data (JSON): ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $fields
     * @return list<array<string,mixed>>
     */
    private function stripPrivate(array $rows, array $fields): array
    {
        $out = [];
        foreach ($rows as $row) {
            $name = (string)($row['name'] ?? '');
            if ($name === '' || $this->privacy->isPrivate($name)) {
                continue;
            }
            $slim = [];
            foreach ($fields as $field) {
                if (isset($row[$field]) && $row[$field] !== '') {
                    $slim[$field] = $row[$field];
                }
            }
            $out[] = $slim;
        }
        return $out;
    }

    /** @param array<string,mixed> $data */
    private function extractText(array $data): string
    {
        $parts = [];
        $content = is_array($data['content'] ?? null) ? $data['content'] : [];
        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $parts[] = trim((string)($block['text'] ?? ''));
            }
        }
        $text = trim(implode("\n", array_filter($parts, static fn(string $p): bool => $p !== '')));
        return mb_substr($text, 0, self::MAX_SUMMARY_LENGTH);
    }
}
