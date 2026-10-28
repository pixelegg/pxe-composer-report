<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Service\Collector;

/**
 * A run could not collect data. `reason` is a stable machine-readable code the
 * module and the CLI translate into a human message; `getMessage()` carries the
 * technical detail (stderr, path, ...).
 */
final class CollectorException extends \RuntimeException
{
    public const CLASSIC_MODE = 'classic_mode';
    public const LOCK_MISSING = 'lock_missing';
    public const PROC_OPEN_DISABLED = 'proc_open_disabled';
    public const BINARY_NOT_FOUND = 'binary_not_found';
    public const COMPOSER_FAILED = 'composer_failed';
    public const PACKAGIST_DISABLED = 'packagist_disabled';
    public const PACKAGIST_FAILED = 'packagist_failed';

    /** @var list<string> */
    public const REASONS = [
        self::CLASSIC_MODE,
        self::LOCK_MISSING,
        self::PROC_OPEN_DISABLED,
        self::BINARY_NOT_FOUND,
        self::COMPOSER_FAILED,
        self::PACKAGIST_DISABLED,
        self::PACKAGIST_FAILED,
    ];

    public function __construct(
        public readonly string $reason,
        string $detail = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($detail !== '' ? $detail : $reason, 0, $previous);
    }
}
