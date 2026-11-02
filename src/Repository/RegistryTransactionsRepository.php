<?php

namespace App\Repository;

use App\DTO\RegistryTransactionDiff;
use App\DTO\RegistryTransactionDto;
use App\DTO\RegistryTransactionProcessingId;
use App\Entity\RegistryTransactions;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<RegistryTransactions>
 *
 * @method RegistryTransactions|null find($id, $lockMode = null, $lockVersion = null)
 * @method RegistryTransactions|null findOneBy(array $criteria, array $orderBy = null)
 * @method RegistryTransactions[]    findAll()
 * @method RegistryTransactions[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RegistryTransactionsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegistryTransactions::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(RegistryTransactions $entity, bool $flush = true): void
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
    public function remove(RegistryTransactions $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }


    public function findProviderId($providerId): ?array
    {
        $sql = $this->createQueryBuilder('t')
            ->innerJoin('t.commandId', 'c')
            ->where("t.providerId = '" . $providerId ."'")
        ;

        return $sql->getQuery()->getResult();
    }

//    /**
//     * @return RegistryTransactions[] Returns an array of RegistryTransactions objects
//     */
//
//    public function findProviderId($providerId)
//    {
//        return $this->createQueryBuilder('t')
//            ->where("t.providerId = '" . $providerId ."'")
//            ->getQuery()
//            ->getArrayResult()
//            ;
//    }

     /**
      * @return RegistryTransactions[] Returns an array of RegistryTransactions objects
      */

    public function findByCommandIdProviderId($value)
    {
        return $this->createQueryBuilder('p')
            ->select('p.providerId')
            ->andWhere('p.commandId = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getSingleColumnResult()
        ;
    }

    public function findByCommandIdProcessingId($value)
    {
        return $this->createQueryBuilder('p')
            ->select('p.processingId')
            ->andWhere('p.commandId = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getSingleColumnResult()
            ;
    }

    /**
     * @return RegistryTransactions[] Returns an array of RegistryTransactions objects
     */

    public function findAllByCommandId($value)
    {
        return $this->createQueryBuilder('p')
            ->select('p.providerId')
            ->where("p.commandId in ($value)")
            ->getQuery()
            ->getSingleColumnResult()
            ;
    }

    public function findAllByCommandIdProcessingId(RegistryTransactionDiff $diff, int $command): RegistryTransactionDiff
    {
        $results = $this->createQueryBuilder('p')
            ->select('p.processingId')
            ->where('p.commandId = :command')
            ->setParameter('command', $command)
            ->getQuery()
            ->getSingleColumnResult();

        foreach ($results as $processingId) {
            $diff->addRegId($processingId, $command);
        }

        return $diff;
    }

    public function findAllByCommandIdProviderId(RegistryTransactionDiff $diff, int $command): RegistryTransactionDiff
    {
        $results = $this->createQueryBuilder('p')
            ->select('p.providerId')
            ->where('p.commandId = :command')
            ->setParameter('command', $command)
            ->getQuery()
            ->getSingleColumnResult();

        foreach ($results as $providerId) {
            $diff->addRegId($providerId, $command);
        }

        return $diff;
    }

    /**
     * @return RegistryTransactions[] Returns an array of RegistryTransactions objects
     */

    public function findByProcessingId($commandIds, $value)
    {

        return $this->createQueryBuilder('p')
            ->andWhere("p.processingId in ($value)")
            ->andWhere("p.commandId in ($commandIds)")
            ->getQuery()
            ->getResult()
            ;
    }

    public function findByProviderId($commandIds, $value)
    {

        return $this->createQueryBuilder('p')
            ->andWhere("p.providerId in ($value)")
            ->andWhere("p.commandId in ($commandIds)")
            ->getQuery()
            ->getResult()
            ;
    }

    public function findVerifiedStatusTransaction($providerId): array
    {
        $qb = $this->createQueryBuilder('tr')
            ->select('tr.providerId')
            ->leftJoin('tr.commandId', 'transactions')
            ->where("tr.providerId in ('{$providerId}') and transactions.status in ('verified') ");
        return $qb->getQuery()->getResult();
    }

    /*
    public function findOneBySomeField($value): ?RegistryTransactions
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
