<?php

namespace App\Repository;

use App\Entity\RegistryConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method RegistryConfig|null find($id, $lockMode = null, $lockVersion = null)
 * @method RegistryConfig|null findOneBy(array $criteria, array $orderBy = null)
 * @method RegistryConfig[]    findAll()
 * @method RegistryConfig[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RegistryConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegistryConfig::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(RegistryConfig $entity, bool $flush = true): void
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
    public function remove(RegistryConfig $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @return RegistryConfig[] Returns an array of BalanceConfig objects
     */
    public function findAllAndSort(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @return RegistryConfig[] Returns an array of BalanceConfig objects
     */
    public function findAllActiveObject(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere("c.isActive = 'true'")
            ->getQuery()
            ->getResult()
            ;
    }

    public function findAllActiveArray(): array
    {
        return array_column(
            $this->createQueryBuilder('c')
                ->select('c.provider')
                ->andWhere('c.isActive = true')
                ->getQuery()
                ->getScalarResult(),
            'provider'
        );
    }

    /**
     * @param $provider
     * @return RegistryConfig[] Returns an array of BalanceConfig objects
     */
    public function findAllActiveProvider($provider): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere("c.isActive = 'true'")
            ->andWhere("c.provider = '{$provider}'")
            ->getQuery()
            ->getResult()
            ;
    }

}
