<?php

namespace App\Repository;

use App\Entity\BalancePartners;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BalancePartners>
 *
 * @method BalancePartners|null find($id, $lockMode = null, $lockVersion = null)
 * @method BalancePartners|null findOneBy(array $criteria, array $orderBy = null)
 * @method BalancePartners[]    findAll()
 * @method BalancePartners[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BalancePartnersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BalancePartners::class);
    }

    public function save(BalancePartners $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(BalancePartners $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return BalancePartners[] Returns an array of BalancePartners objects
//     */
//    public function checkSaveBalancesForDate($onDate): array
//    {
//        $toDate = new \DateTime($onDate);
//        $toDate->modify('+1 day');
//        $toDate = $toDate->format('Y-m-d');
//
//
//        $qb = $this->createQueryBuilder('balance')
//            ->andWhere("balance.createdAt >= '" . $onDate ."'")
//            ->andWhere("balance.createdAt < '" . $toDate ."'");
//        return $qb->getQuery()->getResult();
//    }

    /**
     * @return BalancePartners[] Returns an array of BalancePartners objects
     */
    public function getPartnerIdBalanceForDate($partnerId, $onDate): array
    {
        $toDate = new \DateTime($onDate);
        $toDate->modify('+1 day');
        $toDate = $toDate->format('Y-m-d');


        $qb = $this->createQueryBuilder('balance')
            ->andWhere("balance.createdAt >= '" . $onDate ."'")
            ->andWhere("balance.createdAt < '" . $toDate ."'")
            ->andWhere("balance.partnerId = ". $partnerId);
        return $qb->getQuery()->getResult();
    }

//    public function findOneBySomeField($value): ?BalancePartners
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
