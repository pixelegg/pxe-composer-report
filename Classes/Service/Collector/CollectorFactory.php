<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Service\Collector;

use Pixelegg\PxeComposerReport\Service\ReportConfig;

final class CollectorFactory
{
    public const NOTICE_FALLBACK_NO_PROC_OPEN = 'fallback_no_proc_open';
    public const NOTICE_FALLBACK_NO_BINARY = 'fallback_no_binary';

    public function __construct(
        private readonly ComposerCliCollector $cli,
        private readonly PackagistCollector $packagist,
        private readonly ComposerBinary $binary,
        private readonly ReportConfig $config,
    ) {}

    public function create(): CollectorInterface
    {
        return $this->select()['collector'];
    }

    /**
     * Collector plus notices explaining an automatic fallback (shown in the module).
     *
     * @return array{collector: CollectorInterface, notices: list<string>}
     */
    public function select(): array
    {
        $mode = $this->config->get('collectorMode', 'auto');
        if ($mode === 'packagist') {
            return ['collector' => $this->packagist, 'notices' => []];
        }
        if ($mode === 'composer') {
            return ['collector' => $this->cli, 'notices' => []];
        }
        if (!$this->binary->isProcOpenAvailable()) {
            return ['collector' => $this->packagist, 'notices' => [self::NOTICE_FALLBACK_NO_PROC_OPEN]];
        }
        try {
            $this->binary->resolve();
        } catch (CollectorException) {
            return ['collector' => $this->packagist, 'notices' => [self::NOTICE_FALLBACK_NO_BINARY]];
        }
        return ['collector' => $this->cli, 'notices' => []];
    }
}
