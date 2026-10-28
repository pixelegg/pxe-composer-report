<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Service\Collector;

use Pixelegg\PxeComposerReport\Service\ReportConfig;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Locates the composer executable for the PHP process that runs the report
 * (web user or cron). Either the configured `composerBinary` or `composer` on
 * the PATH; a `*.phar` is run through the PHP CLI binary.
 */
final class ComposerBinary
{
    public function __construct(private readonly ReportConfig $config) {}

    public function isProcOpenAvailable(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        return !in_array('proc_open', $disabled, true);
    }

    /**
     * Command prefix that replaces the `composer` placeholder of
     * ComposerCliCollector::COMMANDS, e.g. ['/usr/local/bin/composer'] or
     * ['/usr/bin/php', '/opt/composer.phar'].
     *
     * @return list<string>
     *
     * @throws CollectorException with reason BINARY_NOT_FOUND
     */
    public function resolve(): array
    {
        $configured = $this->config->get('composerBinary', 'composer');

        if (str_contains($configured, '/') || str_contains($configured, '\\')) {
            if (!is_file($configured)) {
                throw new CollectorException(CollectorException::BINARY_NOT_FOUND, 'Configured composer binary does not exist: ' . $configured);
            }
            if (str_ends_with(strtolower($configured), '.phar')) {
                $php = (new PhpExecutableFinder())->find(false);
                if ($php === false) {
                    throw new CollectorException(CollectorException::BINARY_NOT_FOUND, 'PHP CLI binary not found to run ' . $configured);
                }
                return [$php, $configured];
            }
            if (!is_executable($configured)) {
                throw new CollectorException(CollectorException::BINARY_NOT_FOUND, 'Configured composer binary is not executable: ' . $configured);
            }
            return [$configured];
        }

        $found = (new ExecutableFinder())->find($configured);
        if ($found === null) {
            throw new CollectorException(
                CollectorException::BINARY_NOT_FOUND,
                sprintf('"%s" not found in PATH of the PHP process (PATH=%s)', $configured, (string)getenv('PATH')),
            );
        }
        return [$found];
    }

    public function isAvailable(): bool
    {
        if (!$this->isProcOpenAvailable()) {
            return false;
        }
        try {
            $this->resolve();
            return true;
        } catch (CollectorException) {
            return false;
        }
    }
}
