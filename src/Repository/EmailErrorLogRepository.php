<?php

namespace App\Repository;

use App\Entity\EmailErrorLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailErrorLog>
 *
 * @method EmailErrorLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method EmailErrorLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method EmailErrorLog[]    findAll()
 * @method EmailErrorLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmailErrorLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailErrorLog::class);
    }

    public function save(EmailErrorLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(EmailErrorLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
