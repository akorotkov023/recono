<?php

namespace App\Service\Connection;

use PDO;

/**
 * Interface ManagerConnectionInterface
 * @package App\TestVersionDB\Service\Connection
 */
interface ManagerConnectionInterface
{
    /**
     * @param string $connectionName
     * @return PDO
     */
    public function getConnection(string $connectionName): PDO;

    /**
     * @param string $repository
     * @return PDO
     */
    public function getRepository(string $repository): PDO;
}
