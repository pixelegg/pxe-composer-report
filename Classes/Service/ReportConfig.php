<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Resilient reader for the pxe_composer_report extension configuration.
 * Never throws if the extension was never saved in the install tool; the
 * defaults here mirror ext_conf_template.txt.
 */
final class ReportConfig
{
    public const DEFAULT_MODEL = 'claude-haiku-5-5';
    public const DEFAULT_KEEP_RUNS = 50;

    public function __construct(private readonly ExtensionConfiguration $extensionConfiguration) {}

    /** @return array<string,mixed> */
    public function all(): array
    {
        try {
            $c = $this->extensionConfiguration->get('pxe_composer_report');
            return is_array($c) ? $c : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function get(string $key, string $default = ''): string
    {
        $value = $this->all()[$key] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return $default;
        }
        return is_scalar($value) ? (string)$value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $v = $this->all()[$key] ?? null;
        if ($v === null || $v === '') {
            return $default;
        }
        return $v === true || $v === '1' || $v === 1;
    }

    public function int(string $key, int $default): int
    {
        $v = $this->all()[$key] ?? null;
        if ($v === null || $v === '' || !is_numeric($v)) {
            return $default;
        }
        return (int)$v;
    }

    /**
     * Comma-separated list, trimmed, empty entries dropped.
     *
     * @return list<string>
     */
    public function list(string $key): array
    {
        $items = array_map('trim', explode(',', $this->get($key)));
        return array_values(array_filter($items, static fn(string $s): bool => $s !== ''));
    }

    /** 'en' or 'de' – anything else falls back to English. */
    public function language(): string
    {
        return strtolower($this->get('emailLanguage', 'en')) === 'de' ? 'de' : 'en';
    }

    public function projectLabel(): string
    {
        $label = $this->get('projectLabel');
        if ($label !== '') {
            return $label;
        }
        $siteName = $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] ?? '';
        return is_string($siteName) && $siteName !== '' ? $siteName : 'TYPO3';
    }
}
