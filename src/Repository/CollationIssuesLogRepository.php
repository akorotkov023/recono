<?php

namespace App\Repository;

use App\Entity\CollationIssuesLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use MongoDB\BSON\Timestamp;

/**
 * @method CollationIssuesLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method CollationIssuesLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method CollationIssuesLog[]    findAll()
 * @method CollationIssuesLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CollationIssuesLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CollationIssuesLog::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(CollationIssuesLog $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function remove(CollationIssuesLog $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }


    /**
     * @return CollationIssuesLog[] Returns an array of CollationIssuesLog objects
     */
    public function findByStarted($value): array
    {
        $plusDay = new \DateTime($value);
        $plusDay = $plusDay->modify('+1 day');
        $plusDay = "'" . $plusDay->format('Y-m-d') . "'";
        $value = "'" . $value . "'";

        return $this->createQueryBuilder('c')
            ->andWhere('c.createdAt >= :val')
            ->setParameter('val', $value)
            ->andWhere('c.createdAt < :val2')
            ->setParameter('val2', $plusDay)
            ->getQuery()
            ->getResult()
        ;
    }
}

