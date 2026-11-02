<?php


namespace App\Service\Connection;

use PDO;

final class Pool
{
    private array $connectionProperties;
    private static array $instance = [];


    /**
     * Pool constructor.
     * @param array $connectionProperties
     */
    public function __construct(array $connectionProperties)
    {
        $this->connectionProperties = $connectionProperties;
    }

    /**
     * @param string $connection
     * @return PDO
     */
    public function getConnection(string $connection): PDO
    {
        if (!isset(self::$instance[$connection])) {
            self::$instance[$connection] = $this->createConnection($connection);
        }

        return self::$instance[$connection];
    }

    /**
     * @param string $connection
     * @return PDO
     */
    private function createConnection(string $connection): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $this->connectionProperties[$connection]['host'],
            $this->connectionProperties[$connection]['port'],
            $this->connectionProperties[$connection]['database']
        );

        $pdo =
            new PDO(
                $dsn,
                $this->connectionProperties[$connection]['user'],
                $this->connectionProperties[$connection]['password']
            )
        ;

        $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
