<?php

namespace App\Repository;

use App\Entity\EmailProcess;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 *
 * @method EmailProcess|null find($id, $lockMode = null, $lockVersion = null)
 * @method EmailProcess|null findOneBy(array $criteria, array $orderBy = null)
 * @method EmailProcess[]    findAll()
 * @method EmailProcess[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmailProcessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailProcess::class);
    }

    public function save(EmailProcess $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(EmailProcess $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByExampleField($start, $end): array
    {
        $qb = $this->createQueryBuilder('process')
            ->leftJoin('process.emailConfigId', 'config')
            ->where("process.onDate >= '" . $start ."'" . " and process.onDate <= '" . $end . "'")
            ->orderBy('process.onDate', 'DESC')
            ->orderBy('process.id', 'DESC');
        return $qb->getQuery()->getResult();
    }

    public function findCreatedProcess($value, $onDate): array
    {
        $qb = $this->createQueryBuilder('process')
            ->andWhere('process.emailConfigId = :id')
            ->setParameter('id', $value)
            ->andWhere('process.onDate = :val')
            ->setParameter('val', $onDate);
        return $qb->getQuery()->getResult();
    }

    public function findAllCreatedProcess($onDate, $id): array
    {
        $qb = $this->createQueryBuilder('process')
            ->andWhere("process.status = 'created'")
            ->andWhere('process.onDate = :val')
            ->setParameter('val', $onDate);

        if (isset($id) && $id > 0) {
            $qb->andWhere('process.emailConfigId = :id')
                ->setParameter('id', $id);
        }

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

    public function findAllCheckedProcess($onDate, $id): array
    {
        $qb = $this->createQueryBuilder('process')
            ->andWhere("process.status = 'checked'")
            ->andWhere('process.onDate = :val')
            ->setParameter('val', $onDate);

        if (isset($id) && $id > 0) {
            $qb->andWhere('process.emailConfigId = :id')
                ->setParameter('id', $id);
        }

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

    public function findAllErrorProcess($onDate): array
    {
        $qb = $this->createQueryBuilder('process')
            ->andWhere("process.status = 'error'")
            ->andWhere('process.onDate = :val')
            ->setParameter('val', $onDate);
        return $qb->getQuery()->getResult();
    }
}
