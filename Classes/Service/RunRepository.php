<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Service;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;

// Not final: the hub PanelProvider unit test mocks this repository.
class RunRepository
{
    public const TABLE = 'tx_pxecomposerreport_domain_model_run';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly Context $context,
    ) {}

    /**
     * @param array<string,mixed> $row
     * @return int new uid
     */
    public function insert(array $row): int
    {
        $conn = $this->connectionPool->getConnectionForTable(self::TABLE);
        $now = (int)$this->context->getPropertyFromAspect('date', 'timestamp', time());
        $conn->insert(self::TABLE, $row + ['pid' => 0, 'crdate' => $now, 'tstamp' => $now]);
        return (int)$conn->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function latest(): ?array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $qb->getRestrictions()->removeAll();
        $row = $qb->select('*')->from(self::TABLE)
            ->orderBy('crdate', 'DESC')->addOrderBy('uid', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()->fetchAssociative();
        return $row !== false ? $row : null;
    }

    /** @return list<array<string,mixed>> newest first */
    public function history(int $limit = 20): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $qb->getRestrictions()->removeAll();
        return $qb->select('uid', 'crdate', 'total_outdated', 'security_count', 'major', 'status', 'collector', 'triggered_by')
            ->from(self::TABLE)
            ->orderBy('crdate', 'DESC')->addOrderBy('uid', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()->fetchAllAssociative();
    }

    public function count(): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $qb->getRestrictions()->removeAll();
        return (int)$qb->count('uid')->from(self::TABLE)->executeQuery()->fetchOne();
    }

    /**
     * Delete all but the newest $keep runs. $keep <= 0 keeps everything.
     *
     * @return int number of deleted rows
     */
    public function prune(int $keep): int
    {
        if ($keep <= 0) {
            return 0;
        }
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $qb->getRestrictions()->removeAll();
        $stale = $qb->select('uid')->from(self::TABLE)
            ->orderBy('crdate', 'DESC')->addOrderBy('uid', 'DESC')
            ->setFirstResult($keep)->setMaxResults(10000)
            ->executeQuery()->fetchFirstColumn();
        if ($stale === []) {
            return 0;
        }
        $uids = array_map('intval', $stale);
        return (int)$this->connectionPool->getQueryBuilderForTable(self::TABLE)
            ->delete(self::TABLE)
            ->where('uid IN (' . implode(',', $uids) . ')')
            ->executeStatement();
    }
}
