<?php

namespace App\Repository;

use App\Data\AuditLogSearchData;
use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function findAuditQuery(): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');
    }

    public function findByFilters(AuditLogSearchData $search): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->orderBy('a.createdAt', 'DESC');

        if ($search->action) {
            $qb->andWhere('a.action = :action')
                ->setParameter('action', $search->action);
        }

        if ($search->entityType) {
            $qb->andWhere('a.entityType = :entityType')
                ->setParameter('entityType', $search->entityType);
        }

        if ($search->user) {
            $qb->andWhere('a.user = :user')
                ->setParameter('user', $search->user);
        }

        if ($search->dateFrom) {
            $qb->andWhere('a.createdAt >= :dateFrom')
                ->setParameter('dateFrom', $search->dateFrom);
        }

        if ($search->dateTo) {
            $qb->andWhere('a.createdAt <= :dateTo')
                ->setParameter('dateTo', $search->dateTo->format('Y-m-d') . ' 23:59:59');
        }
        return $qb;
    }

    public function findDistinctActions(): array
    {
        $result = $this->createQueryBuilder('a')
            ->select('DISTINCT a.action')
            ->orderBy('a.action', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $result;
    }

    public function findDistinctEntityTypes(): array
    {
        $result = $this->createQueryBuilder('a')
            ->select('DISTINCT a.entityType')
            ->where('a.entityType IS NOT NULL')
            ->orderBy('a.entityType', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $result;
    }
}
