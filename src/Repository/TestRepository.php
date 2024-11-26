<?php

namespace App\Repository;

use App\Entity\Execution;
use App\Entity\Test;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Test>
 *
 * @method Test|null find($id, $lockMode = null, $lockVersion = null)
 * @method Test|null findOneBy(array<string, mixed> $criteria, array<string, mixed> $orderBy = null)
 * @method Test[] findAll()
 * @method Test[] findBy(array<string, mixed> $criteria, array<string, mixed> $orderBy = null, $limit = null, $offset = null)
 */
class TestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Test::class);
    }

    /**
     * Get comparison data between current report and precedent report
     *
     * @return array<int, array{'old_test_state': string, 'current_test_state': string}>
     */
    public function findComparisonDate(Execution $current, Execution $previous): array
    {
        return $this->createQueryBuilder('t1')
            ->select([
                't1.state as old_test_state',
                't2.state as current_test_state',
            ])
            ->join('t1.suite', 's1')
            ->leftJoin(Test::class, 't2', Expr\Join::WITH, 't2.identifier IS NOT NULL AND t2.identifier = t1.identifier')
            ->join('t2.suite', 's2')
            ->where('s1.execution = :previous')
            ->andWhere('s2.execution = :current')
            ->andWhere('t1.identifier != \'loginBO\'')
            ->andWhere('t2.identifier != \'loginBO\'')
            ->andWhere('(t1.state = \'failed\') OR (t2.state = \'failed\')')
            ->setParameter('previous', $previous)
            ->setParameter('current', $current)
            ->getQuery()
            ->getResult()
        ;
    }
}
