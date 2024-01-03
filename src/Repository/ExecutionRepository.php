<?php

namespace App\Repository;

use App\Entity\Execution;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Execution>
 *
 * @method Execution|null find($id, $lockMode = null, $lockVersion = null)
 * @method Execution|null findOneBy(array $criteria, array $orderBy = null)
 * @method Execution[]    findAll()
 * @method Execution[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExecutionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Execution::class);
    }

    public function findOneByNightly(
        string $version,
        string $platform,
        string $campaign,
        string $date
    ): ?Execution
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.version = :version')
            ->andWhere('e.platform = :platform')
            ->andWhere('e.campaign = :campaign')
            ->andWhere('DATE(e.start_date) = :date')
            ->setParameter('version', $version)
            ->setParameter('platform', $platform)
            ->setParameter('campaign', $campaign)
            ->setParameter('date', $date)
            ->orderBy('e.start_date', 'DESC');

        return $qb->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    public function findOneByNightlyBefore(
        string $version,
        string $platform,
        string $campaign,
        DateTime $date
    ): ?Execution
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.version = :version')
            ->andWhere('e.platform = :platform')
            ->andWhere('e.campaign = :campaign')
            ->andWhere('e.start_date < :date')
            ->setParameter('version', $version)
            ->setParameter('platform', $platform)
            ->setParameter('campaign', $campaign)
            ->setParameter('date', $date)
            ->orderBy('e.start_date', 'DESC');

        return $qb->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    public function findOneByVersionAndDate(string $version, ?string $date): ?Execution
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.version = :version')
            ->setParameter('version', $version)
            ->orderBy('e.start_date', 'DESC');

        if ($date) {
            $qb->andWhere('DATE(e.start_date) = :date')
                ->setParameter('date', $date);
        }

        return $qb->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    public function findAllVersions(): array
    {
        $results = ['develop'];
        foreach ($this->createQueryBuilder('e')
            ->select('e.version')
            ->groupBy('e.version')
            ->getQuery()
            ->getResult() as $datum) {
            if (in_array($datum['version'], $results)) {
                continue;
            }
            $results[] = $datum['version'];
        };
        return $results;
    }

    public function findAllBetweenDates(string $version, string $startDate, string $endDate): array
    {

        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.version = :version')
            ->andWhere('e.start_date >= :start_date')
            ->andWhere('e.start_date < :end_date')
            ->setParameter('version', $version)
            ->setParameter('start_date', $startDate)
            ->setParameter('end_date', $endDate)
            ->orderBy('e.start_date', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
