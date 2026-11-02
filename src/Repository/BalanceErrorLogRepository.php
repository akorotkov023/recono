<?php

namespace App\Repository;

use App\Entity\BalanceErrorLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BalanceErrorLog>
 *
 * @method BalanceErrorLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method BalanceErrorLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method BalanceErrorLog[]    findAll()
 * @method BalanceErrorLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BalanceErrorLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BalanceErrorLog::class);
    }

    public function save(BalanceErrorLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(BalanceErrorLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByExampleField(): array
    {
        $qb = $this->createQueryBuilder('log')
            ->leftJoin('log.processId', 'config')
            ->orderBy('log.onDate', 'DESC');
        return $qb->getQuery()->getResult();
    }

//    /**
//     * @return BalanceErrorLog[] Returns an array of BalanceErrorLog objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('b.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?BalanceErrorLog
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
