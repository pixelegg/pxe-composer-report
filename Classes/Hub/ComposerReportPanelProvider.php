<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Hub;

use Pixelegg\PxeComposerReport\Service\RunRepository;
use Pixelegg\PxeHub\Panel\Metric;
use Pixelegg\PxeHub\Panel\PanelAction;
use Pixelegg\PxeHub\Panel\PanelProvider;
use Pixelegg\PxeHub\Panel\PanelStatus;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * Optional card for the pxe_hub overview module. Only registered when pxe_hub
 * is installed (see Configuration/Services.php).
 */
final class ComposerReportPanelProvider implements PanelProvider
{
    private const LLL = 'LLL:EXT:pxe_composer_report/Resources/Private/Language/locallang.xlf:hub.';

    public function __construct(
        private readonly RunRepository $runRepository,
        private readonly UriBuilder $uriBuilder,
    ) {}

    public function getExtensionKey(): string
    {
        return 'pxe_composer_report';
    }

    public function getLabel(): string
    {
        return $this->translate('label');
    }

    public function getIcon(): string
    {
        return 'module-pxe-composer-report';
    }

    public function getModuleLink(): ?string
    {
        return (string)$this->uriBuilder->buildUriFromRoute('pxe_composer_report');
    }

    public function getStatus(): PanelStatus
    {
        $latest = $this->runRepository->latest();
        if ($latest === null) {
            return PanelStatus::unknown($this->translate('noReport'));
        }
        if (($latest['status'] ?? '') === 'error') {
            return PanelStatus::error($this->translate('lastRunFailed'));
        }
        $security = (int)($latest['security_count'] ?? 0);
        $outdated = (int)($latest['total_outdated'] ?? 0);
        if ($security > 0) {
            return PanelStatus::error(sprintf($this->translate('advisories'), $security));
        }
        if ($outdated > 0) {
            return PanelStatus::warning(sprintf($this->translate('outdated'), $outdated));
        }
        return PanelStatus::ok($this->translate('upToDate'));
    }

    /** @return Metric[] */
    public function getMetrics(): array
    {
        $latest = $this->runRepository->latest();
        if ($latest === null) {
            return [];
        }
        return [
            new Metric($this->translate('metricOutdated'), (string)(int)($latest['total_outdated'] ?? 0)),
            new Metric($this->translate('metricSecurity'), (string)(int)($latest['security_count'] ?? 0)),
            new Metric($this->translate('metricMajor'), (string)(int)($latest['major'] ?? 0)),
        ];
    }

    /** @return PanelAction[] */
    public function getActions(): array
    {
        return [
            new PanelAction($this->translate('openReport'), (string)$this->uriBuilder->buildUriFromRoute('pxe_composer_report')),
        ];
    }

    private function translate(string $key): string
    {
        $lang = $GLOBALS['LANG'] ?? null;
        $value = $lang instanceof LanguageService ? $lang->sL(self::LLL . $key) : '';
        return $value !== '' ? $value : $key;
    }
}
