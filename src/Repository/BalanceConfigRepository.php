<?php

namespace App\Repository;

use App\Entity\BalanceConfig;
use App\Entity\EmailConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BalanceConfig>
 *
 * @method BalanceConfig|null find($id, $lockMode = null, $lockVersion = null)
 * @method BalanceConfig|null findOneBy(array $criteria, array $orderBy = null)
 * @method BalanceConfig[]    findAll()
 * @method BalanceConfig[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BalanceConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BalanceConfig::class);
    }

    public function save(BalanceConfig $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(BalanceConfig $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return BalanceConfig[] Returns an array of BalanceConfig objects
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere("b.isActive = 'true'")
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param $id
     * @return BalanceConfig[] Returns an array of BalanceConfig objects
     */
    public function findOneActive($id): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere("e.isActive = 'true'")
            ->andWhere("e.id = '$id'")
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @return BalanceConfig[] Returns an array of BalanceConfig objects
     */
    public function findAllAndSort(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.id', 'ASC')
            ->getQuery()
            ->getResult()
            ;
    }

//    public function findOneBySomeField($value): ?BalanceConfig
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
