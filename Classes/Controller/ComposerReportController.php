<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Controller;

use Pixelegg\PxeComposerReport\Service\ComposerReportService;
use Pixelegg\PxeComposerReport\Service\ReportConfig;
use Pixelegg\PxeComposerReport\Service\ReportMailer;
use Pixelegg\PxeComposerReport\Service\RunRepository;
use Pixelegg\PxeComposerReport\Service\RunResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ComposerReportController
{
    private const LLL = 'LLL:EXT:pxe_composer_report/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly RunRepository $runRepository,
        private readonly ComposerReportService $service,
        private readonly ReportConfig $config,
        private readonly ReportMailer $mailer,
        private readonly UriBuilder $uriBuilder,
        private readonly PageRenderer $pageRenderer,
    ) {}

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $latest = $this->runRepository->latest();
        $decoded = ($latest !== null && !empty($latest['report_json'])) ? json_decode((string)$latest['report_json'], true) : null;
        $decoded = is_array($decoded) ? $decoded : [];
        $isError = $latest !== null && ($latest['status'] ?? '') === RunResult::STATUS_ERROR;
        $report = (!$isError && isset($decoded['counts'])) ? $decoded : null;
        $error = $isError ? [
            'code' => (string)($decoded['error']['code'] ?? 'unexpected'),
            'message' => (string)($decoded['error']['message'] ?? ($latest['error_log'] ?? '')),
        ] : null;
        $history = $this->runRepository->history(20);

        $this->pageRenderer->loadJavaScriptModule('@pixelegg/pxe-composer-report/report.js');
        $this->pageRenderer->addCssFile('EXT:pxe_composer_report/Resources/Public/Css/report.css');

        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($this->translate('mod.title'), $this->config->projectLabel());
        $view->assignMultiple([
            'latest' => $latest,
            'report' => $report,
            'error' => $error,
            'history' => $history,
            'trend' => $this->trendPoints($history),
            'projectLabel' => $this->config->projectLabel(),
            'refreshUri' => (string)$this->uriBuilder->buildUriFromRoute('pxe_composer_report.refresh'),
            'testMailUri' => (string)$this->uriBuilder->buildUriFromRoute('pxe_composer_report.testMail'),
            'mailRecipients' => implode(', ', $this->config->list('emailRecipients')),
            'collectorMode' => $this->config->get('collectorMode', 'auto'),
        ]);
        return $view->renderResponse('Report/Index');
    }

    public function refreshAction(ServerRequestInterface $request): ResponseInterface
    {
        $result = $this->service->run('manual');
        if ($result->isOk()) {
            $this->flash(
                $request,
                sprintf($this->translate('flash.refreshed'), $result->count('total'), $result->count('security')),
                $result->hasAdvisories() ? ContextualFeedbackSeverity::WARNING : ContextualFeedbackSeverity::OK,
            );
        } else {
            $this->flash(
                $request,
                $this->translate('error.' . $result->errorCode, 'error.unexpected') . ' ' . $result->errorMessage,
                ContextualFeedbackSeverity::ERROR,
            );
        }
        return new RedirectResponse($this->moduleUri());
    }

    public function testMailAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $email = is_array($body) ? trim((string)($body['email'] ?? '')) : '';
        $override = null;
        if ($email !== '') {
            if (!GeneralUtility::validEmail($email)) {
                $this->flash($request, sprintf($this->translate('flash.invalidEmail'), $email), ContextualFeedbackSeverity::ERROR);
                return new RedirectResponse($this->moduleUri());
            }
            $override = [$email];
        }

        $latest = $this->runRepository->latest();
        $report = ($latest !== null && !empty($latest['report_json'])) ? json_decode((string)$latest['report_json'], true) : null;
        if (!is_array($report) || !isset($report['counts'])) {
            $this->flash($request, $this->translate('flash.noReportForMail'), ContextualFeedbackSeverity::WARNING);
            return new RedirectResponse($this->moduleUri());
        }
        $ok = $this->mailer->send($report, (string)($latest['ai_summary'] ?? ''), $this->config->projectLabel(), $override);

        if ($ok) {
            $target = $email !== '' ? $email : $this->translate('flash.configuredRecipients');
            $this->flash($request, sprintf($this->translate('flash.mailSent'), $target), ContextualFeedbackSeverity::OK);
        } else {
            $this->flash($request, $this->translate('flash.mailFailed'), ContextualFeedbackSeverity::ERROR);
        }
        return new RedirectResponse($this->moduleUri());
    }

    /**
     * Normalize the last runs into bar heights (oldest → newest) for a tiny sparkline.
     *
     * @param list<array<string,mixed>> $history newest-first run rows
     * @return list<array{total:int,h:int,error:bool,label:string}>
     */
    private function trendPoints(array $history): array
    {
        if (count($history) < 2) {
            return [];
        }
        $rows = array_reverse($history); // chronological
        $max = 0;
        foreach ($rows as $r) {
            $max = max($max, (int)($r['total_outdated'] ?? 0));
        }
        $points = [];
        foreach ($rows as $r) {
            $total = (int)($r['total_outdated'] ?? 0);
            $error = ($r['status'] ?? '') === RunResult::STATUS_ERROR;
            $date = date('Y-m-d', (int)($r['crdate'] ?? 0));
            $points[] = [
                'total' => $total,
                'h' => $max > 0 ? max(8, (int)round($total / $max * 100)) : 8,
                'error' => $error,
                'label' => $error
                    ? sprintf($this->translate('mod.trendError'), $date)
                    : sprintf($this->translate('mod.trendPoint'), $date, $total),
            ];
        }
        return $points;
    }

    private function flash(ServerRequestInterface $request, string $message, ContextualFeedbackSeverity $severity): void
    {
        $this->moduleTemplateFactory->create($request)
            ->addFlashMessage($message, $this->translate('mod.title'), $severity);
    }

    private function translate(string $key, string $fallbackKey = ''): string
    {
        $value = $this->getLanguageService()->sL(self::LLL . $key);
        if ($value === '' && $fallbackKey !== '') {
            $value = $this->getLanguageService()->sL(self::LLL . $fallbackKey);
        }
        return $value !== '' ? $value : $key;
    }

    private function moduleUri(): string
    {
        return (string)$this->uriBuilder->buildUriFromRoute('pxe_composer_report');
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
