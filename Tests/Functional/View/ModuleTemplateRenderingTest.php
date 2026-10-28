<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Tests\Functional\View;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Renders the backend module template with the three states the controller
 * produces (report, failed run, no run yet). Catches Fluid syntax errors,
 * missing labels and missing icons without a browser.
 */
final class ModuleTemplateRenderingTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];
    protected array $testExtensionsToLoad = ['pixelegg/pxe-composer-report'];

    /** @param array<string,mixed> $variables */
    private function render(array $variables): string
    {
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: ['EXT:pxe_composer_report/Resources/Private/Templates/'],
            layoutRootPaths: ['EXT:pxe_composer_report/Resources/Private/Layouts/'],
        ));
        $view->assignMultiple($variables + [
            'latest' => null,
            'report' => null,
            'error' => null,
            'history' => [],
            'trend' => [],
            'projectLabel' => 'Example Project',
            'refreshUri' => '/typo3/module/tools/pxe-composer-report/refresh?token=x',
            'testMailUri' => '/typo3/module/tools/pxe-composer-report/testMail?token=x',
            'mailRecipients' => 'a@example.com',
            'collectorMode' => 'auto',
        ]);
        return $view->render('Report/Index');
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
        self::assertStringNotContainsString('style="', $html, 'no inline styles in the module');
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('FREUDWEISE', $html);
        self::assertStringNotContainsString('LLL:EXT:', $html, 'every label resolved');
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
    }

    #[Test]
    public function rendersUnknownErrorCodeWithFallbackText(): void
    {
        $html = $this->render([
            'latest' => ['crdate' => 1700000000, 'collector' => '', 'status' => 'error', 'triggered_by' => 'manual', 'ai_summary' => ''],
            'error' => ['code' => 'something_new', 'message' => ''],
        ]);
        self::assertStringContainsString('Unexpected error', $html);
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
