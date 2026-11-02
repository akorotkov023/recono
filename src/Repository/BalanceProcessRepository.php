<?php

namespace App\Repository;

use App\Entity\BalanceProcess;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BalanceProcess>
 *
 * @method BalanceProcess|null find($id, $lockMode = null, $lockVersion = null)
 * @method BalanceProcess|null findOneBy(array $criteria, array $orderBy = null)
 * @method BalanceProcess[]    findAll()
 * @method BalanceProcess[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BalanceProcessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BalanceProcess::class);
    }

    public function save(BalanceProcess $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(BalanceProcess $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByExampleField($start, $end): array
    {
        $qb = $this->createQueryBuilder('process')
            ->leftJoin('process.balanceConfigId', 'config')
            ->where("process.onDate >= '" . $start ."'" . " and process.onDate <= '" . $end . "'")
            ->orderBy('process.onDate', 'DESC');
        return $qb->getQuery()->getResult();
    }

    public function findCreatedProcess($value, $onDate): array
    {
        $qb = $this->createQueryBuilder('process')
            ->andWhere('process.balanceConfigId = :id')
            ->setParameter('id', $value)
            ->andWhere('process.onDate = :val')
            ->setParameter('val', $onDate);
        return $qb->getQuery()->getResult();
    }

    public function findAllCreatedProcess($onDate): array
    {
        $qb = $this->createQueryBuilder('process')
            ->andWhere("process.status = 'created'")
            ->andWhere('process.onDate = :val')
            ->setParameter('val', $onDate);
        return $qb->getQuery()->getResult();
    }

    public function findOneCreatedProcess($process): array
    {
        $qb = $this->createQueryBuilder('process')
            ->andWhere("process.status = 'created'")
            ->andWhere('process.id = :val')
            ->setParameter('val', $process);
        return $qb->getQuery()->getResult();
    }

    public function findAllCheckedProcess($onDate): array
    {
        $qb = $this->createQueryBuilder('process')
            ->andWhere("process.status = 'checked'")
            ->andWhere('process.onDate = :val')
            ->setParameter('val', $onDate);
        return $qb->getQuery()->getResult();

    }

    public function findOneCheckedProcess($process): array
    {
        $qb = $this->createQueryBuilder('process')
            ->andWhere("process.status = 'checked'")
            ->andWhere('process.id = :val')
            ->setParameter('val', $process);
        return $qb->getQuery()->getResult();

    }

    public function findAllErrorProcess($onDate): array
    {
        $qb = $this->createQueryBuilder('process')
            ->andWhere("process.status = 'error'")
            ->andWhere('process.onDate = :val')
            ->setParameter('val', $onDate);
        return $qb->getQuery()->getResult();
    }

    public function findAllCancelProcess($onDate): array
    {
        $qb = $this->createQueryBuilder('process')
            ->andWhere("process.status not in ('finished')")
            ->andWhere('process.onDate = :val')
            ->setParameter('val', $onDate);
        return $qb->getQuery()->getResult();

    }

    public function findOneCancelProcess($process): array
    {
        $qb = $this->createQueryBuilder('process')
            ->andWhere("process.status not in ('finished')")
            ->andWhere('process.id = :val')
            ->setParameter('val', $process);
        return $qb->getQuery()->getResult();

    }

    public function findOneReloadProcess($process): array
    {
        $qb = $this->createQueryBuilder('process')
            ->andWhere("process.status in ('finished', 'checked', 'created')")
            ->andWhere('process.id = :val')
            ->setParameter('val', $process);
        return $qb->getQuery()->getResult();

    }

    public function findAllResetProcess($onDate, $process): array
    {
        $qb = $this->createQueryBuilder('process')
            ->andWhere("process.status in ('finished')")
            ->andWhere('process.onDate = :val')
            ->setParameter('val', $onDate)
            ->andWhere('process.id = :id')
            ->setParameter('id', $process);
        return $qb->getQuery()->getResult();
    }
}
