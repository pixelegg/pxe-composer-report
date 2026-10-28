<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Task;

use Pixelegg\PxeComposerReport\Service\ComposerReportService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Scheduler task: generate one composer report run (read-only).
 *
 * No constructor injection on purpose: in TYPO3 13 the scheduler unserializes
 * task objects from the database (TaskSerializer::deserialize()), so the
 * constructor never runs and injected services would be serialized along with
 * the task. TYPO3 14 fetches tasks from the container when registered there,
 * but the extension supports both versions with one code path, so the service
 * is looked up at execution time. ComposerReportService is therefore public in
 * Services.yaml.
 */
final class ComposerReportTask extends AbstractTask
{
    public function execute(): bool
    {
        $result = GeneralUtility::makeInstance(ComposerReportService::class)->run('scheduler');
        if (!$result->isOk()) {
            // Surface the problem in the scheduler module; the run itself is stored with status "error".
            throw new \RuntimeException('Composer report failed (' . $result->errorCode . '): ' . $result->errorMessage, 1761640000);
        }
        return true;
    }
}
