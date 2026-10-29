<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Tests\Functional\View;

use PHPUnit\Framework\Attributes\Test;
use Pixelegg\PxeComposerReport\Controller\ComposerReportController;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Renders the backend module through ModuleTemplate (core "Module" layout with
 * docheader and module body) with the three states the controller produces
 * (report, failed run, no run yet). Catches Fluid syntax errors, missing labels
 * and missing icons without a browser.
 */
final class ModuleTemplateRenderingTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];
    protected array $testExtensionsToLoad = ['pixelegg/pxe-composer-report'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    private function moduleRequest(): ServerRequestInterface
    {
        $request = (new ServerRequest('https://example.com/typo3/module/tools/pxe-composer-report', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('route', $this->get(Router::class)->getRoute('pxe_composer_report'))
            ->withAttribute('module', $this->get(ModuleProvider::class)->getModule('pxe_composer_report'));
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
        $GLOBALS['TYPO3_REQUEST'] = $request;
        return $request;
    }

    /** @param array<string,mixed> $variables */
    private function render(array $variables): string
    {
        $view = $this->get(ModuleTemplateFactory::class)->create($this->moduleRequest());
        $view->assignMultiple($variables + [
            'latest' => null,
            'report' => null,
            'error' => null,
            'history' => [],
            'trend' => [],
            'projectLabel' => 'Example Project',
            'refreshUri' => '/typo3/module/tools/pxe-composer-report/refresh?token=x',
            'refreshFormId' => ComposerReportController::REFRESH_FORM_ID,
            'testMailUri' => '/typo3/module/tools/pxe-composer-report/testMail?token=x',
            'mailRecipients' => 'a@example.com',
            'collectorMode' => 'auto',
        ]);
        return $view->render('Report/Index');
    }

    /** The extension's own markup (div.pxecr) without the surrounding backend page. */
    private function moduleContent(string $html): string
    {
        $start = strpos($html, '<div class="pxecr">');
        self::assertNotFalse($start, 'module content rendered');
        $end = strpos($html, 'pxecr-footer', $start);
        self::assertNotFalse($end);
        return substr($html, $start, $end - $start);
    }

    #[Test]
    public function controllerRendersCoreModuleLayoutWithDocHeaderButtons(): void
    {
        // What refreshAction()/testMailAction() queue before redirecting back to the module.
        $this->get(ModuleTemplateFactory::class)->create($this->moduleRequest())
            ->addFlashMessage('Report refreshed for the test', 'Composer Report', ContextualFeedbackSeverity::OK, false);

        $html = (string)$this->get(ComposerReportController::class)->indexAction($this->moduleRequest())->getBody();

        // Core "Module" layout: docheader above the padded module body.
        // Match the class attributes, not the JavaScript import map that also names module-docheader.js.
        $docHeader = strpos($html, 'class="module-docheader');
        $body = strpos($html, 'class="module-body');
        self::assertNotFalse($docHeader, 'docheader rendered');
        self::assertNotFalse($body, 'module body rendered');
        self::assertLessThan($body, $docHeader);
        self::assertStringContainsString('<div class="pxecr">', substr($html, $body));

        // Both actions live in the docheader button bar, not in the module body.
        $docHeaderMarkup = substr($html, $docHeader, $body - $docHeader);
        self::assertStringContainsString('form="' . ComposerReportController::REFRESH_FORM_ID . '"', $docHeaderMarkup);
        self::assertStringContainsString('type="submit"', $docHeaderMarkup);
        self::assertStringContainsString('Refresh now', $docHeaderMarkup);
        self::assertStringContainsString('data-pxecr-testmail', $docHeaderMarkup);
        self::assertStringContainsString('Send test mail', $docHeaderMarkup);
        self::assertStringNotContainsString('data-pxecr-testmail', substr($html, $body));

        // The refresh form posts to the CSRF-protected route.
        self::assertMatchesRegularExpression(
            '#<form id="' . ComposerReportController::REFRESH_FORM_ID . '" method="post" action="[^"]*pxe-composer-report/refresh\?token=#',
            $html,
        );
        self::assertStringContainsString('No report yet', $html);
        self::assertStringContainsString('Report refreshed for the test', substr($html, $body), 'flash messages are shown');
    }

    #[Test]
    public function rendersFullReport(): void
    {
        $report = require __DIR__ . '/../Fixtures/report.php';
        $html = $this->render([
            'latest' => ['crdate' => 1700000000, 'collector' => 'composer', 'status' => 'ok', 'triggered_by' => 'cli', 'ai_summary' => 'Short summary.'],
            'report' => $report,
            'trend' => [
                ['total' => 5, 'h' => 100, 'error' => false, 'label' => '2026-01-01: 5 outdated'],
                ['total' => 3, 'h' => 60, 'error' => false, 'label' => '2026-01-08: 3 outdated'],
                ['total' => 0, 'h' => 8, 'error' => true, 'label' => '2026-01-15: run failed'],
            ],
        ]);

        self::assertStringContainsString('Composer Report: Example Project', $html);
        self::assertStringContainsString('Security advisories', $html);
        self::assertStringContainsString('CVE-2026-0001', $html);
        self::assertStringContainsString('https://example.org/advisory', $html);
        self::assertStringContainsString('TYPO3 packages', $html);
        self::assertStringContainsString('13.4.37', $html);
        self::assertStringContainsString('Major: 14.3.10', $html);
        self::assertStringContainsString('abandoned', $html);
        self::assertStringContainsString('Newer major available', $html);
        self::assertStringContainsString('georgringer/news', $html);
        self::assertStringContainsString('Private packages', $html);
        self::assertStringContainsString('mycorp/intranet', $html);
        self::assertStringContainsString('Short summary.', $html);
        self::assertStringContainsString('composer binary was not found', $html, 'notice is translated');
        self::assertStringContainsString('data-pxecr-height="100"', $html);
        self::assertStringContainsString('pxecr-bar-error', $html);
        self::assertStringContainsString('method="post"', $html);
        self::assertStringContainsString('<dialog', $html);
        self::assertStringContainsString('pixelegg.de', $html);
        self::assertStringContainsString('Triggered by: CLI command', $html, 'trigger value translated');
        self::assertStringContainsString('pxecr-chip pxecr-chip-trend', $html, 'trend rendered as a chip');
        $content = $this->moduleContent($html);
        self::assertStringNotContainsString('style="', $content, 'no inline styles in the module');
        self::assertStringNotContainsString('<script', $content);
        self::assertStringNotContainsString('FREUDWEISE', $html);
        self::assertStringNotContainsString('LLL:EXT:', $html, 'every label resolved');
        self::assertStringContainsString('class="module-docheader', $html);
        self::assertStringContainsString('class="module-body', $html);
    }

    #[Test]
    public function rendersFailedRunAsCallout(): void
    {
        $html = $this->render([
            'latest' => ['crdate' => 1700000000, 'collector' => '', 'status' => 'error', 'triggered_by' => 'scheduler', 'ai_summary' => ''],
            'error' => ['code' => 'binary_not_found', 'message' => '"composer" not found in PATH'],
        ]);

        self::assertStringContainsString('callout-danger', $html);
        self::assertStringContainsString('The last run failed', $html);
        self::assertStringContainsString('composerBinary', $html, 'error code translated into advice');
        self::assertStringContainsString('&quot;composer&quot; not found in PATH', $html, 'detail escaped');
        self::assertStringNotContainsString('No report yet', $html);
        self::assertStringContainsString('Triggered by: Scheduler task', $html);
    }

    #[Test]
    public function rendersUnknownErrorCodeWithFallbackText(): void
    {
        $html = $this->render([
            'latest' => ['crdate' => 1700000000, 'collector' => '', 'status' => 'error', 'triggered_by' => 'manual', 'ai_summary' => ''],
            'error' => ['code' => 'something_new', 'message' => ''],
        ]);
        self::assertStringContainsString('Unexpected error', $html);
        self::assertStringContainsString('Triggered by: Manually in the backend', $html);
    }

    #[Test]
    public function unknownTriggerValueIsShownAsIs(): void
    {
        $html = $this->render([
            'latest' => ['crdate' => 1700000000, 'collector' => 'composer', 'status' => 'ok', 'triggered_by' => 'webhook<x>', 'ai_summary' => ''],
        ]);
        self::assertStringContainsString('Triggered by: webhook&lt;x&gt;', $html);
    }

    #[Test]
    public function rendersEmptyState(): void
    {
        $html = $this->render([]);
        self::assertStringContainsString('No report yet', $html);
        self::assertStringContainsString('pxe:composer-report:run', $html);
        self::assertStringNotContainsString('callout-danger', $html);
    }
}
