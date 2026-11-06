<?php

namespace App\Repository;

use App\Entity\RegistryErrorLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RegistryErrorLog>
 *
 * @method RegistryErrorLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method RegistryErrorLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method RegistryErrorLog[]    findAll()
 * @method RegistryErrorLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RegistryErrorLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegistryErrorLog::class);
    }

    public function save(RegistryErrorLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RegistryErrorLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
