<?php

namespace App\Service\Connection;

use PDO;
use Psr\Container\ContainerInterface;

/**
 * Class Manager
 * @package App\TestVersionDB\Service\Connection
 */
final class Manager implements ManagerConnectionInterface
{

    public function __construct(private Pool $connectionPool,
                                private ContainerInterface $parameterBag)
    {
    }

    /**
     * @param string $connectionName
     * @return PDO
     */
    public function getConnection(string $connectionName): PDO
    {
        return $this->connectionPool->getConnection($connectionName);
    }

    public function getRepository(string $repository): PDO
    {

    }
}
