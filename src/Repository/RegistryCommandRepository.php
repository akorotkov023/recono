<?php

namespace App\Repository;

use App\Entity\RegistryCommand;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method RegistryCommand|null find($id, $lockMode = null, $lockVersion = null)
 * @method RegistryCommand|null findOneBy(array $criteria, array $orderBy = null)
 * @method RegistryCommand[]    findAll()
 * @method RegistryCommand[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RegistryCommandRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegistryCommand::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(RegistryCommand $entity, bool $flush = true): void
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
    public function remove(RegistryCommand $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @param $value
     * @return RegistryCommand[] Returns an array of RegistryCommand objects
     */
    public function checkVerifiedRegistryForDate($value): array
    {
        return $this->createQueryBuilder('commands')
            ->andWhere("commands.onDate = '" . $value ."'")
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @throws NonUniqueResultException
     */
    public function checkProviderTotalCountByDate($value, $provider): int
    {
        $res = $this->createQueryBuilder('registry')
            ->select('registry.totalCount')
            ->join('registry.registryConfigId', 'config')
            ->andWhere('registry.onDate = :date')
            ->andWhere('config.provider = :provider')
            ->setParameter('date', $value)
            ->setParameter('provider', $provider)
            ->getQuery()
            ->getOneOrNullResult();

        return $res ? (int) $res['totalCount'] : 0;

    }

    public function findByNotVerifiedCommand($start, $end): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where("c.onDate >= '" . $start ."'" . " and c.onDate <= '" . $end . "'" . " and c.status <> 'verified'")
            ->orderBy('c.onDate', 'DESC');
        return $qb->getQuery()->getResult();
    }

    public function findCheckSumCounterInWas($start, $end): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where("c.onDate >= '" . $start ."'" . " and c.onDate <= '" . $end . "'" . " and c.status <> 'verified'")
            ->orderBy('c.onDate', 'DESC');
        return $qb->getQuery()->getResult();
    }


    public function findOneBySomeField($provider, $start, $end): ?array
    {
        $sql = $this->createQueryBuilder('r')
            ->leftJoin('r.registryConfigId', 'c')
            ->where("c.provider = '" . $provider ."'" . " and r.onDate >= '" . $start ."'" . " and r.onDate <= '" . $end . "'")
        ;

        return $sql->getQuery()->getResult();
    }

    public function findSumTotalCount($onDate): array
    {
        $toDate = new \DateTime($onDate);
        $toDate->modify('+1 day');
        $toDate = $toDate->format('Y-m-d');
        $qb = $this->createQueryBuilder('registry')
            ->select('registry.totalCount as count, config.provider')
            ->join('registry.registryConfigId', 'config')
            ->andWhere("registry.onDate >= '" . $onDate ."'")
            ->andWhere("registry.onDate < '" . $toDate ."'");
        return $qb->getQuery()->getResult();
    }

    public function findSumTotalCountForProvider($provider, $onDate): int
    {
        $toDate = new \DateTime($onDate);
        $toDate->modify('+1 day');
        $toDate = $toDate->format('Y-m-d');
        $qb = $this->createQueryBuilder('r')
            ->select('r.totalCount')
            ->leftJoin('r.registryConfigId', 'c')
            ->where("c.provider = '" . $provider ."'" . " and r.onDate >= '" . $onDate ."'" . " and r.onDate < '" . $toDate . "'");
        $res = $qb->getQuery()->getSingleColumnResult();
        $res[] = 0;
        return array_sum($res);
    }

    public function findAllCommandsByProviderAndDate($provider, $onDate): ?array
    {
        $sql = $this->createQueryBuilder('r')
            ->leftJoin('r.registryConfigId', 'c')
            ->where("c.provider = '" . $provider ."'")
            ->andWhere('r.onDate = :val')
            ->setParameter('val', $onDate)
        ;

        return $sql->getQuery()->getResult();
    }

    public function findAllCommandsByDate($start, $end): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.registryConfigId', 'c')
            ->where("r.onDate >= '" . $start ."'" . " and r.onDate <= '" . $end . "'")
            ->orderBy('r.onDate', 'DESC')
            ->orderBy('r.id', 'DESC');
        return $qb->getQuery()->getResult();
    }

}
