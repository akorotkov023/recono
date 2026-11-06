<?php

namespace App\Repository;

use App\Entity\BalanceConfig;
use App\Entity\EmailConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 *
 * @method EmailConfig|null find($id, $lockMode = null, $lockVersion = null)
 * @method EmailConfig|null findOneBy(array $criteria, array $orderBy = null)
 * @method EmailConfig[]    findAll()
 * @method EmailConfig[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmailConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailConfig::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(EmailConfig $entity, bool $flush = true): void
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
    public function remove(EmailConfig $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @return EmailConfig[] Returns an array of EmailConfig objects
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere("e.isActive = 'true'")
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @param $id
     * @return EmailConfig[] Returns an array of EmailConfig objects
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
     * @return EmailConfig[] Returns an array of EmailConfig objects
     */
    public function findAllSort(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @return EmailConfig[] Returns an array of EmailConfig objects
     */
    public function findAllSortByActive(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.slug, e.id')
            ->orderBy('e.isActive', 'DESC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $data['partners'] = array_column($rows, 'id', 'slug');

        return $data;
    }
}
